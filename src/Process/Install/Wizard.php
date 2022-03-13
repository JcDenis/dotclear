<?php
/**
 * @brief Dotclear install wizard class
 *
 * @package Dotclear
 * @subpackage Install
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Install;

use Dotclear\Database\AbstractConnection;
use Dotclear\Database\AbstractSchema;
use Dotclear\Exception\InstallException;
use Dotclear\Exception\DatabaseException;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Text;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Process\Distrib\Distrib;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Install') {
    return;
}

class Wizard
{
    public function __construct()
    {
        $root_url = preg_replace(
            ['%admin/.*?$%', '%index.php.*?$%', '%/$%'],
            '',
            filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
        );
        $install_url = $root_url . '/admin/install/index.php';

        # Loading locales for detected language
        $dlang = Http::getAcceptLanguage();
        if ($dlang != 'en') {
            L10n::init($dlang);
            L10n::set(Path::implodeRoot(dotclear()->config()->l10n_dir, $dlang, 'main'));
        }

        if (!is_writable(dirname(DOTCLEAR_CONFIG_PATH))) {
            $err = '<p>' . sprintf(__('Path <strong>%s</strong> is not writable.'), Path::real(dirname(DOTCLEAR_CONFIG_PATH), false)) . '</p>' .
            '<p>' . __('Dotclear installation wizard could not create configuration file for you. ' .
                'You must change folder right or create the <strong>config.php</strong> ' .
                'file manually, please refer to ' .
                '<a href="https://dotclear.org/documentation/2.0/admin/install">' .
                'the documentation</a> to learn how to do this.') . '</p>';
        }

        $DBDRIVER      = !empty($_POST['DBDRIVER']) ? $_POST['DBDRIVER'] : 'mysqli';
        $DBHOST        = !empty($_POST['DBHOST']) ? $_POST['DBHOST'] : '';
        $DBNAME        = !empty($_POST['DBNAME']) ? $_POST['DBNAME'] : '';
        $DBUSER        = !empty($_POST['DBUSER']) ? $_POST['DBUSER'] : '';
        $DBPASSWORD    = !empty($_POST['DBPASSWORD']) ? $_POST['DBPASSWORD'] : '';
        $DBPREFIX      = !empty($_POST['DBPREFIX']) ? $_POST['DBPREFIX'] : 'dc_';
        $ADMINMAILFROM = !empty($_POST['ADMINMAILFROM']) ? $_POST['ADMINMAILFROM'] : '';

        if (!empty($_POST)) {
            try {
                if ($DBDRIVER == 'sqlite') {
                    if (!str_contains($DBNAME, '/')) {
                        $sqlite_db_directory = implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, '..', 'db']);
                        Files::makeDir($sqlite_db_directory, true);

                        # Can we write sqlite_db_directory ?
                        if (!is_writable($sqlite_db_directory)) {
                            throw new InstallException(sprintf(__('Cannot write "%s" directory.'), Path::real($sqlite_db_directory, false)));
                        }
                        $DBNAME = $sqlite_db_directory . $DBNAME;
                    }
                }

                # Tries to connect to database (only using distributed database drivers)
                try {
                    $con = AbstractConnection::init($DBDRIVER, $DBHOST, $DBNAME, $DBUSER, $DBPASSWORD);
                } catch (DatabaseException $e) {
                    throw new InstallException('<p>' . __($e->getMessage()) . '</p>');
                }

                # Checks system capabilites
                $_e = [];
                if (!Distrib::checkRequirements($con, $_e)) {
                    $can_install = false;

                    throw new InstallException('<p>' . __('Dotclear cannot be installed.') . '</p><ul><li>' . implode('</li><li>', $_e) . '</li></ul>');
                }

                # Check if dotclear is already installed
                $schema = AbstractSchema::init($con);
                if (in_array($DBPREFIX . 'version', $schema->getTables())) {
                    throw new InstallException(__('Dotclear is already installed.'));
                }
                # Check master email
                if (!Text::isEmail($ADMINMAILFROM)) {
                    throw new InstallException(__('Master email is not valid.'));
                }
                # Can we write config.php
                if (!is_writable(dirname(DOTCLEAR_CONFIG_PATH))) {
                    throw new InstallException(sprintf(__('Cannot write %s file.'), DOTCLEAR_CONFIG_PATH));
                }

                # Creates config.php file
                $full_conf = Distrib::getConfigFile();

                $this->writeConfigValue('database_driver', $DBDRIVER, $full_conf);
                $this->writeConfigValue('database_host', $DBHOST, $full_conf);
                $this->writeConfigValue('database_user', $DBUSER, $full_conf);
                $this->writeConfigValue('database_password', $DBPASSWORD, $full_conf);
                $this->writeConfigValue('database_name', $DBNAME, $full_conf);
                $this->writeConfigValue('database_prefix', $DBPREFIX, $full_conf);

                $admin_url = $root_url . '/admin/index.php';
                $this->writeConfigValue('admin_url', Http::getHost() . $admin_url, $full_conf);
                $admin_email = !empty($ADMINMAILFROM) ? $ADMINMAILFROM : 'dotclear@' . $_SERVER['HTTP_HOST'];
                $this->writeConfigValue('admin_mailform', $admin_email, $full_conf);
                $this->writeConfigValue('master_key', md5(uniqid()), $full_conf);

                $fp = @fopen(DOTCLEAR_CONFIG_PATH, 'wb');
                if ($fp === false) {
                    throw new InstallException(sprintf(__('Cannot write %s file.'), DOTCLEAR_CONFIG_PATH));
                }
                fwrite($fp, $full_conf);
                fclose($fp);
                chmod(DOTCLEAR_CONFIG_PATH, 0666);

                $con->close();

                Http::redirect($install_url . '?wiz=1');
            } catch (InstallException $e) {
                $err = $e->getMessage();
            }
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
          <title><?php echo __('Dotclear installation wizard'); ?></title>
            <link rel="stylesheet" href="?df=css/install.css" type="text/css" media="screen" />
        </head>

        <body id="dotclear-admin" class="install">
        <div id="content">
        <?php
        echo
        '<h1>' . __('Dotclear installation wizard') . '</h1>' .
            '<div id="main">';

        if (!empty($err)) {
            echo '<div class="error" role="alert"><p><strong>' . __('Errors:') . '</strong></p>' . $err . '</div>';
        } else {
            echo '<h2>' . __('Welcome') . '</h2>' .
            '<p>' . __('To complete your Dotclear installation and start writing on your blog, ' .
                'we just need to know how to access your database and who you are. ' .
                'Just fill this two steps wizard with this information and we will be done.') . '</p>' .
            '<p class="message"><strong>' . __('Attention:') . '</strong> ' .
            __('this wizard may not function on every host. If it does not work for you, ' .
                'please refer to <a href="https://dotclear.org/documentation/2.0/admin/install">' .
                'the documentation</a> to learn how to create the <strong>config.php</strong> ' .
                'file manually.') . '</p>';
        }

        echo
        '<h2>' . __('System information') . '</h2>' .

        '<p>' . __('Please provide the following information needed to create your configuration file.') . '</p>' .

        '<form action="' . $install_url . '" method="post">' .
        '<p><label class="required" for="DBDRIVER"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Database type:') . '</label> ' .
        Form::combo('DBDRIVER', [
            __('MySQLi')              => 'mysqli',
            __('MySQLi (full UTF-8)') => 'mysqlimb4',
            __('PostgreSQL')          => 'pgsql',
            __('SQLite')              => 'sqlite'],
            ['default' => $DBDRIVER, 'extra_html' => 'required placeholder="' . __('Driver') . '"']) . '</p>' .
        '<p><label for="DBHOST">' . __('Database Host Name:') . '</label> ' .
        Form::field('DBHOST', 30, 255, Html::escapeHTML($DBHOST)) . '</p>' .
        '<p><label for="DBNAME">' . __('Database Name:') . '</label> ' .
        Form::field('DBNAME', 30, 255, Html::escapeHTML($DBNAME)) . '</p>' .
        '<p><label for="DBUSER">' . __('Database User Name:') . '</label> ' .
        Form::field('DBUSER', 30, 255, Html::escapeHTML($DBUSER)) . '</p>' .
        '<p><label for="DBPASSWORD">' . __('Database Password:') . '</label> ' .
        Form::password('DBPASSWORD', 30, 255) . '</p>' .
        '<p><label for="DBPREFIX" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Database Tables Prefix:') . '</label> ' .
        Form::field('DBPREFIX', 30, 255, [
            'default'    => Html::escapeHTML($DBPREFIX),
            'extra_html' => 'required placeholder="' . __('Prefix') . '"'
        ]) .
        '</p>' .
        '<p><label for="ADMINMAILFROM">' . __('Master Email: (used as sender for password recovery)') . '</label> ' .
        Form::email('ADMINMAILFROM', [
            'size'         => 30,
            'default'      => Html::escapeHTML($ADMINMAILFROM),
            'autocomplete' => 'email'
        ]) .
        '</p>' .

        '<p><input type="submit" value="' . __('Continue') . '" /></p>' .
            '</form>';
        ?>
        </div>
        </div>
        </body>
        </html>
        <?php
        exit;
    }

    protected function writeConfigValue(string $name, string $val, string &$str): void
    {
        $val = str_replace("'", "\'", $val);
        $str = preg_replace('/(\'' . $name . '\')(.*?)$/ms', '$1 => \'' . $val . '\',', $str);
    }
}
