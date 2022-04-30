<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Url;

// Dotclear\Core\Url\Url
use ArrayObject;
use Closure;
use Dotclear\App;
use Dotclear\Core\Trackback\Trackback;
use Dotclear\Core\Xmlrpc\Xmlrpc;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;
use Exception;

/**
 * URL handler (public) methods.
 *
 * @ingroup  Core Public Url
 */
class Url
{
    /**
     * @var array<string,array> $types
     *                          URL registered types
     */
    protected $types = [];

    /**
     * @var callable $default_handler
     *               Default URL handler callback
     */
    protected $default_handler;

    /** @var array<int,callable> $error_handlers
     * Error URL handler
     */
    protected $error_handlers = [];

    /**
     * @var string $mode
     *             URL mode
     */
    public $mode = 'path_info';

    /**
     * @var string $type
     *             URL handler current type
     */
    public $type = 'default';

    /**
     * @var array<int,string> $mod_files
     *                        List of script used files
     */
    public $mod_files = [];

    /**
     * @var array<int,int> $mod_ts
     *                     List of timestamp
     */
    public $mod_ts = [];

    /**
     * @var string $args
     *             URL args
     */
    public $args;

    /**
     * @var string $search_string
     *             Search string
     */
    public $search_string;

    /**
     * @var int $search_count
     *          Search count
     */
    public $search_count;

    /**
     * Constructor.
     *
     * Do not change 'resources' handler as css and js use hard coded resources urls
     */
    public function __construct()
    {
        $this->registerDefault([$this, 'home']);
        $this->registerError([$this, 'default404']);
        $this->register('lang', '', '^([a-zA-Z]{2}(?:-[a-z]{2})?(?:/page/[0-9]+)?)$', [$this, 'lang']);
        $this->register('posts', 'posts', '^posts(/.+)?$', [$this, 'home']);
        $this->register('post', 'post', '^post/(.+)$', [$this, 'post']);
        $this->register('preview', 'preview', '^preview/(.+)$', [$this, 'preview']);
        $this->register('category', 'category', '^category/(.+)$', [$this, 'category']);
        $this->register('archive', 'archive', '^archive(/.+)?$', [$this, 'archive']);
        $this->register('resources', 'resources', '^resources/(.+)?$', [$this, 'resources']);
        $this->register('feed', 'feed', '^feed/(.+)$', [$this, 'feed']);
        $this->register('trackback', 'trackback', '^trackback/(.+)$', [$this, 'trackback']);
        $this->register('webmention', 'webmention', '^webmention(/.+)?$', [$this, 'webmention']);
        $this->register('rsd', 'rsd', '^rsd$', [$this, 'rsd']);
        $this->register('xmlrpc', 'xmlrpc', '^xmlrpc/(.+)$', [$this, 'xmlrpc']);
    }

    /**
     * Get home type.
     *
     * @return string Home type
     */
    protected function getHomeType(): string
    {
        return App::core()->blog()->settings()->get('system')->get('static_home') ? 'static' : 'default';
    }

    /**
     * Is current URL is home.
     *
     * @param string $type The type
     *
     * @return bool True if type correspond
     */
    public function isHome(string $type): bool
    {
        return $this->getHomeType() == $type;
    }

    /**
     * Get URL for given type and optionnal value.
     *
     * @param string     $type  The type
     * @param int|string $value The value
     *
     * @return string The URL
     */
    public function getURLFor(string $type, string|int $value = ''): string
    {
        $url = App::core()->behavior()->call('publicGetURLFor', $type, $value);
        if (!$url) {
            $url = $this->getBase($type);
            if ('' !== $value) {
                if ($url) {
                    $url .= '/';
                }
                $url .= $value;
            }
        }

        return $url ?? '';
    }

    /**
     * Register a URL.
     *
     * @param string               $type           The type
     * @param string               $url            The URL
     * @param string               $representation The representation
     * @param array|Closure|string $handler        The URL handler callback
     */
    public function register(string $type, string $url, string $representation, string|array|Closure $handler): void
    {
        $args = new ArrayObject(func_get_args());

        App::core()->behavior()->call('publicRegisterURL', $args);

        $this->types[$args[0]] = [
            'url'            => $args[1],
            'representation' => $args[2],
            'handler'        => $args[3],
        ];
    }

    /**
     * Register default handler.
     *
     * @param callable $handler The handler
     */
    public function registerDefault(callable $handler): void
    {
        $this->default_handler = $handler;
    }

    /**
     * Register an error handler.
     *
     * @param callable $handler The handler
     */
    public function registerError(callable $handler): void
    {
        array_unshift($this->error_handlers, $handler);
    }

    /**
     * Unregister a URL type.
     *
     * @param string $type The type
     */
    public function unregister(string $type): void
    {
        if (isset($this->types[$type])) {
            unset($this->types[$type]);
        }
    }

    /**
     * Get registered tyeps.
     *
     * @return array The types
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Get base URL for a type.
     *
     * @param string $type The type
     *
     * @return null|string The base URL
     */
    public function getBase(string $type): ?string
    {
        return isset($this->types[$type]) ? $this->types[$type]['url'] : null;
    }

    /**
     * Get current patge number.
     *
     * @param null|string $args Url args
     *
     * @return false|int The page number or false
     */
    protected function getPageNumber(?string &$args): int|false
    {
        if (preg_match('#(^|/)page/([0-9]+)$#', $args, $m)) {
            $n = (int) $m[2];
            if (0 < $n) {
                $args = preg_replace('#(^|/)page/([0-9]+)$#', '', $args);

                return $n;
            }
        }

        return false;
    }

    /**
     * Serve document.
     *
     * @param string $tpl          The template name to serve
     * @param string $content_type The content type (as of HTTP header)
     * @param bool   $http_cache   Use HTTP cache
     * @param bool   $http_etag    Use HTTP etag
     */
    protected function serveDocument(string $tpl, string $content_type = 'text/html', bool $http_cache = true, bool $http_etag = true): void
    {
        if (null === App::core()->context()->get('nb_entry_per_page')) {
            App::core()->context()->set('nb_entry_per_page', App::core()->blog()->settings()->get('system')->get('nb_post_per_page'));
        }
        if (null === App::core()->context()->get('nb_entry_first_page')) {
            App::core()->context()->set('nb_entry_first_page', App::core()->context()->get('nb_entry_per_page'));
        }

        $tpl_file = App::core()->template()->getFilePath($tpl);
        if (!$tpl_file) {
            throw new CoreException('Unable to find template ');
        }

        App::core()->context()->set('current_tpl', $tpl);
        App::core()->context()->set('content_type', $content_type);
        App::core()->context()->set('http_cache', $http_cache);
        App::core()->context()->set('http_etag', $http_etag);

        App::core()->behavior()->call('urlHandlerBeforeGetData', App::core()->context());

        if (App::core()->context()->get('http_cache')) {
            $this->mod_files = array_merge($this->mod_files, [$tpl_file]);
            Http::cache($this->mod_files, $this->mod_ts);
        }

        header('Content-Type: ' . App::core()->context()->get('content_type') . '; charset=UTF-8');

        $this->additionalHeaders();

        $result                 = new ArrayObject();
        $result['content']      = App::core()->template()->getData(App::core()->context()->get('current_tpl'));
        $result['content_type'] = App::core()->context()->get('content_type');
        $result['tpl']          = App::core()->context()->get('current_tpl');
        $result['blogupddt']    = App::core()->blog()->upddt;
        $result['headers']      = headers_list();

        // --BEHAVIOR-- urlHandlerServeDocument
        App::core()->behavior()->call('urlHandlerServeDocument', $result);

        if (App::core()->context()->get('http_cache') && App::core()->context()->get('http_etag')) {
            Http::etag($result['content'], Http::getSelfURI());
        }
        echo $result['content'];
    }

    /**
     * Get document.
     *
     * Parse URL query and search registered URL handler
     */
    public function getDocument(): void
    {
        $type = $args = '';

        if ('path_info' == $this->mode) {
            $part = substr($_SERVER['PATH_INFO'], 1);
        } else {
            $part = '';
            $qs   = $this->parseQueryString();

            // Recreates some _GET and _REQUEST pairs
            if (!empty($qs)) {
                foreach ($_GET as $k => $v) {
                    if (isset($_REQUEST[$k])) {
                        unset($_REQUEST[$k]);
                    }
                }
                $_GET     = $qs;
                $_REQUEST = array_merge($qs, $_REQUEST);

                foreach ($qs as $k => $v) {
                    if (null === $v) {
                        $part = $k;
                        unset($_GET[$k], $_REQUEST[$k]);
                    }

                    break;
                }
            }
        }

        $_SERVER['URL_REQUEST_PART'] = $part;

        $this->getArgs($part, $type, $this->args);

        // --BEHAVIOR-- urlHandlerGetArgsDocument
        App::core()->behavior()->call('urlHandlerGetArgsDocument', $this);

        if (!$type) {
            $this->type = $this->getHomeType();
            $this->callDefaultHandler($this->args);
        } else {
            $this->type = $type;
            $this->callHandler($type, $this->args);
        }
    }

    /**
     * Parse URL arguments.
     *
     * @param null|string $part The part
     * @param null|string $type The type
     * @param null|string $args The arguments
     */
    public function getArgs(?string $part, ?string &$type, ?string &$args): void
    {
        if ('' == $part) {
            $type = null;
            $args = null;

            return;
        }

        $this->sortTypes();

        foreach ($this->types as $k => $v) {
            $repr = $v['representation'];
            if ($repr == $part) {
                $type = $k;
                $args = null;

                return;
            }
            if (preg_match('#' . $repr . '#', (string) $part, $m)) {
                $type = $k;
                $args = $m[1] ?? null;

                return;
            }
        }

        // No type, pass args to default
        $args = $part;
    }

    /**
     * Call URL handler.
     *
     * @param string      $type The type
     * @param null|string $args The arguments
     */
    public function callHandler(string $type, ?string $args): void
    {
        if (!isset($this->types[$type])) {
            throw new CoreException('Unknown URL type');
        }

        $handler = $this->types[$type]['handler'];
        if (!is_callable($handler)) {
            throw new CoreException('Unable to call function');
        }

        try {
            call_user_func($handler, $args);
        } catch (CoreException $e) {
            foreach ($this->error_handlers as $err_handler) {
                if (call_user_func($err_handler, $args, $type, $e) === true) {
                    return;
                }
            }
            // propagate CoreException, as it has not been processed by handlers
            throw $e;
        }
    }

    /**
     * Call default URL handler.
     *
     * @param null|string $args The arguments
     */
    public function callDefaultHandler(?string $args): void
    {
        if (!is_callable($this->default_handler)) {
            throw new CoreException('Unable to call function');
        }

        try {
            call_user_func($this->default_handler, $args);
        } catch (CoreException $e) {
            foreach ($this->error_handlers as $err_handler) {
                if (call_user_func($err_handler, $args, 'default', $e) === true) {
                    return;
                }
            }
            // propagate CoreException, as it has not been processed by handlers
            throw $e;
        }
    }

    /**
     * Parse query string.
     *
     * @return array The arguments
     */
    protected function parseQueryString(): array
    {
        if (!empty($_SERVER['QUERY_STRING'])) {
            $q = explode('&', $_SERVER['QUERY_STRING']);
            $T = [];
            foreach ($q as $v) {
                $t = explode('=', $v, 2);

                $t[0]     = rawurldecode($t[0]);
                $T[$t[0]] = isset($t[1]) ? urldecode($t[1]) : null;
            }

            return $T;
        }

        return [];
    }

    /**
     * Sort types.
     */
    protected function sortTypes(): void
    {
        $r = [];
        foreach ($this->types as $k => $v) {
            $r[$k] = $v['url'];
        }
        array_multisort($r, SORT_DESC, $this->types);
    }

    /**
     * Get page 404.
     */
    public function p404(): void
    {
        throw new CoreException('Page not found', 404);
    }

    /**
     * Get default page 404.
     *
     * @param null|string $args The arguments
     * @param null|string $type The type
     * @param Exception   $e    The exception
     */
    public function default404(?string $args, ?string $type, Exception $e): void
    {
        if ($e->getCode() != 404) {
            throw $e;
        }

        header('Content-Type: text/html; charset=UTF-8');
        Http::head(404, 'Not Found');
        App::core()->url()->type = '404';
        App::core()->context()->set('current_tpl', '404.html');
        App::core()->context()->set('content_type', 'text/html');

        echo App::core()->template()->getData(App::core()->context()->get('current_tpl'));

        // --BEHAVIOR-- publicAfterDocument
        App::core()->behavior()->call('publicAfterDocument');

        exit;
    }

    /**
     * Get home page.
     *
     * @param null|string $args The arguments
     */
    public function home(?string $args): void
    {
        // Page number may have been set by $this->lang() which ends with a call to $this->home(null)
        $n = $args ? $this->getPageNumber($args) : App::core()->context()->page_number();

        if ($args && !$n) {
            // Then specified URL went unrecognized by all URL handlers and
            // defaults to the home page, but is not a page number.
            $this->p404();
        } else {
            App::core()->url()->type = 'default';
            if ($n) {
                App::core()->context()->page_number($n);
                if (1 < $n) {
                    App::core()->url()->type = 'default-page';
                }
            }

            if (empty($_GET['q'])) {
                if (null !== App::core()->blog()->settings()->get('system')->get('nb_post_for_home')) {
                    App::core()->context()->set('nb_entry_first_page', App::core()->blog()->settings()->get('system')->get('nb_post_for_home'));
                }
                $this->serveDocument('home.html');
                App::core()->blog()->posts()->publishScheduledEntries();
            } else {
                $this->search();
            }
        }
    }

    /**
     * Get static home page.
     *
     * @param null|string $args The arguments
     */
    public function static_home(?string $args): void
    {
        App::core()->url()->type = 'static';

        if (empty($_GET['q'])) {
            $this->serveDocument('static.html');
            App::core()->blog()->posts()->publishScheduledEntries();
        } else {
            $this->search();
        }
    }

    /**
     * Get search page.
     */
    public function search(): void
    {
        if (App::core()->blog()->settings()->get('system')->get('no_search')) {
            // Search is disabled for this blog.
            $this->p404();
        } else {
            App::core()->url()->type = 'search';

            App::core()->url()->search_string = !empty($_GET['q']) ? Html::escapeHTML(rawurldecode($_GET['q'])) : '';
            if (App::core()->url()->search_string) {
                $params = new ArrayObject(['search' => App::core()->url()->search_string]);
                App::core()->behavior()->call('publicBeforeSearchCount', $params);
                App::core()->url()->search_count = App::core()->blog()->posts()->getPosts($params, true)->fInt();
            }

            $this->serveDocument('search.html');
        }
    }

    /**
     * Get lang page.
     *
     * @param string $args The lang
     */
    public function lang(string $args): void
    {
        $n      = $this->getPageNumber($args);
        $params = new ArrayObject(['lang' => $args]);

        App::core()->behavior()->call('publicLangBeforeGetLangs', $params, $args);

        App::core()->context()->set('langs', App::core()->blog()->posts()->getLangs($params));

        if (App::core()->context()->get('langs')->isEmpty()) {
            // The specified language does not exist.
            $this->p404();
        } else {
            if ($n) {
                App::core()->context()->page_number($n);
            }
            App::core()->context()->set('cur_lang', $args);
            $this->home(null);
        }
    }

    /**
     * Get category page.
     *
     * @param string $args The category
     */
    public function category(string $args): void
    {
        $n = $this->getPageNumber($args);

        if ('' == $args && !$n) {
            // No category was specified.
            $this->p404();
        } else {
            $params = new ArrayObject([
                'cat_url'       => $args,
                'post_type'     => 'post',
                'without_empty' => false, ]);

            App::core()->behavior()->call('publicCategoryBeforeGetCategories', $params, $args);

            App::core()->context()->set('categories', App::core()->blog()->categories()->getCategories($params));

            if (App::core()->context()->get('categories')->isEmpty()) {
                // The specified category does no exist.
                $this->p404();
            } else {
                if ($n) {
                    App::core()->context()->page_number($n);
                }
                $this->serveDocument('category.html');
            }
        }
    }

    /**
     * Get archive page.
     *
     * @param null|string $args The arguments
     */
    public function archive(?string $args): void
    {
        // Nothing or year and month
        if ('' == $args) {
            $this->serveDocument('archive.html');
        } elseif (preg_match('|^/([0-9]{4})/([0-9]{2})$|', $args, $m)) {
            $params = new ArrayObject([
                'year'  => $m[1],
                'month' => $m[2],
                'type'  => 'month', ]);

            App::core()->behavior()->call('publicArchiveBeforeGetDates', $params, $args);

            App::core()->context()->set('archives', App::core()->blog()->posts()->getDates($params->getArrayCopy()));

            if (App::core()->context()->get('archives')->isEmpty()) {
                // There is no entries for the specified period.
                $this->p404();
            } else {
                $this->serveDocument('archive_month.html');
            }
        } else {
            // The specified URL is not a date.
            $this->p404();
        }
    }

    /**
     * Get post page.
     *
     * @param string $args The post URL
     */
    public function post(string $args): void
    {
        if ('' == $args) {
            // No entry was specified.
            $this->p404();
        } else {
            App::core()->blog()->withoutPassword(false);

            $params = new ArrayObject([
                'post_url' => $args, ]);

            App::core()->behavior()->call('publicPostBeforeGetPosts', $params, $args);

            App::core()->context()->set('posts', App::core()->blog()->posts()->getPosts($params));

            $cp               = new ArrayObject();
            $cp['content']    = '';
            $cp['rawcontent'] = '';
            $cp['name']       = '';
            $cp['mail']       = '';
            $cp['site']       = '';
            $cp['preview']    = false;
            $cp['remember']   = false;
            App::core()->context()->set('comment_preview', $cp);

            App::core()->blog()->withoutPassword(true);

            if (App::core()->context()->get('posts')->isEmpty()) {
                // The specified entry does not exist.
                $this->p404();
            } else {
                $post_id       = App::core()->context()->get('posts')->f('post_id');
                $post_password = App::core()->context()->get('posts')->f('post_password');

                // Password protected entry
                if ('' != $post_password && !App::core()->context()->get('preview')) {
                    // Get passwords cookie
                    if (isset($_COOKIE['dc_passwd'])) {
                        $pwd_cookie = json_decode($_COOKIE['dc_passwd']);
                        if (null === $pwd_cookie) {
                            $pwd_cookie = [];
                        } else {
                            $pwd_cookie = (array) $pwd_cookie;
                        }
                    } else {
                        $pwd_cookie = [];
                    }

                    // Check for match
                    // Note: We must prefix post_id key with '#'' in pwd_cookie array in order to avoid integer conversion
                    // because MyArray["12345"] is treated as MyArray[12345]
                    if (!empty($_POST['password']) && $_POST['password'] == $post_password
                        || isset($pwd_cookie['#' . $post_id]) && $pwd_cookie['#' . $post_id] == $post_password
                    ) {
                        $pwd_cookie['#' . $post_id] = $post_password;
                        setcookie('dc_passwd', json_encode($pwd_cookie), 0, '/');
                    } else {
                        $this->serveDocument('password-form.html', 'text/html', false);

                        return;
                    }
                }

                $post_comment = isset($_POST['c_name'], $_POST['c_mail'], $_POST['c_site'], $_POST['c_content']) && App::core()->context()->get('posts')->commentsActive();

                // Posting a comment
                if ($post_comment) {
                    // Spam trap
                    if (!empty($_POST['f_mail'])) {
                        Http::head(412, 'Precondition Failed');
                        header('Content-Type: text/plain');
                        echo 'So Long, and Thanks For All the Fish';
                        // Exits immediately the application to preserve the server.
                        exit;
                    }

                    $name    = $_POST['c_name'];
                    $mail    = $_POST['c_mail'];
                    $site    = $_POST['c_site'];
                    $content = $_POST['c_content'];
                    $preview = !empty($_POST['preview']);

                    if ('' != $content) {
                        // --BEHAVIOR-- publicBeforeCommentTransform
                        $buffer = App::core()->behavior()->call('publicBeforeCommentTransform', $content);
                        if ('' != $buffer) {
                            $content = $buffer;
                        } else {
                            if (App::core()->blog()->settings()->get('system')->get('wiki_comments')) {
                                App::core()->wiki()->initWikiComment();
                            } else {
                                App::core()->wiki()->initWikiSimpleComment();
                            }
                            $content = App::core()->wiki()->wikiTransform($content);
                        }
                        $content = Html::filter($content);
                    }

                    $cp               = App::core()->context()->get('comment_preview');
                    $cp['content']    = $content;
                    $cp['rawcontent'] = $_POST['c_content'];
                    $cp['name']       = $name;
                    $cp['mail']       = $mail;
                    $cp['site']       = $site;

                    if ($preview) {
                        // --BEHAVIOR-- publicBeforeCommentPreview
                        App::core()->behavior()->call('publicBeforeCommentPreview', $cp);

                        $cp['preview'] = true;
                    } else {
                        // Post the comment
                        $cur = App::core()->con()->openCursor(App::core()->prefix . 'comment');
                        $cur->setField('comment_author', $name);
                        $cur->setField('comment_site', Html::clean($site));
                        $cur->setField('comment_email', Html::clean($mail));
                        $cur->setField('comment_content', $content);
                        $cur->setField('post_id', App::core()->context()->get('posts')->fInt('post_id'));
                        $cur->setField('comment_status', App::core()->blog()->settings()->get('system')->get('comments_pub') ? 1 : -1);
                        $cur->setField('comment_ip', Http::realIP());

                        $redir = App::core()->context()->get('posts')->getURL();
                        $redir .= 'query_string' == App::core()->blog()->settings()->get('system')->get('url_scan') ? '&' : '?';

                        try {
                            if (!Text::isEmail($cur->getField('comment_email'))) {
                                throw new CoreException(__('You must provide a valid email address.'));
                            }

                            // --BEHAVIOR-- publicBeforeCommentCreate
                            App::core()->behavior()->call('publicBeforeCommentCreate', $cur);
                            if ($cur->getField('post_id')) {
                                $comment_id = App::core()->blog()->comments()->addComment($cur);

                                // --BEHAVIOR-- publicAfterCommentCreate
                                App::core()->behavior()->call('publicAfterCommentCreate', $cur, $comment_id);
                            }

                            if (1 == (int) $cur->getField('comment_status')) {
                                $redir_arg = 'pub=1';
                            } else {
                                $redir_arg = 'pub=0';
                            }

                            $redir_arg .= filter_var(App::core()->behavior()->call('publicBeforeCommentRedir', $cur), FILTER_SANITIZE_URL);

                            header('Location: ' . $redir . $redir_arg);
                        } catch (Exception $e) {
                            App::core()->context()->set('form_error', $e->getMessage());
                        }
                    }
                    App::core()->context()->set('comment_preview', $cp);
                }

                // The entry
                if (App::core()->context()->get('posts')->trackbacksActive()) {
                    header('X-Pingback: ' . App::core()->blog()->getURLFor('xmlrpc', App::core()->blog()->id));
                    header('Link: <' . App::core()->blog()->getURLFor('webmention') . '>; rel="webmention"');
                }
                $this->serveDocument('post.html');
            }
        }
    }

    /**
     * Get preview page (for admin).
     *
     * @param string $args The preview URL
     */
    public function preview(string $args): void
    {
        if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#', $args, $m)) {
            // The specified Preview URL is malformed.
            $this->p404();
        } else {
            $user_id  = $m[1];
            $user_key = $m[2];
            $post_url = $m[3];
            if (!App::core()->user()->checkUser($user_id, null, $user_key)) {
                // The user has no access to the entry.
                $this->p404();
            } else {
                App::core()->context()->set('preview', true);
                if ('' != App::core()->config()->get('admin_url')) {
                    App::core()->context()->set('xframeoption', App::core()->config()->get('admin_url'));
                }
                $this->post($post_url);
            }
        }
    }

    /**
     * Get feed page.
     *
     * @param string $args The arguments
     */
    public function feed(string $args): void
    {
        $type     = null;
        $comments = false;
        $cat_url  = false;
        $post_id  = null;
        $subtitle = '';

        $mime = 'application/xml';

        if (preg_match('!^([a-z]{2}(-[a-z]{2})?)/(.*)$!', $args, $m)) {
            $params = new ArrayObject(['lang' => $m[1]]);

            $args = $m[3];

            App::core()->behavior()->call('publicFeedBeforeGetLangs', $params, $args);

            App::core()->context()->set('langs', App::core()->blog()->posts()->getLangs($params));

            if (App::core()->context()->get('langs')->isEmpty()) {
                // The specified language does not exist.
                $this->p404();

                return;
            }
            App::core()->context()->set('cur_lang', $m[1]);
        }

        if (preg_match('#^rss2/xslt$#', $args, $m)) {
            // RSS XSLT stylesheet
            Http::$cache_max_age = 60 * 60;
            $this->serveDocument('rss2.xsl', 'text/xml');

            return;
        }
        if (preg_match('#^(atom|rss2)/comments/([0-9]+)$#', $args, $m)) {
            // Post comments feed
            $type     = $m[1];
            $comments = true;
            $post_id  = (int) $m[2];
        } elseif (preg_match('#^(?:category/(.+)/)?(atom|rss2)(/comments)?$#', $args, $m)) {
            // All posts or comments feed
            $type     = $m[2];
            $comments = !empty($m[3]);
            if (!empty($m[1])) {
                $cat_url = $m[1];
            }
        } else {
            // The specified Feed URL is malformed.
            $this->p404();

            return;
        }

        if ($cat_url) {
            $params = new ArrayObject([
                'cat_url'   => $cat_url,
                'post_type' => 'post', ]);

            App::core()->behavior()->call('publicFeedBeforeGetCategories', $params, $args);

            App::core()->context()->set('categories', App::core()->blog()->categories()->getCategories($params));

            if (App::core()->context()->get('categories')->isEmpty()) {
                // The specified category does no exist.
                $this->p404();

                return;
            }

            $subtitle = ' - ' . App::core()->context()->get('categories')->f('cat_title');
        } elseif ($post_id) {
            $params = new ArrayObject([
                'post_id'   => $post_id,
                'post_type' => '', ]);

            App::core()->behavior()->call('publicFeedBeforeGetPosts', $params, $args);

            App::core()->context()->set('posts', App::core()->blog()->posts()->getPosts($params));

            if (App::core()->context()->get('posts')->isEmpty()) {
                // The specified post does not exist.
                $this->p404();

                return;
            }

            $subtitle = ' - ' . App::core()->context()->get('posts')->f('post_title');
        }

        $tpl = $type;
        if ($comments) {
            $tpl .= '-comments';
            App::core()->context()->set('nb_comment_per_page', (int) App::core()->blog()->settings()->get('system')->get('nb_comment_per_feed'));
        } else {
            App::core()->context()->set('nb_entry_per_page', (int) App::core()->blog()->settings()->get('system')->get('nb_post_per_feed'));
            App::core()->context()->set('short_feed_items', (bool) App::core()->blog()->settings()->get('system')->get('short_feed_items'));
        }
        $tpl .= '.xml';

        if ('atom' == $type) {
            $mime = 'application/atom+xml';
        }

        App::core()->context()->set('feed_subtitle', $subtitle);

        header('X-Robots-Tag: ' . App::core()->context()->robotsPolicy(App::core()->blog()->settings()->get('system')->get('robots_policy'), ''));
        $this->serveDocument($tpl, $mime);
        if (!$comments && !$cat_url) {
            App::core()->blog()->posts()->publishScheduledEntries();
        }
    }

    /**
     * Get trackback action page.
     *
     * @param string $args The trackback id
     */
    public function trackback(string $args): void
    {
        if (!preg_match('/^[0-9]+$/', $args)) {
            // The specified trackback URL is not an number
            $this->p404();
        } else {
            // Save locally post_id from args
            $post_id = (int) $args;

            $args            = [];
            $args['post_id'] = $post_id;
            $args['type']    = 'trackback';

            // --BEHAVIOR-- publicBeforeReceiveTrackback
            App::core()->behavior()->call('publicBeforeReceiveTrackback', $args);

            $trackback = new Trackback();
            $trackback->receiveTrackback($post_id);
        }
    }

    /**
     * Get webmention action page.
     *
     * @param null|string $args The arguments
     */
    public function webmention(?string $args): void
    {
        $args = ['type' => 'webmention'];

        // --BEHAVIOR-- publicBeforeReceiveTrackback
        App::core()->behavior()->call('publicBeforeReceiveTrackback', $args);

        $trackback = new Trackback();
        $trackback->receiveWebmention();
    }

    /**
     * Get rsd page.
     *
     * @param null|string $args The arguments
     */
    public function rsd(?string $args): void
    {
        Http::cache($this->mod_files, $this->mod_ts);

        header('Content-Type: text/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">' . "\n" .
        "<service>\n" .
        "  <engineName>Dotclear</engineName>\n" .
        "  <engineLink>https://dotclear.org/</engineLink>\n" .
        '  <homePageLink>' . Html::escapeHTML(App::core()->blog()->url) . "</homePageLink>\n";

        if (App::core()->blog()->settings()->get('system')->get('enable_xmlrpc')) {
            $u = sprintf(App::core()->config()->get('xmlrpc_url'), App::core()->blog()->url, App::core()->blog()->id);

            echo "  <apis>\n" .
                '    <api name="WordPress" blogID="1" preferred="true" apiLink="' . $u . '"/>' . "\n" .
                '    <api name="Movable Type" blogID="1" preferred="false" apiLink="' . $u . '"/>' . "\n" .
                '    <api name="MetaWeblog" blogID="1" preferred="false" apiLink="' . $u . '"/>' . "\n" .
                '    <api name="Blogger" blogID="1" preferred="false" apiLink="' . $u . '"/>' . "\n" .
                "  </apis>\n";
        }

        echo "</service>\n" .
            "</rsd>\n";
    }

    /**
     * Get xml rpc page.
     *
     * @param string $args The blog id
     */
    public function xmlrpc(string $args): void
    {
        $blog_id = preg_replace('#^([^/]*).*#', '$1', $args);
        $xmlrpc  = new Xmlrpc($blog_id);
        $xmlrpc->serve();
    }

    /**
     * Get resource.
     *
     * @param string $args The arguments
     */
    public function resources(string $args): void
    {
        if (empty($args)) {
            $this->p404();
        }

        $dirs = [];

        // Check if it in Var path
        $var_args = explode('/', $args);
        $var_path = App::core()->config()->get('var_dir');
        if (1 < count($var_args) && 'var' == array_shift($var_args) && !empty($var_path) && is_dir($var_path)) {
            $dirs[] = $var_path;
            $args   = implode('/', $var_args);
        }

        // Try to find module id and type
        if (empty($dirs)) {
            // Public url should be resources/ModuleType/ModuleId/a_sub_folder/a_file.ext
            $module_args = explode('/', $args);
            if (2 < count($module_args)) {
                $module_type = array_shift($module_args);
                $module_id   = array_shift($module_args);

                // Check module type
                $modules_class = 'Dotclear\\Module\\' . $module_type . '\\Public\\Modules' . $module_type;
                if (is_subclass_of($modules_class, 'Dotclear\\Module\\AbstractModules')) {
                    $modules = new $modules_class(null, true);
                    // Chek if module path exists
                    foreach ($modules->getModulesPath() as $modules_path) {
                        if (is_dir(Path::implode($modules_path, $module_id))) {
                            $dirs[] = Path::implode($modules_path, $module_id, 'Public', 'resources');
                            $dirs[] = Path::implode($modules_path, $module_id, 'Common', 'resources');
                            $args   = implode('/', $module_args);

                            break;
                        }
                    }
                }
            }
        }

        // Current Theme paths
        if (empty($dirs) && App::core()->themes()) {
            $dirs = array_merge(
                array_values(App::core()->themes()->getThemePath('Public/resources')),
                array_values(App::core()->themes()->getThemePath('Common/resources'))
            );
        }

        // Blog public path
        if (App::core()->blog()) {
            $dirs[] = App::core()->blog()->public_path;
        }

        // List other available file paths
        $dirs[] = Path::implodeRoot('Process', 'Public', 'resources');
        $dirs[] = Path::implodeRoot('Core', 'resources', 'css');
        $dirs[] = Path::implodeRoot('Core', 'resources', 'js');

        // Search file
        if (!($file = Files::serveFile($args, $dirs, App::core()->config()->get('file_sever_type'), false, true))) {
            $this->p404();
        }

        if (App::core()->context()->get('http_cache')) {
            $this->mod_files = array_merge($this->mod_files, [basename($file)]);
            Http::cache($this->mod_files, $this->mod_ts);
        }

        header('Content-Type: ' . Files::getMimeType($file) . ';');
        $this->additionalHeaders();
        $content = file_get_contents($file);
        Http::etag($content, Http::getSelfURI());

        echo $content;
    }

    /**
     * Get additionnal headers.
     */
    protected function additionalHeaders()
    {
        // Additional headers
        $headers = new ArrayObject();
        if (App::core()->blog()->settings()->get('system')->get('prevents_clickjacking')) {
            if (App::core()->context()->exists('xframeoption')) {
                $url    = parse_url(App::core()->context()->get('xframeoption'));
                $header = sprintf(
                    'X-Frame-Options: %s',
                    is_array($url) ? ('ALLOW-FROM ' . $url['scheme'] . '://' . $url['host']) : 'SAMEORIGIN'
                );
            } else {
                // Prevents Clickjacking as far as possible
                $header = 'X-Frame-Options: SAMEORIGIN'; // FF 3.6.9+ Chrome 4.1+ IE 8+ Safari 4+ Opera 10.5+
            }
            $headers->append($header);
        }

        // --BEHAVIOR-- urlHandlerServeDocumentHeaders
        App::core()->behavior()->call('urlHandlerServeDocumentHeaders', $headers);

        // Send additional headers if any
        foreach ($headers as $header) {
            header($header);
        }
    }
}
