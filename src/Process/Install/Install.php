<?php
/**
 * @brief Dotclear install install class
 *
 * @package Dotclear
 * @subpackage Install
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Install;

use Dotclear\Container\User as ContainerUser;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Database\AbstractSchema;
use Dotclear\Database\Structure;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Text;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Module\Plugin\Admin\ModulesPlugin;
use Dotclear\Process\Distrib\Distrib;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Install') {
    return;
}

class Install
{
    public function __construct()
    {
        /* Set URL (from default structure) */
        $root_url = preg_replace(
            ['%admin/.*?$%', '%index.php.*?$%', '%/$%'],
            '',
            filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
        );
        $admin_url   = $root_url . '/admin/index.php';
        $install_url = $root_url . '/admin/install/index.php';
        $can_install = true;
        $err         = '';

        /* Loading locales for detected language */
        $dlang = Http::getAcceptLanguage();
        if ($dlang != 'en') {
            L10n::init($dlang);
            L10n::set(Path::implodeRoot(dotclear()->config()->l10n_dir, $dlang, 'date'));
            L10n::set(Path::implodeRoot(dotclear()->config()->l10n_dir, $dlang, 'main'));
            L10n::set(Path::implodeRoot(dotclear()->config()->l10n_dir, $dlang, 'plugins'));
        }

        if (dotclear()->config()->master_key == '') {
            $can_install = false;
            $err         = '<p>' . __('Please set a master key in configuration file.') . '</p>';
        }

        /* Check if dotclear is already installed */
        $schema = AbstractSchema::init(dotclear()->con());
        if (in_array(dotclear()->prefix . 'post', $schema->getTables())) {
            $can_install = false;
            $err         = '<p>' . __('Dotclear is already installed.') . '</p>';
        }

        /* Check system capabilites */
        $_e = [];
        if (!Distrib::checkRequirements(dotclear()->con(), $_e)) { //! no need to con, and change _e to arrayobject
            $can_install = false;
            $err         = '<p>' . __('Dotclear cannot be installed.') . '</p><ul><li>' . implode('</li><li>', $_e) . '</li></ul>';
        }

        /* Get information and perform install */
        $u_email = $u_firstname = $u_name = $u_login = $u_pwd = '';

        $mail_sent = false;

        if ($can_install && !empty($_POST)) {
            $u_email     = !empty($_POST['u_email']) ? $_POST['u_email'] : null;
            $u_firstname = !empty($_POST['u_firstname']) ? $_POST['u_firstname'] : null;
            $u_name      = !empty($_POST['u_name']) ? $_POST['u_name'] : null;
            $u_login     = !empty($_POST['u_login']) ? $_POST['u_login'] : null;
            $u_pwd       = !empty($_POST['u_pwd']) ? $_POST['u_pwd'] : null;
            $u_pwd2      = !empty($_POST['u_pwd2']) ? $_POST['u_pwd2'] : null;

            try {
                /* Check user information */
                if (empty($u_login)) {
                    throw new InstallException(__('No user ID given'));
                }
                if (!preg_match('/^[A-Za-z0-9@._-]{2,}$/', $u_login)) {
                    throw new InstallException(__('User ID must contain at least 2 characters using letters, numbers or symbols.'));
                }
                if ($u_email && !Text::isEmail($u_email)) {
                    throw new InstallException(__('Invalid email address'));
                }

                if (empty($u_pwd)) {
                    throw new InstallException(__('No password given'));
                }
                if ($u_pwd != $u_pwd2) {
                    throw new InstallException(__("Passwords don't match"));
                }
                if (strlen($u_pwd) < 6) {
                    throw new InstallException(__('Password must contain at least 6 characters.'));
                }

                /* Try to guess timezone */
                $default_tz = 'Europe/London';
                if (!empty($_POST['u_date']) && function_exists('timezone_open')) {
                    if (preg_match('/\((.+)\)$/', $_POST['u_date'], $_tz)) {
                        $_tz = $_tz[1];
                        $_tz = @timezone_open($_tz);
                        if ($_tz instanceof DateTimeZone) {
                            $_tz = @timezone_name_get($_tz);

                            // check if timezone is valid
                            // date_default_timezone_set throw E_NOTICE and/or E_WARNING if timezone is not valid and return false
                            if (@date_default_timezone_set($_tz) !== false && $_tz) {
                                $default_tz = $_tz;
                            }
                        }
                        unset($_tz);
                    }
                }

                /* Create schema */
                $_s = new Structure(dotclear()->con(), dotclear()->prefix);
                Distrib::getDatabaseStructure($_s);

                $si      = new Structure(dotclear()->con(), dotclear()->prefix);
                $changes = $si->synchronize($_s);

                # Create user
                $cur                 = dotclear()->con()->openCursor(dotclear()->prefix . 'user');
                $cur->user_id        = $u_login;
                $cur->user_super     = 1;
                $cur->user_pwd       = dotclear()->user()->crypt($u_pwd);
                $cur->user_name      = (string) $u_name;
                $cur->user_firstname = (string) $u_firstname;
                $cur->user_email     = (string) $u_email;
                $cur->user_lang      = $dlang;
                $cur->user_tz        = $default_tz;
                $cur->user_creadt    = date('Y-m-d H:i:s');
                $cur->user_upddt     = date('Y-m-d H:i:s');
                $cur->user_options   = serialize(ContainerUser::defaultOptions());
                $cur->insert();

                dotclear()->user()->checkUser($u_login);

                /* Create blog */
                $cur            = dotclear()->con()->openCursor(dotclear()->prefix . 'blog');
                $cur->blog_id   = 'default';
                $cur->blog_url  = Http::getHost() . $root_url . '/index.php?';
                $cur->blog_name = __('My first blog');
                dotclear()->blogs()->addBlog($cur);

                /* Create global blog settings */
                Distrib::setBlogDefaultSettings();

                $blog_settings = new Settings('default');
                $blog_settings->system->put('blog_timezone', $default_tz);
                $blog_settings->system->put('lang', $dlang);

                /* date and time formats */
                $formatDate   = __('%A, %B %e %Y');
                $date_formats = ['%Y-%m-%d', '%m/%d/%Y', '%d/%m/%Y', '%Y/%m/%d', '%d.%m.%Y', '%b %e %Y', '%e %b %Y', '%Y %b %e',
                    '%a, %Y-%m-%d', '%a, %m/%d/%Y', '%a, %d/%m/%Y', '%a, %Y/%m/%d', '%B %e, %Y', '%e %B, %Y', '%Y, %B %e', '%e. %B %Y',
                    '%A, %B %e, %Y', '%A, %e %B, %Y', '%A, %Y, %B %e', '%A, %Y, %B %e', '%A, %e. %B %Y'];
                $time_formats = ['%H:%M', '%I:%M', '%l:%M', '%Hh%M', '%Ih%M', '%lh%M'];
                if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
                    $formatDate   = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $formatDate);
                    $date_formats = array_map(
                        function ($f) {
                            return str_replace('%e', '%#d', $f);
                        },
                        $date_formats);
                }
                $blog_settings->system->put('date_format', $formatDate);
                $blog_settings->system->put('date_formats', $date_formats, 'array', 'Date formats examples', true, true);
                $blog_settings->system->put('time_formats', $time_formats, 'array', 'Time formats examples', true, true);

                /* Add repository URL for themes and plugins */
                $blog_settings->system->put('store_plugin_url', dotclear()->config()->plugin_update_url, 'string', 'Plugins XML feed location', true, true);
                $blog_settings->system->put('store_theme_url', dotclear()->config()->theme_update_url, 'string', 'Themes XML feed location', true, true);
                $blog_settings->system->put('store_iconset_url', dotclear()->config()->iconset_update_url, 'string', 'Iconsets XML feed location', true, true);

                /* CSP directive (admin part) */

                /* SQlite Clearbricks driver does not allow using single quote at beginning or end of a field value
                so we have to use neutral values (localhost and 127.0.0.1) for some CSP directives
                 */
                $csp_prefix = dotclear()->con()->driver() == 'sqlite' ? 'localhost ' : ''; // Hack for SQlite Clearbricks driver
                $csp_suffix = dotclear()->con()->driver() == 'sqlite' ? ' 127.0.0.1' : ''; // Hack for SQlite Clearbricks driver

                $blog_settings->system->put('csp_admin_on', true, 'boolean', 'Send CSP header (admin)', true, true);
                $blog_settings->system->put('csp_admin_report_only', false, 'boolean', 'CSP Report only violations (admin)', true, true);
                $blog_settings->system->put('csp_admin_default',
                    $csp_prefix . "'self'" . $csp_suffix, 'string', 'CSP default-src directive', true, true);
                $blog_settings->system->put('csp_admin_script',
                    $csp_prefix . "'self' 'unsafe-eval'" . $csp_suffix, 'string', 'CSP script-src directive', true, true);
                $blog_settings->system->put('csp_admin_style',
                    $csp_prefix . "'self' 'unsafe-inline'" . $csp_suffix, 'string', 'CSP style-src directive', true, true);
                $blog_settings->system->put('csp_admin_img',
                    $csp_prefix . "'self' data: https://media.dotaddict.org blob:", 'string', 'CSP img-src directive', true, true);

                /* Add Dotclear version */
                $cur          = dotclear()->con()->openCursor(dotclear()->prefix . 'version');
                $cur->module  = 'core';
                $cur->version = (string) dotclear()->config()->core_version;
                $cur->insert();

                /* Create first post */
                dotclear()->setBlog('default');

                $cur               = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
                $cur->user_id      = $u_login;
                $cur->post_format  = 'xhtml';
                $cur->post_lang    = $dlang;
                $cur->post_title   = __('Welcome to Dotclear!');
                $cur->post_content = '<p>' . __('This is your first entry. When you\'re ready ' .
                    'to blog, log in to edit or delete it.') . '</p>';
                $cur->post_content_xhtml = $cur->post_content;
                $cur->post_status        = 1;
                $cur->post_open_comment  = 1;
                $cur->post_open_tb       = 0;
                $post_id                 = dotclear()->blog()->posts()->addPost($cur);

                /* Add a comment to it */
                $cur                  = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');
                $cur->post_id         = $post_id;
                $cur->comment_tz      = $default_tz;
                $cur->comment_author  = __('Dotclear Team');
                $cur->comment_email   = 'contact@dotclear.net';
                $cur->comment_site    = 'https://dotclear.org/';
                $cur->comment_content = __("<p>This is a comment.</p>\n<p>To delete it, log in and " .
                    "view your blog's comments. Then you might remove or edit it.</p>");
                dotclear()->blog()->comments()->addComment($cur);

                /*  Plugins initialization */
                # Only if plugin has Install/Prepend.php file using Install process
                # Else install will be made from admin home page through Admin/Prepend.php file.
                $plugins = new ModulesPlugin();
                $plugins_install = $plugins->installModules($dlang);

                /* Add dashboard module options */
                dotclear()->user()->preference()->dashboard->put('doclinks', true, 'boolean', '', null, true);
                dotclear()->user()->preference()->dashboard->put('dcnews', true, 'boolean', '', null, true);
                dotclear()->user()->preference()->dashboard->put('quickentry', true, 'boolean', '', null, true);
                dotclear()->user()->preference()->dashboard->put('nodcupdate', false, 'boolean', '', null, true);

                /* Add accessibility options */
                dotclear()->user()->preference()->accessibility->put('nodragdrop', false, 'boolean', '', null, true);

                /* Add user interface options */
                dotclear()->user()->preference()->interface->put('enhanceduploader', true, 'boolean', '', null, true);

                /* Add default favorites */
                $init_favs  = ['posts', 'new_post', 'newpage', 'comments', 'categories', 'media', 'blog_theme', 'widgets', 'simpleMenu', 'prefs', 'help'];
                dotclear()->favorite()->setFavoriteIDs($init_favs, true);

                $step = 1;
            } catch (\Exception $e) {
                $err = $e->getMessage();
            }
        }

        if (!isset($step)) {
            $step = 0;
        }
        header('Content-Type: text/html; charset=UTF-8');

        // Prevents Clickjacking as far as possible
        header('X-Frame-Options: SAMEORIGIN'); // FF 3.6.9+ Chrome 4.1+ IE 8+ Safari 4+ Opera 10.5+

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8" />
          <meta http-equiv="Content-Script-Type" content="text/javascript" />
          <meta http-equiv="Content-Style-Type" content="text/css" />
          <meta http-equiv="Content-Language" content="en" />
          <meta name="ROBOTS" content="NOARCHIVE,NOINDEX,NOFOLLOW" />
          <meta name="GOOGLEBOT" content="NOSNIPPET" />
          <title><?php echo __('Dotclear Install'); ?></title>

            <link rel="stylesheet" href="?df=css/install.css" type="text/css" media="screen" />
            <script src="?df=js/prepend.js"></script>
          <?php
          echo
            dotclear()->resource()->json('pwstrength', [
                'min' => sprintf(__('Password strength: %s'), __('weak')),
                'avg' => sprintf(__('Password strength: %s'), __('medium')),
                'max' => sprintf(__('Password strength: %s'), __('strong'))
            ]) .
            dotclear()->resource()->json('install_show', __('show')); ?>
            <script src="?df=js/pwstrength.js"></script>
            <script src="?df=js/jquery/jquery.js"></script>
            <script src="?df=js/_install.js"></script>
        </head>

        <body id="dotclear-admin" class="install">
        <div id="content">
        <?php
        echo
        '<h1>' . __('Dotclear installation') . '</h1>' .
            '<div id="main">';

        if (!is_writable(dotclear()->config()->cache_dir)) {
            echo '<div class="error" role="alert"><p>' . sprintf(__('Cache directory %s is not writable.'), dotclear()->config()->cache_dir) . '</p></div>';
        }

        if ($can_install && !empty($err)) {
            echo '<div class="error" role="alert"><p><strong>' . __('Errors:') . '</strong></p>' . $err . '</div>';
        }

        if (!empty($_GET['wiz'])) {
            echo '<p class="success" role="alert">' . __('Configuration file has been successfully created.') . '</p>';
        }

        if ($can_install && $step == 0) {
            echo
            '<h2>' . __('User information') . '</h2>' .

            '<p>' . __('Please provide the following information needed to create the first user.') . '</p>' .

            '<form action="'. $install_url . '" method="post">' .
            '<fieldset><legend>' . __('User information') . '</legend>' .
            '<p><label for="u_firstname">' . __('First Name:') . '</label> ' .
            Form::field('u_firstname', 30, 255, [
                'default'      => Html::escapeHTML($u_firstname),
                'autocomplete' => 'given-name'
            ]) .
            '</p>' .
            '<p><label for="u_name">' . __('Last Name:') . '</label> ' .
            Form::field('u_name', 30, 255, [
                'default'      => Html::escapeHTML($u_name),
                'autocomplete' => 'family-name'
            ]) .
            '</p>' .
            '<p><label for="u_email">' . __('Email:') . '</label> ' .
            Form::email('u_email', [
                'size'         => 30,
                'default'      => Html::escapeHTML($u_email),
                'autocomplete' => 'email'
            ]) .
            '</p>' .
            '</fieldset>' .

            '<fieldset><legend>' . __('Username and password') . '</legend>' .
            '<p><label for="u_login" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Username:') . '</label> ' .
            Form::field('u_login', 30, 32, [
                'default'      => Html::escapeHTML($u_login),
                'extra_html'   => 'required placeholder="' . __('Username') . '"',
                'autocomplete' => 'user-name'
            ]) .
            '</p>' .
            '<p>' .
            '<label for="u_pwd" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('New password:') . '</label>' .
            Form::password('u_pwd', 30, 255, [
                'class'        => 'pw-strength',
                'extra_html'   => 'data-indicator="pwindicator" required placeholder="' . __('Password') . '"',
                'autocomplete' => 'new-password'
            ]) .
            '</p>' .
            '<p><label for="u_pwd2" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Confirm password:') . '</label> ' .
            Form::password('u_pwd2', 30, 255, [
                'extra_html'   => 'required placeholder="' . __('Password') . '"',
                'autocomplete' => 'new-password'
            ]) .
            '</p>' .
            '</fieldset>' .

            '<p><input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>';
        } elseif ($can_install && $step == 1) {
            # Plugins install messages
            $plugins_install_result = '';
            if (!empty($plugins_install['success'])) {
                $plugins_install_result .= '<div class="static-msg">' . __('Following plugins have been installed:') . '<ul>';
                foreach ($plugins_install['success'] as $k => $v) {
                    $plugins_install_result .= '<li>' . $k . '</li>';
                }
                $plugins_install_result .= '</ul></div>';
            }
            if (!empty($plugins_install['failure'])) {
                $plugins_install_result .= '<div class="error">' . __('Following plugins have not been installed:') . '<ul>';
                foreach ($plugins_install['failure'] as $k => $v) {
                    $plugins_install_result .= '<li>' . $k . ' (' . $v . ')</li>';
                }
                $plugins_install_result .= '</ul></div>';
            }

            echo
            '<h2>' . __('All done!') . '</h2>' .

            $plugins_install_result .

            '<p class="success" role="alert">' . __('Dotclear has been successfully installed. Here is some useful information you should keep.') . '</p>' .

            '<h3>' . __('Your account') . '</h3>' .
            '<ul>' .
            '<li>' . __('Username:') . ' <strong>' . Html::escapeHTML($u_login) . '</strong></li>' .
            '<li>' . __('Password:') . ' <strong id="password">' . Html::escapeHTML($u_pwd) . '</strong></li>' .
            '</ul>' .

            '<h3>' . __('Your blog') . '</h3>' .
            '<ul>' .
            '<li>' . __('Blog address:') . ' <strong>' . Html::escapeHTML(Http::getHost() . $root_url) . '/index.php?</strong></li>' .
            '<li>' . __('Administration interface:') . ' <strong>' . Html::escapeHTML(Http::getHost() . $admin_url) . '</strong></li>' .
            '</ul>' .

            '<form action="'. $admin_url . '" method="post">' .
            '<p><input type="submit" value="' . __('Manage your blog now') . '" />' .
            Form::hidden(['user_id'], Html::escapeHTML($u_login)) .
            Form::hidden(['user_pwd'], Html::escapeHTML($u_pwd)) .
                '</p>' .
                '</form>';
        } elseif (!$can_install) {
            echo '<h2>' . __('Installation can not be completed') . '</h2>' .
            '<div class="error" role="alert"><p><strong>' . __('Errors:') . '</strong></p>' . $err . '</div>' .
            '<p>' . __('For the said reasons, Dotclear can not be installed. ' .
                'Please refer to <a href="https://dotclear.org/documentation/2.0/admin/install">' .
                'the documentation</a> to learn how to correct the problem.') . '</p>';
        }
        ?>
        </div>
        </div>
        </body>
        </html>
        <?php
    }
}
