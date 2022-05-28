<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Context;

// Dotclear\Process\Public\Context\Context
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Text;
use Exception;

/**
 * Context helper.
 *
 * This class provides methods and stack
 * in public context.
 *
 * @ingroup  Public Stack
 */
class Context
{
    /**
     * @var array<string,array> $stack
     *                          Context stack
     */
    public $stack = [
        'nb_entry_per_page'   => [10],
        'nb_entry_first_page' => [10],
        'page_number'         => [0],
        'smilies'             => [[]],
    ];

    /**
     * Set a context value.
     *
     * @param string $name  The porperty name
     * @param mixed  $value The property value
     */
    public function set(string $name, mixed $value): void
    {
        if (null === $value) {
            $this->pop($name);
        } else {
            $this->stack[$name][] = &$value;
            if ($value instanceof Record) {
                $this->stack['cur_loop'][] = &$value;
            }
        }
    }

    /**
     * Get a context value.
     *
     * @param string $name The property name
     *
     * @return mixed The property value
     */
    public function get(string $name): mixed
    {
        if (!isset($this->stack[$name])) {
            return null;
        }

        $n = count($this->stack[$name]);

        return 0 < $n ? $this->stack[$name][($n - 1)] : null;
    }

    /**
     * Check if a context property exists.
     *
     * @param string $name The property name
     */
    public function exists(string $name): bool
    {
        return isset($this->stack[$name][0]);
    }

    /**
     * Pop a context property.
     *
     * @param string $name The property name
     */
    public function pop(string $name): void
    {
        if (isset($this->stack[$name])) {
            $v = array_pop($this->stack[$name]);
            if ($v instanceof Record) {
                array_pop($this->stack['cur_loop']);
            }
            unset($v);
        }
    }

    /**
     * Check a loop position.
     *
     * @param int      $start  Start position
     * @param null|int $length Loop length
     * @param null|int $even   Even/odd test
     * @param null|int $modulo Modulo
     */
    public function loopPosition(int $start, ?int $length = null, ?int $even = null, ?int $modulo = null): bool
    {
        if (!$this->get('cur_loop')) {
            return false;
        }

        $index = $this->get('cur_loop')->index();
        $size  = $this->get('cur_loop')->count();

        $test = false;
        if (0 <= $start) {
            $test = $index >= $start;
            if (null !== $length) {
                if (0 <= $length) {
                    $test = $test && $start + $length > $index;
                } else {
                    $test = $test && $size + $length > $index;
                }
            }
        } else {
            $test = $size + $start <= $index;
            if (null !== $length) {
                if (0 <= $length) {
                    $test = $test && $size + $start + $length > $index;
                } else {
                    $test = $test && $size + $length > $index;
                }
            }
        }

        if (null !== $even) {
            $test = $test && $index % 2 == $even;
        }

        if (null !== $modulo) {
            $test = $test && ($index % $modulo == 0);
        }

        return $test;
    }

    /**
     * Apply default filters.
     *
     * @param string $filter The filter (name)
     * @param string $str    The content to apply filters
     * @param mixed  $arg    The additionnal argument
     *
     * @return string The filtered content
     */
    private function default_filters(string $filter, string $str, mixed $arg): string
    {
        return match ($filter) {
            'strip_tags'  => $this->strip_tags($str),
            'remove_html' => preg_replace('/\s+/', ' ', $this->remove_html($str)),
            'encode_xml', 'encode_html' => $this->encode_xml($str),
            'cut_string' => $this->cut_string($str, (int) $arg),
            'lower_case' => $this->lower_case($str),
            'capitalize' => $this->capitalize($str),
            'upper_case' => $this->upper_case($str),
            'encode_url' => $this->encode_url($str),
            default      => $str,
        };
    }

    /**
     * Apply global filters.
     *
     * @param string $str  The content
     * @param array  $args The aguments
     * @param string $tag  The tag
     *
     * @return string The filtered content
     */
    public function global_filters(string $str, array $args, string $tag = ''): string
    {
        $filters = [
            'strip_tags',                             // Removes HTML tags (mono line)
            'remove_html',                            // Removes HTML tags
            'encode_xml', 'encode_html',              // Encode HTML entities
            'cut_string',                             // Cut string (length in $args['cut_string'])
            'lower_case', 'capitalize', 'upper_case', // Case transformations
            'encode_url',                             // URL encode (as for insert in query string)
        ];

        $args[0] = &$str;

        // --BEHAVIOR-- publicBeforeContentFilter
        App::core()->behavior()->call('publicBeforeContentFilter', $tag, $args);
        $str = $args[0];

        foreach ($filters as $filter) {
            // --BEHAVIOR-- publicContentFilter
            switch (App::core()->behavior()->call('publicContentFilter', $tag, $args, $filter)) {
                case '1':
                    // 3rd party filter applied and must stop
                    break;

                case '0':
                default:
                    // 3rd party filter applied and should continue
                    // Apply default filter
                    if (isset($args[$filter]) && $args[$filter]) {
                        $str = $this->default_filters($filter, $str, $args[$filter]);
                    }
            }
        }

        // --BEHAVIOR-- publicAfterContentFilter
        App::core()->behavior()->call('publicAfterContentFilter', $tag, $args);
        $str = $args[0];

        return $str;
    }

    /**
     * Filter, encode URL.
     *
     * @param string $str The content
     */
    public function encode_url(string $str): string
    {
        return urlencode($str);
    }

    /**
     * Filter, cut string.
     *
     * @param string $str The content
     * @param int    $l   The cut length
     */
    public function cut_string(string $str, int $l): string
    {
        return Text::cutString($str, $l);
    }

    /**
     * Filter, encode XML.
     *
     * @param string $str The content
     */
    public function encode_xml(string $str): string
    {
        return Html::escapeHTML($str);
    }

    /**
     * Filter, remove isolated figcaptiong.
     *
     * @param string $str The content
     */
    public function remove_isolated_figcaption(string $str): string
    {
        // When using remove_html() or stript_tags(), we may have remaining figcaption's text without any image/audio media
        // This function will remove those cases from string

        // <figure><img …><figcaption>isolated text</figcaption></figure>
        $str = preg_replace('/<figure[^>]*>([\t\n\r\s]*)(<a[^>]*>)*<img[^>]*>([\t\n\r\s]*)(<\/a[^>]*>)*([\t\n\r\s]*)<figcaption[^>]*>(.*?)<\/figcaption>([\t\n\r\s]*)<\/figure>/', '', (string) $str);

        // <figure><figcaption>isolated text</figcaption><audio…>…</audio></figure>
        return preg_replace('/<figure[^>]*>([\t\n\r\s]*)<figcaption[^>]*>(.*)<\/figcaption>([\t\n\r\s]*)<audio[^>]*>(([\t\n\r\s]|.)*)<\/audio>([\t\n\r\s]*)<\/figure>/', '', $str);
    }

    /**
     * Filter, remove HTML.
     *
     * @param string $str The content
     */
    public function remove_html(string $str): string
    {
        return Html::decodeEntities(Html::clean($this->remove_isolated_figcaption($str)));
    }

    /**
     * Filter, strip tags.
     *
     * @param string $str The content
     */
    public function strip_tags(string $str): string
    {
        return trim(preg_replace('/ {2,}/', ' ', str_replace(["\r", "\n", "\t"], ' ', Html::clean($this->remove_isolated_figcaption($str)))));
    }

    /**
     * Filter, convert to lower case.
     *
     * @param string $str The content
     */
    public function lower_case(string $str): string
    {
        return mb_strtolower($str);
    }

    /**
     * Filter, convert to upper case.
     *
     * @param string $str The content
     */
    public function upper_case(string $str): string
    {
        return mb_strtoupper($str);
    }

    /**
     * Filter, capitalize content.
     *
     * @param string $str The content
     */
    public function capitalize(string $str): string
    {
        if ('' != $str) {
            $str[0] = mb_strtoupper($str[0]);
        }

        return $str;
    }

    /**
     * Get/set page number.
     *
     * @param int|string $p To set a page number
     */
    public function page_number(string|int $p = null): int
    {
        if (null !== $p) {
            $this->set('page_number', (int) abs((int) $p) + 0);
        }

        return $this->get('page_number');
    }

    /**
     * Build category post param.
     *
     * @param Param $param The param
     */
    public function categoryPostParam(Param $param): void
    {
        $url = $param->get('cat_url');
        $not = substr($url, 0, 1) == '!';
        if ($not) {
            $url = substr($url, 1);
        }

        $url = preg_split('/\s*,\s*/', $url, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($url as &$v) {
            if ($not) {
                $v .= ' ?not';
            }
            if ($this->exists('categories') && preg_match('/#self/', $v)) {
                $v = preg_replace('/#self/', $this->get('categories')->f('cat_url'), $v);
            } elseif ($this->exists('posts') && preg_match('/#self/', $v)) {
                $v = preg_replace('/#self/', $this->get('posts')->f('cat_url'), $v);
            }
        }
        $param->set('cat_url', $url);
    }

    /**
     * Get pagination number of pages.
     */
    public function PaginationNbPages(): int|false
    {
        if (null === $this->get('pagination')) {
            return false;
        }

        $nb_posts = $this->get('pagination');
        $nb_pages = in_array(App::core()->url()->type, ['default', 'default-page']) ?
            ceil(($nb_posts - (int) $this->get('nb_entry_first_page')) / (int) $this->get('nb_entry_per_page') + 1) :
            ceil($nb_posts                                             / (int) $this->get('nb_entry_per_page'));

        return (int) $nb_pages;
    }

    /**
     * Get pagination position.
     *
     * @param int $offset The offset
     */
    public function PaginationPosition(int $offset = 0): int
    {
        if (!($p = $this->page_number())) {
            $p = 1;
        }

        $p = $p + $offset;

        if (!($n = $this->PaginationNbPages())) {
            return $p;
        }

        return $p > $n || 0 >= $p ? 1 : $p;
    }

    /**
     * Check if it's pagination start.
     */
    public function PaginationStart(): bool
    {
        return $this->PaginationPosition() == 1;
    }

    /**
     * Check if it's pagination end.
     */
    public function PaginationEnd(): bool
    {
        return $this->PaginationPosition() == $this->PaginationNbPages();
    }

    /**
     * Get pagination URL.
     *
     * @param int $offset The offset
     */
    public function PaginationURL(int $offset = 0): string
    {
        $url = App::core()->blog()->url . preg_replace('#(^|/)page/([0-9]+)$#', '', $_SERVER['URL_REQUEST_PART']);

        $n = $this->PaginationPosition($offset);
        if (1 < $n) {
            $url = preg_replace('#/$#', '', $url);
            $url .= '/page/' . $n;
        }

        // If search param
        if (!GPC::get()->empty('q')) {
            $url .= (str_contains($url, '?') ? '&amp;' : '?') . 'q=' . rawurlencode(GPC::get()->string('q'));
        }

        return $url;
    }

    /**
     * Get Robots policy.
     */
    public function robotsPolicy(string $base, string $over): string
    {
        $pol  = ['INDEX' => 'INDEX', 'FOLLOW' => 'FOLLOW', 'ARCHIVE' => 'ARCHIVE'];
        $base = array_flip(preg_split('/\s*,\s*/', $base));
        $over = array_flip(preg_split('/\s*,\s*/', $over));

        foreach ($pol as $k => &$v) {
            if (isset($base[$k]) || isset($base['NO' . $k])) {
                $pol[$k] = $v = isset($base['NO' . $k]) ? 'NO' . $k : $k;
            }
            if (isset($over[$k]) || isset($over['NO' . $k])) {
                $pol[$k] = $v = isset($over['NO' . $k]) ? 'NO' . $k : $k;
            }
        }

        if ('ARCHIVE' == $pol['ARCHIVE']) {
            unset($pol['ARCHIVE']);
        }

        return implode(', ', $pol);
    }

    /**
     * Load smilies.
     */
    public function getSmilies(): bool
    {
        if (!empty($this->get('smilies'))) {
            return true;
        }

        // Search smilies on public path then Theme path and then parent theme path and then core path
        $base_url = App::core()->blog()->public_url . '/smilies/';
        $src      = '/resources/smilies/smilies.txt';
        $paths    = array_merge(
            [App::core()->blog()->public_path . '/smilies/smilies.txt'],
            array_values(App::core()->themes()->getThemePath('Public' . $src)),
            array_values(App::core()->themes()->getThemePath('Common' . $src)),
            [Path::implodeSrc('Process', 'Public', $src)]
        );

        foreach ($paths as $file) {
            if ($file && file_exists($file)) {
                $this->set('smilies', $this->smiliesDefinition($file, $base_url));

                return true;
            }
        }

        return false;
    }

    /**
     * Parse smilies definition.
     *
     * @param string $file Definiton file
     * @param string $url  smilies URL
     */
    public function smiliesDefinition(string $file, string $url): array
    {
        $def = file($file);

        $res = [];
        foreach ($def as $v) {
            $v = trim($v);
            if (preg_match('|^([^\t\s]*)[\t\s]+(.*)$|', $v, $matches)) {
                $r = '/(\G|[\s]+|>)(' . preg_quote($matches[1], '/') . ')([\s]+|[<]|\Z)/ms';
                $s = '$1<img src="' . $url . $matches[2] . '" ' .
                    'alt="$2" class="smiley" />$3';
                $res[$r] = $s;
            }
        }

        return $res;
    }

    /**
     * Add smilies to content.
     *
     * @param string $str The content
     */
    public function addSmilies(string $str): string
    {
        if (empty($this->get('smilies'))) {
            return $str;
        }

        // Process part adapted from SmartyPants engine (J. Gruber et al.) :

        $tokens = $this->tokenizeHTML($str);
        $result = '';
        $in_pre = 0; // Keep track of when we're inside <pre> or <code> tags.

        foreach ($tokens as $cur_token) {
            if ('tag' == $cur_token[0]) {
                // Don't mess with quotes inside tags.
                $result .= $cur_token[1];
                if (preg_match('@<(/?)(?:pre|code|kbd|script|math)[\s>]@', $cur_token[1], $matches)) {
                    $in_pre = isset($matches[1]) && '/' == $matches[1] ? 0 : 1;
                }
            } else {
                $t = $cur_token[1];
                if (!$in_pre) {
                    $t = preg_replace(array_keys($this->get('smilies')), array_values($this->get('smilies')), $t);
                }
                $result .= $t;
            }
        }

        return $result;
    }

    private function tokenizeHTML(string $str): array
    {
        // Function from SmartyPants engine (J. Gruber et al.)
        //
        //   Parameter:  String containing HTML markup.
        //   Returns:    An array of the tokens comprising the input
        //               string. Each token is either a tag (possibly with nested,
        //               tags contained therein, such as <a href="<MTFoo>">, or a
        //               run of text between tags. Each element of the array is a
        //               two-element array; the first is either 'tag' or 'text';
        //               the second is the actual value.
        //
        //
        //   Regular expression derived from the _tokenize() subroutine in
        //   Brad Choate's MTRegex plugin.
        //   <http://www.bradchoate.com/past/mtregex.php>
        //
        $index  = 0;
        $tokens = [];

        $match = '(?s:<!(?:--.*?--\s*)+>)|' . // comment
        '(?s:<\?.*?\?>)|' . // processing instruction
        // regular tags
        '(?:<[/!$]?[-a-zA-Z0-9:]+\b(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*>)';

        $parts = preg_split("{({$match})}", $str, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $part) {
            if (++$index % 2 && '' != $part) {
                $tokens[] = ['text', $part];
            } else {
                $tokens[] = ['tag', $part];
            }
        }

        return $tokens;
    }

    /**
     * First post image helpers.
     *
     * @param string $size          Size
     * @param bool   $with_category Use category
     * @param string $class         Class
     * @param bool   $no_tag        Return URL only
     * @param bool   $content_only  Search only in content
     * @param bool   $cat_only      Search on category only
     */
    public function EntryFirstImageHelper(string $size, bool $with_category, string $class = '', bool $no_tag = false, bool $content_only = false, bool $cat_only = false): string
    {
        if (!App::core()->media()) {
            return '';
        }

        try {
            if (!preg_match('/^' . implode('|', App::core()->media()->thumbsize()->getCodes()) . '|o$/', $size)) {
                $size = 's';
            }
            $p_url  = App::core()->blog()->public_url;
            $p_site = preg_replace('#^(.+?//.+?)/(.*)$#', '$1', App::core()->blog()->url);
            $p_root = App::core()->blog()->public_path;

            $pattern = '(?:' . preg_quote($p_site, '/') . ')?' . preg_quote($p_url, '/');
            $pattern = sprintf('/<img.+?src="%s(.*?\.(?:jpg|jpeg|gif|png|svg|webp))"[^>]+/msui', $pattern);

            $src = '';
            $alt = '';

            // We first look in post content
            if (!$cat_only && $this->get('posts')) {
                $subject = ($content_only ? '' : $this->get('posts')->f('post_excerpt_xhtml')) . $this->get('posts')->f('post_content_xhtml');
                if (0 < preg_match_all($pattern, $subject, $m)) {
                    foreach ($m[1] as $i => $img) {
                        if (false !== ($src = $this->ContentFirstImageLookup($p_root, $img, $size))) {
                            $dirname = str_replace('\\', '/', dirname($img));
                            $src     = $p_url . ('/' != $dirname ? $dirname : '') . '/' . $src;
                            if (preg_match('/alt="([^"]+)"/', $m[0][$i], $malt)) {
                                $alt = $malt[1];
                            }

                            break;
                        }
                    }
                }
            }

            // No src, look in category description if available
            if (!$src && $with_category && $this->get('posts')->f('cat_desc')) {
                if (0 < preg_match_all($pattern, $this->get('posts')->f('cat_desc'), $m)) {
                    foreach ($m[1] as $i => $img) {
                        if (false !== ($src = $this->ContentFirstImageLookup($p_root, $img, $size))) {
                            $dirname = str_replace('\\', '/', dirname($img));
                            $src     = $p_url . ('/' != $dirname ? $dirname : '') . '/' . $src;
                            if (preg_match('/alt="([^"]+)"/', $m[0][$i], $malt)) {
                                $alt = $malt[1];
                            }

                            break;
                        }
                    }
                }
            }

            if ($src) {
                if ($no_tag) {
                    return $src;
                }

                return '<img alt="' . $alt . '" src="' . $src . '" class="' . $class . '" />';
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        return '';
    }

    /**
     * Search first image of content.
     *
     * @param string $root Image root path
     * @param string $img  Image path
     * @param string $size Image size
     *
     * @return false|string Image path or false
     */
    private function ContentFirstImageLookup(string $root, string $img, string $size): string|false
    {
        if (!App::core()->media()) {
            return false;
        }

        // Image extensions
        $formats = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'wepb'];

        // Get base name and extension
        $info = Path::info($img);
        $base = $info['base'];

        $res = false;

        try {
            if (preg_match('/^\.(.+)_(' . implode('|', App::core()->media()->thumbsize()->getCodes()) . ')$/', $base, $m)) {
                $base = $m[1];
            }

            $res = false;
            if ('o' != $size && file_exists($root . '/' . $info['dirname'] . '/.' . $base . '_' . $size . '.jpg')) {
                $res = '.' . $base . '_' . $size . '.jpg';
            } elseif ('o' != $size && file_exists($root . '/' . $info['dirname'] . '/.' . $base . '_' . $size . '.png')) {
                $res = '.' . $base . '_' . $size . '.png';
            } elseif ('o' != $size && file_exists($root . '/' . $info['dirname'] . '/.' . $base . '_' . $size . '.webp')) {
                $res = '.' . $base . '_' . $size . '.webp';
            } else {
                $f = $root . '/' . $info['dirname'] . '/' . $base;
                if (file_exists($f . '.' . $info['extension'])) {
                    $res = $base . '.' . $info['extension'];
                } else {
                    foreach ($formats as $format) {
                        if (file_exists($f . '.' . $format)) {
                            $res = $base . '.' . $format;

                            break;
                        }
                        if (file_exists($f . '.' . strtoupper($format))) {
                            $res = $base . '.' . strtoupper($format);

                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        if ($res) {
            return $res;
        }

        return false;
    }
}
