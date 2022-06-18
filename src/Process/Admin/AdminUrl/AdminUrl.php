<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\AdminUrl;

// Dotclear\Process\Admin\AdminUrl\AdminUrl
use Dotclear\App;
use Dotclear\Exception\InvalidValueReference;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Network\Http;

/**
 * Admin URL handler.
 *
 * Accessible from App::core()->adminurl()->
 *
 * @ingroup  Admin Handler
 */
final class AdminUrl
{
    /**
     * @var array<string,AdminUrlItem> $urls
     *                                 List of registered URLs
     */
    private $urls = [];

    /**
     * Constructor.
     *
     * Set default admin URLs
     */
    public function __construct()
    {
        $ns = 'Dotclear\\Process\\Admin\\Handler\\';

        $default = [
            ['admin.home', $ns . 'Home'],
            ['admin.auth', $ns . 'Auth'],
            ['admin.posts', $ns . 'Posts'],
            ['admin.posts.popup', $ns . 'PostsPopup'],
            ['admin.post', $ns . 'Post'],
            ['admin.post.media', $ns . 'PostMedia'],
            ['admin.blogs', $ns . 'Blogs'],
            ['admin.blog', $ns . 'Blog'],
            ['admin.blog.pref', $ns . 'BlogPref'],
            ['admin.blog.del', $ns . 'BlogDel'],
            ['admin.categories', $ns . 'Categories'],
            ['admin.category', $ns . 'Category'],
            ['admin.comments', $ns . 'Comments'],
            ['admin.comment', $ns . 'Comment'],
            ['admin.help', $ns . 'Help'],
            ['admin.help.charte', $ns . 'Charte'],
            ['admin.langs', $ns . 'Langs'],
            ['admin.link.popup', $ns . 'LinkPopup'],
            ['admin.media', $ns . 'Media'],
            ['admin.media.item', $ns . 'MediaItem'],
            ['admin.search', $ns . 'Search'],
            ['admin.users', $ns . 'Users'],
            ['admin.user', $ns . 'User'],
            ['admin.user.pref', $ns . 'UserPref'],
            ['admin.user.actions', $ns . 'UserAction'],
            ['admin.update', $ns . 'Update'],
            ['admin.services', $ns . 'Services'],
            ['admin.xmlrpc', $ns . 'Xmlrpc'],
            ['admin.cspreport', $ns . 'CspReport'],
        ];

        foreach ($default as $url) {
            $this->addItem(new AdminUrlItem(
                name: $url[0],
                class: $url[1],
            ));
        }
    }

    /**
     * Register a new URL class.
     *
     * @param AdminUrlItem $item The admin URL descriptor
     */
    public function addItem(AdminUrlItem $item): void
    {
        $this->urls[$item->name] = $item;
    }

    /**
     * Register a new URL as a copy of an existing one.
     *
     * @param string $name     The URL handler name
     * @param string $orig     The class to copy information from
     * @param array  $params   The extra parameters to add
     * @param string $newclass The new class if different from the original
     *
     * @throws InvalidValueReference On unknow URL handler
     */
    public function copyItem(string $name, string $orig, array $params = [], string $newclass = ''): void
    {
        if (!isset($this->urls[$orig])) {
            throw new InvalidValueReference('Unknown URL handler for ' . $orig);
        }

        $this->urls[$name] = new AdminUrlItem(
            name: $name,
            class: '' != $newclass ? $newclass : $this->urls[$orig]->class,
            params: array_merge($this->urls[$orig]->params, $params),
        );
    }

    /**
     * Check if an URL handler is registered.
     *
     * @param string $name The handler name
     *
     * @return bool True if exists
     */
    public function hasItem(string $name): bool
    {
        return isset($this->urls[$name]);
    }

    /**
     * Return registered URLs properties.
     *
     * @return array<string,AdminUrlItem> The registred URLs
     */
    public function getItems(): array
    {
        return $this->urls;
    }

    /**
     * Admin URL alias.
     *
     * @return string The admin root URL
     */
    public function root(): string
    {
        return App::core()->config()->get('admin_url');
    }

    /**
     * Get last called URL handler name.
     *
     * @return string The URL handler name
     */
    public function called(): string
    {
        return GPC::request()->string('handler', empty($this->urls) ? '' : key($this->urls));
    }

    /**
     * Check called URL handler.
     *
     * @param string $name The URL handler name to check
     *
     * @return bool True if this is the called URL handler
     */
    public function is(string $name): bool
    {
        return $this->called() == $name;
    }

    /**
     * Retrieve a URL given its name, and optional parameters.
     *
     * @param string $name       The URL handler name
     * @param array  $params     The query string parameters, given as an associative array
     * @param string $separator  The separator to use between QS parameters
     * @param bool   $parametric set to true if URL will be used as (s)printf() format
     *
     * @throws InvalidValueReference On unknow URL handler
     *
     * @return string The forged URL
     */
    public function get(string $name, array $params = [], string $separator = '&amp;', bool $parametric = false): string
    {
        if (!isset($this->urls[$name])) {
            throw new InvalidValueReference('Unknown URL handler for ' . $name);
        }

        $p   = array_merge($this->urls[$name]->params, $params, ['handler' => $name]);
        $u   = http_build_query($p, '', $separator);

        if ($parametric) {
            // Dirty hack to get back %[n$]s instead of %25[{0..9}%24]s in URLs used with (s)printf(), as http_build_query urlencode() its result.
            $u = preg_replace('/\%25(([0-9])+?\%24)*?s/', '%$2s', $u);
        }

        return $this->root() . '?' . $u;
    }

    /**
     * Redirect to an URL given its name, and optional parameters.
     *
     * @param string $name   The URL handler name
     * @param array  $params The query string parameters, given as an associative array
     * @param string $suffix The suffix to be added to the QS parameters
     *
     * @throws InvalidValueReference On unknow URL handler
     */
    public function redirect(string $name, array $params = [], string $suffix = ''): void
    {
        if (!isset($this->urls[$name])) {
            throw new InvalidValueReference('Unknown URL handler for ' . $name);
        }
        Http::redirect($this->get($name, $params, '&') . $suffix);
    }

    /**
     * Retrieve a class name given its handler name, and optional parameters
     * acts like get, but without the query string, should be used within forms actions.
     *
     * @param string $name The URL handler name
     *
     * @throws InvalidValueReference On unknow URL handler
     *
     * @return string The full class name
     */
    public function getBase(string $name): string
    {
        if (!isset($this->urls[$name])) {
            throw new InvalidValueReference('Unknown URL handler for ' . $name);
        }

        return $this->urls[$name]->class;
    }

    /**
     * Forge form hidden fields to pass to a generated form.
     *
     * @param string $name   The URL handler name
     * @param array  $params The query string parameters, given as an associative array
     * @param bool   $nonce  Add the Nonce field
     *
     * @throws InvalidValueReference On unknow URL handler
     *
     * @return string The forged form data
     */
    public function getHiddenFormFields(string $name, array $params = [], bool $nonce = false): string
    {
        if (!isset($this->urls[$name])) {
            throw new InvalidValueReference('Unknown URL handler for ' . $name);
        }

        $p   = array_merge($this->urls[$name]->params, $params, ['handler' => $name]);
        $str = '';
        foreach ($p as $k => $v) {
            $str .= (new Hidden($k, (string) $v))->render();
        }
        if ($nonce) {
            $str .= App::core()->nonce()->form();
        }

        return $str;
    }
}
