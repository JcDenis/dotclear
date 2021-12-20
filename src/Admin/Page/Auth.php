<?php
/**
 * @brief Dotclear admin auth class
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Utils\Html;
use Dotclear\Utils\Http;
use Dotclear\Utils\L10n;
use Dotclear\Utils\Form;
use Dotclear\Utils\Mail;

use Dotclear\Distrib\Upgrade;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'admin') {
    return;
}

class Auth
{
    /** @var Core Core instance */
    public $core;

    /** @var string default lang */
    protected $default_lang;

    /** @var string this page url */
    protected $page_url;

    /** @var boolean can change password */
    protected $change_pwd;

    /** @var string login data */
    protected $login_data;

    /** @var boolean password recover */
    protected $recover;

    /** @var boolean safe mode */
    protected $safe_mode;

    /** @var string recovery key */
    protected $akey;

    /** @var string|null user id */
    protected $user_id;

    /** @var string|null user password */
    protected $user_pwd;

    /** @var srting|null user key */
    protected $user_key;

    /** @var string|null user email */
    protected $user_email;

    /** @var string|null error message */
    protected $err;

    /** @var string|null success message */
    protected $msg;

    public function __construct(Core $core)
    {
        $this->core = $core;

        # If we have a session cookie, go to index.php
        if (isset($_SESSION['sess_user_id'])) {
            $core->adminurl->redirect('admin.home');
        }

        # Loading locales for detected language
        $dlang = Http::getAcceptLanguage();
        $dlang = ($dlang == '' ? 'en' : $dlang);
        if ($dlang != 'en' && preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $dlang)) {
            L10n::lang($dlang);
            L10n::set($core::root(DOTCLEAR_L10N_DIR, $dlang, 'main'));
        }

        $this->default_lang = $dlang;
        $this->page_url     = $core->adminurl->get('admin.auth');
        $this->change_pwd   = $core->auth->allowPassChange() && isset($_POST['new_pwd']) && isset($_POST['new_pwd_c']) && isset($_POST['login_data']);
        $this->login_data   = !empty($_POST['login_data']) ? Html::escapeHTML($_POST['login_data']) : null;
        $this->recover      = $core->auth->allowPassChange() && !empty($_REQUEST['recover']);
        $this->safe_mode    = !empty($_REQUEST['safe_mode']);
        $this->akey         = $core->auth->allowPassChange() && !empty($_GET['akey']) ? $_GET['akey'] : null;
        $this->user_id      =
        $this->user_pwd     =
        $this->user_key     =
        $this->user_email   =
        $this->err          =
        $this->msg          = null;

        $this->upgrade();

        # If we have POST login informations, go throug auth process
        if (!empty($_POST['user_id']) && !empty($_POST['user_pwd'])) {
            $this->user_id  = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
            $this->user_pwd = !empty($_POST['user_pwd']) ? $_POST['user_pwd'] : null;
        }
        # If we have COOKIE login informations, go throug auth process
        elseif (isset($_COOKIE['dc_admin']) && strlen($_COOKIE['dc_admin']) == 104) {
            # If we have a remember cookie, go through auth process with user_key
            $user_id = substr($_COOKIE['dc_admin'], 40);
            $user_id = @unpack('a32', @pack('H*', $user_id));
            if (is_array($this->user_id)) {
                $this->user_id  = trim($user_id[1]);
                $this->user_key = substr($_COOKIE['dc_admin'], 0, 40);
                $this->user_pwd = null;
            } else {
                $this->user_id = null;
            }
        }

        # Recover password
        if ($this->recover && !empty($_POST['user_id']) && !empty($_POST['user_email'])) {
            $this->recoverPassword();
        # Send new password
        } elseif ($this->akey) {
            $this->sendNewPassword();
        # Change password and retry to log
        } elseif ($this->change_pwd) {
            $this->changePassword();
        # Try to log
        } elseif ($this->user_id !== null && ($this->user_pwd !== null || $this->user_key !== null)) {
            $this->logon();
        }

        if (isset($_GET['user'])) {
            $this->user_id = $_GET['user'];
        }

        $this->display();
    }

    protected function upgrade(): void
    {
        # Auto upgrade
        if (empty($_GET) && empty($_POST)) {
            try {
                if (($changes = Upgrade::dotclearUpgrade($this->core)) !== false) {
                    $this->msg = __('Dotclear has been upgraded.') . '<!-- ' . $changes . ' -->';
                }
            } catch (Exception $e) {
                $this->err = $e->getMessage();
            }
        }
    }

    protected function recoverPassword(): void
    {
        $this->user_id    = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
        $this->user_email = !empty($_POST['user_email']) ? Html::escapeHTML($_POST['user_email']) : '';

        try {
            $recover_key = $this->core->auth->setRecoverKey($this->user_id, $this->user_email);

            $subject = Mail::B64Header('Dotclear ' . __('Password reset'));
            $message = __('Someone has requested to reset the password for the following site and username.') . "\n\n" .
            $this->page_url . "\n" . __('Username:') . ' ' . $this->user_id . "\n\n" .
            __('To reset your password visit the following address, otherwise just ignore this email and nothing will happen.') . "\n" .
                $this->page_url . '?akey=' . $recover_key;

            $headers[] = 'From: ' . (defined('DOTCLEAR_ADMIN_MAILFROM') && DOTCLEAR_ADMIN_MAILFROM ? DOTCLEAR_ADMIN_MAILFROM : 'dotclear@local');
            $headers[] = 'Content-Type: text/plain; charset=UTF-8;';

            Mail::sendMail($this->user_email, $subject, $message, $headers);
            $this->msg = sprintf(__('The e-mail was sent successfully to %s.'), $this->user_email);
        } catch (Exception $e) {
            $this->err = $e->getMessage();
        }
    }

    protected function sendNewPassword(): void
    {
        try {
            $recover_res = $this->core->auth->recoverUserPassword($this->akey);

            $subject = mb_encode_mimeheader('Dotclear ' . __('Your new password'), 'UTF-8', 'B');
            $message = __('Username:') . ' ' . $recover_res['user_id'] . "\n" .
            __('Password:') . ' ' . $recover_res['new_pass'] . "\n\n" .
            preg_replace('/\?(.*)$/', '', $this->page_url);

            $headers[] = 'From: ' . (defined('DOTCLEAR_ADMIN_MAILFROM') && DOTCLEAR_ADMIN_MAILFROM ? DOTCLEAR_ADMIN_MAILFROM : 'dotclear@local');
            $headers[] = 'Content-Type: text/plain; charset=UTF-8;';

            Mail::sendMail($recover_res['user_email'], $subject, $message, $headers);
            $this->msg = __('Your new password is in your mailbox.');
        } catch (Exception $e) {
            $this->err = $e->getMessage();
        }
    }

    protected function changePassword()
    {
        try {
            $tmp_data = explode('/', $_POST['login_data']);
            if (count($tmp_data) != 3) {
                throw new AdminException();
            }
            $data = [
                'user_id'       => base64_decode($tmp_data[0]),
                'cookie_admin'  => $tmp_data[1],
                'user_remember' => $tmp_data[2] == '1',
            ];
            if ($data['user_id'] === false) {   // @phpstan-ignore-line
                throw new AdminException();
            }

            # Check login informations
            $check_user = false;
            if (strlen($data['cookie_admin']) == 104) {
                $user_id = substr($data['cookie_admin'], 40);
                $user_id = @unpack('a32', @pack('H*', $user_id));
                if (is_array($user_id)) {
                    $this->user_id    = trim($data['user_id']);
                    $this->user_key   = substr($data['cookie_admin'], 0, 40);
                    $check_user = $this->core->auth->checkUser($this->user_id, null, $this->user_key) === true;
                } else {
                    $this->user_id = trim($user_id);  // @phpstan-ignore-line
                }
            }

            if (!$this->core->auth->allowPassChange() || !$check_user) {
                $this->change_pwd = false;

                throw new AdminException();
            }

            if ($_POST['new_pwd'] != $_POST['new_pwd_c']) {
                throw new AdminException(__("Passwords don't match"));
            }

            if ($this->core->auth->checkUser($this->user_id, $_POST['new_pwd']) === true) {
                throw new AdminException(__("You didn't change your password."));
            }

            $cur                  = $this->core->con->openCursor($this->core->prefix . 'user');
            $cur->user_change_pwd = 0;
            $cur->user_pwd        = $_POST['new_pwd'];
            $this->core->updUser($this->core->auth->userID(), $cur);

            $this->core->session->start();
            $_SESSION['sess_user_id']     = $this->user_id;
            $_SESSION['sess_browser_uid'] = http::browserUID(DOTCLEAR_MASTER_KEY);

            if ($data['user_remember']) {
                setcookie('dc_admin', $data['cookie_admin'], strtotime('+15 days'), '', '', DOTCLEAR_ADMIN_SSL);
            }

            $this->core->adminurl->redirect('admin.home');
        } catch (Exception $e) {
            $this->err = $e->getMessage();
        }
    }

    protected function logon()
    {
        # We check the user
        $check_user = $this->core->auth->checkUser($this->user_id, $this->user_pwd, $this->user_key, false) === true;
        if ($check_user) {
            $check_perms = $this->core->auth->findUserBlog() !== false;
        } else {
            $check_perms = false;
        }

        $cookie_admin = Http::browserUID(DOTCLEAR_MASTER_KEY . $this->user_id .
            $this->core->auth->cryptLegacy($this->user_id)) . bin2hex(pack('a32', $this->user_id));

        if ($check_perms && $this->core->auth->mustChangePassword()) {
            $login_data = join('/', [
                base64_encode($this->user_id),
                $cookie_admin,
                empty($_POST['user_remember']) ? '0' : '1',
            ]);

            if (!$this->core->auth->allowPassChange()) {
                $this->err = __('You have to change your password before you can login.');
            } else {
                $this->err        = __('In order to login, you have to change your password now.');
                $this->change_pwd = true;
            }
        } elseif ($check_perms && !empty($_POST['safe_mode']) && !$this->core->auth->isSuperAdmin()) {
            $this->err = __('Safe Mode can only be used for super administrators.');
        } elseif ($check_perms) {
            $this->core->session->start();
            $_SESSION['sess_user_id']     = $this->user_id;
            $_SESSION['sess_browser_uid'] = Http::browserUID(DOTCLEAR_MASTER_KEY);

            if (!empty($_POST['blog'])) {
                $_SESSION['sess_blog_id'] = $_POST['blog'];
            }

            if (!empty($_POST['safe_mode']) && $this->core->auth->isSuperAdmin()) {
                $_SESSION['sess_safe_mode'] = true;
            }

            if (!empty($_POST['user_remember'])) {
                setcookie('dc_admin', $cookie_admin, strtotime('+15 days'), '', '', DOTCLEAR_ADMIN_SSL);
            }

            $this->core->adminurl->redirect('admin.home');
        } else {
            if ($check_user) {
                $this->err = __('Insufficient permissions');
            } else {
                $this->err = isset($_COOKIE['dc_admin']) ? __('Administration session expired') : __('Wrong username or password');
            }
            if (isset($_COOKIE['dc_admin'])) {
                unset($_COOKIE['dc_admin']);
                setcookie('dc_admin', '', -600, '', '', DOTCLEAR_ADMIN_SSL);
            }
        }
    }

    protected function display()
    {
        header('Content-Type: text/html; charset=UTF-8');

        // Prevents Clickjacking as far as possible
        header('X-Frame-Options: SAMEORIGIN'); // FF 3.6.9+ Chrome 4.1+ IE 8+ Safari 4+ Opera 10.5+

?>
<!DOCTYPE html>
<html lang="<?php echo $this->default_lang; ?>">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="Content-Script-Type" content="text/javascript" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta http-equiv="Content-Language" content="<?php echo $this->default_lang; ?>" />
  <meta name="ROBOTS" content="NOARCHIVE,NOINDEX,NOFOLLOW" />
  <meta name="GOOGLEBOT" content="NOSNIPPET" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo Html::escapeHTML(DOTCLEAR_VENDOR_NAME); ?></title>
  <link rel="icon" type="image/png" href="?df=images/favicon96-logout.png" />
  <link rel="shortcut icon" href="?df=images/favicon.ico" type="image/x-icon" />


<?php
//        echo dcPage::jsCommon();
?>

    <link rel="stylesheet" href="?df=style/default.css" type="text/css" media="screen" />

<?php
/*        # --BEHAVIOR-- loginPageHTMLHead
        $core->callBehavior('loginPageHTMLHead');

        echo
            dcPage::jsJson('pwstrength', [
                'min' => sprintf(__('Password strength: %s'), __('weak')),
                'avg' => sprintf(__('Password strength: %s'), __('medium')),
                'max' => sprintf(__('Password strength: %s'), __('strong')),
            ]) .
            dcPage::jsLoad('js/pwstrength.js') .
            dcPage::jsLoad('js/_auth.js');
*/
?>
</head>

<body id="dotclear-admin" class="auth">

<form action="<?php echo $this->core->adminurl->get('admin.auth'); ?>" method="post" id="login-screen">
<h1 role="banner"><?php echo Html::escapeHTML(DOTCLEAR_VENDOR_NAME); ?></h1>

<?php
        if ($this->err) {
            echo '<div class="' . ($this->change_pwd ? 'info' : 'error') . '" role="alert">' . $this->err . '</div>';
        }
        if ($this->msg) {
            echo '<p class="success" role="alert">' . $this->msg . '</p>';
        }

        if ($this->akey) {
            echo '<p><a href="' . $this->core->adminurl->get('admin.auth') . '">' . __('Back to login screen') . '</a></p>';
        } elseif ($this->recover) {
            echo
            '<div class="fieldset" role="main"><h2>' . __('Request a new password') . '</h2>' .
            '<p><label for="user_id">' . __('Username:') . '</label> ' .
            Form::field(
                'user_id',
                20,
                32,
                [
                    'default'      => Html::escapeHTML($this->user_id),
                    'autocomplete' => 'username',
                ]
            ) .
            '</p>' .

            '<p><label for="user_email">' . __('Email:') . '</label> ' .
            Form::email(
                'user_email',
                [
                    'default'      => Html::escapeHTML($this->user_email),
                    'autocomplete' => 'email',
                ]
            ) .
            '</p>' .

            '<p><input type="submit" value="' . __('recover') . '" />' .
            Form::hidden('recover', 1) . '</p>' .
            '</div>' .

            '<details open id="issue">' . "\n" .
            '<summary>' . __('Other option') . '</summary>' . "\n" .
            '<p><a href="' . $this->core->adminurl->get('admin.auth') . '">' . __('Back to login screen') . '</a></p>' .
            '</details>';
        } elseif ($this->change_pwd) {
            echo
            '<div class="fieldset"><h2>' . __('Change your password') . '</h2>' .
            '<p><label for="new_pwd">' . __('New password:') . '</label> ' .
            Form::password(
                'new_pwd',
                20,
                255,
                [
                    'autocomplete' => 'new-password',
                    'class'        => 'pw-strength',
                ]
            ) . '</p>' .

            '<p><label for="new_pwd_c">' . __('Confirm password:') . '</label> ' .
            Form::password(
                'new_pwd_c',
                20,
                255,
                [
                    'autocomplete' => 'new-password',
                ]
            ) . '</p>' .
            '<p><input type="submit" value="' . __('change') . '" />' .
            Form::hidden('login_data', $this->login_data) . '</p>' .
            '</div>';
        } else {
            if (is_callable([$this->core->auth, 'authForm'])) {
                echo $this->core->auth->authForm($this->user_id);
            } else {
                if ($this->safe_mode) {
                    echo '<div class="fieldset" role="main">';
                    echo '<h2>' . __('Safe mode login') . '</h2>';
                    echo
                    '<p class="form-note">' .
                    __('This mode allows you to login without activating any of your plugins. This may be useful to solve compatibility problems') . '&nbsp;</p>' .
                    '<p class="form-note">' . __('Disable or delete any plugin suspected to cause trouble, then log out and log back in normally.') .
                        '</p>';
                } else {
                    echo '<div class="fieldset" role="main">';
                }

                echo
                '<p><label for="user_id">' . __('Username:') . '</label> ' .
                Form::field(
                    'user_id',
                    20,
                    32,
                    [
                        'default'      => Html::escapeHTML($this->user_id),
                        'autocomplete' => 'username',
                    ]
                ) . '</p>' .

                '<p><label for="user_pwd">' . __('Password:') . '</label> ' .
                Form::password(
                    'user_pwd',
                    20,
                    255,
                    [
                        'autocomplete' => 'current-password',
                    ]
                ) . '</p>' .

                '<p>' .
                Form::checkbox('user_remember', 1) .
                '<label for="user_remember" class="classic">' .
                __('Remember my ID on this device') . '</label></p>' .

                '<p><input type="submit" value="' . __('log in') . '" class="login" /></p>';

                if (!empty($_REQUEST['blog'])) {
                    echo Form::hidden('blog', Html::escapeHTML($_REQUEST['blog']));
                }
                if ($this->safe_mode) {
                    echo
                    Form::hidden('safe_mode', 1) .
                        '</div>';
                } else {
                    echo '</div>';
                }
                echo
                '<p id="cookie_help" class="error">' . __('You must accept cookies in order to use the private area.') . '</p>';

                echo '<details ' . ($this->safe_mode ? 'open ' : '') . 'id="issue">' . "\n";
                if ($this->safe_mode) {
                    echo '<summary>' . __('Other option') . '</summary>' . "\n";
                    echo
                    '<p><a href="' . $this->core->adminurl->get('admin.auth') . '" id="normal_mode_link">' . __('Get back to normal authentication') . '</a></p>';
                } else {
                    echo '<summary>' . __('Connection issue?') . '</summary>' . "\n";
                    if ($this->core->auth->allowPassChange()) {
                        echo '<p><a href="' . $this->core->adminurl->get('admin.auth', ['recover' => 1]) . '">' . __('I forgot my password') . '</a></p>';
                    }
                    echo '<p><a href="' . $this->core->adminurl->get('admin.auth', ['safe_mode' => 1]) . '" id="safe_mode_link">' . __('I want to log in in safe mode') . '</a></p>';
                }
                echo '</details>';
            }
        }
?>
</form>
</body>
</html>
<?php
    }
}
