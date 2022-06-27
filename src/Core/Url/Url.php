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
use Dotclear\App;
use Dotclear\Core\Trackback\Trackback;
use Dotclear\Core\Xmlrpc\Xmlrpc;
use Dotclear\Database\Param;
use Dotclear\Exception\InvalidValueReference;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;
use Dotclear\Modules\Modules;
use Exception;

/**
 * URL handler (public) methods.
 *
 * @ingroup  Core Public Url
 */
final class Url
{
    /**
     * @var array<string,UrlItem> $handlers
     *                            URL registered types
     */
    private $handlers = [];

    /**
     * @var callable $default_handler
     *               Default URL handler callback
     */
    private $default_handler;

    /** @var array<int,callable> $error_handlers
     * Error URL handler
     */
    private $error_handlers = [];

    /**
     * @var string $mode
     *             URL mode
     */
    private $mode = 'path_info';

    /**
     * @var string $type
     *             URL handler current type
     */
    private $type = 'default';

    /**
     * @var array<int,string> $mod_files
     *                        List of script used files
     */
    private $mod_files = [];

    /**
     * @var array<int,int> $mod_ts
     *                     List of timestamp
     */
    private $mod_ts = [];

    /**
     * @var string $search_string
     *             Search string
     */
    private $search_string = '';

    /**
     * @var int $search_count
     *          Search count
     */
    private $search_count = 0;

    /**
     * Constructor.
     *
     * Do not change 'resources' handler as css and js use hard coded resources urls
     */
    public function __construct()
    {
        $this->setDefaultHandler(callback: [$this, 'home']);
        $this->setErrorHandler(callback: [$this, 'default404']);

        $this->addItem(new UrlItem(
            type: 'lang',
            url: '',
            scheme: '^([a-zA-Z]{2}(?:-[a-z]{2})?(?:/page/[0-9]+)?)$',
            callback: [$this, 'lang']
        ));
        $this->addItem(new UrlItem(
            type: 'posts',
            url: 'posts',
            scheme: '^posts(/.+)?$',
            callback: [$this, 'home']
        ));
        $this->addItem(new UrlItem(
            type: 'post',
            url: 'post',
            scheme: '^post/(.+)$',
            callback: [$this, 'post']
        ));
        $this->addItem(new UrlItem(
            type: 'preview',
            url: 'preview',
            scheme: '^preview/(.+)$',
            callback: [$this, 'preview']
        ));
        $this->addItem(new UrlItem(
            type: 'category',
            url: 'category',
            scheme: '^category/(.+)$',
            callback: [$this, 'category']
        ));
        $this->addItem(new UrlItem(
            type: 'archive',
            url: 'archive',
            scheme: '^archive(/.+)?$',
            callback: [$this, 'archive']
        ));
        $this->addItem(new UrlItem(
            type: 'resources',
            url: 'resources',
            scheme: '^resources/(.+)?$',
            callback: [$this, 'resources']
        ));
        $this->addItem(new UrlItem(
            type: 'feed',
            url: 'feed',
            scheme: '^feed/(.+)$',
            callback: [$this, 'feed']
        ));
        $this->addItem(new UrlItem(
            type: 'trackback',
            url: 'trackback',
            scheme: '^trackback/(.+)$',
            callback: [$this, 'trackback']
        ));
        $this->addItem(new UrlItem(
            type: 'webmention',
            url: 'webmention',
            scheme: '^webmention(/.+)?$',
            callback: [$this, 'webmention']
        ));
        $this->addItem(new UrlItem(
            type: 'rsd',
            url: 'rsd',
            scheme: '^rsd$',
            callback: [$this, 'rsd']
        ));
        $this->addItem(new UrlItem(
            type: 'xmlrpc',
            url: 'xmlrpc',
            scheme: '^xmlrpc/(.+)$',
            callback: [$this, 'xmlrpc']
        ));
    }

    /**
     * Set URL mode.
     *
     * Should be path_info or query_string.
     * Default is path_info.
     *
     * @param string $mode The URL mode
     */
    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Get script used files (for cache).
     *
     * @return array<int,string> List of script used files
     */
    public function getModFiles(): array
    {
        return $this->mod_files;
    }

    /**
     * Add script used files (for cache).
     *
     * @param Strings $files The files paths
     */
    public function addModFiles(Strings $files)
    {
        $this->mod_files = array_merge($this->mod_files, $files->dump());
    }

    /**
     * Get update times (for cache).
     *
     * @return array<int,int> The timestamps
     */
    public function getModTimestamps(): array
    {
        return $this->mod_ts;
    }

    /**
     * Add update times (for cache).
     *
     * @param Integers $timestamps The timestamps
     */
    public function addModTimestamps(Integers $timestamps)
    {
        $this->mod_ts = array_merge($this->mod_ts, $timestamps->dump());
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
     * Get home type.
     *
     * @return string Home type
     */
    private function getHomeType(): string
    {
        return App::core()->blog()->settings('system')->getSetting('static_home') ? 'static' : 'default';
    }

    /**
     * Get search query string.
     *
     * @return string The search query string
     */
    public function getSearchString(): string
    {
        return $this->search_string;
    }

    /**
     * Get search result count.
     *
     * @return int The search result count
     */
    public function getSearchCount(): int
    {
        return $this->search_count;
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
        $url = App::core()->behavior('publicBeforeGetURLFor')->call($type, $value);
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
     * @param UrlItem $item The URL handler definition instance
     */
    public function addItem(UrlItem $item): void
    {
        // --BEHAVIOR-- publicBeforeAddItem, UrlItem
        App::core()->behavior('publicBeforeAddItem')->call(item: $item);

        $this->handlers[$item->type] = $item;
    }

    /**
     * Unregister a URL handler.
     *
     * @param string $type The handler type
     */
    public function removeItem(string $type): void
    {
        if (isset($this->handlers[$type])) {
            unset($this->handlers[$type]);
        }
    }

    /**
     * Get registered handlers.
     *
     * @return array<string,UrlItem> The types
     */
    public function getItems(): array
    {
        return $this->handlers;
    }

    /**
     * Sort handlers.
     */
    private function sortItems(): void
    {
        $r = [];
        foreach ($this->handlers as $handler) {
            $r[$handler->type] = $handler->url;
        }
        array_multisort($r, SORT_DESC, $this->handlers);
    }

    /**
     * Register default handler.
     *
     * @param callable $callback The handler
     */
    public function setDefaultHandler(callable $callback): void
    {
        $this->default_handler = $callback;
    }

    /**
     * Register an error handler.
     *
     * @param callable $callback The handler
     */
    public function setErrorHandler(callable $callback): void
    {
        array_unshift($this->error_handlers, $callback);
    }

    /**
     * Get current URL type.
     *
     * @return string The URL type
     */
    public function getCurrentType()
    {
        return $this->type;
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
        return isset($this->handlers[$type]) ? $this->handlers[$type]->url : null;
    }

    /**
     * Get current patge number.
     *
     * @param null|string $args Url args
     *
     * @return false|int The page number or false
     */
    public function getPageNumber(?string &$args): int|false
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
    public function serveDocument(string $tpl, string $content_type = 'text/html', bool $http_cache = true, bool $http_etag = true): void
    {
        if (null === App::core()->context()->get('nb_entry_per_page')) {
            App::core()->context()->set('nb_entry_per_page', App::core()->blog()->settings('system')->getSetting('nb_post_per_page'));
        }
        if (null === App::core()->context()->get('nb_entry_first_page')) {
            App::core()->context()->set('nb_entry_first_page', App::core()->context()->get('nb_entry_per_page'));
        }

        $tpl_file = App::core()->template()->getFilePath($tpl);
        if (!$tpl_file) {
            throw new InvalidValueReference('Unable to find template ');
        }

        App::core()->context()->set('current_tpl', $tpl);
        App::core()->context()->set('content_type', $content_type);
        App::core()->context()->set('http_cache', $http_cache);
        App::core()->context()->set('http_etag', $http_etag);

        // --BEHAVIOR-- publicBeforeServeDocument, Context
        App::core()->behavior('publicBeforeServeDocument')->call(context: App::core()->context());

        if (App::core()->context()->get('http_cache')) {
            $this->mod_files = array_merge($this->mod_files, [$tpl_file]);
            Http::cache($this->mod_files, $this->mod_ts);
        }

        header('Content-Type: ' . App::core()->context()->get('content_type') . '; charset=UTF-8');

        $this->additionalHeaders();

        $param = new Param();
        $param->set('content', App::core()->template()->getData(App::core()->context()->get('current_tpl')));
        $param->set('content_type', App::core()->context()->get('content_type'));
        $param->set('tpl', App::core()->context()->get('current_tpl'));
        $param->set('blogupddt', App::core()->blog()->upddt);
        $param->set('headers', headers_list());

        // --BEHAVIOR-- publicAfterServeDocument, Param (not really after but hey)
        App::core()->behavior('publicAfterServeDocument')->call(param: $param);

        if (App::core()->context()->get('http_cache') && App::core()->context()->get('http_etag')) {
            Http::etag($param->get('content'), Http::getSelfURI());
        }
        echo $param->get('content');
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
                // todo: find a way to reproduce this with readonly GPC.
                //
                // foreach ($_GET as $k => $v) {
                // if (isset($_REQUEST[$k])) {
                // unset($_REQUEST[$k]);
                // }
                // }
                //
                // $_GET     = $qs;
                // $_REQUEST = array_merge($qs, $_REQUEST);
                //
                foreach ($qs as $k => $v) {
                    if (null === $v) {
                        $part = $k;
//                        unset($_GET[$k], $_REQUEST[$k]);
                    }

                    break;
                }
            }
        }

        $_SERVER['URL_REQUEST_PART'] = $part;

        $this->getArgs($part, $type, $args);

        // --BEHAVIOR-- publicBeforeGetDocument
        App::core()->behavior('publicBeforeGetDocument')->call();

        if (!$type) {
            $this->type = $this->getHomeType();
            $this->callDefaultHandler($args);
        } else {
            $this->type = $type;
            $this->callHandler($type, $args);
        }

        // --BEHAVIOR-- publicAfterGetDocument
        App::core()->behavior('publicAfterGetDocument')->call();
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

        $this->sortItems();

        foreach ($this->handlers as $handler) {
            if ($part == $handler->scheme) {
                $type = $handler->type;
                $args = null;

                return;
            }
            if (preg_match('#' . $handler->scheme . '#', (string) $part, $m)) {
                $type = $handler->type;
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
     *
     * @throws InvalidValueReference
     */
    public function callHandler(string $type, ?string $args): void
    {
        if (!isset($this->handlers[$type])) {
            throw new InvalidValueReference('Unknown URL type');
        }

        try {
            call_user_func($this->handlers[$type]->callback, $args);
        } catch (Exception $e) {
            foreach ($this->error_handlers as $err_handler) {
                if (true === call_user_func($err_handler, $args, $type, $e)) {
                    return;
                }
            }
            // propagate Exception, as it has not been processed by handlers
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
        try {
            call_user_func($this->default_handler, $args);
        } catch (Exception $e) {
            foreach ($this->error_handlers as $err_handler) {
                if (true === call_user_func($err_handler, $args, 'default', $e)) {
                    return;
                }
            }
            // propagate Exception, as it has not been processed by handlers
            throw $e;
        }
    }

    /**
     * Parse query string.
     *
     * @return array The arguments
     */
    private function parseQueryString(): array
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
     * Get page 404.
     *
     * @throws InvalidValueReference
     */
    public function p404(): void
    {
        throw new InvalidValueReference('Page not found', 404);
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
        $this->type = '404';
        App::core()->context()->set('current_tpl', '404.html');
        App::core()->context()->set('content_type', 'text/html');

        echo App::core()->template()->getData(App::core()->context()->get('current_tpl'));

        // --BEHAVIOR-- publicAfterDocument (recall this behavior as we stop script here)
        App::core()->behavior('publicAfterGetDocument')->call();

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
            $this->type = 'default';
            if ($n) {
                App::core()->context()->page_number($n);
                if (1 < $n) {
                    $this->type = 'default-page';
                }
            }

            if (GPC::get()->empty('q')) {
                if (null !== App::core()->blog()->settings('system')->getSetting('nb_post_for_home')) {
                    App::core()->context()->set('nb_entry_first_page', App::core()->blog()->settings('system')->getSetting('nb_post_for_home'));
                }
                $this->serveDocument('home.html');
                App::core()->blog()->posts()->publishScheduledPosts();
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
        $this->type = 'static';

        if (GPC::get()->empty('q')) {
            $this->serveDocument('static.html');
            App::core()->blog()->posts()->publishScheduledPosts();
        } else {
            $this->search();
        }
    }

    /**
     * Get search page.
     */
    public function search(): void
    {
        if (App::core()->blog()->settings('system')->getSetting('no_search')) {
            // Search is disabled for this blog.
            $this->p404();
        } else {
            $this->type = 'search';

            $this->search_string = Html::escapeHTML(rawurldecode(GPC::get()->string('q')));
            if ($this->search_string) {
                $param = new Param();
                $param->set('search', $this->search_string);

                // --BEHAVIOR-- publicBeforeCountPostsOnSearch, Param
                App::core()->behavior('publicBeforeCountPostsOnSearch')->call(param: $param, args: '');

                $this->search_count = App::core()->blog()->posts()->countPosts(param: $param);
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
        $n     = $this->getPageNumber($args);
        $param = new Param();
        $param->set('post_lang', $args);

        // --BEHAVIOR-- publicBeforeGetLangsOnLang, Param
        App::core()->behavior('publicBeforeGetLangsOnLang')->call(param: $param, args: $args);

        App::core()->context()->set('langs', App::core()->blog()->posts()->getLangs(param: $param));

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
            $param = new Param();
            $param->set('cat_url', $args);
            $param->set('post_type', 'post');
            $param->set('without_empty', false);

            // --BEHAVIOR-- publicBeforeGetCategoriesOnCategory, Param
            App::core()->behavior('publicBeforeGetCategoriesOnCategory')->call(param: $param, args: $args);

            App::core()->context()->set('categories', App::core()->blog()->categories()->getCategories(param: $param));

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
            $param = new Param();
            $param->set('year', (int) $m[1]);
            $param->set('month', (int) $m[2]);
            $param->set('type', 'month');

            // --BEHAVIOR-- publicBeforeGetDatesOnArchive, Param
            App::core()->behavior('publicBeforeGetDatesOnArchive')->call(param: $param, args: $args);

            App::core()->context()->set('archives', App::core()->blog()->posts()->getDates(param: $param));

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
            App::core()->blog()->setWithPassword();

            $param = new Param();
            $param->set('post_url', $args);

            // --BEHAVIOR-- publicBeforeGetPostsOnPost, Param, string
            App::core()->behavior('publicBeforeGetPostsOnPost')->call(param: $param, args: $args);

            App::core()->context()->set('posts', App::core()->blog()->posts()->getPosts(param: $param));

            $comment_preview = new Param();
            $comment_preview->set('content', '');
            $comment_preview->set('rawcontent', '');
            $comment_preview->set('name', '');
            $comment_preview->set('mail', '');
            $comment_preview->set('site', '');
            $comment_preview->set('preview', false);
            $comment_preview->set('remember', false);
            App::core()->context()->set('comment_preview', $comment_preview);

            App::core()->blog()->setWithoutPassword();

            if (App::core()->context()->get('posts')->isEmpty()) {
                // The specified entry does not exist.
                $this->p404();
            } else {
                $post_id       = App::core()->context()->get('posts')->field('post_id');
                $post_password = App::core()->context()->get('posts')->field('post_password');

                // Password protected entry
                if ('' != $post_password && !App::core()->context()->get('preview')) {
                    // Get passwords cookie
                    if (GPC::cookie()->isset('dc_passwd')) {
                        $pwd_cookie = json_decode(GPC::cookie()->string('dc_passwd'));
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
                    if (!GPC::post()->empty('password') && GPC::post()->string('password') == $post_password
                        || isset($pwd_cookie['#' . $post_id]) && $pwd_cookie['#' . $post_id] == $post_password
                    ) {
                        $pwd_cookie['#' . $post_id] = $post_password;
                        setcookie('dc_passwd', json_encode($pwd_cookie), 0, '/');
                    } else {
                        $this->serveDocument('password-form.html', 'text/html', false);

                        return;
                    }
                }

                // Posting a comment
                if (GPC::post()->isset('c_name')
                    && GPC::post()->isset('c_mail')
                    && GPC::post()->isset('c_site')
                    && GPC::post()->isset('c_content')
                    && App::core()->context()->get('posts')->commentsActive()
                ) {
                    // Spam trap
                    if (!GPC::post()->empty('f_mail')) {
                        Http::head(412, 'Precondition Failed');
                        header('Content-Type: text/plain');
                        echo 'So Long, and Thanks For All the Fish';
                        // Exits immediately the application to preserve the server.
                        exit;
                    }

                    $name    = GPC::post()->string('c_name');
                    $mail    = GPC::post()->string('c_mail');
                    $site    = GPC::post()->string('c_site');
                    $content = GPC::post()->string('c_content');
                    $preview = !GPC::post()->empty('preview');

                    if ('' != $content) {
                        // --BEHAVIOR-- publicBeforeTransformComment
                        $response = App::core()->behavior('publicBeforeTransformComment')->call(content: $content);
                        if ('' != $response) {
                            $content = $response;
                        } else {
                            if (App::core()->blog()->settings('system')->getSetting('wiki_comments')) {
                                App::core()->wiki()->initWikiComment();
                            } else {
                                App::core()->wiki()->initWikiSimpleComment();
                            }
                            $content = App::core()->wiki()->wikiTransform($content);
                        }
                        $content = Html::filter($content);
                    }

                    $comment_preview = App::core()->context()->get('comment_preview');
                    $comment_preview->set('content', $content);
                    $comment_preview->set('rawcontent', GPC::post()->string('c_content'));
                    $comment_preview->set('name', $name);
                    $comment_preview->set('mail', $mail);
                    $comment_preview->set('site', $site);

                    if ($preview) {
                        // --BEHAVIOR-- publicBeforePreviewComment, Param
                        App::core()->behavior('publicBeforePreviewComment')->call(param: $comment_preview);

                        $comment_preview->set('preview', true);
                    } else {
                        // Post the comment
                        $cursor = App::core()->con()->openCursor(App::core()->getPrefix() . 'comment');
                        $cursor->setField('comment_author', $name);
                        $cursor->setField('comment_site', Html::clean($site));
                        $cursor->setField('comment_email', Html::clean($mail));
                        $cursor->setField('comment_content', $content);
                        $cursor->setField('post_id', App::core()->context()->get('posts')->integer('post_id'));
                        $cursor->setField('comment_status', App::core()->blog()->settings('system')->getSetting('comments_pub') ? 1 : -1);
                        $cursor->setField('comment_ip', Http::realIP());

                        $redir = App::core()->context()->get('posts')->getURL();
                        $redir .= 'query_string' == App::core()->blog()->settings('system')->getSetting('url_scan') ? '&' : '?';

                        try {
                            if (!Text::isEmail($cursor->getField('comment_email'))) {
                                throw new MissingOrEmptyValue(__('You must provide a valid email address.'));
                            }

                            if ($cursor->getField('post_id')) {
                                App::core()->blog()->comments()->createComment(cursor: $cursor);
                            }

                            if (1 == (int) $cursor->getField('comment_status')) {
                                $redir_arg = 'pub=1';
                            } else {
                                $redir_arg = 'pub=0';
                            }

                            // --BEHAVIOR-- publicBeforeRedirectComment, Cursor
                            $redir_arg .= filter_var(App::core()->behavior('publicBeforeRedirectComment')->call(cursor: $cursor), FILTER_SANITIZE_URL);

                            header('Location: ' . $redir . $redir_arg);
                        } catch (Exception $e) {
                            App::core()->context()->set('form_error', $e->getMessage());
                        }
                    }
                    App::core()->context()->set('comment_preview', $comment_preview);
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
            $param = new Param();
            $param->set('lang', $m[1]);

            $args = $m[3];

            // --BEHAVIOR-- publicBeforeGetLangsOnFeed, Param, string
            App::core()->behavior('publicBeforeGetLangsOnFeed')->call(param: $param, args: $args);

            App::core()->context()->set('langs', App::core()->blog()->posts()->getLangs(param: $param));

            if (App::core()->context()->get('langs')->isEmpty()) {
                // The specified language does not exist.
                $this->p404();

                return;
            }
            $m[1] = $param->get('lang');
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
            $param = new Param();
            $param->set('cat_url', $cat_url);
            $param->set('post_type', 'post');

            // --BEHAVIOR-- publicBeforeGetCategoriesOnFeed, Param, string
            App::core()->behavior('publicBeforeGetCategoriesOnFeed')->call(param: $param, args: $args);

            App::core()->context()->set('categories', App::core()->blog()->categories()->getCategories(param: $param));

            if (App::core()->context()->get('categories')->isEmpty()) {
                // The specified category does no exist.
                $this->p404();

                return;
            }

            $subtitle = ' - ' . App::core()->context()->get('categories')->field('cat_title');
        } elseif ($post_id) {
            $param = new Param();
            $param->set('post_id', $post_id);
            $param->set('post_type', '');

            // --BEHAVIOR-- publicBeforeGetPostsOnFeed, Param, string
            App::core()->behavior('publicBeforeGetPostsOnFeed')->call(param: $param, args: $args);

            App::core()->context()->set('posts', App::core()->blog()->posts()->getPosts(param: $param));

            if (App::core()->context()->get('posts')->isEmpty()) {
                // The specified post does not exist.
                $this->p404();

                return;
            }

            $subtitle = ' - ' . App::core()->context()->get('posts')->field('post_title');
        }

        $tpl = $type;
        if ($comments) {
            $tpl .= '-comments';
            App::core()->context()->set('nb_comment_per_page', (int) App::core()->blog()->settings('system')->getSetting('nb_comment_per_feed'));
        } else {
            App::core()->context()->set('nb_entry_per_page', (int) App::core()->blog()->settings('system')->getSetting('nb_post_per_feed'));
            App::core()->context()->set('short_feed_items', (bool) App::core()->blog()->settings('system')->getSetting('short_feed_items'));
        }
        $tpl .= '.xml';

        if ('atom' == $type) {
            $mime = 'application/atom+xml';
        }

        App::core()->context()->set('feed_subtitle', $subtitle);

        header('X-Robots-Tag: ' . App::core()->context()->robotsPolicy(App::core()->blog()->settings('system')->getSetting('robots_policy'), ''));
        $this->serveDocument($tpl, $mime);
        if (!$comments && !$cat_url) {
            App::core()->blog()->posts()->publishScheduledPosts();
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

            $param = new Param();
            $param->set('post_id', $post_id);
            $param->set('type', 'trackback');

            // --BEHAVIOR-- publicBeforeReceiveTrackback, Param, string
            App::core()->behavior('publicBeforeReceiveTrackback')->call(param: $param, args: $args);

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
        $param = new Param();
        $param->set('type', 'webmention');

        // --BEHAVIOR-- publicBeforeReceiveTrackback, Param, string
        App::core()->behavior('publicBeforeReceiveTrackback')->call(param: $param, args: $args);

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

        if (App::core()->blog()->settings('system')->getSetting('enable_xmlrpc')) {
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
                $modules = new Modules(type: $module_type, no_load: true);
                // Chek if module path exists
                foreach ($modules->getPaths() as $modules_path) {
                    if (is_dir(Path::implode($modules_path, $module_id))) {
                        $dirs[] = Path::implode($modules_path, $module_id, 'Public', 'resources');
                        $dirs[] = Path::implode($modules_path, $module_id, 'Common', 'resources');
                        $args   = implode('/', $module_args);

                        break;
                    }
                }
            }
        }

        // Current Theme paths
        if (empty($dirs)) {
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
        $dirs[] = Path::implodeSrc('Process', 'Public', 'resources');
        $dirs[] = Path::implodeSrc('Core', 'resources', 'css');
        $dirs[] = Path::implodeSrc('Core', 'resources', 'js');

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
    private function additionalHeaders()
    {
        // Additional headers
        $headers = new Strings();
        if (App::core()->blog()->settings('system')->getSetting('prevents_clickjacking')) {
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
            $headers->add($header);
        }

        // --BEHAVIOR-- publicBeforeSendAdditionalHeaders, Strings
        App::core()->behavior('publicBeforeSendAdditionalHeaders')->call(headers: $headers);

        // Send additional headers if any
        foreach ($headers->dump() as $header) {
            header($header);
        }
    }
}
