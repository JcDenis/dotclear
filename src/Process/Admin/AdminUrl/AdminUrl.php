<?php
/**
 * @note Dotclear\Process\Admin\AdminUrl\AdminUrl
 * @brief Dotclear admin url handler class
 *
 * Accessible from dotclear()->adminurl()->
 *
 * @ingroup  Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\AdminUrl;

use ArrayObject;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Network\Http;

class AdminUrl
{
    /** @var string Admin URL */
    protected $root_url;

    /** @var ArrayObject List of registered URLs */
    protected $urls;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->root_url = dotclear()->config()->get('admin_url');
        $this->urls     = new ArrayObject();
    }

    /**
     * Admin URL alias.
     *
     * @return string The admin root URL
     */
    public function root(): string
    {
        return $this->root_url;
    }

    /**
     * Get last called URL handler name.
     *
     * @return string The URL handler name
     */
    public function called(): string
    {
        return $_REQUEST['handler'] ?? ($this->urls->count() ? key($this->urls->getArrayCopy()) : '');
    }

    /**
     * Check called URL handler.
     *
     * @param string $name The URL handler name to check
     */
    public function is(string $name): bool
    {
        return $this->called() == $name;
    }

    /**
     * Register a new URL class.
     *
     * @param string $name   The URL handler name
     * @param string $class  The class name (with namespace)
     * @param array  $params The query string params (optional)
     */
    public function register(string $name, string $class, array $params = []): void
    {
        $this->urls[$name] = ['class' => $class, 'qs' => $params];
    }

    /**
     * Register multiple new URL class.
     *
     * @param array $args The array of URL name, class, params
     */
    public function registerMultiple(array ...$args): void
    {
        foreach ($args as $arg) {
            $name   = isset($arg[0]) && is_string($arg[0]) ? $arg[0] : null;
            $class  = isset($arg[1]) && is_string($arg[1]) ? $arg[1] : null;
            $params = isset($arg[2]) && is_array($arg[2]) ? $arg[2] : [];

            if ($name && $class) {
                $this->urls[$name] = ['class' => $class, 'qs' => $params];
            }
        }
    }

    /**
     * Register a new URL as a copy of an existing one.
     *
     * @param string $name     The URL handler name
     * @param string $orig     The class to copy information from
     * @param array  $params   The extra parameters to add
     * @param string $newclass THe new class if different from the original
     *
     * @throws AdminException On unknow URL handler
     */
    public function registerCopy(string $name, string $orig, array $params = [], string $newclass = ''): void
    {
        if (!isset($this->urls[$orig])) {
            throw new AdminException('Unknown URL handler for ' . $orig);
        }
        $url       = $this->urls[$orig];
        $url['qs'] = array_merge($url['qs'], $params);
        if ('' != $newclass) {
            $url['class'] = $newclass;
        }
        $this->urls[$name] = $url;
    }

    /**
     * Retrieve a URL given its name, and optional parameters.
     *
     * @param string $name       The URL handler name
     * @param array  $params     The query string parameters, given as an associative array
     * @param string $separator  The separator to use between QS parameters
     * @param bool   $parametric set to true if URL will be used as (s)printf() format
     *
     * @throws AdminException On unknow URL handler
     *
     * @return string The forged URL
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
     * Redirect to an URL given its name, and optional parameters.
     *
     * @param string $name   The URL handler name
     * @param array  $params The query string parameters, given as an associative array
     * @param string $suffix The suffix to be added to the QS parameters
     *
     * @throws AdminException On unknow URL handler
     */
    public function redirect(string $name, array $params = [], string $suffix = ''): void
    {
        if (!isset($this->urls[$name])) {
            throw new AdminException('Unknown URL handler for ' . $name);
        }
        Http::redirect($this->get($name, $params, '&') . $suffix);
    }

    /**
     * Check if an URL handler is registered.
     *
     * @param string $name The handler name
     *
     * @return bool True if exists
     */
    public function exists(string $name): bool
    {
        return $this->urls->count() && isset($this->urls[$name]);
    }

    /**
     * Retrieve a class name given its handler name, and optional parameters
     * acts like get, but without the query string, should be used within forms actions.
     *
     * @param string $name The URL handler name
     *
     * @throws AdminException On unknow URL handler
     *
     * @return string The full class name
     */
    public function getBase(string $name): string
    {
        if (!isset($this->urls[$name])) {
            throw new AdminException('Unknown URL handler for ' . $name);
        }

        return $this->urls[$name]['class'];
    }

    /**
     * Forge form hidden fields to pass to a generated <form>.
     *
     * @param string $name   The URL handler name
     * @param array  $params query string parameters, given as an associative array
     * @param bool   $nonce  Add the Nonce field
     *
     * @throws AdminException On unknow URL handler
     *
     * @return string The forged form data
     */
    public function getHiddenFormFields(string $name, array $params = [], bool $nonce = false): string
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
        if ($nonce) {
            $str .= dotclear()->nonce()->form();
        }

        return $str;
    }

    /**
     * Return registered URLs properties.
     *
     * @return ArrayObject The registred URLs
     */
    public function dump()
    {
        return $this->urls;
    }

    /**
     * Setup admin URLs.
     */
    public function setup()
    {
        $this->initDefaultURLs();
        dotclear()->behavior()->call('adminURLs', $this);
    }

    /**
     * Register default Dotclear admin URLs.
     */
    protected function initDefaultURLs()
    {
        $d = 'Dotclear\\Process\\Admin\\Handler\\';

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
            ['admin.langs', $d . 'Langs'],
            ['admin.link.popup', $d . 'LinkPopup'],
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
