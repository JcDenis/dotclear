<?php
/**
 * @brief Dotclear admin core prepend class
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Core\Prepend as BasePrepend;
use Dotclear\Core\Core;
Use Dotclear\Core\Utils;

use Dotclear\Utils\L10n;
use Dotclear\Utils\Path;
use Dotclear\Utils\Files;
use Dotclear\Utils\Http;

class Prepend extends BasePrepend
{
    protected $process = 'Admin';

    /** @var UrlHandler UrlHandler instance */
    public $adminurl;

    public function __construct()
    {
        parent::__construct();

        // HTTP/1.1
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

        // HTTP/1.0
        header('Pragma: no-cache');

        $this->adminurl = new UrlHandler($this, defined('DC_ADMIN_URL') ? DC_ADMIN_URL : '');

        $this->adminurl->register('admin.home', 'Dotclear\Admin\Page\Home');
        $this->adminurl->register('admin.auth', 'Dotclear\Admin\Page\Auth');

        $this->loadSession();

        if ((!$this->auth->userID() || $this->blog === null) && $this->adminurl->called() != 'admin.auth') {
            $this->adminurl->redirect('admin.auth');
            exit;
        } else {
            $this->loadPage();
        }

exit('admin: j en suis la ');
    }

    private function loadSession(): bool
    {
        if (defined('DOTCLEAR_AUTH_SESS_ID') && defined('DOTCLEAR_AUTH_SESS_UID')) {
            # We have session information in constants
            $_COOKIE[DOTCLEAR_SESSION_NAME] = DOTCLEAR_AUTH_SESS_ID;

            if (!$this->auth->checkSession(DOTCLEAR_AUTH_SESS_UID)) {
                throw new Exception('Invalid session data.');
            }

            # Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->checkNonce($_POST['xd_check'])) {
                    throw new Exception('Precondition Failed.');
                }
            }

            if (empty($_SESSION['sess_blog_id'])) {
                throw new Exception('Permission denied.');
            }

            # Loading locales
            $this->loadLocales();

            $this->setBlog($_SESSION['sess_blog_id']);
            if (!$this->blog->id) {
                throw new Exception('Permission denied.');
            }
        } elseif ($this->auth->sessionExists()) {
            # If we have a session we launch it now
            try {
                if (!$this->auth->checkSession()) {
                    # Avoid loop caused by old cookie
                    $p    = $this->session->getCookieParameters(false, -600);
                    $p[3] = '/';
                    call_user_func_array('setcookie', $p);

                    $this->adminurl->redirect('admin.auth');
                    exit;
                }
            } catch (Exception $e) {
                static::error(__('Database error'), __('There seems to be no Session table in your database. Is Dotclear completly installed?'), 20);
            }

            # Check nonce from POST requests
            if (!empty($_POST)) {
                if (empty($_POST['xd_check']) || !$this->checkNonce($_POST['xd_check'])) {
                    Http::head(412);
                    header('Content-Type: text/plain');
                    echo 'Precondition Failed';
                    exit;
                }
            }

            if (!empty($_REQUEST['switchblog'])
                && $this->auth->getPermissions($_REQUEST['switchblog']) !== false) {
                $_SESSION['sess_blog_id'] = $_REQUEST['switchblog'];
                if (isset($_SESSION['media_manager_dir'])) {
                    unset($_SESSION['media_manager_dir']);
                }
                if (isset($_SESSION['media_manager_page'])) {
                    unset($_SESSION['media_manager_page']);
                }

                if (!empty($_REQUEST['redir'])) {
                    # Keep context as far as possible
                    $redir = $_REQUEST['redir'];
                } else {
                    # Removing switchblog from URL
                    $redir = $_SERVER['REQUEST_URI'];
                    $redir = preg_replace('/switchblog=(.*?)(&|$)/', '', $redir);
                    $redir = preg_replace('/\?$/', '', $redir);
                }
                Http::redirect($redir);
                exit;
            }

            # Check blog to use and log out if no result
            if (isset($_SESSION['sess_blog_id'])) {
                if ($this->auth->getPermissions($_SESSION['sess_blog_id']) === false) {
                    unset($_SESSION['sess_blog_id']);
                }
            } else {
                if (($b = $this->auth->findUserBlog($this->auth->getInfo('user_default_blog'))) !== false) {
                    $_SESSION['sess_blog_id'] = $b;
                    unset($b);
                }
            }

            # Loading locales
            $this->loadLocales();

            if (isset($_SESSION['sess_blog_id'])) {
                $this->setBlog($_SESSION['sess_blog_id']);
            } else {
                $this->session->destroy();
                $this->adminurl->redirect('admin.auth');
                exit;
            }
        }

        return true;
    }

    private function loadPage(?string $page = null): void
    {
        if ($page === null) {
            $page = $_REQUEST['handler'] ?? 'admin.home';
        }
        try {
            $class = $this->adminurl->getBase($page);

            if (class_exists($class)) {
                new $class($this);
                exit;
            } else {
                throw new Exception(sprintf(__('<p>Failed to load URL for handler %s.</p>'), $page));
            }
        } catch (Exception $e) {
            static::error(
                __('Unknow URL'),
                $e->getMessage(),
                20
            );
        }
    }

    protected function loadLocales()
    {
        $_lang = $this->auth->getInfo('user_lang');
        $_lang = preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_lang) ? $_lang : 'en';

        l10n::lang($_lang);
        if (l10n::set(static::root(DOTCLEAR_L10N_DIR, $_lang, 'date')) === false && $_lang != 'en') {
            l10n::set(static::root(DOTCLEAR_L10N_DIR, 'en', 'date'));
        }
        l10n::set(static::root(DOTCLEAR_L10N_DIR, $_lang, 'main'));
        l10n::set(static::root(DOTCLEAR_L10N_DIR, $_lang, 'public'));
        l10n::set(static::root(DOTCLEAR_L10N_DIR, $_lang, 'plugins'));

        // Set lexical lang
        Utils::setlexicalLang('admin', $_lang);
    }

    protected function dc_admin_icon_url($img)
    {
        $this->auth->user_prefs->addWorkspace('interface');
        $user_ui_iconset = @$this->auth->user_prefs->interface->iconset;
        if (($user_ui_iconset) && ($img)) {
            $icon = false;
            if ((preg_match('/^images\/menu\/(.+)$/', $img, $m)) || (preg_match('/^index\.php\?pf=(.+)$/', $img, $m))) {
                if ($m[1]) {
                    $icon = Path::real(dirname(__FILE__) . '/../../admin/images/iconset/' . $user_ui_iconset . '/' . $m[1], false);
                    if ($icon !== false) {
                        $allow_types = ['svg', 'png', 'jpg', 'jpeg', 'gif'];
                        if (is_file($icon) && is_readable($icon) && in_array(Files::getExtension($icon), $allow_types)) {
                            return DC_ADMIN_URL . 'images/iconset/' . $user_ui_iconset . '/' . $m[1];
                        }
                    }
                }
            }
        }

        return $img;
    }

    public function addMenuItem($section, $desc, $adminurl, $icon, $perm, $pinned = false, $strict = false)
    {
        $url     = $this->adminurl->get($adminurl);
        $pattern = '@' . preg_quote($url) . ($strict ? '' : '(\?.*)?') . '$@';
        $_menu[$section]->prependItem($desc, $url, $icon,
            preg_match($pattern, $_SERVER['REQUEST_URI']), $perm, null, null, $pinned);
    }
}
