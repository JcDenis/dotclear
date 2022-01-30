<?php
/**
 * @class Dotclear\Admin\UrlHandler
 * @brief Dotclear admin url handler class
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use ArrayObject;

use Dotclear\Exception\AdminException;
use Dotclear\Exception\DeprecatedException;

use Dotclear\Html\Form;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class UrlHandler
{
    protected $root_url;
    protected $urls;

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->root_url = defined('DOTCLEAR_ADMIN_URL') ? DOTCLEAR_ADMIN_URL : '';
        $this->urls = new ArrayObject();
    }

    public function called()
    {
        return $_REQUEST['handler'] ?? (empty($this->urls) ? '' : key($this->urls->getArrayCopy()));
    }

    /**
     * Registers a new url class
     *
     * @param  string $name     the url name
     * @param  string $class    class value
     * @param  array  $params   query string params (optional)
     */
    public function register(string $name, string $class, array $params = []): void
    {
        $this->urls[$name] = ['class' => $class, 'qs' => $params];
    }

    /**
     * Registers multiple new url class
     *
     * @param  array  $args   array of url name, class, params
     */
    public function registerMultiple(array ...$args): void
    {
        foreach($args as $arg) {
            $name   = isset($arg[0]) && is_string($arg[0]) ? $arg[0] : null;
            $class  = isset($arg[1]) && is_string($arg[1]) ? $arg[1] : null;
            $params = isset($arg[2]) && is_array($arg[2]) ? $arg[2] : [];

            if ($name && $class) {
                $this->urls[$name] = ['class' => $class, 'qs' => $params];
            }
        }
    }

    /**
     * Registers a new url as a copy of an existing one
     *
     * @param  string $name   url name
     * @param  string $orig   class to copy information from
     * @param  array  $params extra parameters to add
     * @param  string $newclass new class if different from the original
     */
    public function registerCopy(string $name, string $orig, array $params = [], string $newclass = ''): void
    {
        if (!isset($this->urls[$orig])) {
            throw new AdminException('Unknown URL handler for ' . $orig);
        }
        $url       = $this->urls[$orig];
        $url['qs'] = array_merge($url['qs'], $params);
        if ($newclass != '') {
            $url['class'] = $newclass;
        }
        $this->urls[$name] = $url;
    }

    /**
     * retrieves a URL given its name, and optional parameters
     *
     * @param  string   $name       URL Name
     * @param  array    $params     query string parameters, given as an associative array
     * @param  string   $separator  separator to use between QS parameters
     * @param  boolean  $parametric set to true if url will be used as (s)printf() format.
     *
     * @return string            the forged url
     */
    public function get(string $name, array $params = [], string $separator = '&amp;', bool $parametric = false): string
    {
        if (!isset($this->urls[$name])) {
            throw new AdminException('Unknown URL handler for ' . $name);
        }

        $url = $this->urls[$name];
        $p   = array_merge($url['qs'], $params, ['handler' => $name]);
        $u   = http_build_query($p, '', $separator);

        if ($parametric) {
            // Dirty hack to get back %[n$]s instead of %25[{0..9}%24]s in URLs used with (s)printf(), as http_build_query urlencode() its result.
            $u = preg_replace('/\%25(([0-9])+?\%24)*?s/', '%$2s', $u);
        }

        return $this->root_url . '?' . $u;
    }

    /**
     * Redirect to an URL given its name, and optional parameters
     *
     * @param  string $name      URL Name
     * @param  array  $params    query string parameters, given as an associative array
     * @param  string $suffix suffix to be added to the QS parameters
     */
    public function redirect($name, $params = [], $suffix = '')
    {
        if (!isset($this->urls[$name])) {
            throw new AdminException('Unknown URL handler for ' . $name);
        }
        Http::redirect($this->get($name, $params, '&') . $suffix);
    }

    /**
     * retrieves a php page given its name, and optional parameters
     * acts like get, but without the query string, should be used within forms actions
     *
     * @param  string $name      URL Name
     * @return string            the forged url
     */
    public function getBase($name)
    {
        if (!isset($this->urls[$name])) {
            throw new AdminException('Unknown URL handler for ' . $name);
        }

        return $this->urls[$name]['class'];
    }

    /**
     * forges form hidden fields to pass to a generated <form>. Should be used in combination with
     * form action retrieved from getBase()
     *
     * @param  string $name      URL Name
     * @param  array  $params    query string parameters, given as an associative array
     * @return string            the forged form data
     */
    public function getHiddenFormFields($name, $params = [])
    {
        if (!isset($this->urls[$name])) {
            throw new AdminException('Unknown URL handler for ' . $name);
        }
        $url = $this->urls[$name];
        $p   = array_merge($url['qs'], $params, ['handler' => $name]);
        $str = '';
        foreach ((array) $p as $k => $v) {
            $str .= Form::hidden([$k], $v);
        }

        return $str;
    }

    /**
     * retrieves a URL (decoded — useful for echoing) given its name, and optional parameters
     *
     * @deprecated     should be used carefully, parameters are no more escaped
     *
     * @param  string $name      URL Name
     * @param  array  $params    query string parameters, given as an associative array
     * @param  string $separator separator to use between QS parameters
     * @return string            the forged decoded url
     */
    public function decode($name, $params = [], $separator = '&')
    {
        DeprecatedException::throw();

        return urldecode($this->get($name, $params, $separator));
    }

    /**
     * Returns $urls property content.
     *
     * @return  ArrayObject
     */
    public function dumpUrls()
    {
        return $this->urls;
    }

    public function setup()
    {
        $this->initDefaultURLs();
        dcCore()->behaviors->call('adminURLs', $this);
    }

    protected function initDefaultURLs()
    {
        $d = 'Dotclear\\Admin\\Page\\';

        $this->registerMultiple(
            ['admin.home', $d . 'Home'],
            ['admin.auth', $d . 'Auth'],
            ['admin.posts', $d . 'Posts'],
            ['admin.posts.popup', $d . 'PostsPopup'],
            ['admin.post', $d . 'Post'],
            ['admin.post.media', $d . 'PostMedia'],
            ['admin.blogs', $d . 'Blogs'],
            ['admin.blog', $d . 'Blog'],
            ['admin.blog.pref', $d . 'BlogPref'],
            ['admin.blog.del', $d . 'BlogDel'],
            ['admin.categories', $d . 'Categories'],
            ['admin.category', $d . 'Category'],
            ['admin.comments', $d . 'Comments'],
            ['admin.comment', $d . 'Comment'],
            ['admin.help', $d . 'Help'],
            ['admin.help.charte', $d . 'Charte'],
            ['admin.langs', $d .'Langs'],
            ['admin.link.popup', $d .'LinkPopup'],
            ['admin.media', $d . 'Media'],
            ['admin.media.item', $d . 'MediaItem'],
            ['admin.search', $d . 'Search'],
            ['admin.users', $d . 'Users'],
            ['admin.user', $d . 'User'],
            ['admin.user.pref', $d . 'UserPref'],
            ['admin.user.actions', $d . 'UserAction'],
            ['admin.update', $d . 'Update'],
            ['admin.services', $d . 'Services'],
            ['admin.xmlrpc', $d . 'Xmlrpc'],
            ['admin.cspreport', $d . 'CspReport']
        );

    }
}
