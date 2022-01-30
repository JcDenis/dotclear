<?php
/**
 * @class Dotclear\Theme\BlowUp\Admin\Page
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeBlowup
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\BlowUp\Admin;

use Dotclear\Exception;

use Dotclear\Module\AbstractPage;

use Dotclear\Theme\BlowUp\Lib\BlowupConfig;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\File\Files;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    private $blowup_can_write_images = false;
    private $blowup_notices = '';
    private $blowup_user = [
        'body_bg_c' => null,
        'body_bg_g' => 'light',

        'body_txt_f'       => null,
        'body_txt_s'       => null,
        'body_txt_c'       => null,
        'body_line_height' => null,

        'top_image'  => 'default',
        'top_height' => null,
        'uploaded'   => null,

        'blog_title_hide' => null,
        'blog_title_f'    => null,
        'blog_title_s'    => null,
        'blog_title_c'    => null,
        'blog_title_a'    => null,
        'blog_title_p'    => null,

        'body_link_c'   => null,
        'body_link_f_c' => null,
        'body_link_v_c' => null,

        'sidebar_position' => null,
        'sidebar_text_f'   => null,
        'sidebar_text_s'   => null,
        'sidebar_text_c'   => null,
        'sidebar_title_f'  => null,
        'sidebar_title_s'  => null,
        'sidebar_title_c'  => null,
        'sidebar_title2_f' => null,
        'sidebar_title2_s' => null,
        'sidebar_title2_c' => null,
        'sidebar_line_c'   => null,
        'sidebar_link_c'   => null,
        'sidebar_link_f_c' => null,
        'sidebar_link_v_c' => null,

        'date_title_f' => null,
        'date_title_s' => null,
        'date_title_c' => null,

        'post_title_f'        => null,
        'post_title_s'        => null,
        'post_title_c'        => null,
        'post_comment_bg_c'   => null,
        'post_comment_c'      => null,
        'post_commentmy_bg_c' => null,
        'post_commentmy_c'    => null,

        'prelude_c'   => null,
        'footer_f'    => null,
        'footer_s'    => null,
        'footer_c'    => null,
        'footer_l_c'  => null,
        'footer_bg_c' => null,

        'extra_css' => null
    ];

    protected $blowup_config;

    protected function getPermissions(): string|null|false
    {
        return 'admin';
    }

    private function blowupGradientTypesCombo()
    {
        return [
            __('Light linear gradient')  => 'light',
            __('Medium linear gradient') => 'medium',
            __('Dark linear gradient')   => 'dark',
            __('Solid color')            => 'solid'
        ];
    }

    private function blowupTopImagesCombo()
    {
        return array_merge([__('Custom...') => 'custom'], array_flip($this->blowup_config->top_images));
    }

    protected function getPagePrepend(): ?bool
    {
        $this->blowup_config = new BlowupConfig();

        $this->blowup_can_write_images = $this->blowup_config->canWriteImages();

        if (dcCore()->error()->flag()) {
            $this->blowup_notices = dcCore()->error()->toHTML();
            dcCore()->error()->reset();
        }

        $blowup_style = (string) dcCore()->blog->settings->themes->blowup_style;
        $blowup_user = @unserialize($blowup_style);
        if (is_array($blowup_user)) {
            $this->blowup_user = array_merge($this->blowup_user, $blowup_user);
        }

        if (!empty($_POST)) {
            try {
                $this->blowup_user['body_txt_f']       = $_POST['body_txt_f'];
                $this->blowup_user['body_txt_s']       = $this->blowup_config->utils->adjustFontSize($_POST['body_txt_s']);
                $this->blowup_user['body_txt_c']       = $this->blowup_config->utils->adjustColor($_POST['body_txt_c']);
                $this->blowup_user['body_line_height'] = $this->blowup_config->utils->adjustFontSize($_POST['body_line_height']);

                $this->blowup_user['blog_title_hide'] = (integer) !empty($_POST['blog_title_hide']);
                $update_blog_title              = !$this->blowup_user['blog_title_hide'] && (
                    !empty($_POST['blog_title_f']) || !empty($_POST['blog_title_s']) || !empty($_POST['blog_title_c']) || !empty($_POST['blog_title_a']) || !empty($_POST['blog_title_p'])
                );

                if ($update_blog_title) {
                    $this->blowup_user['blog_title_f'] = $_POST['blog_title_f'];
                    $this->blowup_user['blog_title_s'] = $this->blowup_config->utils->adjustFontSize($_POST['blog_title_s']);
                    $this->blowup_user['blog_title_c'] = $this->blowup_config->utils->adjustColor($_POST['blog_title_c']);
                    $this->blowup_user['blog_title_a'] = preg_match('/^(left|center|right)$/', ($_POST['blog_title_a'] ?? '')) ? $_POST['blog_title_a'] : null;
                    $this->blowup_user['blog_title_p'] = $this->blowup_config->utils->adjustPosition($_POST['blog_title_p']);
                }

                $this->blowup_user['body_link_c']   = $this->blowup_config->utils->adjustColor($_POST['body_link_c']);
                $this->blowup_user['body_link_f_c'] = $this->blowup_config->utils->adjustColor($_POST['body_link_f_c']);
                $this->blowup_user['body_link_v_c'] = $this->blowup_config->utils->adjustColor($_POST['body_link_v_c']);

                $this->blowup_user['sidebar_text_f']   = ($_POST['sidebar_text_f'] ?? null);
                $this->blowup_user['sidebar_text_s']   = $this->blowup_config->utils->adjustFontSize($_POST['sidebar_text_s']);
                $this->blowup_user['sidebar_text_c']   = $this->blowup_config->utils->adjustColor($_POST['sidebar_text_c']);
                $this->blowup_user['sidebar_title_f']  = ($_POST['sidebar_title_f'] ?? null);
                $this->blowup_user['sidebar_title_s']  = $this->blowup_config->utils->adjustFontSize($_POST['sidebar_title_s']);
                $this->blowup_user['sidebar_title_c']  = $this->blowup_config->utils->adjustColor($_POST['sidebar_title_c']);
                $this->blowup_user['sidebar_title2_f'] = ($_POST['sidebar_title2_f'] ?? null);
                $this->blowup_user['sidebar_title2_s'] = $this->blowup_config->utils->adjustFontSize($_POST['sidebar_title2_s']);
                $this->blowup_user['sidebar_title2_c'] = $this->blowup_config->utils->adjustColor($_POST['sidebar_title2_c']);
                $this->blowup_user['sidebar_line_c']   = $this->blowup_config->utils->adjustColor($_POST['sidebar_line_c']);
                $this->blowup_user['sidebar_link_c']   = $this->blowup_config->utils->adjustColor($_POST['sidebar_link_c']);
                $this->blowup_user['sidebar_link_f_c'] = $this->blowup_config->utils->adjustColor($_POST['sidebar_link_f_c']);
                $this->blowup_user['sidebar_link_v_c'] = $this->blowup_config->utils->adjustColor($_POST['sidebar_link_v_c']);

                $this->blowup_user['sidebar_position'] = ($_POST['sidebar_position'] ?? '') == 'left' ? 'left' : null;

                $this->blowup_user['date_title_f'] = ($_POST['date_title_f'] ?? null);
                $this->blowup_user['date_title_s'] = $this->blowup_config->utils->adjustFontSize($_POST['date_title_s']);
                $this->blowup_user['date_title_c'] = $this->blowup_config->utils->adjustColor($_POST['date_title_c']);

                $this->blowup_user['post_title_f']     = ($_POST['post_title_f'] ?? null);
                $this->blowup_user['post_title_s']     = $this->blowup_config->utils->adjustFontSize($_POST['post_title_s']);
                $this->blowup_user['post_title_c']     = $this->blowup_config->utils->adjustColor($_POST['post_title_c']);
                $this->blowup_user['post_comment_c']   = $this->blowup_config->utils->adjustColor($_POST['post_comment_c']);
                $this->blowup_user['post_commentmy_c'] = $this->blowup_config->utils->adjustColor($_POST['post_commentmy_c']);

                $this->blowup_user['footer_f']    = ($_POST['footer_f'] ?? null);
                $this->blowup_user['footer_s']    = $this->blowup_config->utils->adjustFontSize($_POST['footer_s']);
                $this->blowup_user['footer_c']    = $this->blowup_config->utils->adjustColor($_POST['footer_c']);
                $this->blowup_user['footer_l_c']  = $this->blowup_config->utils->adjustColor($_POST['footer_l_c']);
                $this->blowup_user['footer_bg_c'] = $this->blowup_config->utils->adjustColor($_POST['footer_bg_c']);

                $this->blowup_user['extra_css'] = $this->blowup_config->utils->cleanCSS($_POST['extra_css']);

                if ($this->blowup_can_write_images) {
                    $uploaded = null;
                    if ($this->blowup_user['uploaded'] && is_file($this->blowup_config->imagesPath() . '/' . $this->blowup_user['uploaded'])) {
                        $uploaded = $this->blowup_config->imagesPath() . '/' . $this->blowup_user['uploaded'];
                    }

                    if (!empty($_FILES['upfile']) && !empty($_FILES['upfile']['name'])) {
                        files::uploadStatus($_FILES['upfile']);
                        $uploaded                = $this->blowup_config->uploadImage($_FILES['upfile']);
                        $this->blowup_user['uploaded'] = basename($uploaded);
                    }

                    $this->blowup_user['top_image'] = in_array(($_POST['top_image'] ?? ''), self::blowupTopImagesCombo()) ? $_POST['top_image'] : 'default';

                    $this->blowup_user['body_bg_c']           = $this->blowup_config->utils->adjustColor($_POST['body_bg_c']);
                    $this->blowup_user['body_bg_g']           = in_array(($_POST['body_bg_g'] ?? ''), $this->blowupGradientTypesCombo()) ? $_POST['body_bg_g'] : '';
                    $this->blowup_user['post_comment_bg_c']   = $this->blowup_config->utils->adjustColor($_POST['post_comment_bg_c']);
                    $this->blowup_user['post_commentmy_bg_c'] = $this->blowup_config->utils->adjustColor($_POST['post_commentmy_bg_c']);
                    $this->blowup_user['prelude_c']           = $this->blowup_config->utils->adjustColor($_POST['prelude_c']);
                    $this->blowup_config->createImages($this->blowup_user, $uploaded);
                }

                if ($this->blowup_config->canWriteCss()) {
                    $this->blowup_config->createCss($this->blowup_user);
                }

                dcCore()->blog->settings->addNamespace('themes');
                dcCore()->blog->settings->themes->put('blowup_style', serialize($this->blowup_user));
                dcCore()->blog->triggerBlog();

                dcCore()->notices->addSuccessNotice(__('Theme configuration has been successfully updated.'));
                dcCore()->adminurl->redirect('admin.plugin.BlowUp');
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }
        }


        # Page setup
        $this
            ->setPageTitle(__('Blowup configuration'))
            ->setPageHelp('BlowupConfig')
            ->setPageHead(
                static::jsJson('blowup', [
                    'blowup_public_url' => $this->blowup_config->imagesURL(),
                    'msg'               => [
                        'predefined_styles'      => __('Predefined styles'),
                        'apply_code'             => __('Apply code'),
                        'predefined_style_title' => __('Choose a predefined style'),
                    ]
                ]) .
                static::jsLoad('?mf=Theme/BlowUp/files/js/config.js')
            )
            ->setPageBreadcrumb([
                html::escapeHTML(dcCore()->blog->name) => '',
                __('Blog appearance')               => dcCore()->adminurl->get('admin.blog.theme'),
                __('Blowup configuration')          => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo
        '<p><a class="back" href="' . dcCore()->adminurl->get('admin.blog.theme') . '">' . __('Back to Blog appearance') . '</a></p>';

        if (!$this->blowup_can_write_images) {
            Notices::message(__('For the following reasons, images cannot be created. You won\'t be able to change some background properties.') .
                $this->blowup_notices, false, true);
        }

        echo '<form id="theme_config" action="' . dcCore()->adminurl->get('admin.plugin.BlowUp') . '" method="post" enctype="multipart/form-data">';

        echo '<div class="fieldset"><h3>' . __('Customization') . '</h3>' .
        '<h4>' . __('General') . '</h4>';

        if ($this->blowup_can_write_images) {
            echo
            '<p class="field"><label for="body_bg_c">' . __('Background color:') . '</label> ' .
            form::color('body_bg_c', ['default' => $this->blowup_user['body_bg_c']]) . '</p>' .

            '<p class="field"><label for="body_bg_g">' . __('Background color fill:') . '</label> ' .
            form::combo('body_bg_g', self::blowupGradientTypesCombo(), $this->blowup_user['body_bg_g']) . '</p>';
        }

        echo
        '<p class="field"><label for="body_txt_f">' . __('Main text font:') . '</label> ' .
        form::combo('body_txt_f', $this->blowup_config->fontsList(), $this->blowup_user['body_txt_f']) . '</p>' .

        '<p class="field"><label for="body_txt_s">' . __('Main text font size:') . '</label> ' .
        form::field('body_txt_s', 7, 7, $this->blowup_user['body_txt_s']) . '</p>' .

        '<p class="field"><label for="body_txt_c">' . __('Main text color:') . '</label> ' .
        form::color('body_txt_c', ['default' => $this->blowup_user['body_txt_c']]) . '</p>' .

        '<p class="field"><label for="body_line_height">' . __('Text line height:') . '</label> ' .
        form::field('body_line_height', 7, 7, $this->blowup_user['body_line_height']) . '</p>' .

        '<h4 class="border-top">' . __('Links') . '</h4>' .
        '<p class="field"><label for="body_link_c">' . __('Links color:') . '</label> ' .
        form::color('body_link_c', ['default' => $this->blowup_user['body_link_c']]) . '</p>' .

        '<p class="field"><label for="body_link_v_c">' . __('Visited links color:') . '</label> ' .
        form::color('body_link_v_c', ['default' => $this->blowup_user['body_link_v_c']]) . '</p>' .

        '<p class="field"><label for="body_link_f_c">' . __('Focus links color:') . '</label> ' .
        form::color('body_link_f_c', ['default' => $this->blowup_user['body_link_f_c']]) . '</p>' .

        '<h4 class="border-top">' . __('Page top') . '</h4>';

        if ($this->blowup_can_write_images) {
            echo
            '<p class="field"><label for="prelude_c">' . __('Prelude color:') . '</label> ' .
            form::color('prelude_c', ['default' => $this->blowup_user['prelude_c']]) . '</p>';
        }

        echo
        '<p class="field"><label for="blog_title_hide">' . __('Hide main title') . '</label> ' .
        form::checkbox('blog_title_hide', 1, $this->blowup_user['blog_title_hide']) . '</p>' .

        '<p class="field"><label for="blog_title_f">' . __('Main title font:') . '</label> ' .
        form::combo('blog_title_f', $this->blowup_config->fontsList(), $this->blowup_user['blog_title_f']) . '</p>' .

        '<p class="field"><label for="blog_title_s">' . __('Main title font size:') . '</label> ' .
        form::field('blog_title_s', 7, 7, $this->blowup_user['blog_title_s']) . '</p>' .

        '<p class="field"><label for="blog_title_c">' . __('Main title color:') . '</label> ' .
        form::color('blog_title_c', ['default' => $this->blowup_user['blog_title_c']]) . '</p>' .

        '<p class="field"><label for="blog_title_a">' . __('Main title alignment:') . '</label> ' .
        form::combo('blog_title_a', [__('center') => 'center', __('left') => 'left', __('right') => 'right'], $this->blowup_user['blog_title_a']) . '</p>' .

        '<p class="field"><label for="blog_title_p">' . __('Main title position (x:y)') . '</label> ' .
        form::field('blog_title_p', 7, 7, $this->blowup_user['blog_title_p']) . '</p>';

        if ($this->blowup_can_write_images) {
            if ($this->blowup_user['top_image'] == 'custom' && $this->blowup_user['uploaded']) {
                $preview_image = http::concatURL(dcCore()->blog->url, $this->blowup_config->imagesURL() . '/page-t.png');
            } else {
                $preview_image = '?mf=Theme/BlowUp/files/alpha-img/page-t/' . $this->blowup_user['top_image'] . '.png';
            }

            echo
            '<h5 class="pretty-title">' . __('Top image') . '</h5>' .
            '<p class="field"><label for="top_image">' . __('Top image') . '</label> ' .
            form::combo('top_image', $this->blowupTopImagesCombo(), ($this->blowup_user['top_image'] ?: 'default')) . '</p>' .
            '<p>' . __('Choose "Custom..." to upload your own image.') . '</p>' .

            '<p id="uploader"><label for="upfile">' . __('Add your image:') . '</label> ' .
            ' (' . sprintf(__('JPEG or PNG file, 800 pixels wide, maximum size %s'), files::size((int) DOTCLEAR_MAX_UPLOAD_SIZE)) . ')' .
            '<input type="file" name="upfile" id="upfile" size="35" />' .
            '</p>' .

            '<h5>' . __('Preview') . '</h5>' .
                '<div class="grid" style="width:800px;border:1px solid #ccc;">' .
                '<img style="display:block;" src="' . $preview_image . '" alt="" id="image-preview" />' .
                '</div>';
        }

        echo
        '<h4 class="border-top">' . __('Sidebar') . '</h4>' .
        '<p class="field"><label for="sidebar_position">' . __('Sidebar position:') . '</label> ' .
        form::combo('sidebar_position', [__('right') => 'right', __('left') => 'left'], $this->blowup_user['sidebar_position']) . '</p>' .

        '<p class="field"><label for="sidebar_text_f">' . __('Sidebar text font:') . '</label> ' .
        form::combo('sidebar_text_f', $this->blowup_config->fontsList(), $this->blowup_user['sidebar_text_f']) . '</p>' .

        '<p class="field"><label for="sidebar_text_s">' . __('Sidebar text font size:') . '</label> ' .
        form::field('sidebar_text_s', 7, 7, $this->blowup_user['sidebar_text_s']) . '</p>' .

        '<p class="field"><label for="sidebar_text_c">' . __('Sidebar text color:') . '</label> ' .
        form::color('sidebar_text_c', ['default' => $this->blowup_user['sidebar_text_c']]) . '</p>' .

        '<p class="field"><label for="sidebar_title_f">' . __('Sidebar titles font:') . '</label> ' .
        form::combo('sidebar_title_f', $this->blowup_config->fontsList(), $this->blowup_user['sidebar_title_f']) . '</p>' .

        '<p class="field"><label for="sidebar_title_s">' . __('Sidebar titles font size:') . '</label> ' .
        form::field('sidebar_title_s', 7, 7, $this->blowup_user['sidebar_title_s']) . '</p>' .

        '<p class="field"><label for="sidebar_title_c">' . __('Sidebar titles color:') . '</label> ' .
        form::color('sidebar_title_c', ['default' => $this->blowup_user['sidebar_title_c']]) . '</p>' .

        '<p class="field"><label for="sidebar_title2_f">' . __('Sidebar 2nd level titles font:') . '</label> ' .
        form::combo('sidebar_title2_f', $this->blowup_config->fontsList(), $this->blowup_user['sidebar_title2_f']) . '</p>' .

        '<p class="field"><label for="sidebar_title2_s">' . __('Sidebar 2nd level titles font size:') . '</label> ' .
        form::field('sidebar_title2_s', 7, 7, $this->blowup_user['sidebar_title2_s']) . '</p>' .

        '<p class="field"><label for="sidebar_title2_c">' . __('Sidebar 2nd level titles color:') . '</label> ' .
        form::color('sidebar_title2_c', ['default' => $this->blowup_user['sidebar_title2_c']]) . '</p>' .

        '<p class="field"><label for="sidebar_line_c">' . __('Sidebar lines color:') . '</label> ' .
        form::color('sidebar_line_c', ['default' => $this->blowup_user['sidebar_line_c']]) . '</p>' .

        '<p class="field"><label for="sidebar_link_c">' . __('Sidebar links color:') . '</label> ' .
        form::color('sidebar_link_c', ['default' => $this->blowup_user['sidebar_link_c']]) . '</p>' .

        '<p class="field"><label for="sidebar_link_v_c">' . __('Sidebar visited links color:') . '</label> ' .
        form::color('sidebar_link_v_c', ['default' => $this->blowup_user['sidebar_link_v_c']]) . '</p>' .

        '<p class="field"><label for="sidebar_link_f_c">' . __('Sidebar focus links color:') . '</label> ' .
        form::color('sidebar_link_f_c', ['default' => $this->blowup_user['sidebar_link_f_c']]) . '</p>' .

        '<h4 class="border-top">' . __('Entries') . '</h4>' .
        '<p class="field"><label for="date_title_f">' . __('Date title font:') . '</label> ' .
        form::combo('date_title_f', $this->blowup_config->fontsList(), $this->blowup_user['date_title_f']) . '</p>' .

        '<p class="field"><label for="date_title_s">' . __('Date title font size:') . '</label> ' .
        form::field('date_title_s', 7, 7, $this->blowup_user['date_title_s']) . '</p>' .

        '<p class="field"><label for="date_title_c">' . __('Date title color:') . '</label> ' .
        form::color('date_title_c', ['default' => $this->blowup_user['date_title_c']]) . '</p>' .

        '<p class="field"><label for="post_title_f">' . __('Entry title font:') . '</label> ' .
        form::combo('post_title_f', $this->blowup_config->fontsList(), $this->blowup_user['post_title_f']) . '</p>' .

        '<p class="field"><label for="post_title_s">' . __('Entry title font size:') . '</label> ' .
        form::field('post_title_s', 7, 7, $this->blowup_user['post_title_s']) . '</p>' .

        '<p class="field"><label for="post_title_c">' . __('Entry title color:') . '</label> ' .
        form::color('post_title_c', ['default' => $this->blowup_user['post_title_c']]) . '</p>';

        if ($this->blowup_can_write_images) {
            echo
            '<p class="field"><label for="post_comment_bg_c">' . __('Comment background color:') . '</label> ' .
            form::color('post_comment_bg_c', ['default' => $this->blowup_user['post_comment_bg_c']]) . '</p>';
        }

        echo
        '<p class="field"><label for="post_comment_c">' . __('Comment text color:') . '</label> ' .
        form::color('post_comment_c', ['default' => $this->blowup_user['post_comment_c']]) . '</p>';

        if ($this->blowup_can_write_images) {
            echo
            '<p class="field"><label for="post_commentmy_bg_c">' . __('My comment background color:') . '</label> ' .
            form::color('post_commentmy_bg_c', ['default' => $this->blowup_user['post_commentmy_bg_c']]) . '</p>';
        }

        echo
        '<p class="field"><label for="post_commentmy_c">' . __('My comment text color:') . '</label> ' .
        form::color('post_commentmy_c', ['default' => $this->blowup_user['post_commentmy_c']]) . '</p>' .

        '<h4 class="border-top">' . __('Footer') . '</h4>' .
        '<p class="field"><label for="footer_f">' . __('Footer font:') . '</label> ' .
        form::combo('footer_f', $this->blowup_config->fontsList(), $this->blowup_user['footer_f']) . '</p>' .

        '<p class="field"><label for="footer_s">' . __('Footer font size:') . '</label> ' .
        form::field('footer_s', 7, 7, $this->blowup_user['footer_s']) . '</p>' .

        '<p class="field"><label for="footer_c">' . __('Footer color:') . '</label> ' .
        form::color('footer_c', ['default' => $this->blowup_user['footer_c']]) . '</p>' .

        '<p class="field"><label for="footer_l_c">' . __('Footer links color:') . '</label> ' .
        form::color('footer_l_c', ['default' => $this->blowup_user['footer_l_c']]) . '</p>' .

        '<p class="field"><label for="footer_bg_c">' . __('Footer background color:') . '</label> ' .
        form::color('footer_bg_c', ['default' => $this->blowup_user['footer_bg_c']]) . '</p>';

        echo
        '<h4 class="border-top">' . __('Additional CSS') . '</h4>' .
        '<p><label for="extra_css">' . __('Any additional CSS styles (must be written using the CSS syntax):') . '</label> ' .
        form::textarea('extra_css', 72, 5, [
            'default'    => html::escapeHTML($this->blowup_user['extra_css']),
            'class'      => 'maximal',
            'extra_html' => 'title="' . __('Additional CSS') . '"'
        ]) .
            '</p>' .
            '</div>';

        // Import / Export configuration
        $tmp_array   = [];
        $tmp_exclude = ['uploaded', 'top_height'];
        if ($this->blowup_user['top_image'] == 'custom') {
            $tmp_exclude[] = 'top_image';
        }
        foreach ($this->blowup_user as $k => $v) {
            if (!in_array($k, $tmp_exclude)) {
                $tmp_array[] = $k . ':' . '"' . $v . '"';
            }
        }
        echo
        '<div class="fieldset">' .
        '<h3 id="bu_export">' . __('Configuration import / export') . '</h3>' .
        '<div id="bu_export_content">' .
        '<p>' . __('You can share your configuration using the following code. To apply a configuration, paste the code, click on "Apply code" and save.') . '</p>' .
        '<p>' . form::textarea('export_code', 72, 5, [
            'default'    => implode('; ', $tmp_array),
            'class'      => 'maximal',
            'extra_html' => 'title="' . __('Copy this code:') . '"'
        ]) . '</p>' .
            '</div>' .
            '</div>';

        echo
        '<p class="clear"><input type="submit" value="' . __('Save') . '" />' .
        dcCore()->formNonce() . '</p>' .
            '</form>';

    }
}
