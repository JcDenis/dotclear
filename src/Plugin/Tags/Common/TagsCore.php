<?php
/**
 * @class Dotclear\Plugin\Tags\Common\TagsCore
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Common;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class TagsCore
{
    public static function initTags()
    {
        dotclear()->behavior()->add('coreInitWikiPost', function ($wiki2xhtml) {
            $wiki2xhtml->registerFunction('url:tag', [__CLASS__, 'wiki2xhtmlTag']);
        });
    }

    public static function wiki2xhtmlTag($url, $content)
    {
        $url = substr($url, 4);
        if (strpos($content, 'tag:') === 0) {
            $content = substr($content, 4);
        }

        $tag_url        = Html::stripHostURL(dotclear()->blog()->url . dotclear()->url()->getURLFor('tag'));
        $res['url']     = $tag_url . '/' . rawurlencode(dotclear()->meta()::sanitizeMetaID($url));
        $res['content'] = $content;

        return $res;
    }
}
