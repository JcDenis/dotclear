<?php
/**
 * @class Dotclear\Theme\Ductile\Admin\Page
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeBlowup
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Admin;

use Dotclear\Exception;

use Dotclear\Module\AbstractPage;
use Dotclear\Module\Theme\Admin\ConfigTheme;

use Dotclear\Html\Html;
use Dotclear\Html\Form;
use Dotclear\File\Files;
use Dotclear\Network\Http;
use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    protected $workspaces = ['accessibility'];
    protected $namespaces = ['themes'];

    private $Ductile_list_types = [];
    private $Ductile_user = [
        // HTML
        'subtitle_hidden'       => null,
        'logo_src'              => null,
        'preview_not_mandatory' => null,
        // CSS
        'body_font'                => null,
        'body_webfont_family'      => null,
        'body_webfont_url'         => null,
        'body_webfont_api'         => null,
        'alternate_font'           => null,
        'alternate_webfont_family' => null,
        'alternate_webfont_url'    => null,
        'alternate_webfont_api'    => null,
        'blog_title_w'             => null,
        'blog_title_s'             => null,
        'blog_title_c'             => null,
        'post_title_w'             => null,
        'post_title_s'             => null,
        'post_title_c'             => null,
        'post_link_w'              => null,
        'post_link_v_c'            => null,
        'post_link_f_c'            => null,
        'blog_title_w_m'           => null,
        'blog_title_s_m'           => null,
        'blog_title_c_m'           => null,
        'post_title_w_m'           => null,
        'post_title_s_m'           => null,
        'post_title_c_m'           => null,
        'post_simple_title_c'      => null,
    ];
    private $Ductile_lists = [
        'default'      => 'short',
        'default-page' => 'short',
        'category'     => 'short',
        'tag'          => 'short',
        'search'       => 'short',
        'archive'      => 'short',
    ];
    private $Ductile_counts = [
        'default'      => null,
        'default-page' => null,
        'category'     => null,
        'tag'          => null,
        'search'       => null,
    ];
    private $Ductile_stickers = [];
    private $Ductile_conf_tab = '';
    private $Ductile_config;

    protected function getPermissions(): string|null|false
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        $this->Ductile_config = new ConfigTheme();

        $this->Ductile_list_types = [
            __('Title') => 'title',
            __('Short') => 'short',
            __('Full')  => 'full',
        ];

        # Get all _entry-*.html in tpl folder of theme
        $list_types_templates = Files::scandir( __DIR__ . '/../tpl/');
        if (is_array($list_types_templates)) {
            foreach ($list_types_templates as $v) {
                if (preg_match('/^_entry\-(.*)\.html$/', $v, $m)) {
                    if (isset($m[1])) {
                        if (!in_array($m[1], $this->Ductile_list_types)) {
                            # template not already in full list
                            $this->Ductile_list_types[__($m[1])] = $m[1];
                        }
                    }
                }
            }
        }

        $ductile_user = (string) dotclear()->blog->settings->themes->get(dotclear()->blog->settings->system->theme . '_style');
        $ductile_user = @unserialize($ductile_user);
        if (is_array($ductile_user)) {
            $this->Ductile_user = array_merge($this->Ductile_user, $ductile_user);
        }

        $ductile_lists = (string) dotclear()->blog->settings->themes->get(dotclear()->blog->settings->system->theme . '_entries_lists');
        $ductile_lists = @unserialize($ductile_lists);
        if (is_array($ductile_lists)) {
            $this->Ductile_lists = array_merge($this->Ductile_lists, $ductile_lists);
        }

        $ductile_counts = (string) dotclear()->blog->settings->themes->get(dotclear()->blog->settings->system->theme . '_entries_counts');
        $ductile_counts = @unserialize($ductile_counts);
        if (is_array($ductile_counts)) {
            $this->Ductile_counts = array_merge($this->Ductile_counts, $ductile_counts);
        }

        $this->Ductile_stickers = [[
            'label' => __('Subscribe'),
            'url'   => dotclear()->blog->url .
            dotclear()->url->getURLFor('feed', 'atom'),
            'image' => 'sticker-feed.png'
        ]];

        $ductile_stickers = (string) dotclear()->blog->settings->themes->get(dotclear()->blog->settings->system->theme . '_stickers');
        $ductile_stickers = @unserialize($ductile_stickers);
        if (is_array($ductile_stickers)) {
            $this->Ductile_stickers = $ductile_stickers;
        }

        $ductile_stickers_full = [];
        foreach ($this->Ductile_stickers as $v) {
            $ductile_stickers_full[] = $v['image'];
        }

        $ductile_stickers_images = Files::scandir( __DIR__ . '/../files/img/');
        if (is_array($ductile_stickers_images)) {
            foreach ($ductile_stickers_images as $v) {
                if (preg_match('/^sticker\-(.*)\.png$/', $v)) {
                    if (!in_array($v, $ductile_stickers_full)) {
                        // image not already used
                        $this->Ductile_stickers[] = [
                            'label' => null,
                            'url'   => null,
                            'image' => $v];
                    }
                }
            }
        }

        $this->Ductile_conf_tab = $_POST['conf_tab'] ?? 'html';

        if (!empty($_POST)) {
            try {
                # HTML
                if ($this->Ductile_conf_tab == 'html') {
                    $this->Ductile_user['subtitle_hidden']       = (integer) !empty($_POST['subtitle_hidden']);
                    $this->Ductile_user['logo_src']              = $_POST['logo_src'];
                    $this->Ductile_user['preview_not_mandatory'] = (integer) !empty($_POST['preview_not_mandatory']);

                    $this->Ductile_stickers = [];
                    for ($i = 0; $i < count($_POST['sticker_image']); $i++) {
                        $this->Ductile_stickers[] = [
                            'label' => $_POST['sticker_label'][$i],
                            'url'   => $_POST['sticker_url'][$i],
                            'image' => $_POST['sticker_image'][$i]
                        ];
                    }

                    $order = [];
                    if (empty($_POST['ds_order']) && !empty($_POST['order'])) {
                        $order = $_POST['order'];
                        asort($order);
                        $order = array_keys($order);
                    }
                    if (!empty($order)) {
                        $new_ductile_stickers = [];
                        foreach ($order as $i => $k) {
                            $new_ductile_stickers[] = [
                                'label' => $this->Ductile_stickers[$k]['label'],
                                'url'   => $this->Ductile_stickers[$k]['url'],
                                'image' => $this->Ductile_stickers[$k]['image']
                            ];
                        }
                        $this->Ductile_stickers = $new_ductile_stickers;
                    }

                    for ($i = 0; $i < count($_POST['list_type']); $i++) {
                        $this->Ductile_lists[$_POST['list_ctx'][$i]] = $_POST['list_type'][$i];
                    }

                    for ($i = 0; $i < count($_POST['count_nb']); $i++) {
                        $this->Ductile_counts[$_POST['count_ctx'][$i]] = $_POST['count_nb'][$i];
                    }
                }

                # CSS
                if ($this->Ductile_conf_tab == 'css') {
                    $this->Ductile_user['body_font']           = $_POST['body_font'];
                    $this->Ductile_user['body_webfont_family'] = $_POST['body_webfont_family'];
                    $this->Ductile_user['body_webfont_url']    = $_POST['body_webfont_url'];
                    $this->Ductile_user['body_webfont_api']    = $_POST['body_webfont_api'];

                    $this->Ductile_user['alternate_font']           = $_POST['alternate_font'];
                    $this->Ductile_user['alternate_webfont_family'] = $_POST['alternate_webfont_family'];
                    $this->Ductile_user['alternate_webfont_url']    = $_POST['alternate_webfont_url'];
                    $this->Ductile_user['alternate_webfont_api']    = $_POST['alternate_webfont_api'];

                    $this->Ductile_user['blog_title_w'] = (integer) !empty($_POST['blog_title_w']);
                    $this->Ductile_user['blog_title_s'] = $this->Ductile_config->adjustFontSize($_POST['blog_title_s']);
                    $this->Ductile_user['blog_title_c'] = $this->Ductile_config->adjustColor($_POST['blog_title_c']);

                    $this->Ductile_user['post_title_w'] = (integer) !empty($_POST['post_title_w']);
                    $this->Ductile_user['post_title_s'] = $this->Ductile_config->adjustFontSize($_POST['post_title_s']);
                    $this->Ductile_user['post_title_c'] = $this->Ductile_config->adjustColor($_POST['post_title_c']);

                    $this->Ductile_user['post_link_w']   = (integer) !empty($_POST['post_link_w']);
                    $this->Ductile_user['post_link_v_c'] = $this->Ductile_config->adjustColor($_POST['post_link_v_c']);
                    $this->Ductile_user['post_link_f_c'] = $this->Ductile_config->adjustColor($_POST['post_link_f_c']);

                    $this->Ductile_user['post_simple_title_c'] = $this->Ductile_config->adjustColor($_POST['post_simple_title_c']);

                    $this->Ductile_user['blog_title_w_m'] = (integer) !empty($_POST['blog_title_w_m']);
                    $this->Ductile_user['blog_title_s_m'] = $this->Ductile_config->adjustFontSize($_POST['blog_title_s_m']);
                    $this->Ductile_user['blog_title_c_m'] = $this->Ductile_config->adjustColor($_POST['blog_title_c_m']);

                    $this->Ductile_user['post_title_w_m'] = (integer) !empty($_POST['post_title_w_m']);
                    $this->Ductile_user['post_title_s_m'] = $this->Ductile_config->adjustFontSize($_POST['post_title_s_m']);
                    $this->Ductile_user['post_title_c_m'] = $this->Ductile_config->adjustColor($_POST['post_title_c_m']);
                }

                dotclear()->blog->settings->themes->put(dotclear()->blog->settings->system->theme . '_style', serialize($this->Ductile_user));
                dotclear()->blog->settings->themes->put(dotclear()->blog->settings->system->theme . '_stickers', serialize($this->Ductile_stickers));
                dotclear()->blog->settings->themes->put(dotclear()->blog->settings->system->theme . '_entries_lists', serialize($this->Ductile_lists));
                dotclear()->blog->settings->themes->put(dotclear()->blog->settings->system->theme . '_entries_counts', serialize($this->Ductile_counts));

                dotclear()->blog->triggerBlog();
                dotclear()->emptyTemplatesCache();

                dotclear()->notices->addSuccessNotice(__('Theme configuration upgraded.'));
            } catch (Exception $e) {
                dotclear()->error($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Ductile configuration'))
            ->setPageHelp('ductile')
            ->setPageHead(static::jsPageTabs())
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog->name) => '',
                __('Blog appearance')               => dotclear()->adminurl->get('admin.blog.theme'),
                __('Ductile configuration')          => ''
            ])
        ;

        if (!dotclear()->auth->user_prefs->accessibility->nodragdrop) {
            $this->setpageHead(
                static::jsLoad('js/jquery/jquery-ui.custom.js') .
                static::jsLoad('js/jquery/jquery.ui.touch-punch.js') .
                static::jsLoad('?mf=Theme/Ductile/files/js/config.js')
            );
        }

        return true;
    }

    protected function getPageContent(): void
    {
        $contexts = [
            'default'      => __('Home (first page)'),
            'default-page' => __('Home (other pages)'),
            'category'     => __('Entries for a category'),
            'tag'          => __('Entries for a tag'),
            'search'       => __('Search result entries'),
            'archive'      => __('Month archive entries'),
        ];

        $fonts = [
            __('Default')           => '',
            __('Ductile primary')   => 'Ductile body',
            __('Ductile secondary') => 'Ductile alternate',
            __('Times New Roman')   => 'Times New Roman',
            __('Georgia')           => 'Georgia',
            __('Garamond')          => 'Garamond',
            __('Helvetica/Arial')   => 'Helvetica/Arial',
            __('Verdana')           => 'Verdana',
            __('Trebuchet MS')      => 'Trebuchet MS',
            __('Impact')            => 'Impact',
            __('Monospace')         => 'Monospace',
        ];

        $webfont_apis = [
            __('none')                => '',
            __('javascript (Adobe)')  => 'js',
            __('stylesheet (Google)') => 'css',
        ];

        $img_url = dotclear()->blog->url . 'files/img/';

        # HTML Tab

        echo '<div class="multi-part" id="themes-list-html" title="' . __('Content') . '">' .
        '<h3>' . __('Content') . '</h3>';

        echo '<form id="theme_config" action="' . dotclear()->adminurl->get('admin.plugin.Ductile') . '#themes-list-html' .
            '" method="post" enctype="multipart/form-data">';

        echo '<h4>' . __('Header') . '</h4>' .
        '<p class="field"><label for="subtitle_hidden">' . __('Hide blog description:') . '</label> ' .
        Form::checkbox('subtitle_hidden', 1, $this->Ductile_user['subtitle_hidden']) . '</p>';
        echo '<p class="field"><label for="logo_src">' . __('Logo URL:') . '</label> ' .
        Form::field('logo_src', 40, 255, $this->Ductile_user['logo_src']) . '</p>';
        if (dotclear()->plugins->hasModule('SimpleMenu')) {
            echo '<p>' . sprintf(__('To configure the top menu go to the <a href="%s">Simple Menu administration page</a>.'),
                dotclear()->adminurl->get('admin.plugin.SimpleMenu')) . '</p>';
        }

        echo '<h4 class="border-top pretty-title">' . __('Stickers') . '</h4>';

        echo
        '<div class="table-outer">' .
        '<table class="dragable">' . '<caption>' . __('Stickers (footer)') . '</caption>' .
        '<thead>' .
        '<tr>' .
        '<th scope="col">' . '</th>' .
        '<th scope="col">' . __('Image') . '</th>' .
        '<th scope="col">' . __('Label') . '</th>' .
        '<th scope="col">' . __('URL') . '</th>' .
            '</tr>' .
            '</thead>' .
            '<tbody id="stickerslist">';
        $count = 0;
        foreach ($this->Ductile_stickers as $i => $v) {
            $count++;
            echo
            '<tr class="line" id="l_' . $i . '">' .
            '<td class="handle minimal">' . Form::number(['order[' . $i . ']'], [
                'min'     => 0,
                'max'     => count($this->Ductile_stickers),
                'default' => $count,
                'class'   => 'position'
            ]) .
            Form::hidden(['dynorder[]', 'dynorder-' . $i], $i) . '</td>' .
            '<td>' . Form::hidden(['sticker_image[]'], $v['image']) . '<img src="' . $img_url . $v['image'] . '" alt="' . $v['image'] . '" /> ' . '</td>' .
            '<td scope="row">' . Form::field(['sticker_label[]', 'dsl-' . $i], 20, 255, $v['label']) . '</td>' .
            '<td>' . Form::field(['sticker_url[]', 'dsu-' . $i], 40, 255, $v['url']) . '</td>' .
                '</tr>';
        }
        echo
            '</tbody>' .
            '</table></div>';

        echo '<h4 class="border-top pretty-title">' . __('Entries list types and limits') . '</h4>';

        echo '<table id="entrieslist">' . '<caption class="hidden">' . __('Entries lists') . '</caption>' .
        '<thead>' .
        '<tr>' .
        '<th scope="col">' . __('Context') . '</th>' .
        '<th scope="col">' . __('Entries list type') . '</th>' .
        '<th scope="col">' . __('Number of entries') . '</th>' .
            '</tr>' .
            '</thead>' .
            '<tbody>';
        foreach ($this->Ductile_lists as $k => $v) {
            echo
            '<tr>' .
            '<td scope="row">' . $contexts[$k] . '</td>' .
            '<td>' . Form::hidden(['list_ctx[]'], $k) . Form::combo(['list_type[]'], $this->Ductile_list_types, $v) . '</td>';
            if (array_key_exists($k, $this->Ductile_counts)) {
                echo '<td>' . Form::hidden(['count_ctx[]'], $k) . Form::number(['count_nb[]'], [
                    'min'     => 0,
                    'max'     => 999,
                    'default' => $this->Ductile_counts[$k]
                ]) . '</td>';
            } else {
                echo '<td></td>';
            }
            echo
                '</tr>';
        }
        echo
            '</tbody>' .
            '</table>';

        echo '<h4 class="border-top pretty-title">' . __('Miscellaneous options') . '</h4>';
        echo '<p><label for="preview_not_mandatory" class="classic">' . __('Comment preview is not mandatory:') . '</label> ' .
        Form::checkbox('preview_not_mandatory', 1, $this->Ductile_user['preview_not_mandatory']) . '</p>';

        echo '<p><input type="hidden" name="conf_tab" value="html" /></p>';
        echo '<p class="clear">' . Form::hidden('ds_order', '') . '<input type="submit" value="' . __('Save') . '" />' . dotclear()->formNonce() . '</p>';
        echo '</form>';

        echo '</div>'; // Close tab

        # CSS tab

        echo '<div class="multi-part" id="themes-list-css' . '" title="' . __('Presentation') . '">';

        echo '<form id="theme_config" action="' . dotclear()->adminurl->get('admin.plugin.Ductile') . '#themes-list-css' .
            '" method="post" enctype="multipart/form-data">';

        echo '<h3>' . __('General settings') . '</h3>';

        echo '<h4 class="pretty-title">' . __('Fonts') . '</h4>';

        echo '<div class="two-cols">';
        echo '<div class="col">';
        echo
        '<h5>' . __('Main text') . '</h5>' .
        '<p class="field"><label for="body_font">' . __('Main font:') . '</label> ' .
        Form::combo('body_font', $fonts, $this->Ductile_user['body_font']) .
        (!empty($this->Ductile_user['body_font']) ? ' ' . $this->fontDef($this->Ductile_user['body_font']) : '') .
        ' <span class="form-note">' . __('Set to Default to use a webfont.') . '</span>' .
        '</p>' .
        '<p class="field"><label for="body_webfont_family">' . __('Webfont family:') . '</label> ' .
        Form::field('body_webfont_family', 25, 255, $this->Ductile_user['body_webfont_family']) . '</p>' .
        '<p class="field"><label for="body_webfont_url">' . __('Webfont URL:') . '</label> ' .
        Form::url('body_webfont_url', 50, 255, $this->Ductile_user['body_webfont_url']) . '</p>' .
        '<p class="field"><label for="body_webfont_url">' . __('Webfont API:') . '</label> ' .
        Form::combo('body_webfont_api', $webfont_apis, $this->Ductile_user['body_webfont_api']) . '</p>';
        echo '</div>';
        echo '<div class="col">';
        echo
        '<h5>' . __('Secondary text') . '</h5>' .
        '<p class="field"><label for="alternate_font">' . __('Secondary font:') . '</label> ' .
        Form::combo('alternate_font', $fonts, $this->Ductile_user['alternate_font']) .
        (!empty($this->Ductile_user['alternate_font']) ? ' ' . $this->fontDef($this->Ductile_user['alternate_font']) : '') .
        ' <span class="form-note">' . __('Set to Default to use a webfont.') . '</span>' .
        '</p>' .
        '<p class="field"><label for="alternate_webfont_family">' . __('Webfont family:') . '</label> ' .
        Form::field('alternate_webfont_family', 25, 255, $this->Ductile_user['alternate_webfont_family']) . '</p>' .
        '<p class="field"><label for="alternate_webfont_url">' . __('Webfont URL:') . '</label> ' .
        Form::url('alternate_webfont_url', 50, 255, $this->Ductile_user['alternate_webfont_url']) . '</p>' .
        '<p class="field"><label for="alternate_webfont_api">' . __('Webfont API:') . '</label> ' .
        Form::combo('alternate_webfont_api', $webfont_apis, $this->Ductile_user['alternate_webfont_api']) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<h4 class="clear border-top pretty-title">' . __('Titles') . '</h4>';
        echo '<div class="two-cols">';
        echo '<div class="col">';
        echo '<h5>' . __('Blog title') . '</h5>' .
        '<p class="field"><label for="blog_title_w">' . __('In bold:') . '</label> ' .
        Form::checkbox('blog_title_w', 1, $this->Ductile_user['blog_title_w']) . '</p>' .

        '<p class="field"><label for="blog_title_s">' . __('Font size (in em by default):') . '</label> ' .
        Form::field('blog_title_s', 7, 7, $this->Ductile_user['blog_title_s']) . '</p>' .

        '<p class="field picker"><label for="blog_title_c">' . __('Color:') . '</label> ' .
        Form::color('blog_title_c', ['default' => $this->Ductile_user['blog_title_c']]) .
        $this->Ductile_config->contrastRatio($this->Ductile_user['blog_title_c'], '#ffffff',
            (!empty($this->Ductile_user['blog_title_s']) ? $this->Ductile_user['blog_title_s'] : '2em'),
            $this->Ductile_user['blog_title_w']) .
            '</p>';

        echo '</div>';
        echo '<div class="col">';

        echo '<h5>' . __('Post title') . '</h5>' .
        '<p class="field"><label for="post_title_w">' . __('In bold:') . '</label> ' .
        Form::checkbox('post_title_w', 1, $this->Ductile_user['post_title_w']) . '</p>' .

        '<p class="field"><label for="post_title_s">' . __('Font size (in em by default):') . '</label> ' .
        Form::field('post_title_s', 7, 7, $this->Ductile_user['post_title_s']) . '</p>' .

        '<p class="field picker"><label for="post_title_c">' . __('Color:') . '</label> ' .
        Form::color('post_title_c', ['default' => $this->Ductile_user['post_title_c']]) .
        $this->Ductile_config->contrastRatio($this->Ductile_user['post_title_c'], '#ffffff',
            (!empty($this->Ductile_user['post_title_s']) ? $this->Ductile_user['post_title_s'] : '2.5em'),
            $this->Ductile_user['post_title_w']) .
            '</p>';

        echo '</div>';
        echo '</div>';

        echo '<h5>' . __('Titles without link') . '</h5>' .

        '<p class="field picker"><label for="post_simple_title_c">' . __('Color:') . '</label> ' .
        Form::color('post_simple_title_c', ['default' => $this->Ductile_user['post_simple_title_c']]) .
        $this->Ductile_config->contrastRatio($this->Ductile_user['post_simple_title_c'], '#ffffff',
            '1.1em', // H5 minimum size
            false) .
            '</p>';

        echo '<h4 class="border-top pretty-title">' . __('Inside posts links') . '</h4>' .
        '<p class="field"><label for="post_link_w">' . __('In bold:') . '</label> ' .
        Form::checkbox('post_link_w', 1, $this->Ductile_user['post_link_w']) . '</p>' .

        '<p class="field picker"><label for="post_link_v_c">' . __('Normal and visited links color:') . '</label> ' .
        Form::color('post_link_v_c', ['default' => $this->Ductile_user['post_link_v_c']]) .
        $this->Ductile_config->contrastRatio($this->Ductile_user['post_link_v_c'], '#ffffff',
            '1em',
            $this->Ductile_user['post_link_w']) .
        '</p>' .

        '<p class="field picker"><label for="post_link_f_c">' . __('Active, hover and focus links color:') . '</label> ' .
        Form::color('post_link_f_c', ['default' => $this->Ductile_user['post_link_f_c']]) .
        $this->Ductile_config->contrastRatio($this->Ductile_user['post_link_f_c'], '#ebebee',
            '1em',
            $this->Ductile_user['post_link_w']) .
            '</p>';

        echo '<h3 class="border-top">' . __('Mobile specific settings') . '</h3>';

        echo '<div class="two-cols">';
        echo '<div class="col">';

        echo '<h4 class="pretty-title">' . __('Blog title') . '</h4>' .
        '<p class="field"><label for="blog_title_w_m">' . __('In bold:') . '</label> ' .
        Form::checkbox('blog_title_w_m', 1, $this->Ductile_user['blog_title_w_m']) . '</p>' .

        '<p class="field"><label for="blog_title_s_m">' . __('Font size (in em by default):') . '</label> ' .
        Form::field('blog_title_s_m', 7, 7, $this->Ductile_user['blog_title_s_m']) . '</p>' .

        '<p class="field picker"><label for="blog_title_c_m">' . __('Color:') . '</label> ' .
        Form::color('blog_title_c_m', ['default' => $this->Ductile_user['blog_title_c_m']]) .
        $this->Ductile_config->contrastRatio($this->Ductile_user['blog_title_c_m'], '#d7d7dc',
            (!empty($this->Ductile_user['blog_title_s_m']) ? $this->Ductile_user['blog_title_s_m'] : '1.8em'),
            $this->Ductile_user['blog_title_w_m']) .
            '</p>';

        echo '</div>';
        echo '<div class="col">';

        echo '<h4 class="pretty-title">' . __('Post title') . '</h4>' .
        '<p class="field"><label for="post_title_w_m">' . __('In bold:') . '</label> ' .
        Form::checkbox('post_title_w_m', 1, $this->Ductile_user['post_title_w_m']) . '</p>' .

        '<p class="field"><label for="post_title_s_m">' . __('Font size (in em by default):') . '</label> ' .
        Form::field('post_title_s_m', 7, 7, $this->Ductile_user['post_title_s_m']) . '</p>' .

        '<p class="field picker"><label for="post_title_c_m">' . __('Color:') . '</label> ' .
        Form::color('post_title_c_m', ['default' => $this->Ductile_user['post_title_c_m']]) .
        $this->Ductile_config->contrastRatio($this->Ductile_user['post_title_c_m'], '#ffffff',
            (!empty($this->Ductile_user['post_title_s_m']) ? $this->Ductile_user['post_title_s_m'] : '1.5em'),
            $this->Ductile_user['post_title_w_m']) .
            '</p>';

        echo '</div>';
        echo '</div>';

        echo '<p><input type="hidden" name="conf_tab" value="css" /></p>';
        echo '<p class="clear border-top"><input type="submit" value="' . __('Save') . '" />' . dotclear()->formNonce() . '</p>';
        echo '</form>';

        echo '</div>'; // Close tab
    }

    protected function fontDef($c)
    {
        return isset($this->font_families[$c]) ? '<span style="position:absolute;top:0;left:32em;">' . $this->font_families[$c] . '</span>' : '';
    }
}
