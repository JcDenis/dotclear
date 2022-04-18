<?php
/**
 * @note Dotclear\Plugin\Tags\Common\TagsCore
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Common;

use Dotclear\Core\Wiki\Wiki2xhtml;
use Dotclear\Helper\Html\Html;

class TagsCore
{
    public function __construct()
    {
        dotclear()->behavior()->add('coreInitWikiPost', function (Wiki2xhtml $wiki2xhtml): void {
            $wiki2xhtml->registerFunction('url:tag', [$this, 'wiki2xhtmlTag']);
        });
    }

    public function wiki2xhtmlTag(string $url, string $content): array
    {
        $url = substr($url, 4);
        if (str_starts_with($content, 'tag:')) {
            $content = substr($content, 4);
        }

        $tag_url        = Html::stripHostURL(dotclear()->blog()->getURLFor('tag'));
        $res['url']     = $tag_url . '/' . rawurlencode(dotclear()->meta()::sanitizeMetaID($url));
        $res['content'] = $content;

        return $res;
    }
}
