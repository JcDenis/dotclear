<?php
/**
 * @class Dotclear\Plugin\CKEditor\Admin\CKEditorBehavior
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginCKEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\CKEditor\Admin;

use ArrayObject;

use Dotclear\Helper\Html\Html;

class CKEditorBehavior
{
    public function __construct()
    {
        dotclear()->behavior()->add('adminPostEditor', [$this, 'adminPostEditor']);
        dotclear()->behavior()->add('adminPopupMedia', [$this, 'adminPopupMedia']);
        dotclear()->behavior()->add('adminPopupLink', [$this, 'adminPopupLink']);
        dotclear()->behavior()->add('adminPopupPosts', [$this, 'adminPopupPosts']);
        dotclear()->behavior()->add('adminMediaURL', [$this, 'adminMediaURL']);
        dotclear()->behavior()->add('adminPageHTTPHeaderCSP', [$this, 'adminPageHTTPHeaderCSP']);
    }

    /**
     * adminPostEditor add javascript to the DOM to load ckeditor depending on context
     *
     * @param      string  $editor   The wanted editor
     * @param      string  $context  The page context (post,page,comment,event,...)
     * @param      array   $tags     The array of ids to inject editor
     * @param      string  $syntax   The wanted syntax (wiki,markdown,...)
     *
     * @return     string
     */
    public function adminPostEditor(string $editor = '', string $context = '', array $tags = [], string $syntax = 'xhtml'): string
    {
        if (empty($editor) || 'CKEditor' != $editor || 'xhtml' != $syntax) {
            return '';
        }

        $config_js = dotclear()->adminurl()->get('admin.plugin.CKEditorPost', [], '&');
        if (!empty($context)) {
            $config_js .= '&context=' . $context;
        }

        $res =
        dotclear()->resource()->json('ck_editor_ctx', [
            'ckeditor_context'      => $context,
            'ckeditor_tags_context' => [$context => $tags],
            'admin_base_url'        => dotclear()->adminurl()->root(),
            'base_url'              => dotclear()->blog()->host,
            'dcckeditor_plugin_url' => dotclear()->adminurl()->root() .'?df=Plugin/CKEditor/Admin/resources', //!
            'user_language'         => dotclear()->user()->getInfo('user_lang'),
        ]) .
        dotclear()->resource()->json('ck_editor_var', [
            'CKEDITOR_BASEPATH' => dotclear()->adminurl()->root() .'?df=Plugin/CKEditor/Admin/resources/js/ckeditor/', //!
        ]) .
        dotclear()->resource()->json('ck_editor_msg', [
            'img_select_title'     => __('Media chooser'),
            'img_select_accesskey' => __('m'),
            'post_link_title'      => __('Link to an entry'),
            'link_title'           => __('Link'),
            'link_accesskey'       => __('l'),
            'img_title'            => __('External image'),
            'url_cannot_be_empty'  => __('URL field cannot be empty.'),
        ]) .
        dotclear()->resource()->load('_post_editor.js', 'Plugin', 'CKEditor') .
        dotclear()->resource()->load('ckeditor/ckeditor.js', 'Plugin', 'CKEditor') .
        dotclear()->resource()->load('ckeditor/adapters/jquery.js', 'Plugin', 'CKEditor') .
        dotclear()->resource()->js($config_js);

        return $res;
    }

    public function adminPopupMedia(string $editor = ''): string
    {
        return 'CKEditor' != $editor ? '' : dotclear()->resource()->load('popup_media.js', 'Plugin', 'CKEditor');
    }

    public function adminPopupLink(string $editor = ''): string
    {
        return 'CKEditor' != $editor ? '' : dotclear()->resource()->load('popup_link.js', 'Plugin', 'CKEditor');
    }

    public function adminPopupPosts(string $editor = ''): string
    {
        return 'CKEditor' != $editor ? '' : dotclear()->resource()->load('popup_posts.js', 'Plugin', 'CKEditor');
    }

    public function adminMediaURLParams(ArrayObject $p): void
    {
        if (!empty($_GET['editor'])) {
            $p['editor'] = Html::sanitizeURL($_GET['editor']);
        }
    }

    public function adminPageHTTPHeaderCSP(ArrayObject $csp): void
    {
        // add 'unsafe-inline' for CSS, add 'unsafe-eval' for scripts as far as CKEditor 4.x is used
        if (!str_contains($csp['style-src'], 'unsafe-inline')) {
            $csp['style-src'] .= " 'unsafe-inline'";
        }
        if (!str_contains($csp['script-src'], 'unsafe-inline')) {
            $csp['script-src'] .= " 'unsafe-inline'";
        }
        if (!str_contains($csp['script-src'], 'unsafe-eval')) {
            $csp['script-src'] .= " 'unsafe-eval'";
        }
    }
}
