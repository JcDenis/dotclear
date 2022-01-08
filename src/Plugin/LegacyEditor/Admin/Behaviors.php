<?php
/**
 * @class Dotclear\Plugin\LegacyEditor\Admin\Behaviors
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PlugniUserPref
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

use Dotclear\Core\Core;
use Dotclear\Admin\Page;
use Dotclear\Utils\l10n;

class Behaviors
{
    protected static $p_url = '?pf=LegacyEditor';

    /**
     * adminPostEditor add javascript to the DOM to load legacy editor depending on context
     *
     * @param      Core    $core     Core instance
     * @param      string  $editor   The wanted editor
     * @param      string  $context  The page context (post,page,comment,event,...)
     * @param      array   $tags     The array of ids to inject editor
     * @param      string  $syntax   The wanted syntax (wiki,markdown,...)
     *
     * @return     mixed
     */
    public static function adminPostEditor(Core $core, $editor = '', $context = '', array $tags = [], $syntax = '')
    {
        if (empty($editor) || $editor != 'LegacyEditor') {
            return;
        }

        $js = [
            'legacy_editor_context'      => $context,
            'legacy_editor_syntax'       => $syntax,
            'legacy_editor_tags_context' => [$context => $tags],
        ];

        return
        self::jsToolBar($core) .
        Page::jsJson('legacy_editor_ctx', $js) .
        Page::jsLoad('?pf=LegacyEditor/js/_post_editor.js');
    }

    public static function adminPopupMedia(Core $core, $editor = '')
    {
        if (empty($editor) || $editor != 'LegacyEditor') {
            return;
        }

        return Page::jsLoad('?pf=LegacyEditor/js/jsToolBar/popup_media.js');
    }

    public static function adminPopupLink(Core $core, $editor = '')
    {
        if (empty($editor) || $editor != 'LegacyEditor') {
            return;
        }

        return Page::jsLoad('?pf=LegacyEditor/js/jsToolBar/popup_link.js');
    }

    public static function adminPopupPosts(Core $core, $editor = '')
    {
        if (empty($editor) || $editor != 'LegacyEditor') {
            return;
        }

        return Page::jsLoad('?pf=LegacyEditor/js/jsToolBar/popup_posts.js');
    }

    protected static function jsToolBar($core)
    {
        $rtl = l10n::getLanguageTextDirection($core->_lang) == 'rtl' ? 'direction: rtl;' : '';
        $css = <<<EOT
            body {
                color: #000;
                background: #f9f9f9;
                margin: 0;
                padding: 2px;
                border: none;
                $rtl
            }
            code {
                color: #666;
                font-weight: bold;
            }
            body > p:first-child {
                margin-top: 0;
            }
            EOT;
        $js = [
            'dialog_url'            => 'popup.php',
            'iframe_css'            => $css,
            'base_url'              => $core->blog->host,
            'switcher_visual_title' => __('visual'),
            'switcher_source_title' => __('source'),
            'legend_msg'            => __('You can use the following shortcuts to format your text.'),
            'elements'              => [
                'blocks' => [
                    'title'   => __('Block format'),
                    'options' => [
                        'none'    => __('-- none --'),
                        'nonebis' => __('-- block format --'),
                        'p'       => __('Paragraph'),
                        'h1'      => __('Level 1 header'),
                        'h2'      => __('Level 2 header'),
                        'h3'      => __('Level 3 header'),
                        'h4'      => __('Level 4 header'),
                        'h5'      => __('Level 5 header'),
                        'h6'      => __('Level 6 header'),
                    ], ],

                'strong'     => ['title' => __('Strong emphasis')],
                'em'         => ['title' => __('Emphasis')],
                'ins'        => ['title' => __('Inserted')],
                'del'        => ['title' => __('Deleted')],
                'quote'      => ['title' => __('Inline quote')],
                'code'       => ['title' => __('Code')],
                'mark'       => ['title' => __('Mark')],
                'br'         => ['title' => __('Line break')],
                'blockquote' => ['title' => __('Blockquote')],
                'pre'        => ['title' => __('Preformated text')],
                'ul'         => ['title' => __('Unordered list')],
                'ol'         => ['title' => __('Ordered list')],

                'link' => [
                    'title'           => __('Link'),
                    'accesskey'       => __('l'),
                    'href_prompt'     => __('URL?'),
                    'hreflang_prompt' => __('Language?'),
                ],

                'img' => [
                    'title'      => __('External image'),
                    'src_prompt' => __('URL?'),
                ],

                'img_select' => [
                    'title'     => __('Media chooser'),
                    'accesskey' => __('m'),
                ],

                'post_link'    => ['title' => __('Link to an entry')],
                'removeFormat' => ['title' => __('Remove text formating')],
            ],
            'toolbar_bottom' => (bool) isset($core->auth) && $core->auth->getOption('toolbar_bottom'),
        ];
        if (!$core->auth->check('media,media_admin', $core->blog->id)) {
            $js['elements']['img_select']['disabled'] = true;
        }

        $res = Page::jsJson('legacy_editor', $js) .
        Page::cssLoad('?pf=LegacyEditor/css/jsToolBar/jsToolBar.css') .
        Page::jsLoad('?pf=LegacyEditor/js/jsToolBar/jsToolBar.js');

        if (isset($core->auth) && $core->auth->getOption('enable_wysiwyg')) {
            $res .= Page::jsLoad('?pf=LegacyEditor/js/jsToolBar/jsToolBar.wysiwyg.js');
        }

        $res .= Page::jsLoad('?pf=LegacyEditor/js/jsToolBar/jsToolBar.dotclear.js') .
        Page::jsLoad('?pf=LegacyEditor/js/jsToolBar/jsToolBar.config.js');

        return $res;
    }
}
