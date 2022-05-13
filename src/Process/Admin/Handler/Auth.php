<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Auth
use Dotclear\App;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Mail;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Distrib\Upgrade;

/**
 * Admin user auth page.
 *
 * @ingroup  Admin User Handler
 */
class Auth extends AbstractPage
{
    /**
     * @var bool $auth_change_pwd
     *           User can change password
     */
    protected $auth_change_pwd;

    /**
     * @var string $auth_login_data
     *             User login data
     */
    protected $auth_login_data;

    /**
     * @var bool $auth_recover
     *           User password recover
     */
    protected $auth_recover;

    /**
     * @var bool $auth_safe_mode
     *           Log in safe mode
     */
    protected $auth_safe_mode;

    /**
     * @var string $auth_akey
     *             User recovery key
     */
    protected $auth_akey;

    /**
     * @var null|string $auth_id
     *                  User id
     */
    protected $auth_id;

    /**
     * @var null|string $auth_pwd
     *                  User password
     */
    protected $auth_pwd;

    /**
     * @var null|string $auth_key
     *                  User key
     */
    protected $auth_key;

    /**
     * @var null|string $auth_email
     *                  User email
     */
    protected $auth_email;

    /**
     * @var null|string $auth_error
     *                  Error message
     */
    protected $auth_error;

    /**
     * @var null|string $auth_success
     *                  Success message
     */
    protected $auth_success;

    public function __construct()
    {
        parent::__construct();

        $this->setPageType('custom');

        // If we have a session cookie, go to index.php
        if (isset($_SESSION['sess_user_id'])) {
            App::core()->adminurl()->redirect('admin.home');
        }

        $this->auth_change_pwd       = App::core()->user()->allowPassChange() && isset($_POST['new_pwd'], $_POST['new_pwd_c'], $_POST['login_data']);
        $this->auth_login_data       = !empty($_POST['login_data']) ? Html::escapeHTML($_POST['login_data']) : null;
        $this->auth_recover          = App::core()->user()->allowPassChange() && !empty($_REQUEST['recover']);
        $this->auth_safe_mode        = !empty($_REQUEST['safe_mode']);
        $this->auth_akey             = App::core()->user()->allowPassChange() && !empty($_GET['akey']) ? $_GET['akey'] : null;
        $this->auth_id               =
        $this->auth_pwd              =
        $this->auth_key              =
        $this->auth_email            =
        $this->auth_error            =
        $this->auth_success          = null;

        $this->upgrade();

        // If we have POST login informations, go throug auth process
        if (!empty($_POST['user_id']) && !empty($_POST['user_pwd'])) {
            $this->auth_id  = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
            $this->auth_pwd = !empty($_POST['user_pwd']) ? $_POST['user_pwd'] : null;
        }
        // If we have COOKIE login informations, go throug auth process
        elseif (isset($_COOKIE['dc_admin']) && strlen($_COOKIE['dc_admin']) == 104) {
            // If we have a remember cookie, go through auth process with auth_key
            $user_id = substr($_COOKIE['dc_admin'], 40);
            $user_id = @unpack('a32', @pack('H*', $user_id));
            if (is_array($user_id)) {
                $this->auth_id  = trim((string) $user_id[1]);
                $this->auth_key = substr($_COOKIE['dc_admin'], 0, 40);
                $this->auth_pwd = null;
            } else {
                $this->auth_id = null;
            }
        }

        // Recover password
        if ($this->auth_recover && !empty($_POST['user_id']) && !empty($_POST['user_email'])) {
            $this->recoverPassword();
        // Send new password
        } elseif ($this->auth_akey) {
            $this->sendNewPassword();
        // Change password and retry to log
        } elseif ($this->auth_change_pwd) {
            $this->changePassword();
        // Try to log
        } elseif (null !== $this->auth_id && (null !== $this->auth_pwd || null !== $this->auth_key)) {
            $this->logon();
        }

        if (isset($_GET['user'])) {
            $this->auth_id = $_GET['user'];
        }
    }

    protected function upgrade(): void
    {
        // Auto upgrade
        $get = $_GET;
        if (isset($get['handler'])) {
            unset($get['handler']);
        }
        if (empty($get) && empty($_POST)) {
            try {
                if (($changes = (-1 !== (new Upgrade())->doUpgrade()))) {
                    $this->auth_success = __('Dotclear has been upgraded.') . '<!-- ' . $changes . ' -->';
                }
            } catch (\Exception $e) {
                $this->auth_error = $e->getMessage();
            }
        }
    }

    protected function recoverPassword(): void
    {
        $this->auth_id    = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
        $this->auth_email = !empty($_POST['user_email']) ? Html::escapeHTML($_POST['user_email']) : '';

        try {
            $recover_key = App::core()->user()->setRecoverKey($this->auth_id, $this->auth_email);

            $subject = Mail::B64Header('Dotclear ' . __('Password reset'));
            $message = __('Someone has requested to reset the password for the following site and username.') . "\n\n" .
            App::core()->adminurl()->get('admin.auth') . "\n" . __('Username:') . ' ' . $this->auth_id . "\n\n" .
            __('To reset your password visit the following address, otherwise just ignore this email and nothing will happen.') . "\n" .
                App::core()->adminurl()->get('admin.auth', ['akey' => $recover_key]);

            $headers[] = 'From: ' . (App::core()->config()->get('admin_mailform') != '' ? App::core()->config()->get('admin_mailform') : 'dotclear@local');
            $headers[] = 'Content-Type: text/plain; charset=UTF-8;';

            Mail::sendMail($this->auth_email, $subject, $message, $headers);
            $this->auth_success = sprintf(__('The e-mail was sent successfully to %s.'), $this->auth_email);
        } catch (\Exception $e) {
            $this->auth_error = $e->getMessage();
        }
    }

    protected function sendNewPassword(): void
    {
        try {
            $recover_res = App::core()->user()->recoverUserPassword($this->auth_akey);

            $subject = mb_encode_mimeheader('Dotclear ' . __('Your new password'), 'UTF-8', 'B');
            $message = __('Username:') . ' ' . $recover_res['user_id'] . "\n" .
            __('Password:') . ' ' . $recover_res['new_pass'] . "\n\n" .
            preg_replace('/\?(.*)$/', '', App::core()->adminurl()->get('admin.auth'));

            $headers[] = 'From: ' . (App::core()->config()->get('admin_mailform') != '' ? App::core()->config()->get('admin_mailform') : 'dotclear@local');
            $headers[] = 'Content-Type: text/plain; charset=UTF-8;';

            Mail::sendMail($recover_res['user_email'], $subject, $message, $headers);
            $this->auth_success = __('Your new password is in your mailbox.');
        } catch (\Exception $e) {
            $this->auth_error = $e->getMessage();
        }
    }

    protected function changePassword(): void
    {
        try {
            $tmp_data = explode('/', $_POST['login_data']);
            if (count($tmp_data) != 3) {
                throw new AdminException();
            }
            $data = [
                'user_id'       => base64_decode($tmp_data[0]),
                'cookie_admin'  => $tmp_data[1],
                'user_remember' => '1' == $tmp_data[2],
            ];
            if (empty($data['user_id'])) {
                throw new AdminException();
            }

            // Check login informations
            $check_user = false;
            if (strlen($data['cookie_admin']) == 104) {
                $user_id = substr($data['cookie_admin'], 40);
                $user_id = @unpack('a32', @pack('H*', $user_id));
                if (is_array($user_id)) {
                    $this->auth_id  = trim((string) $data['user_id']);
                    $this->auth_key = substr($data['cookie_admin'], 0, 40);
                    $check_user     = App::core()->user()->checkUser($this->auth_id, null, $this->auth_key) === true;
                } else {
                    $this->auth_id = trim((string) $user_id);
                }
            }

            if (!App::core()->user()->allowPassChange() || !$check_user) {
                $this->auth_change_pwd = false;

                throw new AdminException();
            }

            if ($_POST['new_pwd'] != $_POST['new_pwd_c']) {
                throw new AdminException(__("Passwords don't match"));
            }

            if (App::core()->user()->checkUser($this->auth_id, $_POST['new_pwd']) === true) {
                throw new AdminException(__("You didn't change your password."));
            }

            $cur = App::core()->con()->openCursor(App::core()->prefix() . 'user');
            $cur->setField('user_change_pwd', 0);
            $cur->setField('user_pwd', $_POST['new_pwd']);
            App::core()->users()->updUser(App::core()->user()->userID(), $cur);

            App::core()->session()->start();
            $_SESSION['sess_user_id']     = $this->auth_id;
            $_SESSION['sess_browser_uid'] = Http::browserUID(App::core()->config()->get('master_key'));

            if ($data['user_remember']) {
                setcookie('dc_admin', $data['cookie_admin'], Clock::ts(date: '+15 days'), '', '', App::core()->config()->get('admin_ssl'));
            }

            App::core()->adminurl()->redirect('admin.home');
        } catch (\Exception $e) {
            $this->auth_error = $e->getMessage();
        }
    }

    protected function logon(): void
    {
        // We check the user
        $check_user = App::core()->user()->checkUser($this->auth_id, $this->auth_pwd, $this->auth_key, false) === true;
        if ($check_user) {
            $check_perms = App::core()->user()->findUserBlog() !== false;
        } else {
            $check_perms = false;
        }

        $cookie_admin = Http::browserUID(App::core()->config()->get('master_key') . $this->auth_id .
            App::core()->user()->cryptLegacy($this->auth_id)) . bin2hex(pack('a32', $this->auth_id));

        if ($check_perms && App::core()->user()->mustChangePassword()) {
            $this->auth_login_data = join('/', [
                base64_encode($this->auth_id),
                $cookie_admin,
                empty($_POST['user_remember']) ? '0' : '1',
            ]);

            if (!App::core()->user()->allowPassChange()) {
                $this->auth_error = __('You have to change your password before you can login.');
            } else {
                $this->auth_error        = __('In order to login, you have to change your password now.');
                $this->auth_change_pwd   = true;
            }
        } elseif ($check_perms && !empty($_POST['safe_mode']) && !App::core()->user()->isSuperAdmin()) {
            $this->auth_error = __('Safe Mode can only be used for super administrators.');
        } elseif ($check_perms) {
            App::core()->session()->start();
            $_SESSION['sess_user_id']     = $this->auth_id;
            $_SESSION['sess_browser_uid'] = Http::browserUID(App::core()->config()->get('master_key'));

            if (!empty($_POST['blog'])) {
                $_SESSION['sess_blog_id'] = $_POST['blog'];
            }

            if (!empty($_POST['safe_mode']) && App::core()->user()->isSuperAdmin()) {
                $_SESSION['sess_safe_mode'] = true;
            }

            if (!empty($_POST['user_remember'])) {
                setcookie('dc_admin', $cookie_admin, Clock::ts(date: '+15 days'), '', '', App::core()->config()->get('admin_ssl'));
            }

            App::core()->adminurl()->redirect('admin.home');
        } else {
            if ($check_user) {
                $this->auth_error = __('Insufficient permissions');
            } else {
                $this->auth_error = isset($_COOKIE['dc_admin']) ? __('Administration session expired') : __('Wrong username or password');
            }
            if (isset($_COOKIE['dc_admin'])) {
                unset($_COOKIE['dc_admin']);
                setcookie('dc_admin', '', -600, '', '', App::core()->config()->get('admin_ssl'));
            }
        }
    }

    // Don't check premissions to show this page
    protected function getPermissions(): string|bool
    {
        return true;
    }

    protected function getPageBegin(): void
    {
        header('Content-Type: text/html; charset=UTF-8');

        // Prevents Clickjacking as far as possible
        header('X-Frame-Options: SAMEORIGIN'); // FF 3.6.9+ Chrome 4.1+ IE 8+ Safari 4+ Opera 10.5+

?>
<!DOCTYPE html>
<html lang="<?php echo App::core()->lang(); ?>">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="Content-Script-Type" content="text/javascript" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta http-equiv="Content-Language" content="<?php echo App::core()->lang(); ?>" />
  <meta name="ROBOTS" content="NOARCHIVE,NOINDEX,NOFOLLOW" />
  <meta name="GOOGLEBOT" content="NOSNIPPET" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo Html::escapeHTML(App::core()->config()->get('vendor_name')); ?></title>
  <link rel="icon" type="image/png" href="?df=images/favicon96-logout.png" />
  <link rel="shortcut icon" href="?df=images/favicon.ico" type="image/x-icon" />


<?php
        echo App::core()->resource()->common(); ?>

    <link rel="stylesheet" href="?df=css/default.css" type="text/css" media="screen" />

<?php
        // --BEHAVIOR-- loginPageHTMLHead
        App::core()->behavior()->call('loginPageHTMLHead');

        echo App::core()->resource()->json('pwstrength', [
            'min' => sprintf(__('Password strength: %s'), __('weak')),
            'avg' => sprintf(__('Password strength: %s'), __('medium')),
            'max' => sprintf(__('Password strength: %s'), __('strong')),
        ]) .
            App::core()->resource()->load('pwstrength.js') .
            App::core()->resource()->load('_auth.js'); ?>
</head>
<?php
    }

    protected function getPageContent(): void
    {
        ?>
<body id="dotclear-admin" class="auth">

<form action="<?php echo App::core()->adminurl()->get('admin.auth'); ?>" method="post" id="login-screen">
<h1 role="banner"><?php echo Html::escapeHTML(App::core()->config()->get('vendor_name')); ?></h1>

<?php
        if ($this->auth_error) {
            echo '<div class="' . ($this->auth_change_pwd ? 'info' : 'error') . '" role="alert">' . $this->auth_error . '</div>';
        }
        if ($this->auth_success) {
            echo '<p class="success" role="alert">' . $this->auth_success . '</p>';
        }

        if ($this->auth_akey) {
            echo '<p><a href="' . App::core()->adminurl()->get('admin.auth') . '">' . __('Back to login screen') . '</a></p>';
        } elseif ($this->auth_recover) {
            echo '<div class="fieldset" role="main"><h2>' . __('Request a new password') . '</h2>' .
            '<p><label for="user_id">' . __('Username:') . '</label> ' .
            Form::field(
                'user_id',
                20,
                32,
                [
                    'default'      => Html::escapeHTML($this->auth_id),
                    'autocomplete' => 'username',
                ]
            ) .
            '</p>' .

            '<p><label for="user_email">' . __('Email:') . '</label> ' .
            Form::email(
                'user_email',
                [
                    'default'      => Html::escapeHTML($this->auth_email),
                    'autocomplete' => 'email',
                ]
            ) .
            '</p>' .

            '<p><input type="submit" value="' . __('recover') . '" />' .
            Form::hidden('recover', 1) . '</p>' .
            '</div>' .

            '<details open id="issue">' . "\n" .
            '<summary>' . __('Other option') . '</summary>' . "\n" .
            '<p><a href="' . App::core()->adminurl()->get('admin.auth') . '">' . __('Back to login screen') . '</a></p>' .
            '</details>';
        } elseif ($this->auth_change_pwd) {
            echo '<div class="fieldset"><h2>' . __('Change your password') . '</h2>' .
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
            Form::hidden('login_data', $this->auth_login_data) . '</p>' .
            '</div>';
        } else {
            if (is_callable([App::core()->user(), 'authForm'])) {
                echo App::core()->user()->authForm($this->auth_id);
            } else {
                if ($this->auth_safe_mode) {
                    echo '<div class="fieldset" role="main">';
                    echo '<h2>' . __('Safe mode login') . '</h2>';
                    echo '<p class="form-note">' .
                    __('This mode allows you to login without activating any of your plugins. This may be useful to solve compatibility problems') . '&nbsp;</p>' .
                    '<p class="form-note">' . __('Disable or delete any plugin suspected to cause trouble, then log out and log back in normally.') .
                        '</p>';
                } else {
                    echo '<div class="fieldset" role="main">';
                }

                echo '<p><label for="user_id">' . __('Username:') . '</label> ' .
                Form::field(
                    'user_id',
                    20,
                    32,
                    [
                        'default'      => Html::escapeHTML($this->auth_id),
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
                if ($this->auth_safe_mode) {
                    echo Form::hidden('safe_mode', 1) .
                        '</div>';
                } else {
                    echo '</div>';
                }
                echo '<p id="cookie_help" class="error">' . __('You must accept cookies in order to use the private area.') . '</p>';

                echo '<details ' . ($this->auth_safe_mode ? 'open ' : '') . 'id="issue">' . "\n";
                if ($this->auth_safe_mode) {
                    echo '<summary>' . __('Other option') . '</summary>' . "\n";
                    echo '<p><a href="' . App::core()->adminurl()->get('admin.auth') . '" id="normal_mode_link">' . __('Get back to normal authentication') . '</a></p>';
                } else {
                    echo '<summary>' . __('Connection issue?') . '</summary>' . "\n";
                    if (App::core()->user()->allowPassChange()) {
                        echo '<p><a href="' . App::core()->adminurl()->get('admin.auth', ['recover' => 1]) . '">' . __('I forgot my password') . '</a></p>';
                    }
                    echo '<p><a href="' . App::core()->adminurl()->get('admin.auth', ['safe_mode' => 1]) . '" id="safe_mode_link">' . __('I want to log in in safe mode') . '</a></p>';
                }
                echo '</details>';
            }
        } ?>
</form>
<?php
    }

    protected function getPageEnd(): void
    {
        ?>
</body>
</html>
<?php
    }
}
