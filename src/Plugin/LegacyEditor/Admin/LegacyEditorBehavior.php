<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

// Dotclear\Plugin\LegacyEditor\Admin\LegacyEditorBehavior
use Dotclear\App;
use Dotclear\Helper\L10n;

/**
 * Admin behaviors for plugin LegacyEditor.
 *
 * @ingroup  Plugin LegacyEditor Behavior
 */
class LegacyEditorBehavior
{
    public function __construct()
    {
        App::core()->behavior()->add('adminPostEditor', [$this, 'adminPostEditor']);
        App::core()->behavior()->add('adminPopupMedia', [$this, 'adminPopupMedia']);
        App::core()->behavior()->add('adminPopupLink', [$this, 'adminPopupLink']);
        App::core()->behavior()->add('adminPopupPosts', [$this, 'adminPopupPosts']);
    }

    /**
     * adminPostEditor add javascript to the DOM to load legacy editor depending on context.
     *
     * @param string $editor  The wanted editor
     * @param string $context The page context (post,page,comment,event,...)
     * @param array  $tags    The array of ids to inject editor
     * @param string $syntax  The wanted syntax (wiki,markdown,...)
     */
    public function adminPostEditor(string $editor = '', string $context = '', array $tags = [], string $syntax = ''): string
    {
        if ('LegacyEditor' != $editor) {
            return '';
        }

        $js = [
            'legacy_editor_context'      => $context,
            'legacy_editor_syntax'       => $syntax,
            'legacy_editor_tags_context' => [$context => $tags],
        ];

        return
        $this->jsToolBar() .
        App::core()->resource()->json('legacy_editor_ctx', $js) .
        App::core()->resource()->load('_post_editor.js', 'Plugin', 'LegacyEditor');
    }

    public function adminPopupMedia(string $editor = ''): string
    {
        return 'LegacyEditor' != $editor ? '' : App::core()->resource()->load('jsToolBar/popup_media.js', 'Plugin', 'LegacyEditor');
    }

    public function adminPopupLink(string $editor = ''): string
    {
        return 'LegacyEditor' != $editor ? '' : App::core()->resource()->load('jsToolBar/popup_link.js', 'Plugin', 'LegacyEditor');
    }

    public function adminPopupPosts(string $editor = ''): string
    {
        return 'LegacyEditor' != $editor ? '' : App::core()->resource()->load('jsToolBar/popup_posts.js', 'Plugin', 'LegacyEditor');
    }

    protected function jsToolBar(): string
    {
        $js = [
            'dialog_url'            => 'popup.php',
            'base_url'              => App::core()->blog()->host,
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
                'preview'      => ['title' => __('Preview')],
            ],
            'toolbar_bottom' => App::core()->user()->getOption('toolbar_bottom'),
        ];

        // Tricky code to avoid xgettext bug on indented end heredoc identifier (see https://savannah.gnu.org/bugs/?62158)
        $rtl = 'rtl' == L10n::getLanguageTextDirection(L10n::lang()) ? 'direction: rtl;' : '';
        $css = <<<EOT
            body {
                color: #000;
                background: #f9f9f9;
                margin: 0;
                padding: 2px;
                border: none;
                {$rtl}
            }
            code {
                color: #666;
                font-weight: bold;
            }
            body > p:first-child {
                margin-top: 0;
            }
            EOT;
        $js['iframe_css'] = $css;
        // End of tricky code

        if (!App::core()->user()->check('media,media_admin', App::core()->blog()->id)) {
            $js['elements']['img_select']['disabled'] = true;
        }

        $res =
        App::core()->resource()->json('legacy_editor', $js) .
        App::core()->resource()->load('jsToolBar/jsToolBar.css', 'Plugin', 'LegacyEditor') .
        App::core()->resource()->load('jsToolBar/jsToolBar.js', 'Plugin', 'LegacyEditor');

        if (App::core()->user()->getOption('enable_wysiwyg')) {
            $res .= App::core()->resource()->load('jsToolBar/jsToolBar.wysiwyg.js', 'Plugin', 'LegacyEditor');
        }

        $res .= App::core()->resource()->load('jsToolBar/jsToolBar.dotclear.js', 'Plugin', 'LegacyEditor') .
            App::core()->resource()->load('jsToolBar/jsToolBar.config.js', 'Plugin', 'LegacyEditor');

        return $res;
    }
}
