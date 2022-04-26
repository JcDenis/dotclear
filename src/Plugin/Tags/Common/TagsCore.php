<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Common;

// Dotclear\Plugin\Tags\Common\TagsCore
use Dotclear\App;
use Dotclear\Core\Wiki\Wiki2xhtml;
use Dotclear\Helper\Html\Html;

/**
 * Core methods of plugin Tags.
 *
 * @ingroup  Plugin Tags
 */
class TagsCore
{
    public function __construct()
    {
        App::core()->behavior()->add('coreInitWikiPost', function (Wiki2xhtml $wiki2xhtml): void {
            $wiki2xhtml->registerFunction('url:tag', [$this, 'wiki2xhtmlTag']);
        });
    }

    public function wiki2xhtmlTag(string $url, string $content): array
    {
        $url = substr($url, 4);
        if (str_starts_with($content, 'tag:')) {
            $content = substr($content, 4);
        }

        $tag_url        = Html::stripHostURL(App::core()->blog()->getURLFor('tag'));
        $res['url']     = $tag_url . '/' . rawurlencode(App::core()->meta()::sanitizeMetaID($url));
        $res['content'] = $content;

        return $res;
    }
}
