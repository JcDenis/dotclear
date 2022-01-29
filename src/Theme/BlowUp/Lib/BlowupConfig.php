<?php
/**
 * @class Dotclear\Theme\BlowUp\Lib\BlowupConfig
 * @brief Helper for default theme (blowup) config.
 *
 * @package Dotclear
 * @subpackage Theme
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\BlowUp\Lib;

use Dotclear\Core\Core;

use Dotclear\Exception\ModuleException;

use Dotclear\Module\Theme\Admin\ConfigTheme;

use Dotclear\File\Files;

class BlowupConfig
{
    protected $css_folder = 'blowup-css';
    protected $img_folder = 'blowup-images';

    protected $fonts = [
        'sans-serif' => [
            'ss1' => 'Arial, Helvetica, sans-serif',
            'ss2' => 'Verdana,Geneva, Arial, Helvetica, sans-serif',
            'ss3' => '"Lucida Grande", "Lucida Sans Unicode", sans-serif',
            'ss4' => '"Trebuchet MS", Helvetica, sans-serif',
            'ss5' => 'Impact, Charcoal, sans-serif'
        ],

        'serif' => [
            's1' => 'Times, "Times New Roman", serif',
            's2' => 'Georgia, serif',
            's3' => 'Baskerville, "Palatino Linotype", serif'
        ],

        'monospace' => [
            'm1' => '"Andale Mono", "Courier New", monospace',
            'm2' => '"Courier New", Courier, mono, monospace'
        ]
    ];

    protected $fonts_combo = [];
    protected $fonts_list  = [];

    public $top_images = [
        'default'        => 'Default',
        'blank'          => 'Blank',
        'light-trails-1' => 'Light Trails 1',
        'light-trails-2' => 'Light Trails 2',
        'light-trails-3' => 'Light Trails 3',
        'light-trails-4' => 'Light Trails 4',
        'butterflies'    => 'Butterflies',
        'flourish-1'     => 'Flourished 1',
        'flourish-2'     => 'Flourished 2',
        'animals'        => 'Animals',
        'plumetis'       => 'Plumetis',
        'flamingo'       => 'Flamingo',
        'rabbit'         => 'Rabbit',
        'roadrunner-1'   => 'Road Runner 1',
        'roadrunner-2'   => 'Road Runner 2',
        'typo'           => 'Typo'
    ];

    public $utils;

    public function __construct(Core $core)
    {
        $this->core = $core;
        $this->utils = new ConfigTheme($core);
    }

    public function fontsList()
    {
        if (empty($this->fonts_combo)) {
            $this->fonts_combo[__('default')] = '';
            foreach ($this->fonts as $family => $g) {
                $fonts = [];
                foreach ($g as $code => $font) {
                    $fonts[str_replace('"', '', $font)] = $code;
                }
                $this->fonts_combo[$family] = $fonts;
            }
        }

        return $this->fonts_combo;
    }

    public function fontDef($c)
    {
        if (empty($this->fonts_list)) {
            foreach ($this->fonts as $family => $g) {
                foreach ($g as $code => $font) {
                    $this->fonts_list[$code] = $font;
                }
            }
        }

        return $this->fonts_list[$c] ?? null;
    }

    public function canWriteCss($create = false)
    {
        return $this->utils->canWriteCss($this->css_folder, $create);
    }

    protected function backgroundImg(&$css, $selector, $value, $image)
    {
        $this->utils->backgroundImg($this->img_folder, $css, $selector, $value, $image);
    }

    private function writeCss($theme, $css)
    {
        $this->utils->writeCSS($this->css_folder, $theme, $css);
    }

    public function dropCss($theme)
    {
        $this->utils->dropCss($this->css_folder, $theme);
    }

    public function publicCssUrlHelper()
    {
        return $this->utils->publicCssUrlHelper($this->css_folder);
    }

    public function imagesPath()
    {
        return $this->utils->imagesPath($this->img_folder);
    }

    public function imagesURL()
    {
        return $this->utils->imagesURL($this->img_folder);
    }

    public function canWriteImages($create = false)
    {
        return $this->utils->canWriteImages($this->img_folder, $create);
    }

    public function uploadImage($f)
    {
        return $this->utils->uploadImage($this->img_folder, $f, 800);
    }

    public function dropImage($img)
    {
        $this->utils->dropImage($this->img_folder, $img);
    }

    public function createCss($s)
    {
        if ($s === null) {
            return;
        }

        $css = [];

        /* Sidebar position
        ---------------------------------------------- */
        if ($s['sidebar_position'] == 'left') {
            $css['#wrapper']['background-position'] = '-300px 0';
            $css['#main']['float']                  = 'right';
            $css['#sidebar']['float']               = 'left';
        }

        /* Properties
        ---------------------------------------------- */
        $this->utils->prop($css, 'body', 'background-color', $s['body_bg_c']);

        $this->utils->prop($css, 'body', 'color', $s['body_txt_c']);
        $this->utils->prop($css, '.post-tags li a:link, .post-tags li a:visited, .post-info-co a:link, .post-info-co a:visited', 'color', $s['body_txt_c']);
        $this->utils->prop($css, '#page', 'font-size', $s['body_txt_s']);
        $this->utils->prop($css, 'body', 'font-family', $this->fontDef($s['body_txt_f']));

        $this->utils->prop($css, '.post-content, .post-excerpt, #comments dd, #pings dd, dd.comment-preview', 'line-height', $s['body_line_height']);

        if (!$s['blog_title_hide']) {
            $this->utils->prop($css, '#top h1 a', 'color', $s['blog_title_c']);
            $this->utils->prop($css, '#top h1', 'font-size', $s['blog_title_s']);
            $this->utils->prop($css, '#top h1', 'font-family', $this->fontDef($s['blog_title_f']));

            if ($s['blog_title_a'] == 'right' || $s['blog_title_a'] == 'left') {
                $css['#top h1'][$s['blog_title_a']] = '0px';
                $css['#top h1']['width']            = 'auto';
            }

            if ($s['blog_title_p']) {
                $_p                    = explode(':', $s['blog_title_p']);
                $css['#top h1']['top'] = $_p[1] . 'px';
                if ($s['blog_title_a'] != 'center') {
                    $_a                  = $s['blog_title_a'] == 'right' ? 'right' : 'left';
                    $css['#top h1'][$_a] = $_p[0] . 'px';
                }
            }
        } else {
            $this->utils->prop($css, '#top h1 span', 'text-indent', '-5000px');
            $this->utils->prop($css, '#top h1', 'top', '0px');
            $css['#top h1 a'] = [
                'display' => 'block',
                'height'  => $s['top_height'] ? ($s['top_height'] - 10) . 'px' : '120px',
                'width'   => '800px'
            ];
        }
        $this->utils->prop($css, '#top', 'height', $s['top_height']);

        $this->utils->prop($css, '.day-date', 'color', $s['date_title_c']);
        $this->utils->prop($css, '.day-date', 'font-family', $this->fontDef($s['date_title_f']));
        $this->utils->prop($css, '.day-date', 'font-size', $s['date_title_s']);

        $this->utils->prop($css, 'a', 'color', $s['body_link_c']);
        $this->utils->prop($css, 'a:visited', 'color', $s['body_link_v_c']);
        $this->utils->prop($css, 'a:hover, a:focus, a:active', 'color', $s['body_link_f_c']);

        $this->utils->prop($css, '#comment-form input, #comment-form textarea', 'color', $s['body_link_c']);
        $this->utils->prop($css, '#comment-form input.preview', 'color', $s['body_link_c']);
        $this->utils->prop($css, '#comment-form input.preview:hover', 'background', $s['body_link_f_c']);
        $this->utils->prop($css, '#comment-form input.preview:hover', 'border-color', $s['body_link_f_c']);
        $this->utils->prop($css, '#comment-form input.submit', 'color', $s['body_link_c']);
        $this->utils->prop($css, '#comment-form input.submit:hover', 'background', $s['body_link_f_c']);
        $this->utils->prop($css, '#comment-form input.submit:hover', 'border-color', $s['body_link_f_c']);

        $this->utils->prop($css, '#sidebar', 'font-family', $this->fontDef($s['sidebar_text_f']));
        $this->utils->prop($css, '#sidebar', 'font-size', $s['sidebar_text_s']);
        $this->utils->prop($css, '#sidebar', 'color', $s['sidebar_text_c']);

        $this->utils->prop($css, '#sidebar h2', 'font-family', $this->fontDef($s['sidebar_title_f']));
        $this->utils->prop($css, '#sidebar h2', 'font-size', $s['sidebar_title_s']);
        $this->utils->prop($css, '#sidebar h2', 'color', $s['sidebar_title_c']);

        $this->utils->prop($css, '#sidebar h3', 'font-family', $this->fontDef($s['sidebar_title2_f']));
        $this->utils->prop($css, '#sidebar h3', 'font-size', $s['sidebar_title2_s']);
        $this->utils->prop($css, '#sidebar h3', 'color', $s['sidebar_title2_c']);

        $this->utils->prop($css, '#sidebar ul', 'border-top-color', $s['sidebar_line_c']);
        $this->utils->prop($css, '#sidebar li', 'border-bottom-color', $s['sidebar_line_c']);
        $this->utils->prop($css, '#topnav ul', 'border-bottom-color', $s['sidebar_line_c']);

        $this->utils->prop($css, '#sidebar li a', 'color', $s['sidebar_link_c']);
        $this->utils->prop($css, '#sidebar li a:visited', 'color', $s['sidebar_link_v_c']);
        $this->utils->prop($css, '#sidebar li a:hover, #sidebar li a:focus, #sidebar li a:active', 'color', $s['sidebar_link_f_c']);
        $this->utils->prop($css, '#search input', 'color', $s['sidebar_link_c']);
        $this->utils->prop($css, '#search .submit', 'color', $s['sidebar_link_c']);
        $this->utils->prop($css, '#search .submit:hover', 'background', $s['sidebar_link_f_c']);
        $this->utils->prop($css, '#search .submit:hover', 'border-color', $s['sidebar_link_f_c']);

        $this->utils->prop($css, '.post-title', 'color', $s['post_title_c']);
        $this->utils->prop($css, '.post-title a, .post-title a:visited', 'color', $s['post_title_c']);
        $this->utils->prop($css, '.post-title', 'font-family', $this->fontDef($s['post_title_f']));
        $this->utils->prop($css, '.post-title', 'font-size', $s['post_title_s']);

        $this->utils->prop($css, '#comments dd', 'background-color', $s['post_comment_bg_c']);
        $this->utils->prop($css, '#comments dd', 'color', $s['post_comment_c']);
        $this->utils->prop($css, '#comments dd.me', 'background-color', $s['post_commentmy_bg_c']);
        $this->utils->prop($css, '#comments dd.me', 'color', $s['post_commentmy_c']);

        $this->utils->prop($css, '#prelude, #prelude a', 'color', $s['prelude_c']);

        $this->utils->prop($css, '#footer p', 'background-color', $s['footer_bg_c']);
        $this->utils->prop($css, '#footer p', 'color', $s['footer_c']);
        $this->utils->prop($css, '#footer p', 'font-size', $s['footer_s']);
        $this->utils->prop($css, '#footer p', 'font-family', $this->fontDef($s['footer_f']));
        $this->utils->prop($css, '#footer p a', 'color', $s['footer_l_c']);

        /* Images
        ------------------------------------------------------ */
        $this->backgroundImg($css, 'body', $s['body_bg_c'], 'body-bg.png');
        $this->backgroundImg($css, 'body', $s['body_bg_g'] != 'light', 'body-bg.png');
        $this->backgroundImg($css, 'body', $s['prelude_c'], 'body-bg.png');
        $this->backgroundImg($css, '#top', $s['body_bg_c'], 'page-t.png');
        $this->backgroundImg($css, '#top', $s['body_bg_g'] != 'light', 'page-t.png');
        $this->backgroundImg($css, '#top', $s['uploaded'] || $s['top_image'], 'page-t.png');
        $this->backgroundImg($css, '#footer', $s['body_bg_c'], 'page-b.png');
        $this->backgroundImg($css, '#comments dt', $s['post_comment_bg_c'], 'comment-t.png');
        $this->backgroundImg($css, '#comments dd', $s['post_comment_bg_c'], 'comment-b.png');
        $this->backgroundImg($css, '#comments dt.me', $s['post_commentmy_bg_c'], 'commentmy-t.png');
        $this->backgroundImg($css, '#comments dd.me', $s['post_commentmy_bg_c'], 'commentmy-b.png');

        $res = '';
        foreach ($css as $selector => $values) {
            $res .= $selector . " {\n";
            foreach ($values as $k => $v) {
                if ($v) {
                    $res .= $k . ':' . $v . ";\n";
                }
            }
            $res .= "}\n";
        }

        $res .= $s['extra_css'];

        if (!$this->canWriteCss(true)) {
            throw new ModuleException(__('Unable to create css file.'));
        }

        # erase old css file
        $this->dropCss($this->core->blog->settings->system->theme);

        # create new css file into public blowup-css subdirectory
        $this->writeCss($this->core->blog->settings->system->theme, $res);

        return $res;
    }

    public function createImages(&$config, $uploaded)
    {
        $body_color       = $config['body_bg_c'];
        $prelude_color    = $config['prelude_c'];
        $gradient         = $config['body_bg_g'];
        $comment_color    = $config['post_comment_bg_c'];
        $comment_color_my = $config['post_commentmy_bg_c'];
        $top_image        = $config['top_image'];

        $config['top_height'] = null;

        if ($top_image != 'custom' && !isset($this->top_images[$top_image])) {
            $top_image = 'default';
        }
        if ($uploaded && !is_file($uploaded)) {
            $uploaded = null;
        }

        if (!$this->canWriteImages(true)) {
            throw new ModuleException(__('Unable to create images.'));
        }

        $body_fill = [
            'light'  => __DIR__ . '/alpha-img/gradient-l.png',
            'medium' => __DIR__ . '/alpha-img/gradient-m.png',
            'dark'   => __DIR__ . '/alpha-img/gradient-d.png'
        ];

        $body_g = $body_fill[$gradient] ?? false;

        if ($top_image == 'custom' && $uploaded) {
            $page_t = $uploaded;
        } else {
            $page_t = __DIR__ . '/alpha-img/page-t/' . $top_image . '.png';
        }

        $body_bg         = __DIR__ . '/alpha-img/body-bg.png';
        $page_t_mask     = __DIR__ . '/alpha-img/page-t/image-mask.png';
        $page_b          = __DIR__ . '/alpha-img/page-b.png';
        $comment_t       = __DIR__ . '/alpha-img/comment-t.png';
        $comment_b       = __DIR__ . '/alpha-img/comment-b.png';
        $default_bg      = '#e0e0e0';
        $default_prelude = '#ededed';

        $this->dropImage(basename($body_bg));
        $this->dropImage('page-t.png');
        $this->dropImage(basename($page_b));
        $this->dropImage(basename($comment_t));
        $this->dropImage(basename($comment_b));

        $body_color    = $this->utils->adjustColor($body_color);
        $prelude_color = $this->utils->adjustColor($prelude_color);
        $comment_color = $this->utils->adjustColor($comment_color);

        $d_body_bg = false;

        if ($top_image || $body_color || $gradient != 'light' || $prelude_color || $uploaded) {
            if (!$body_color) {
                $body_color = $default_bg;
            }
            $body_color = sscanf($body_color, '#%2X%2X%2X');

            # Create body gradient with color
            $d_body_bg = imagecreatetruecolor(50, 180);
            $fill      = imagecolorallocate($d_body_bg, $body_color[0], $body_color[1], $body_color[2]);
            imagefill($d_body_bg, 0, 0, $fill);

            # User choosed a gradient
            if ($body_g) {
                $s_body_bg = imagecreatefrompng($body_g);
                imagealphablending($s_body_bg, true);
                imagecopy($d_body_bg, $s_body_bg, 0, 0, 0, 0, 50, 180);
                imagedestroy($s_body_bg);
            }

            if (!$prelude_color) {
                $prelude_color = $default_prelude;
            }
            $prelude_color = sscanf($prelude_color, '#%2X%2X%2X');

            $s_prelude = imagecreatetruecolor(50, 30);
            $fill      = imagecolorallocate($s_prelude, $prelude_color[0], $prelude_color[1], $prelude_color[2]);
            imagefill($s_prelude, 0, 0, $fill);
            imagecopy($d_body_bg, $s_prelude, 0, 0, 0, 0, 50, 30);

            imagepng($d_body_bg, $this->imagesPath() . '/' . basename($body_bg));
        }

        if ($top_image || $body_color || $gradient != 'light') {
            # Create top image from uploaded image
            $size = getimagesize($page_t);
            $size = $size[1];
            $type = Files::getMimeType($page_t);

            $d_page_t = imagecreatetruecolor(800, $size);

            if ($type == 'image/png') {
                $s_page_t = @imagecreatefrompng($page_t);
            } else {
                $s_page_t = @imagecreatefromjpeg($page_t);
            }

            if (!$s_page_t) {
                throw new ModuleException(__('Unable to open image.'));
            }

            $fill = imagecolorallocate($d_page_t, $body_color[0], $body_color[1], $body_color[2]);
            imagefill($d_page_t, 0, 0, $fill);

            if ($type == 'image/png') {
                # PNG, we only add body gradient and image
                imagealphablending($s_page_t, true);
                imagecopyresized($d_page_t, $d_body_bg, 0, 0, 0, 50, 800, 130, 50, 130);
                imagecopy($d_page_t, $s_page_t, 0, 0, 0, 0, 800, $size);
            } else {
                # JPEG, we add image and a frame with rounded corners
                imagecopy($d_page_t, $s_page_t, 0, 0, 0, 0, 800, $size);

                imagecopy($d_page_t, $d_body_bg, 0, 0, 0, 50, 8, 4);
                imagecopy($d_page_t, $d_body_bg, 0, 4, 0, 54, 4, 4);
                imagecopy($d_page_t, $d_body_bg, 792, 0, 0, 50, 8, 4);
                imagecopy($d_page_t, $d_body_bg, 796, 4, 0, 54, 4, 4);

                $mask = imagecreatefrompng($page_t_mask);
                imagealphablending($mask, true);
                imagecopy($d_page_t, $mask, 0, 0, 0, 0, 800, 11);
                imagedestroy($mask);

                $fill = imagecolorallocate($d_page_t, 255, 255, 255);
                imagefilledrectangle($d_page_t, 0, 11, 3, $size - 1, $fill);
                imagefilledrectangle($d_page_t, 796, 11, 799, $size - 1, $fill);
                imagefilledrectangle($d_page_t, 0, $size - 9, 799, $size - 1, $fill);
            }

            $config['top_height'] = ($size) . 'px';

            imagepng($d_page_t, $this->imagesPath() . '/page-t.png');

            imagedestroy($d_body_bg);
            imagedestroy($d_page_t);
            imagedestroy($s_page_t);

            # Create bottom image with color
            $d_page_b = imagecreatetruecolor(800, 8);
            $fill     = imagecolorallocate($d_page_b, $body_color[0], $body_color[1], $body_color[2]);
            imagefill($d_page_b, 0, 0, $fill);

            $s_page_b = imagecreatefrompng($page_b);
            imagealphablending($s_page_b, true);
            imagecopy($d_page_b, $s_page_b, 0, 0, 0, 0, 800, 160);

            imagepng($d_page_b, $this->imagesPath() . '/' . basename($page_b));

            imagedestroy($d_page_b);
            imagedestroy($s_page_b);
        }

        if ($comment_color) {
            $this->commentImages($comment_color, $comment_t, $comment_b, basename($comment_t), basename($comment_b));
        }
        if ($comment_color_my) {
            $this->commentImages($comment_color_my, $comment_t, $comment_b, 'commentmy-t.png', 'commentmy-b.png');
        }
    }

    protected function commentImages($comment_color, $comment_t, $comment_b, $dest_t, $dest_b)
    {
        $comment_color = sscanf($comment_color, '#%2X%2X%2X');

        $d_comment_t = imagecreatetruecolor(500, 25);
        $fill        = imagecolorallocate($d_comment_t, $comment_color[0], $comment_color[1], $comment_color[2]);
        imagefill($d_comment_t, 0, 0, $fill);

        $s_comment_t = imagecreatefrompng($comment_t);
        imagealphablending($s_comment_t, true);
        imagecopy($d_comment_t, $s_comment_t, 0, 0, 0, 0, 500, 25);

        imagepng($d_comment_t, $this->imagesPath() . '/' . $dest_t);
        imagedestroy($d_comment_t);
        imagedestroy($s_comment_t);

        $d_comment_b = imagecreatetruecolor(500, 7);
        $fill        = imagecolorallocate($d_comment_b, $comment_color[0], $comment_color[1], $comment_color[2]);
        imagefill($d_comment_b, 0, 0, $fill);

        $s_comment_b = imagecreatefrompng($comment_b);
        imagealphablending($s_comment_b, true);
        imagecopy($d_comment_b, $s_comment_b, 0, 0, 0, 0, 500, 7);

        imagepng($d_comment_b, $this->imagesPath() . '/' . $dest_b);
        imagedestroy($d_comment_b);
        imagedestroy($s_comment_b);
    }
}
