<?php
/**
 * @class Dotclear\Plugin\CKEditor\Admin\Behavior
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

use Dotclear\Core\Utils;
use Dotclear\Html\Html;

class Behavior
{
    protected static $config_url = '?handler=admin.plugin.CKEditorPost';

    /**
     * adminPostEditor add javascript to the DOM to load ckeditor depending on context
     *
     * @param      string  $editor   The wanted editor
     * @param      string  $context  The page context (post,page,comment,event,...)
     * @param      array   $tags     The array of ids to inject editor
     * @param      string  $syntax   The wanted syntax (wiki,markdown,...)
     *
     * @return     mixed
     */
    public static function adminPostEditor($editor = '', $context = '', array $tags = [], $syntax = 'xhtml')
    {
        if (empty($editor) || $editor != 'CKEditor' || $syntax != 'xhtml') {
            return;
        }

        $config_js = self::$config_url;
        if (!empty($context)) {
            $config_js .= '&context=' . $context;
        }

        $res = Utils::jsJson('ck_editor_ctx', [
            'ckeditor_context'      => $context,
            'ckeditor_tags_context' => [$context => $tags],
            'admin_base_url'        => dotclear()->config()->admin_url .'?mf=Plugin/CKEditor/files/',
            'base_url'              => dotclear()->blog()->host,
            'dcckeditor_plugin_url' => dotclear()->config()->admin_url .'?mf=Plugin/CKEditor/files/',
            'user_language'         => dotclear()->user()->getInfo('user_lang'),
        ]) .
        Utils::jsJson('ck_editor_var', [
            'CKEDITOR_BASEPATH' => dotclear()->config()->admin_url .'?mf=Plugin/CKEditor/files/js/ckeditor/',
        ]) .
        Utils::jsJson('ck_editor_msg', [
            'img_select_title'     => __('Media chooser'),
            'img_select_accesskey' => __('m'),
            'post_link_title'      => __('Link to an entry'),
            'link_title'           => __('Link'),
            'link_accesskey'       => __('l'),
            'img_title'            => __('External image'),
            'url_cannot_be_empty'  => __('URL field cannot be empty.'),
        ]) .
        Utils::jsLoad('?mf=Plugin/CKEditor/files/js/_post_editor.js') .
        Utils::jsLoad('?mf=Plugin/CKEditor/files/js/ckeditor/ckeditor.js') .
        Utils::jsLoad('?mf=Plugin/CKEditor/files/js/ckeditor/adapters/jquery.js') .
        Utils::jsLoad($config_js);

        return $res;
    }

    public static function adminPopupMedia($editor = '')
    {
        if (empty($editor) || $editor != 'dcCKEditor') {
            return;
        }

        return Utils::jsLoad('?mf=Plugin/CKEditor/files/js/popup_media.js');
    }

    public static function adminPopupLink($editor = '')
    {
        if (empty($editor) || $editor != 'dcCKEditor') {
            return;
        }

        return Utils::jsLoad('?mf=Plugin/CKEditor/files/js/popup_link.js');
    }

    public static function adminPopupPosts($editor = '')
    {
        if (empty($editor) || $editor != 'dcCKEditor') {
            return;
        }

        return Utils::jsLoad('?mf=Plugin/CKEditor/files/js/popup_posts.js');
    }

    public static function adminMediaURLParams($p)
    {
        if (!empty($_GET['editor'])) {
            $p['editor'] = Html::sanitizeURL($_GET['editor']);
        }
    }

    public static function adminPageHTTPHeaderCSP($csp)
    {
        // add 'unsafe-inline' for CSS, add 'unsafe-eval' for scripts as far as CKEditor 4.x is used
        if (strpos($csp['style-src'], 'unsafe-inline') === false) {
            $csp['style-src'] .= " 'unsafe-inline'";
        }
        if (strpos($csp['script-src'], 'unsafe-inline') === false) {
            $csp['script-src'] .= " 'unsafe-inline'";
        }
        if (strpos($csp['script-src'], 'unsafe-eval') === false) {
            $csp['script-src'] .= " 'unsafe-eval'";
        }
    }
}
