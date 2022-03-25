<?php
/**
 * @class Dotclear\Helper\Html\Html
 * @brief Basic html tool
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html;

use Dotclear\Helper\Html\HtmlFilter;

class Html
{
    public static $url_root;
    public static $absolute_regs = []; ///< Array of regular expression for {@link absoluteURLs()}

    /**
     * HTML escape
     *
     * Replaces HTML special characters by entities.
     *
     * @param     string $str    String to escape
     * @return    string
     */
    public static function escapeHTML(?string $str): string
    {
        return htmlspecialchars($str ?: '', ENT_COMPAT, 'UTF-8');
    }

    /**
     * Decode HTML entities
     *
     * Returns a string with all entities decoded.
     *
     * @param string        $str            String to protect
     * @param bool   $keep_special   Keep special characters: &gt; &lt; &amp;
     * @return    string
     */
    public static function decodeEntities(?string $str, bool $keep_special = false): string
    {
        if ($keep_special) {
            $str = str_replace(
                ['&amp;', '&gt;', '&lt;'],
                ['&amp;amp;', '&amp;gt;', '&amp;lt;'],
                $str
            );
        }

        # Some extra replacements
        $extra = [
            '&apos;' => "'",
        ];

        $str = str_replace(array_keys($extra), array_values($extra), $str);

        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Remove markup
     *
     * Removes every tags, comments, cdata from string
     *
     * @param string    $str        String to clean
     * @return    string
     */
    public static function clean(?string $str): string
    {
        $str = strip_tags($str);

        return $str;
    }

    /**
     * Javascript escape
     *
     * Returns a protected JavaScript string
     *
     * @param string    $str        String to protect
     * @return    string
     */
    public static function escapeJS(?string $str): string
    {
        $str = htmlspecialchars($str, ENT_NOQUOTES, 'UTF-8');
        $str = str_replace("'", "\'", $str);
        $str = str_replace('"', '\"', $str);

        return $str;
    }

    /**
     * URL escape
     *
     * Returns an escaped URL string for HTML content
     *
     * @param string    $str        String to escape
     * @return    string
     */
    public static function escapeURL(?string $str): string
    {
        return str_replace('&', '&amp;', $str);
    }

    /**
     * URL sanitize
     *
     * Encode every parts between / in url
     *
     * @param string    $str        String to satinyze
     * @return    string
     */
    public static function sanitizeURL(?string $str): string
    {
        return str_replace('%2F', '/', rawurlencode($str));
    }

    /**
     * Remove host in URL
     *
     * Removes host part in URL
     *
     * @param string    $url        URL to transform
     * @return    string
     */
    public static function stripHostURL(?string $url): string
    {
        return preg_replace('|^[a-z]{3,}://.*?(/.*$)|', '$1', $url);
    }

    /**
     * Set links to absolute ones
     *
     * Appends $root URL to URIs attributes in $str.
     *
     * @param string    $str        HTML to transform
     * @param string    $root    Base URL
     * @return    string
     */
    public static function absoluteURLs(?string $str, ?string $root): string
    {
        self::$url_root = $root;
        $attr           = 'action|background|cite|classid|code|codebase|data|download|formaction|href|longdesc|profile|src|usemap';

        $str = preg_replace_callback('/((?:' . $attr . ')=")(.*?)(")/msu', ['self', 'absoluteURLHandler'], $str);

        foreach (self::$absolute_regs as $r) {
            $str = preg_replace_callback($r, ['self', 'absoluteURLHandler'], $str);
        }

        self::$url_root = null;

        return $str;
    }

    private static function absoluteURLHandler(array $m): string
    {
        $url = $m[2];

        $link = str_replace('%', '%%', $m[1]) . '%s' . str_replace('%', '%%', $m[3]);
        $host = preg_replace('|^([a-z]{3,}://)(.*?)/(.*)$|', '$1$2', self::$url_root);

        $parse = parse_url($m[2]);
        if (empty($parse['scheme'])) {
            if (str_starts_with($url, '//')) {
                // Nothing to do. Already an absolute URL.
            } elseif (str_starts_with($url, '/')) {
                // Beginning by a / return host + url
                $url = $host . $url;
            } elseif (str_starts_with($url, '#')) {
                // Beginning by a # return root + hash
                $url = self::$url_root . $url;
            } elseif (preg_match('|/$|', self::$url_root)) {
                // Root is ending by / return root + url
                $url = self::$url_root . $url;
            } else {
                $url = dirname(self::$url_root) . '/' . $url;
            }
        }

        return sprintf($link, $url);
    }

    /**
     * Filter HTML string
     *
     * Calls HTML filter to drop bad tags and produce valid XHTML output (if
     * tidy extension is present). If <b>enable_html_filter</b> blog setting is
     * false, returns not filtered string.
     *
     * @param   string  $str    The string
     *
     * @return  string
     */
    public static function filter(string $str): string
    {
        if (!empty(dotclear()->blog()) && !dotclear()->blog()->settings()->system->enable_html_filter) {
            return $str;
        }

        $options = new ArrayObject([
            'keep_aria' => false,
            'keep_data' => false,
            'keep_js'   => false
        ]);

        # --BEHAVIOR-- HTMLfilter, \ArrayObject
        dotclear()->behavior()->call('HTMLfilter', $options);

        $filter = new HtmlFilter($options['keep_aria'], $options['keep_data'], $options['keep_js']);
        $str    = trim($filter->apply($str));

        return $str;
    }

    /**
     * Append version
     *
     * Usefull to bypass cache
     *
     * @param   string  $src    THe path
     * @param   string  $v      The version (suffix)
     *
     * @return  string          The versioned path
     */
    private static function appendVersion(string $src, ?string $v = ''): string
    {
        return $src .
            (str_contains($src, '?') ? '&amp;' : '?') .
            'v=' . (!dotclear()->production() ? md5(uniqid()) : ($v ?: dotclear()->config()->core_version));
    }

    /**
     * Get HTML code to load a css file
     *
     * @param   string          $src    The path
     * @param   string          $media  The media type
     * @param   string|null     $v      The version
     *
     * @return  string                  The HTML code
     */
    public static function cssLoad(string $src, string $media = 'screen', string $v = null): string
    {
        $escaped_src = Html::escapeHTML($src);
        if ($v !== null) {
            $escaped_src = self::appendVersion($escaped_src, $v);
        }

        return '<link rel="stylesheet" href="' . $escaped_src . '" type="text/css" media="' . $media . '" />' . "\n";
    }

    /**
     * Get HTML code to load a js file
     *
     * @param   string          $src    The path
     * @param   string          $media  The media type
     * @param   string|null     $v      The version
     *
     * @return  string                  The HTML code
     */
    public static function jsLoad(string $src, string $v = null): string
    {
        $escaped_src = Html::escapeHTML($src);
        if ($v !== null) {
            $escaped_src = self::appendVersion($escaped_src, $v);
        }

        return '<script src="' . $escaped_src . '"></script>' . "\n";
    }

    /**
     * Get HTML code to set a js var
     *
     * @param   string  $id     The var name
     * @param   mixed   $vars   The var value
     *
     * @return  string          The HTML code
     */
    public static function jsJson(string $id, mixed $vars): string
    {
        // Use echo self::jsLoad(dotclear()->blog()->public_url . '/util.js'); to use the JS dotclear.getData() decoder in public mode
        $ret = '<script type="application/json" id="' . Html::escapeHTML($id) . '-data">' . "\n" .
            json_encode($vars, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . "\n" . '</script>';

        return $ret;
    }
}