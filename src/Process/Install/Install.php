<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Install;

// Dotclear\Process\Install\Install
use Dotclear\App;
use Dotclear\Core\User\UserContainer;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Database\AbstractSchema;
use Dotclear\Database\Structure;
use Dotclear\Exception\InstallException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;
use Dotclear\Process\Distrib\Distrib;
use Error;
use Exception;

/**
 * Install methods.
 *
 * @ingroup  Install
 */
class Install
{
    public function __construct()
    {
        // Set URL (from default structure)
        $root_url = preg_replace(
            ['%admin/.*?$%', '%index.php.*?$%', '%/$%'],
            '',
            filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
        );
        $admin_url   = $root_url . '/admin/index.php';
        $install_url = $root_url . '/admin/install/index.php';
        $can_install = true;
        $err         = '';

        // Loading locales for detected language
        $dlang = Http::getAcceptLanguage();
        if ('en' != $dlang) {
            L10n::init($dlang);
            L10n::set(Path::implode(App::core()->config()->get('l10n_dir'), $dlang, 'date'));
            L10n::set(Path::implode(App::core()->config()->get('l10n_dir'), $dlang, 'main'));
            L10n::set(Path::implode(App::core()->config()->get('l10n_dir'), $dlang, 'plugins'));
        }

        if ('' == App::core()->config()->get('master_key')) {
            $can_install = false;
            $err         = '<p>' . __('Please set a master key in configuration file.') . '</p>';
        }

        // Check if dotclear is already installed
        $schema = AbstractSchema::init(App::core()->con());
        if (in_array(App::core()->prefix() . 'post', $schema->getTables())) {
            $can_install = false;
            $err         = '<p>' . __('Dotclear is already installed.') . '</p>';
        }

        // Check system capabilites
        $_e = new Strings();
        if (!Distrib::checkRequirements(App::core()->con(), $_e)) {
            $can_install = false;
            $err         = '<p>' . __('Dotclear cannot be installed.') . '</p><ul><li>' . implode('</li><li>', $_e->dump()) . '</li></ul>';
        }

        // Get information and perform install
        $u_email = $u_firstname = $u_name = $u_login = $u_pwd = '';

        $mail_sent = false;

        if ($can_install && GPC::post()->count()) {
            $u_email     = GPC::post()->string('u_email', null);
            $u_firstname = GPC::post()->string('u_firstname', null);
            $u_name      = GPC::post()->string('u_name', null);
            $u_login     = GPC::post()->string('u_login', null);
            $u_pwd       = GPC::post()->string('u_pwd', null);
            $u_pwd2      = GPC::post()->string('u_pwd2', null);

            try {
                // Check user information
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
                if (6 > strlen($u_pwd)) {
                    throw new InstallException(__('Password must contain at least 6 characters.'));
                }

                // Try to guess timezone
                $default_tz = 'Europe/London';
                if (!GPC::post()->empty('u_date')) {
                    if (preg_match('/\((.+)\)$/', GPC::post()->string('u_date'), $_tz)) {
                        if (Clock::zoneExists($_tz[1])) {
                            $default_tz = $_tz[1];
                        }
                        unset($_tz);
                    }
                }

                // Create schema
                $_s = new Structure(App::core()->con(), App::core()->prefix());
                Distrib::getDatabaseStructure($_s);

                $si      = new Structure(App::core()->con(), App::core()->prefix());
                $changes = $si->synchronize($_s);

                // Create user
                $cur = App::core()->con()->openCursor(App::core()->prefix() . 'user');
                $cur->setField('user_id', $u_login);
                $cur->setField('user_super', 1);
                $cur->setField('user_pwd', App::core()->user()->crypt($u_pwd));
                $cur->setField('user_name', (string) $u_name);
                $cur->setField('user_firstname', (string) $u_firstname);
                $cur->setField('user_email', (string) $u_email);
                $cur->setField('user_lang', $dlang);
                $cur->setField('user_tz', $default_tz);
                $cur->setField('user_creadt', Clock::database());
                $cur->setField('user_upddt', Clock::database());
                $cur->setField('user_options', serialize(UserContainer::defaultOptions()));
                $cur->insert();

                App::core()->user()->checkUser($u_login);

                // Create blog
                $cur = App::core()->con()->openCursor(App::core()->prefix() . 'blog');
                $cur->setField('blog_id', 'default');
                $cur->setField('blog_url', Http::getHost() . $root_url . '/index.php?');
                $cur->setField('blog_name', __('My first blog'));
                App::core()->blogs()->createBlog(cursor: $cur);

                // Create global blog settings
                Distrib::setBlogDefaultSettings();

                $settings = new Settings(blog: 'default');
                $system   = $settings->getGroup('system');
                $system->putSetting('blog_timezone', $default_tz);
                $system->putSetting('lang', $dlang);

                // date and time formats
                $formatDate   = __('%A, %B %e %Y');
                $date_formats = ['%Y-%m-%d', '%m/%d/%Y', '%d/%m/%Y', '%Y/%m/%d', '%d.%m.%Y', '%b %e %Y', '%e %b %Y', '%Y %b %e',
                    '%a, %Y-%m-%d', '%a, %m/%d/%Y', '%a, %d/%m/%Y', '%a, %Y/%m/%d', '%B %e, %Y', '%e %B, %Y', '%Y, %B %e', '%e. %B %Y',
                    '%A, %B %e, %Y', '%A, %e %B, %Y', '%A, %Y, %B %e', '%A, %Y, %B %e', '%A, %e. %B %Y', ];
                $time_formats = ['%H:%M', '%I:%M', '%l:%M', '%Hh%M', '%Ih%M', '%lh%M'];
                if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
                    $formatDate   = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $formatDate); // ! not working
                    $date_formats = array_map(
                        fn ($f) => str_replace('%e', '%#d', $f),
                        $date_formats
                    );
                }
                $system->putSetting('date_format', $formatDate);
                $system->putSetting('date_formats', $date_formats, 'array', 'Date formats examples', true, true);
                $system->putSetting('time_formats', $time_formats, 'array', 'Time formats examples', true, true);

                // Add repository URL for themes and plugins
                $system->putSetting('store_plugin_url', App::core()->config()->get('plugin_update_url'), 'string', 'Plugins XML feed location', true, true);
                $system->putSetting('store_theme_url', App::core()->config()->get('theme_update_url'), 'string', 'Themes XML feed location', true, true);

                // CSP directive (admin part)

                /* SQlite Clearbricks driver does not allow using single quote at beginning or end of a field value
                so we have to use neutral values (localhost and 127.0.0.1) for some CSP directives
                 */
                $csp_prefix = App::core()->con()->driver() == 'sqlite' ? 'localhost ' : ''; // Hack for SQlite Clearbricks driver
                $csp_suffix = App::core()->con()->driver() == 'sqlite' ? ' 127.0.0.1' : ''; // Hack for SQlite Clearbricks driver

                $system->putSetting('csp_admin_on', true, 'boolean', 'Send CSP header (admin)', true, true);
                $system->putSetting('csp_admin_report_only', false, 'boolean', 'CSP Report only violations (admin)', true, true);
                $system->putSetting(
                    'csp_admin_default',
                    $csp_prefix . "'self'" . $csp_suffix,
                    'string',
                    'CSP default-src directive',
                    true,
                    true
                );
                $system->putSetting(
                    'csp_admin_script',
                    $csp_prefix . "'self' 'unsafe-eval'" . $csp_suffix,
                    'string',
                    'CSP script-src directive',
                    true,
                    true
                );
                $system->putSetting(
                    'csp_admin_style',
                    $csp_prefix . "'self' 'unsafe-inline'" . $csp_suffix,
                    'string',
                    'CSP style-src directive',
                    true,
                    true
                );
                $system->putSetting(
                    'csp_admin_img',
                    $csp_prefix . "'self' data: https://media.dotaddict.org blob:",
                    'string',
                    'CSP img-src directive',
                    true,
                    true
                );

                // JQuery stuff
                $system->putSetting('jquery_migrate_mute', true, 'boolean', 'Mute warnings for jquery migrate plugin ?', false);
                $system->putSetting('jquery_allow_old_version', false, 'boolean', 'Allow older version of jQuery', false, true);

                // Add Dotclear version
                $cur = App::core()->con()->openCursor(App::core()->prefix() . 'version');
                $cur->setField('module', 'core');
                $cur->setField('version', (string) App::core()->config()->get('core_version'));
                $cur->insert();

                // Create first post
                App::core()->setBlog('default');

                $cur = App::core()->con()->openCursor(App::core()->prefix() . 'post');
                $cur->setField('user_id', $u_login);
                $cur->setField('post_format', 'xhtml');
                $cur->setField('post_lang', $dlang);
                $cur->setField('post_title', __('Welcome to Dotclear!'));
                $cur->setField('post_content', '<p>' . __('This is your first entry. When you\'re ready to blog, log in to edit or delete it.') . '</p>');
                $cur->setField('post_content_xhtml', $cur->getField('post_content'));
                $cur->setField('post_status', 1);
                $cur->setField('post_open_comment', 1);
                $cur->setField('post_open_tb', 0);
                $post_id = App::core()->blog()->posts()->createPost(cursor: $cur);

                // Add a comment to it
                $cur = App::core()->con()->openCursor(App::core()->prefix() . 'comment');
                $cur->setField('post_id', $post_id);
                $cur->setField('comment_author', __('Dotclear Team'));
                $cur->setField('comment_email', 'contact@dotclear.net');
                $cur->setField('comment_site', 'https://dotclear.org/');
                $cur->setField('comment_content', __("<p>This is a comment.</p>\n<p>To delete it, log in and view your blog's comments. Then you might remove or edit it.</p>"));
                App::core()->blog()->comments()->createComment(cursor: $cur);

                // Add dashboard module options
                App::core()->user()->preference()->get('dashboard')->put('doclinks', true, 'boolean', '', null, true);
                App::core()->user()->preference()->get('dashboard')->put('dcnews', true, 'boolean', '', null, true);
                App::core()->user()->preference()->get('dashboard')->put('quickentry', true, 'boolean', '', null, true);
                App::core()->user()->preference()->get('dashboard')->put('nodcupdate', false, 'boolean', '', null, true);

                // Add accessibility options
                App::core()->user()->preference()->get('accessibility')->put('nodragdrop', false, 'boolean', '', null, true);

                // Add user interface options
                App::core()->user()->preference()->get('interface')->put('enhanceduploader', true, 'boolean', '', null, true);

                // Add default favorites
                $init_favs = ['posts', 'new_post', 'newpage', 'comments', 'categories', 'media', 'blog_theme', 'widgets', 'simpleMenu', 'prefs', 'help'];
                App::core()->favorite()->setFavoriteIDs($init_favs, true);

                // Check existence of default blog public directory (only on new install)
                try {
                    $public_path = Path::implodeBase('public');
                    if (!is_dir($public_path)) {
                        // Try to create it
                        Files::makeDir($public_path);
                    }
                } catch (Exception|Error) {
                }

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
          echo App::core()->resource()->json('pwstrength', [
              'min' => sprintf(__('Password strength: %s'), __('weak')),
              'avg' => sprintf(__('Password strength: %s'), __('medium')),
              'max' => sprintf(__('Password strength: %s'), __('strong')),
          ]) .
            App::core()->resource()->json('install_show', __('show')); ?>
            <script src="?df=js/pwstrength.js"></script>
            <script src="?df=js/jquery/jquery.js"></script>
            <script src="?df=js/_install.js"></script>
        </head>

        <body id="dotclear-admin" class="install">
        <div id="content">
        <?php
        echo '<h1>' . __('Dotclear installation') . '</h1>' .
            '<div id="main">';

        if (!is_writable(App::core()->config()->get('cache_dir'))) {
            echo '<div class="error" role="alert"><p>' . sprintf(__('Cache directory %s is not writable.'), App::core()->config()->get('cache_dir')) . '</p></div>';
        }

        if ($can_install && !empty($err)) {
            echo '<div class="error" role="alert"><p><strong>' . __('Errors:') . '</strong></p>' . $err . '</div>';
        }

        if (!GPC::get()->empty('wiz')) {
            echo '<p class="success" role="alert">' . __('Configuration file has been successfully created.') . '</p>';
        }

        if ($can_install && 0 == $step) {
            echo '<h2>' . __('User information') . '</h2>' .

            '<p>' . __('Please provide the following information needed to create the first user.') . '</p>' .

            '<form action="' . $install_url . '" method="post">' .
            '<fieldset><legend>' . __('User information') . '</legend>' .
            '<p><label for="u_firstname">' . __('First Name:') . '</label> ' .
            Form::field('u_firstname', 30, 255, [
                'default'      => Html::escapeHTML($u_firstname),
                'autocomplete' => 'given-name',
            ]) .
            '</p>' .
            '<p><label for="u_name">' . __('Last Name:') . '</label> ' .
            Form::field('u_name', 30, 255, [
                'default'      => Html::escapeHTML($u_name),
                'autocomplete' => 'family-name',
            ]) .
            '</p>' .
            '<p><label for="u_email">' . __('Email:') . '</label> ' .
            Form::email('u_email', [
                'size'         => 30,
                'default'      => Html::escapeHTML($u_email),
                'autocomplete' => 'email',
            ]) .
            '</p>' .
            '</fieldset>' .

            '<fieldset><legend>' . __('Username and password') . '</legend>' .
            '<p><label for="u_login" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Username:') . '</label> ' .
            Form::field('u_login', 30, 32, [
                'default'      => Html::escapeHTML($u_login),
                'extra_html'   => 'required placeholder="' . __('Username') . '"',
                'autocomplete' => 'user-name',
            ]) .
            '</p>' .
            '<p>' .
            '<label for="u_pwd" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('New password:') . '</label>' .
            Form::password('u_pwd', 30, 255, [
                'class'        => 'pw-strength',
                'extra_html'   => 'data-indicator="pwindicator" required placeholder="' . __('Password') . '"',
                'autocomplete' => 'new-password',
            ]) .
            '</p>' .
            '<p><label for="u_pwd2" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Confirm password:') . '</label> ' .
            Form::password('u_pwd2', 30, 255, [
                'extra_html'   => 'required placeholder="' . __('Password') . '"',
                'autocomplete' => 'new-password',
            ]) .
            '</p>' .
            '</fieldset>' .

            '<p><input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>';
        } elseif ($can_install) {
            echo '<h2>' . __('All done!') . '</h2>' .

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

            '<form action="' . $admin_url . '" method="post">' .
            '<p><input type="submit" value="' . __('Manage your blog now') . '" />' .
            Form::hidden(['user_id'], Html::escapeHTML($u_login)) .
            Form::hidden(['user_pwd'], Html::escapeHTML($u_pwd)) .
                '</p>' .
                '</form>';
        } else {
            echo '<h2>' . __('Installation can not be completed') . '</h2>' .
            '<div class="error" role="alert"><p><strong>' . __('Errors:') . '</strong></p>' . $err . '</div>' .
            '<p>' . __('For the said reasons, Dotclear can not be installed. ' .
                'Please refer to <a href="https://dotclear.org/documentation/2.0/admin/install">' .
                'the documentation</a> to learn how to correct the problem.') . '</p>';
        } ?>
        </div>
        </div>
        </body>
        </html>
        <?php
    }
}
