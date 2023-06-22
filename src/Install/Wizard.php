<?php
/**
 * @package Dotclear
 * @subpackage Install
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
namespace Dotclear\Install;

use dcNsProcess;
use Dotclear\Core\Process;
use Dotclear\Database\AbstractHandler;
use Dotclear\Database\AbstractSchema;
use Dotclear\Helper\Clearbricks;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;
use Exception;
use form;

class Wizard extends dcNsProcess
{
    private static $err           = '';
    private static $DBDRIVER      = 'mysqli';
    private static $DBHOST        = '';
    private static $DBNAME        = '';
    private static $DBUSER        = '';
    private static $DBPASSWORD    = '';
    private static $DBPREFIX      = 'dc_';
    private static $ADMINMAILFROM = '';

    public static function init(): bool
    {

        // Loading locales for detected language
        $dlang = Http::getAcceptLanguage();
        if ($dlang != 'en') {
            L10n::init($dlang);
            L10n::set(__DIR__ . '/../../locales/' . $dlang . '/main');
        }

        if (!is_writable(dirname(DC_RC_PATH))) {
            self::$err = '<p>' . sprintf(__('Path <strong>%s</strong> is not writable.'), Path::real(dirname(DC_RC_PATH))) . '</p>' .
            '<p>' . __('Dotclear installation wizard could not create configuration file for you. ' .
                'You must change folder right or create the <strong>config.php</strong> ' .
                'file manually, please refer to ' .
                '<a href="https://dotclear.org/documentation/2.0/admin/install">' .
                'the documentation</a> to learn how to do this.') . '</p>';
        }

        return true;
    }

    public static function process(): bool
    {
        self::$DBDRIVER      = !empty($_POST['DBDRIVER']) ? $_POST['DBDRIVER'] : 'mysqli';
        self::$DBHOST        = !empty($_POST['DBHOST']) ? $_POST['DBHOST'] : '';
        self::$DBNAME        = !empty($_POST['DBNAME']) ? $_POST['DBNAME'] : '';
        self::$DBUSER        = !empty($_POST['DBUSER']) ? $_POST['DBUSER'] : '';
        self::$DBPASSWORD    = !empty($_POST['DBPASSWORD']) ? $_POST['DBPASSWORD'] : '';
        self::$DBPREFIX      = !empty($_POST['DBPREFIX']) ? $_POST['DBPREFIX'] : 'dc_';
        self::$ADMINMAILFROM = !empty($_POST['ADMINMAILFROM']) ? $_POST['ADMINMAILFROM'] : '';

        if (!empty($_POST)) {
            try {
                if (self::$DBDRIVER == 'sqlite' && strpos(self::$DBNAME, '/') === false) {
                    $sqlite_db_directory = dirname(DC_RC_PATH) . '/../db/';
                    Files::makeDir($sqlite_db_directory, true);

                    # Can we write sqlite_db_directory ?
                    if (!is_writable($sqlite_db_directory)) {
                        throw new Exception(sprintf(__('Cannot write "%s" directory.'), Path::real($sqlite_db_directory, false)));
                    }
                    self::$DBNAME = $sqlite_db_directory . self::$DBNAME;
                }

                # Tries to connect to database
                try {
                    $con = AbstractHandler::init(self::$DBDRIVER, self::$DBHOST, self::$DBNAME, self::$DBUSER, self::$DBPASSWORD);
                } catch (Exception $e) {
                    throw new Exception('<p>' . __($e->getMessage()) . '</p>');
                }

                # Checks system capabilites
                require __DIR__ . '/check.php';
                if (!Utils::check($con, $_e)) {
                    $can_install = false;

                    throw new Exception('<p>' . __('Dotclear cannot be installed.') . '</p><ul><li>' . implode('</li><li>', $_e) . '</li></ul>');
                }

                # Check if dotclear is already installed
                $schema = AbstractSchema::init($con);
                if (in_array(self::$DBPREFIX . 'version', $schema->getTables())) {
                    throw new Exception(__('Dotclear is already installed.'));
                }
                # Check master email
                if (!Text::isEmail(self::$ADMINMAILFROM)) {
                    throw new Exception(__('Master email is not valid.'));
                }

                # Does config.php.in exist?
                $config_in = __DIR__ . '/../../inc/config.php.in';
                if (!is_file($config_in)) {
                    throw new Exception(sprintf(__('File %s does not exist.'), $config_in));
                }

                # Can we write config.php
                if (!is_writable(dirname(DC_RC_PATH))) {
                    throw new Exception(sprintf(__('Cannot write %s file.'), DC_RC_PATH));
                }

                # Creates config.php file
                $full_conf = file_get_contents($config_in);

                self::writeConfigValue('DC_DBDRIVER', self::$DBDRIVER, $full_conf);
                self::writeConfigValue('DC_DBHOST', self::$DBHOST, $full_conf);
                self::writeConfigValue('DC_DBUSER', self::$DBUSER, $full_conf);
                self::writeConfigValue('DC_DBPASSWORD', self::self::self::$DBPASSWORD, $full_conf);
                self::writeConfigValue('DC_DBNAME', self::$DBNAME, $full_conf);
                self::writeConfigValue('DC_DBPREFIX', self::$DBPREFIX, $full_conf);

                $admin_url = preg_replace('%install/index.php$%', '', (string) $_SERVER['REQUEST_URI']);
                self::writeConfigValue('DC_ADMIN_URL', Http::getHost() . $admin_url, $full_conf);
                $admin_email = !empty(self::$ADMINMAILFROM) ? self::$ADMINMAILFROM : 'dotclear@' . $_SERVER['HTTP_HOST'];
                self::writeConfigValue('DC_ADMIN_MAILFROM', $admin_email, $full_conf);
                self::writeConfigValue('DC_MASTER_KEY', md5(uniqid()), $full_conf);

                $fp = @fopen(DC_RC_PATH, 'wb');
                if ($fp === false) {
                    throw new Exception(sprintf(__('Cannot write %s file.'), DC_RC_PATH));
                }
                fwrite($fp, $full_conf);
                fclose($fp);

                if (function_exists('chmod')) {
                    try {
                        @chmod(DC_RC_PATH, 0666);
                    } catch (Exception $e) {
                    }
                }

                $con->close();
                Http::redirect('index.php?wiz=1');
            } catch (Exception $e) {
                $err = $e->getMessage();
            }
        }

        return true;
    }

    public static function render(): void
    {
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
    <link rel="stylesheet" href="../style/install.css" type="text/css" media="screen" />
</head>

<body id="dotclear-admin" class="install">
<div id="content">
<?php
echo
'<h1>' . __('Dotclear installation wizard') . '</h1>' .
    '<div id="main">';

if (!empty(self::$err)) {
    echo '<div class="error" role="alert"><p><strong>' . __('Errors:') . '</strong></p>' . self::$err . '</div>';
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

'<form action="wizard.php" method="post">' .
'<p><label class="required" for="DBDRIVER"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Database type:') . '</label> ' .
form::combo(
    'DBDRIVER',
    [
        __('MySQLi')              => 'mysqli',
        __('MySQLi (full UTF-8)') => 'mysqlimb4',
        __('PostgreSQL')          => 'pgsql',
        __('SQLite')              => 'sqlite', ],
    ['default' => self::$DBDRIVER, 'extra_html' => 'required placeholder="' . __('Driver') . '"']
) . '</p>' .
'<p><label for="DBHOST">' . __('Database Host Name:') . '</label> ' .
form::field('DBHOST', 30, 255, Html::escapeHTML(self::$DBHOST)) . '</p>' .
'<p><label for="DBNAME">' . __('Database Name:') . '</label> ' .
form::field('DBNAME', 30, 255, Html::escapeHTML(self::$DBNAME)) . '</p>' .
'<p><label for="DBUSER">' . __('Database User Name:') . '</label> ' .
form::field('DBUSER', 30, 255, Html::escapeHTML(self::$DBUSER)) . '</p>' .
'<p><label for="DBPASSWORD">' . __('Database Password:') . '</label> ' .
form::password('DBPASSWORD', 30, 255) . '</p>' .
'<p><label for="DBPREFIX" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Database Tables Prefix:') . '</label> ' .
form::field('DBPREFIX', 30, 255, [
    'default'    => Html::escapeHTML(self::$DBPREFIX),
    'extra_html' => 'required placeholder="' . __('Prefix') . '"',
]) .
'</p>' .
'<p><label for="ADMINMAILFROM">' . __('Master Email: (used as sender for password recovery)') . '</label> ' .
form::email('ADMINMAILFROM', [
    'size'         => 30,
    'default'      => Html::escapeHTML(self::$ADMINMAILFROM),
    'autocomplete' => 'email',
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
    }

    private static function writeConfigValue($name, $val, &$str)
    {
        $val = str_replace("'", "\'", $val);
        $str = preg_replace('/(\'' . $name . '\')(.*?)$/ms', '$1,\'' . $val . '\');', $str);
    }
}
