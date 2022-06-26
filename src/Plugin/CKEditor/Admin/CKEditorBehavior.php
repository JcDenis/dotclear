<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\CKEditor\Admin;

// Dotclear\Plugin\CKEditor\Admin\CKEditorBehavior
use Dotclear\App;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\NamedStrings;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\FilterStack;

/**
 * Admin behaviors for plugin CKEditor.
 *
 * @ingroup  Plugin CKEditor Behavior
 */
class CKEditorBehavior
{
    public function __construct()
    {
        App::core()->behavior('adminBeforeGetPostEditorHead')->add([$this, 'adminBeforeGetPostEditorHead']);
        App::core()->behavior('adminPopupMedia')->add([$this, 'adminPopupMedia']);
        App::core()->behavior('adminPopupLink')->add([$this, 'adminPopupLink']);
        App::core()->behavior('adminPopupPosts')->add([$this, 'adminPopupPosts']);
        App::core()->behavior('adminFiltersAddFilters')->add([$this, 'adminFiltersAddFilters']);
        App::core()->behavior('adminBeforeGetPageCSPHeaders')->add([$this, 'adminBeforeGetPageCSPHeaders']);
    }

    /**
     * adminBeforeGetPostEditorHead add javascript to the DOM to load ckeditor depending on context.
     *
     * @param string $editor  The wanted editor
     * @param string $context The page context (post,page,comment,event,...)
     * @param array  $tags    The array of ids to inject editor
     * @param string $syntax  The wanted syntax (wiki,markdown,...)
     */
    public function adminBeforeGetPostEditorHead(string $editor = '', string $context = '', array $tags = [], string $syntax = 'xhtml'): string
    {
        if (empty($editor) || 'CKEditor' != $editor || 'xhtml' != $syntax) {
            return '';
        }

        $config_js = App::core()->adminurl()->get('admin.plugin.CKEditorPost', [], '&');
        if (!empty($context)) {
            $config_js .= '&context=' . $context;
        }

        return App::core()->resource()->json('ck_editor_ctx', [
            'ckeditor_context'      => $context,
            'ckeditor_tags_context' => [$context => $tags],
            'admin_base_url'        => App::core()->adminurl()->root(),
            'base_url'              => App::core()->blog()->host,
            'dcckeditor_plugin_url' => App::core()->adminurl()->root() . '?df=Plugin/CKEditor/Admin/resources', // !
            'user_language'         => App::core()->user()->getInfo('user_lang'),
        ]) .
        App::core()->resource()->json('ck_editor_var', [
            'CKEDITOR_BASEPATH' => App::core()->adminurl()->root() . '?df=Plugin/CKEditor/Admin/resources/js/ckeditor/', // !
        ]) .
        App::core()->resource()->json('ck_editor_msg', [
            'img_select_title'     => __('Media chooser'),
            'img_select_accesskey' => __('m'),
            'post_link_title'      => __('Link to an entry'),
            'link_title'           => __('Link'),
            'link_accesskey'       => __('l'),
            'img_title'            => __('External image'),
            'url_cannot_be_empty'  => __('URL field cannot be empty.'),
        ]) .
        App::core()->resource()->load('_post_editor.js', 'Plugin', 'CKEditor') .
        App::core()->resource()->load('ckeditor/ckeditor.js', 'Plugin', 'CKEditor') .
        App::core()->resource()->load('ckeditor/adapters/jquery.js', 'Plugin', 'CKEditor') .
        App::core()->resource()->js($config_js);
    }

    public function adminPopupMedia(string $editor = ''): string
    {
        return 'CKEditor' != $editor ? '' : App::core()->resource()->load('popup_media.js', 'Plugin', 'CKEditor');
    }

    public function adminPopupLink(string $editor = ''): string
    {
        return 'CKEditor' != $editor ? '' : App::core()->resource()->load('popup_link.js', 'Plugin', 'CKEditor');
    }

    public function adminPopupPosts(string $editor = ''): string
    {
        return 'CKEditor' != $editor ? '' : App::core()->resource()->load('popup_posts.js', 'Plugin', 'CKEditor');
    }

    public function adminFiltersAddFilters(string $id, FilterStack $filter_stack): void
    {
        if ('media' == $id && !GPC::get()->empty('editor')) {
            // Add hidden filter to pass editor to filters URLs
            $filter_stack->addFilter(filter: new Filter(id: 'editor', value: Html::sanitizeURL(GPC::get()->string('editor'))));
        }
    }

    public function adminBeforeGetPageCSPHeaders(NamedStrings $csp): void
    {
        // add 'unsafe-inline' for CSS, add 'unsafe-eval' for scripts as far as CKEditor 4.x is used
        if (!str_contains($csp->get('style-src'), 'unsafe-inline')) {
            $csp->concat('style-src', " 'unsafe-inline'");
        }
        if (!str_contains($csp->get('script-src'), 'unsafe-inline')) {
            $csp->concat('script-src', " 'unsafe-inline'");
        }
        if (!str_contains($csp->get('script-src'), 'unsafe-eval')) {
            $csp->concat('script-src', " 'unsafe-eval'");
        }
    }
}
