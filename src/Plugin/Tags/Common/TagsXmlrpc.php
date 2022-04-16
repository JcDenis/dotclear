<?php
/**
 * @class Dotclear\Plugin\Tags\Common\TagsXmlrpc
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

use Dotclear\Database\Cursor;
use Dotclear\Core\Xmlrpc\Xmlrpc;

class TagsXmlrpc
{
    public function __construct()
    {
        dotclear()->behavior()->add('xmlrpcGetPostInfo', [$this, 'getPostInfo']);
        dotclear()->behavior()->add('xmlrpcAfterNewPost', [$this, 'editPost']);
        dotclear()->behavior()->add('xmlrpcAfterEditPost', [$this, 'editPost']);
    }

    public function getPostInfo(Xmlrpc $x, string $type, array $res): void
    {
        $res = &$res[0];

        $rs = dotclear()->meta()->getMetadata([
            'meta_type' => 'tag',
            'post_id'   => $res['postid'], ]);

        $m = [];
        while ($rs->fetch()) {
            $m[] = $rs->meta_id;
        }

        $res['mt_keywords'] = implode(', ', $m);
    }

    # Same function for newPost and editPost
    public function editPost(Xmlrpc $x, int $post_id, Cursor $cur, string $content, array $struct, int $publish): void
    {
        # Check if we have mt_keywords in struct
        if (isset($struct['mt_keywords'])) {
            dotclear()->meta()->delPostMeta($post_id, 'tag');

            foreach (dotclear()->meta()->splitMetaValues($struct['mt_keywords']) as $m) {
                dotclear()->meta()->setPostMeta($post_id, 'tag', $m);
            }
        }
    }
}
