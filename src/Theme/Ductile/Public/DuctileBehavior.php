<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Public;

// Dotclear\Theme\Ductile\Public\DuctileBehavior
use Dotclear\App;
use Dotclear\Modules\ModuleHelper;

/**
 * Public behaviors for theme Ductile.
 *
 * @ingroup  Theme Ductile Behavior
 */
class DuctileBehavior
{
    private $config;

    protected $fonts = [
        // Theme standard
        'Ductile body'      => '"Century Schoolbook", "Century Schoolbook L", Georgia, serif',
        'Ductile alternate' => '"Franklin gothic medium", "arial narrow", "DejaVu Sans Condensed", "helvetica neue", helvetica, sans-serif',

        // Serif families
        'Times New Roman' => 'Cambria, "Hoefler Text", Utopia, "Liberation Serif", "Nimbus Roman No9 L Regular", Times, "Times New Roman", serif',
        'Georgia'         => 'Constantia, "Lucida Bright", Lucidabright, "Lucida Serif", Lucida, "DejaVu Serif", "Bitstream Vera Serif", "Liberation Serif", Georgia, serif',
        'Garamond'        => '"Palatino Linotype", Palatino, Palladio, "URW Palladio L", "Book Antiqua", Baskerville, "Bookman Old Style", "Bitstream Charter", "Nimbus Roman No9 L", Garamond, "Apple Garamond", "ITC Garamond Narrow", "New Century Schoolbook", "Century Schoolbook", "Century Schoolbook L", Georgia, serif',

        // Sans-serif families
        'Helvetica/Arial' => 'Frutiger, "Frutiger Linotype", Univers, Calibri, "Gill Sans", "Gill Sans MT", "Myriad Pro", Myriad, "DejaVu Sans Condensed", "Liberation Sans", "Nimbus Sans L", Tahoma, Geneva, "Helvetica Neue", Helvetica, Arial, sans-serif',
        'Verdana'         => 'Corbel, "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", "DejaVu Sans", "Bitstream Vera Sans", "Liberation Sans", Verdana, "Verdana Ref", sans-serif',
        'Trebuchet MS'    => '"Segoe UI", Candara, "Bitstream Vera Sans", "DejaVu Sans", "Bitstream Vera Sans", "Trebuchet MS", Verdana, "Verdana Ref", sans-serif',

        // Cursive families
        'Impact' => 'Impact, Haettenschweiler, "Franklin Gothic Bold", Charcoal, "Helvetica Inserat", "Bitstream Vera Sans Bold", "Arial Black", sans-serif',

        // Monospace families
        'Monospace' => 'Consolas, "Andale Mono WT", "Andale Mono", "Lucida Console", "Lucida Sans Typewriter", "DejaVu Sans Mono", "Bitstream Vera Sans Mono", "Liberation Mono", "Nimbus Mono L", Monaco, "Courier New", Courier, monospace',
    ];

    public function __construct()
    {
        $this->config = new ModuleHelper();

        App::core()->behavior('templateAfterGetHead')->add([$this, 'behaviorPublicHeadContent']);
        App::core()->behavior('templateBeforeGetFooter')->add([$this, 'behaviorPublicInsideFooter']);
    }

    public function behaviorPublicHeadContent()
    {
        echo '<style type="text/css">' . "\n" .
        '/* ' . __('Additionnal style directives') . ' */' . "\n" .
        $this->ductileStyleHelper() .
            "</style>\n";

        echo '<script src="?resources/js/ductile.js"></script>' . "\n";

        echo $this->ductileWebfontHelper();
    }

    protected function fontDef($c)
    {
        return $this->fonts[$c] ?? null;
    }

    public function ductileWebfontHelper()
    {
        $s = App::core()->blog()->settings('themes')->getSetting(App::core()->blog()->settings('system')->getSetting('theme') . '_style');

        if (null === $s) {
            return;
        }

        $s = @unserialize($s);
        if (!is_array($s)) {
            return;
        }

        $this->config->resetCss();
        $ret = '';
        $uri = [];
        if (!isset($s['body_font']) || ('' == $s['body_font'])) {
            // See if webfont defined for main font
            if (isset($s['body_webfont_api'], $s['body_webfont_family'], $s['body_webfont_url'])) {
                $uri[] = $s['body_webfont_url'];

                switch ($s['body_webfont_api']) {
                    case 'js':
                        $ret .= sprintf('<script src="%s"></script>', $s['body_webfont_url']) . "\n";

                        break;

                    case 'css':
                        $ret .= sprintf('<link type="text/css" href="%s" rel="stylesheet" />', $s['body_webfont_url']) . "\n";

                        break;
                }
                // Main font
                $selectors = 'body, .supranav li a span, #comments.me, a.comment-number';
                $this->config->prop($selectors, 'font-family', $s['body_webfont_family']);
            }
        }
        if (!isset($s['alternate_font']) || ('' == $s['alternate_font'])) {
            // See if webfont defined for secondary font
            if (isset($s['alternate_webfont_api'], $s['alternate_webfont_family'], $s['alternate_webfont_url'])) {
                if (!in_array($s['alternate_webfont_url'], $uri)) {
                    switch ($s['alternate_webfont_api']) {
                        case 'js':
                            $ret .= sprintf('<script src="%s"></script>', $s['alternate_webfont_url']) . "\n";

                            break;

                        case 'css':
                            $ret .= sprintf('<link type="text/css" href="%s" rel="stylesheet" />', $s['alternate_webfont_url']) . "\n";

                            break;
                    }
                }
                // Secondary font
                $selectors = '#blogdesc, .supranav, #content-info, #subcategories, #comments-feed, #sidebar h2, #sidebar h3, #footer';
                $this->config->prop($selectors, 'font-family', $s['alternate_webfont_family']);
            }
        }
        // Style directives
        $res = $this->config->parseCss();
        if ('' != $res) {
            $ret .= '<style type="text/css">' . "\n" . $res . '</style>' . "\n";
        }

        return $ret;
    }

    public function ductileStyleHelper()
    {
        $s = App::core()->blog()->settings('themes')->getSetting(App::core()->blog()->settings('system')->getSetting('theme') . '_style');

        if (null === $s) {
            return;
        }

        $s = @unserialize($s);
        if (!is_array($s)) {
            return;
        }

        $this->config->resetCss();

        // Properties

        // Blog description
        $selectors = '#blogdesc';
        if (isset($s['subtitle_hidden'])) {
            $this->config->prop($selectors, 'display', ($s['subtitle_hidden'] ? 'none' : null));
        }

        // Main font
        $selectors = 'body, .supranav li a span, #comments.me, a.comment-number';
        if (isset($s['body_font'])) {
            $this->config->prop($selectors, 'font-family', $this->fontDef($s['body_font']));
        }

        // Secondary font
        $selectors = '#blogdesc, .supranav, #content-info, #subcategories, #comments-feed, #sidebar h2, #sidebar h3, #footer';
        if (isset($s['alternate_font'])) {
            $this->config->prop($selectors, 'font-family', $this->fontDef($s['alternate_font']));
        }

        // Inside posts links font weight
        $selectors = '.post-excerpt a, .post-content a';
        if (isset($s['post_link_w'])) {
            $this->config->prop($selectors, 'font-weight', ($s['post_link_w'] ? 'bold' : 'normal'));
        }

        // Inside posts links colors (normal, visited)
        $selectors = '.post-excerpt a:link, .post-excerpt a:visited, .post-content a:link, .post-content a:visited';
        if (isset($s['post_link_v_c'])) {
            $this->config->prop($selectors, 'color', $s['post_link_v_c']);
        }

        // Inside posts links colors (hover, active, focus)
        $selectors = '.post-excerpt a:hover, .post-excerpt a:active, .post-excerpt a:focus, .post-content a:hover, .post-content a:active, .post-content a:focus';
        if (isset($s['post_link_f_c'])) {
            $this->config->prop($selectors, 'color', $s['post_link_f_c']);
        }

        // Style directives
        $res = $this->config->parseCss();

        // Large screens
        $this->config->resetCss();

        // Blog title font weight
        $selectors = 'h1, h1 a:link, h1 a:visited, h1 a:hover, h1 a:visited, h1 a:focus';
        if (isset($s['blog_title_w'])) {
            $this->config->prop($selectors, 'font-weight', ($s['blog_title_w'] ? 'bold' : 'normal'));
        }

        // Blog title font size
        $selectors = 'h1';
        if (isset($s['blog_title_s'])) {
            $this->config->prop($selectors, 'font-size', $s['blog_title_s']);
        }

        // Blog title color
        $selectors = 'h1 a:link, h1 a:visited, h1 a:hover, h1 a:visited, h1 a:focus';
        if (isset($s['blog_title_c'])) {
            $this->config->prop($selectors, 'color', $s['blog_title_c']);
        }

        // Post title font weight
        $selectors = 'h2.post-title, h2.post-title a:link, h2.post-title a:visited, h2.post-title a:hover, h2.post-title a:visited, h2.post-title a:focus';
        if (isset($s['post_title_w'])) {
            $this->config->prop($selectors, 'font-weight', ($s['post_title_w'] ? 'bold' : 'normal'));
        }

        // Post title font size
        $selectors = 'h2.post-title';
        if (isset($s['post_title_s'])) {
            $this->config->prop($selectors, 'font-size', $s['post_title_s']);
        }

        // Post title color
        $selectors = 'h2.post-title a:link, h2.post-title a:visited, h2.post-title a:hover, h2.post-title a:visited, h2.post-title a:focus';
        if (isset($s['post_title_c'])) {
            $this->config->prop($selectors, 'color', $s['post_title_c']);
        }

        // Simple title color (title without link)
        $selectors = '#content-info h2, .post-title, .post h3, .post h4, .post h5, .post h6, .arch-block h3';
        if (isset($s['post_simple_title_c'])) {
            $this->config->prop($selectors, 'color', $s['post_simple_title_c']);
        }

        // Style directives for large screens
        $large = $this->config->parseCss();
        if ($large) {
            $res .= '@media only screen and (min-width: 481px) {' . "\n" . $large . "}\n";
        }

        // Small screens
        $this->config->resetCss();

        // Blog title font weight
        $selectors = 'h1, h1 a:link, h1 a:visited, h1 a:hover, h1 a:visited, h1 a:focus';
        if (isset($s['blog_title_w_m'])) {
            $this->config->prop($selectors, 'font-weight', ($s['blog_title_w_m'] ? 'bold' : 'normal'));
        }

        // Blog title font size
        $selectors = 'h1';
        if (isset($s['blog_title_s_m'])) {
            $this->config->prop($selectors, 'font-size', $s['blog_title_s_m']);
        }

        // Blog title color
        $selectors = 'h1 a:link, h1 a:visited, h1 a:hover, h1 a:visited, h1 a:focus';
        if (isset($s['blog_title_c_m'])) {
            $this->config->prop($selectors, 'color', $s['blog_title_c_m']);
        }

        // Post title font weight
        $selectors = 'h2.post-title, h2.post-title a:link, h2.post-title a:visited, h2.post-title a:hover, h2.post-title a:visited, h2.post-title a:focus';
        if (isset($s['post_title_w_m'])) {
            $this->config->prop($selectors, 'font-weight', ($s['post_title_w_m'] ? 'bold' : 'normal'));
        }

        // Post title font size
        $selectors = 'h2.post-title';
        if (isset($s['post_title_s_m'])) {
            $this->config->prop($selectors, 'font-size', $s['post_title_s_m']);
        }

        // Post title color
        $selectors = 'h2.post-title a:link, h2.post-title a:visited, h2.post-title a:hover, h2.post-title a:visited, h2.post-title a:focus';
        if (isset($s['post_title_c_m'])) {
            $this->config->prop($selectors, 'color', $s['post_title_c_m']);
        }

        // Style directives for small screens
        $small = $this->config->parseCss();
        if ($small) {
            $res .= '@media only screen and (max-width: 480px) {' . "\n" . $small . "}\n";
        }

        return $res;
    }

    public function behaviorPublicInsideFooter()
    {
        $res     = '';
        $default = false;
        $img_url = App::core()->blog()->url . 'resources/img/';

        $s = App::core()->blog()->settings('themes')->getSetting(App::core()->blog()->settings('system')->getSetting('theme') . '_stickers');

        if (null === $s) {
            $default = true;
        } else {
            $s = @unserialize($s);
            if (!is_array($s)) {
                $default = true;
            } else {
                $s = array_filter($s, [$this, 'cleanStickers']);
                if (count($s) == 0) {
                    $default = true;
                } else {
                    $count = 1;
                    foreach ($s as $sticker) {
                        $res .= $this->setSticker($count, (count($s) == $count), $sticker['label'], $sticker['url'], $img_url . $sticker['image']);
                        ++$count;
                    }
                }
            }
        }

        if ($default || '' == $res) {
            $res = $this->setSticker(1, true, __('Subscribe'), App::core()->blog()->getURLFor('feed', 'atom'), $img_url . 'sticker-feed.png');
        }

        if ('' != $res) {
            $res = '<ul id="stickers">' . "\n" . $res . '</ul>' . "\n";
            echo $res;
        }
    }

    protected function cleanStickers($s)
    {
        if (is_array($s)) {
            if (isset($s['label'], $s['url'], $s['image'])) {
                if (null != $s['label'] && null != $s['url'] && null != $s['image']) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function setSticker($position, $last, $label, $url, $image)
    {
        return '<li id="sticker' . $position . '"' . ($last ? ' class="last"' : '') . '>' . "\n" .
            '<a href="' . $url . '">' . "\n" .
            '<img alt="" src="' . $image . '" />' . "\n" .
            '<span>' . $label . '</span>' . "\n" .
            '</a>' . "\n" .
            '</li>' . "\n";
    }
}
