<?php
/**
 * @class Dotclear\Core\Instance\Url
 * @brief Dotclear core url handler (public) class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use ArrayObject;

use Dotclear\Core\Xmlrpc;
use Dotclear\Exception\CoreException;
use Dotclear\File\Files;
use Dotclear\File\Path;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Utils\Text;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Url
{
    use \Dotclear\Core\Instance\TraitTrackback;

    protected $types = [];
    protected $default_handler;
    protected $error_handlers = [];

    public $mode = 'path_info';
    public $type = 'default';

    public $mod_files = [];
    public $mod_ts = [];

    public $allow_sub_dir = false;

    public $args;

    public function __construct()
    {
        $this->initDefaulthandlers();
    }

    protected function getHomeType()
    {
        return dotclear()->blog()->settings->system->static_home ? 'static' : 'default';
    }

    public function isHome($type)
    {
        return $type == $this->getHomeType();
    }

    public function getURLFor($type, $value = '')
    {
        $url  = dotclear()->behavior()->call('publicGetURLFor', $type, $value);
        if (!$url) {
            $url = $this->getBase($type);
            if ($value) {
                if ($url) {
                    $url .= '/';
                }
                $url .= $value;
            }
        }

        return $url;
    }

    public function register($type, $url, $representation, $handler)
    {
        $args = new ArrayObject(func_get_args());

        dotclear()->behavior()->call('publicRegisterURL', $args);

        $this->types[$args[0]] = [
            'url'            => $args[1],
            'representation' => $args[2],
            'handler'        => $args[3],
        ];
    }

    public function registerDefault($handler)
    {
        $this->default_handler = $handler;
    }

    public function registerError($handler)
    {
        array_unshift($this->error_handlers, $handler);
    }

    public function unregister($type)
    {
        if (isset($this->types[$type])) {
            unset($this->types[$type]);
        }
    }

    public function getTypes()
    {
        return $this->types;
    }

    public function getBase($type)
    {
        if (isset($this->types[$type])) {
            return $this->types[$type]['url'];
        }
    }

    protected function getPageNumber(&$args)
    {
        if (preg_match('#(^|/)page/([0-9]+)$#', $args, $m)) {
            $n = (int) $m[2];
            if ($n > 0) {
                $args = preg_replace('#(^|/)page/([0-9]+)$#', '', $args);

                return $n;
            }
        }

        return false;
    }

    protected function serveDocument($tpl, $content_type = 'text/html', $http_cache = true, $http_etag = true)
    {
        if (dotclear()->context->nb_entry_per_page === null) {
            dotclear()->context->nb_entry_per_page = dotclear()->blog()->settings->system->nb_post_per_page;
        }
        if (dotclear()->context->nb_entry_first_page === null) {
            dotclear()->context->nb_entry_first_page = dotclear()->context->nb_entry_per_page;
        }

        $tpl_file = dotclear()->tpl->getFilePath($tpl);
        if (!$tpl_file) {
            throw new CoreException('Unable to find template ');
        }


        dotclear()->context->current_tpl  = $tpl;
        dotclear()->context->content_type = $content_type;
        dotclear()->context->http_cache   = $http_cache;
        dotclear()->context->http_etag    = $http_etag;

        dotclear()->behavior()->call('urlHandlerBeforeGetData', dotclear()->context);

        if (dotclear()->context->http_cache) {
            $this->mod_files = array_merge($this->mod_files, [$tpl_file]);
            Http::cache($this->mod_files, $this->mod_ts);
        }

        header('Content-Type: ' . dotclear()->context->content_type . '; charset=UTF-8');

        // Additional headers
        $headers = new ArrayObject();
        if (dotclear()->blog()->settings->system->prevents_clickjacking) {
            if (dotclear()->context->exists('xframeoption')) {
                $url    = parse_url(dotclear()->context->xframeoption);
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
        if (dotclear()->blog()->settings->system->prevents_floc) {
            $headers->append('Permissions-Policy: interest-cohort=()');
        }

        # --BEHAVIOR-- urlHandlerServeDocumentHeaders
        dotclear()->behavior()->call('urlHandlerServeDocumentHeaders', $headers);

        // Send additional headers if any
        foreach ($headers as $header) {
            header($header);
        }

        $result                 = new ArrayObject();
        $result['content']      = dotclear()->tpl->getData(dotclear()->context->current_tpl);
        $result['content_type'] = dotclear()->context->content_type;
        $result['tpl']          = dotclear()->context->current_tpl;
        $result['blogupddt']    = dotclear()->blog()->upddt;
        $result['headers']      = headers_list();

        # --BEHAVIOR-- urlHandlerServeDocument
        dotclear()->behavior()->call('urlHandlerServeDocument', $result);

        if (dotclear()->context->http_cache && dotclear()->context->http_etag) {
            Http::etag($result['content'], Http::getSelfURI());
        }
        echo $result['content'];
    }

    public function getDocument()
    {
        $type = $args = '';

        if ($this->mode == 'path_info') {
            $part = substr($_SERVER['PATH_INFO'], 1);
        } else {
            $part = '';
            $qs   = $this->parseQueryString();

            # Recreates some _GET and _REQUEST pairs
            if (!empty($qs)) {
                foreach ($_GET as $k => $v) {
                    if (isset($_REQUEST[$k])) {
                        unset($_REQUEST[$k]);
                    }
                }
                $_GET     = $qs;
                $_REQUEST = array_merge($qs, $_REQUEST);

                foreach ($qs as $k => $v) {
                    if ($v === null) {
                        $part = $k;
                        unset($_GET[$k], $_REQUEST[$k]);
                    }

                    break;
                }
            }
        }

        $_SERVER['URL_REQUEST_PART'] = $part;

        $this->getArgs($part, $type, $this->args);

        # --BEHAVIOR-- urlHandlerGetArgsDocument
        dotclear()->behavior()->call('urlHandlerGetArgsDocument', $this);

        if (!$type) {
            $this->type = $this->getHomeType();
            $this->callDefaultHandler($this->args);
        } else {
            $this->type = $type;
            $this->callHandler($type, $this->args);
        }
    }

    public function getArgs($part, &$type, &$args)
    {
        if ($part == '') {
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
            } elseif (preg_match('#' . $repr . '#', (string) $part, $m)) {
                $type = $k;
                $args = $m[1] ?? null;

                return;
            }
        }

        # No type, pass args to default
        $args = $part;
    }

    public function callHandler($type, $args)
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
            # propagate CoreException, as it has not been processed by handlers
            throw $e;
        }
    }

    public function callDefaultHandler($args)
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
            # propagate CoreException, as it has not been processed by handlers
            throw $e;
        }
    }

    protected function parseQueryString()
    {
        if (!empty($_SERVER['QUERY_STRING'])) {
            $q = explode('&', $_SERVER['QUERY_STRING']);
            $T = [];
            foreach ($q as $v) {
                $t = explode('=', $v, 2);

                $t[0] = rawurldecode($t[0]);
                if (!isset($t[1])) {
                    $T[$t[0]] = null;
                } else {
                    $T[$t[0]] = urldecode($t[1]);
                }
            }

            return $T;
        }

        return [];
    }

    protected function sortTypes()
    {
        $r = [];
        foreach ($this->types as $k => $v) {
            $r[$k] = $v['url'];
        }
        array_multisort($r, SORT_DESC, $this->types);
    }

    public function p404()
    {
        throw new CoreException('Page not found', 404);
    }

    public function default404($args, $type, $e)
    {
        if ($e->getCode() != 404) {
            throw $e;
        }

        header('Content-Type: text/html; charset=UTF-8');
        Http::head(404, 'Not Found');
        dotclear()->url()->type    = '404';
        dotclear()->context->current_tpl  = '404.html';
        dotclear()->context->content_type = 'text/html';

        echo dotclear()->tpl->getData(dotclear()->context->current_tpl);

        # --BEHAVIOR-- publicAfterDocument
        dotclear()->behavior()->call('publicAfterDocument');

        exit;
    }

    public function home($args)
    {
        // Page number may have been set by $this->lang() which ends with a call to $this->home(null)
        $n = $args ? $this->getPageNumber($args) : dotclear()->context->page_number();

        if ($args && !$n) {
            # Then specified URL went unrecognized by all URL handlers and
            # defaults to the home page, but is not a page number.
            $this->p404();
        } else {
            dotclear()->url()->type = 'default';
            if ($n) {
                dotclear()->context->page_number($n);
                if ($n > 1) {
                    dotclear()->url()->type = 'default-page';
                }
            }

            if (empty($_GET['q'])) {
                if (dotclear()->blog()->settings->system->nb_post_for_home !== null) {
                    dotclear()->context->nb_entry_first_page = dotclear()->blog()->settings->system->nb_post_for_home;
                }
                $this->serveDocument('home.html');
                dotclear()->blog()->posts()->publishScheduledEntries();
            } else {
                $this->search();
            }
        }
    }

    public function static_home($args)
    {
        dotclear()->url()->type = 'static';

        if (empty($_GET['q'])) {
            $this->serveDocument('static.html');
            dotclear()->blog()->posts()->publishScheduledEntries();
        } else {
            $this->search();
        }
    }

    public function search()
    {
        if (dotclear()->blog()->settings->system->no_search) {

            # Search is disabled for this blog.
            $this->p404();
        } else {
            dotclear()->url()->type = 'search';

            $GLOBALS['_search'] = !empty($_GET['q']) ? Html::escapeHTML(rawurldecode($_GET['q'])) : '';
            if ($GLOBALS['_search']) {
                $params = new ArrayObject(['search' => $GLOBALS['_search']]);
                dotclear()->behavior()->call('publicBeforeSearchCount', $params);
                $GLOBALS['_search_count'] = dotclear()->blog()->posts()->getPosts($params, true)->f(0);
            }

            $this->serveDocument('search.html');
        }
    }

    public function lang($args)
    {
        $n      = $this->getPageNumber($args);
        $params = new ArrayObject([
            'lang' => $args]);

        dotclear()->behavior()->call('publicLangBeforeGetLangs', $params, $args);

        dotclear()->context->langs = dotclear()->blog()->posts()->getLangs($params);

        if (dotclear()->context->langs->isEmpty()) {
            # The specified language does not exist.
            $this->p404();
        } else {
            if ($n) {
                dotclear()->context->page_number($n);
            }
            dotclear()->context->cur_lang = $args;
            $this->home(null);
        }
    }

    public function category($args)
    {
        $n = $this->getPageNumber($args);

        if ($args == '' && !$n) {
            # No category was specified.
            $this->p404();
        } else {
            $params = new ArrayObject([
                'cat_url'       => $args,
                'post_type'     => 'post',
                'without_empty' => false]);

            dotclear()->behavior()->call('publicCategoryBeforeGetCategories', $params, $args);

            dotclear()->context->categories = dotclear()->blog()->categories()->getCategories($params);

            if (dotclear()->context->categories->isEmpty()) {
                # The specified category does no exist.
                $this->p404();
            } else {
                if ($n) {
                    dotclear()->context->page_number($n);
                }
                $this->serveDocument('category.html');
            }
        }
    }

    public function archive($args)
    {
        # Nothing or year and month
        if ($args == '') {
            $this->serveDocument('archive.html');
        } elseif (preg_match('|^/([0-9]{4})/([0-9]{2})$|', $args, $m)) {
            $params = new ArrayObject([
                'year'  => $m[1],
                'month' => $m[2],
                'type'  => 'month']);

            dotclear()->behavior()->call('publicArchiveBeforeGetDates', $params, $args);

            dotclear()->context->archives = dotclear()->blog()->posts()->getDates($params->getArrayCopy());

            if (dotclear()->context->archives->isEmpty()) {
                # There is no entries for the specified period.
                $this->p404();
            } else {
                $this->serveDocument('archive_month.html');
            }
        } else {
            # The specified URL is not a date.
            $this->p404();
        }
    }

    public function post($args)
    {
        if ($args == '') {
            # No entry was specified.
            $this->p404();
        } else {
            dotclear()->blog()->withoutPassword(false);

            $params = new ArrayObject([
                'post_url' => $args]);

            dotclear()->behavior()->call('publicPostBeforeGetPosts', $params, $args);

            dotclear()->context->posts = dotclear()->blog()->posts()->getPosts($params);

            dotclear()->context->comment_preview               = new ArrayObject();
            dotclear()->context->comment_preview['content']    = '';
            dotclear()->context->comment_preview['rawcontent'] = '';
            dotclear()->context->comment_preview['name']       = '';
            dotclear()->context->comment_preview['mail']       = '';
            dotclear()->context->comment_preview['site']       = '';
            dotclear()->context->comment_preview['preview']    = false;
            dotclear()->context->comment_preview['remember']   = false;

            dotclear()->blog()->withoutPassword(true);

            if (dotclear()->context->posts->isEmpty()) {
                # The specified entry does not exist.
                $this->p404();
            } else {
                $post_id       = dotclear()->context->posts->post_id;
                $post_password = dotclear()->context->posts->post_password;

                # Password protected entry
                if ($post_password != '' && !dotclear()->context->preview) {
                    # Get passwords cookie
                    if (isset($_COOKIE['dc_passwd'])) {
                        $pwd_cookie = json_decode($_COOKIE['dc_passwd']);
                        if ($pwd_cookie === null) {
                            $pwd_cookie = [];
                        } else {
                            $pwd_cookie = (array) $pwd_cookie;
                        }
                    } else {
                        $pwd_cookie = [];
                    }

                    # Check for match
                    # Note: We must prefix post_id key with '#'' in pwd_cookie array in order to avoid integer conversion
                    # because MyArray["12345"] is treated as MyArray[12345]
                    if ((!empty($_POST['password']) && $_POST['password'] == $post_password)
                        || (isset($pwd_cookie['#' . $post_id]) && $pwd_cookie['#' . $post_id] == $post_password)) {
                        $pwd_cookie['#' . $post_id] = $post_password;
                        setcookie('dc_passwd', json_encode($pwd_cookie), 0, '/');
                    } else {
                        $this->serveDocument('password-form.html', 'text/html', false);

                        return;
                    }
                }

                $post_comment = isset($_POST['c_name']) && isset($_POST['c_mail']) && isset($_POST['c_site']) && isset($_POST['c_content']) && dotclear()->context->posts->commentsActive();

                # Posting a comment
                if ($post_comment) {
                    # Spam trap
                    if (!empty($_POST['f_mail'])) {
                        Http::head(412, 'Precondition Failed');
                        header('Content-Type: text/plain');
                        echo 'So Long, and Thanks For All the Fish';
                        # Exits immediately the application to preserve the server.
                        exit;
                    }

                    $name    = $_POST['c_name'];
                    $mail    = $_POST['c_mail'];
                    $site    = $_POST['c_site'];
                    $content = $_POST['c_content'];
                    $preview = !empty($_POST['preview']);

                    if ($content != '') {
                        # --BEHAVIOR-- publicBeforeCommentTransform
                        $buffer = dotclear()->behavior()->call('publicBeforeCommentTransform', $content);
                        if ($buffer != '') {
                            $content = $buffer;
                        } else {
                            if (dotclear()->blog()->settings->system->wiki_comments) {
                                dotclear()->initWikiComment();
                            } else {
                                dotclear()->initWikiSimpleComment();
                            }
                            $content = dotclear()->wikiTransform($content);
                        }
                        $content = Html::filter($content);
                    }

                    dotclear()->context->comment_preview['content']    = $content;
                    dotclear()->context->comment_preview['rawcontent'] = $_POST['c_content'];
                    dotclear()->context->comment_preview['name']       = $name;
                    dotclear()->context->comment_preview['mail']       = $mail;
                    dotclear()->context->comment_preview['site']       = $site;

                    if ($preview) {
                        # --BEHAVIOR-- publicBeforeCommentPreview
                        dotclear()->behavior()->call('publicBeforeCommentPreview', dotclear()->context->comment_preview);

                        dotclear()->context->comment_preview['preview'] = true;
                    } else {
                        # Post the comment
                        $cur                  = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');
                        $cur->comment_author  = $name;
                        $cur->comment_site    = Html::clean($site);
                        $cur->comment_email   = Html::clean($mail);
                        $cur->comment_content = $content;
                        $cur->post_id         = dotclear()->context->posts->post_id;
                        $cur->comment_status  = dotclear()->blog()->settings->system->comments_pub ? 1 : -1;
                        $cur->comment_ip      = Http::realIP();

                        $redir = dotclear()->context->posts->getURL();
                        $redir .= dotclear()->blog()->settings->system->url_scan == 'query_string' ? '&' : '?';

                        try {
                            if (!Text::isEmail($cur->comment_email)) {
                                throw new CoreException(__('You must provide a valid email address.'));
                            }

                            # --BEHAVIOR-- publicBeforeCommentCreate
                            dotclear()->behavior()->call('publicBeforeCommentCreate', $cur);
                            if ($cur->post_id) {
                                $comment_id = dotclear()->blog()->comments()->addComment($cur);

                                # --BEHAVIOR-- publicAfterCommentCreate
                                dotclear()->behavior()->call('publicAfterCommentCreate', $cur, $comment_id);
                            }

                            if ($cur->comment_status == 1) {
                                $redir_arg = 'pub=1';
                            } else {
                                $redir_arg = 'pub=0';
                            }

                            $redir_arg .= filter_var(dotclear()->behavior()->call('publicBeforeCommentRedir', $cur), FILTER_SANITIZE_URL);

                            header('Location: ' . $redir . $redir_arg);
                        } catch (\Exception $e) {
                            dotclear()->context->form_error = $e->getMessage();
                        }
                    }
                }

                # The entry
                if (dotclear()->context->posts->trackbacksActive()) {
                    header('X-Pingback: ' . dotclear()->blog()->url . dotclear()->url()->getURLFor('xmlrpc', dotclear()->blog()->id));
                    header('Link: <' . dotclear()->blog()->url . dotclear()->url()->getURLFor('webmention') . '>; rel="webmention"');
                }
                $this->serveDocument('post.html');
            }
        }
    }

    public function preview($args)
    {
        if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#', $args, $m)) {
            # The specified Preview URL is malformed.
            $this->p404();
        } else {
            $user_id  = $m[1];
            $user_key = $m[2];
            $post_url = $m[3];
            if (!dotclear()->auth()->checkUser($user_id, null, $user_key)) {
                # The user has no access to the entry.
                $this->p404();
            } else {
                dotclear()->context->preview = true;
                if (dotclear()->config()->admin_url != '') {
                    dotclear()->context->xframeoption = dotclear()->config()->admin_url;
                }
                $this->post($post_url);
            }
        }
    }

    public function feed($args)
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

            dotclear()->behavior()->call('publicFeedBeforeGetLangs', $params, $args);

            dotclear()->context->langs = dotclear()->blog()->posts()->getLangs($params);

            if (dotclear()->context->langs->isEmpty()) {
                # The specified language does not exist.
                $this->p404();

                return;
            }
            dotclear()->context->cur_lang = $m[1];
        }

        if (preg_match('#^rss2/xslt$#', $args, $m)) {
            # RSS XSLT stylesheet
            Http::$cache_max_age = 60 * 60;
            $this->serveDocument('rss2.xsl', 'text/xml');

            return;
        } elseif (preg_match('#^(atom|rss2)/comments/([0-9]+)$#', $args, $m)) {
            # Post comments feed
            $type     = $m[1];
            $comments = true;
            $post_id  = (int) $m[2];
        } elseif (preg_match('#^(?:category/(.+)/)?(atom|rss2)(/comments)?$#', $args, $m)) {
            # All posts or comments feed
            $type     = $m[2];
            $comments = !empty($m[3]);
            if (!empty($m[1])) {
                $cat_url = $m[1];
            }
        } else {
            # The specified Feed URL is malformed.
            $this->p404();

            return;
        }

        if ($cat_url) {
            $params = new ArrayObject([
                'cat_url'   => $cat_url,
                'post_type' => 'post']);

            dotclear()->behavior()->call('publicFeedBeforeGetCategories', $params, $args);

            dotclear()->context->categories = dotclear()->blog()->categories()->getCategories($params);

            if (dotclear()->context->categories->isEmpty()) {
                # The specified category does no exist.
                $this->p404();

                return;
            }

            $subtitle = ' - ' . dotclear()->context->categories->cat_title;
        } elseif ($post_id) {
            $params = new ArrayObject([
                'post_id'   => $post_id,
                'post_type' => '']);

            dotclear()->behavior()->call('publicFeedBeforeGetPosts', $params, $args);

            dotclear()->context->posts = dotclear()->blog()->posts()->getPosts($params);

            if (dotclear()->context->posts->isEmpty()) {
                # The specified post does not exist.
                $this->p404();

                return;
            }

            $subtitle = ' - ' . dotclear()->context->posts->post_title;
        }

        $tpl = $type;
        if ($comments) {
            $tpl .= '-comments';
            dotclear()->context->nb_comment_per_page = dotclear()->blog()->settings->system->nb_comment_per_feed;
        } else {
            dotclear()->context->nb_entry_per_page = dotclear()->blog()->settings->system->nb_post_per_feed;
            dotclear()->context->short_feed_items  = dotclear()->blog()->settings->system->short_feed_items;
        }
        $tpl .= '.xml';

        if ($type == 'atom') {
            $mime = 'application/atom+xml';
        }

        dotclear()->context->feed_subtitle = $subtitle;

        header('X-Robots-Tag: ' . dotclear()->context->robotsPolicy(dotclear()->blog()->settings->system->robots_policy, ''));
        $this->serveDocument($tpl, $mime);
        if (!$comments && !$cat_url) {
            dotclear()->blog()->posts()->publishScheduledEntries();
        }
    }

    public function trackback($args)
    {
        if (!preg_match('/^[0-9]+$/', $args)) {
            # The specified trackback URL is not an number
            $this->p404();
        } else {
            // Save locally post_id from args
            $post_id = (int) $args;

            if (!is_array($args)) {
                $args = [];
            }

            $args['post_id'] = $post_id;
            $args['type']    = 'trackback';

            # --BEHAVIOR-- publicBeforeReceiveTrackback
            dotclear()->behavior()->call('publicBeforeReceiveTrackback', $args);

            $this->trackback()->receiveTrackback($post_id);
        }
    }

    public function webmention($args)
    {
        if (!is_array($args)) {
            $args = [];
        }

        $args['type'] = 'webmention';

        # --BEHAVIOR-- publicBeforeReceiveTrackback
        dotclear()->behavior()->call('publicBeforeReceiveTrackback', $args);

        $this->trackback()->receiveWebmention();
    }

    public function rsd($args)
    {
        Http::cache($GLOBALS['mod_files'], $GLOBALS['mod_ts']);

        header('Content-Type: text/xml; charset=UTF-8');
        echo
        '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">' . "\n" .
        "<service>\n" .
        "  <engineName>Dotclear</engineName>\n" .
        "  <engineLink>https://dotclear.org/</engineLink>\n" .
        '  <homePageLink>' . Html::escapeHTML(dotclear()->blog()->url) . "</homePageLink>\n";

        if (dotclear()->blog()->settings->system->enable_xmlrpc) {
            $u = sprintf(dotclear()->config()->xmlrpc_url, dotclear()->blog()->url, dotclear()->blog()->id); // @phpstan-ignore-line

            echo
                "  <apis>\n" .
                '    <api name="WordPress" blogID="1" preferred="true" apiLink="' . $u . '"/>' . "\n" .
                '    <api name="Movable Type" blogID="1" preferred="false" apiLink="' . $u . '"/>' . "\n" .
                '    <api name="MetaWeblog" blogID="1" preferred="false" apiLink="' . $u . '"/>' . "\n" .
                '    <api name="Blogger" blogID="1" preferred="false" apiLink="' . $u . '"/>' . "\n" .
                "  </apis>\n";
        }

        echo
            "</service>\n" .
            "</rsd>\n";
    }

    public function xmlrpc($args)
    {
        $blog_id = preg_replace('#^([^/]*).*#', '$1', $args);
        $server  = new XmlRpc($blog_id);
        $server->serve();
    }

    public function files($args)
    {
        $args  = Path::clean($args);
        $args  = trim($args);
        $types = ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'css', 'js', 'swf', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'html', 'xml', 'json', 'txt'];

        # No files given
        if (empty($args)) {
            $this->p404();
        }

        # Disable directory change ".."
        if (!$this->allow_sub_dir && strpos('..', $args) !== false) {
            $this->p404();
        }

        # Current Theme dir
        $dirs = dotclear()->themes->getThemePath('files');

        # Modules dirs
        $pos = strpos($args, '/');
        if ($pos) {
            # Sanitize modules type
            $type = ucfirst(strtolower(substr($args, 0, $pos)));
            $modf = substr($args, $pos, strlen($args));

            # Check class
            $class = root_ns('Module', $type, 'Public', 'Modules' . $type);
            if (is_subclass_of($class, 'Dotclear\\Module\\AbstractModules')) {
                # Get paths and serve file
                $modules = new $class();
                $dirs    = $modules->getModulesPath();
                $args    = $modf;
            }
        }

        # List other available file paths
        $dirs[] = dotclear()->config()->var_dir;
        $dirs[] = root_path('Public', 'files');
        $dirs[] = root_path('Core', 'files', 'css');
        $dirs[] = root_path('Core', 'files', 'js');

        # Search dirs
        $file = false;
        foreach ($dirs as $dir) {
            $file = Path::real(implode(DIRECTORY_SEPARATOR, [$dir, $args]));

            if ($file !== false) {
                break;
            }
        }
        unset($dirs);

        # Check file
        if ($file === false || !is_file($file) || !is_readable($file)) {
            $this->p404();
        }

        # Check file extension
        if (!in_array(Files::getExtension($file), $types)) {
            $this->p404();
        }

        # Set http cache (one week)
        Http::$cache_max_age = 7 * 24 * 60 * 60; // One week cache
        Http::cache(array_merge([$file], get_included_files()));

        # Send file to output
        header('Content-Type: ' . Files::getMimeType($file));
        // Content-length is not mandatory and must be the exact size of content transfered AFTER possible compression (gzip, deflate, …)
        //header('Content-Length: '.filesize($file));
        readfile($file);
        exit;
    }

    public function initDefaultHandlers()
    {
        $this->registerDefault([$this, 'home']);
        $this->registerError([$this, 'default404']);
        $this->register('lang', '', '^([a-zA-Z]{2}(?:-[a-z]{2})?(?:/page/[0-9]+)?)$', [$this, 'lang']);
        $this->register('posts', 'posts', '^posts(/.+)?$', [$this, 'home']);
        $this->register('post', 'post', '^post/(.+)$', [$this, 'post']);
        $this->register('preview', 'preview', '^preview/(.+)$', [$this, 'preview']);
        $this->register('category', 'category', '^category/(.+)$', [$this, 'category']);
        $this->register('archive', 'archive', '^archive(/.+)?$', [$this, 'archive']);
        $this->register('files', 'files', '^files/(.+)?$', [$this, 'files']);

        $this->register('feed', 'feed', '^feed/(.+)$', [$this, 'feed']);
        $this->register('trackback', 'trackback', '^trackback/(.+)$', [$this, 'trackback']);
        $this->register('webmention', 'webmention', '^webmention(/.+)?$', [$this, 'webmention']);
        $this->register('rsd', 'rsd', '^rsd$', [$this, 'rsd']);
        $this->register('xmlrpc', 'xmlrpc', '^xmlrpc/(.+)$', [$this, 'xmlrpc']);
    }
}