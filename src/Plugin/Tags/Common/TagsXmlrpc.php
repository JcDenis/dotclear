<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Common;

// Dotclear\Plugin\Tags\Common\TagsXmlrpc
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Core\Xmlrpc\Xmlrpc;

/**
 * XML-RPC methods of plugin Tags.
 *
 * @ingroup  Plugin Tags Xmlrpc
 */
class TagsXmlrpc
{
    public function __construct()
    {
        App::core()->behavior()->add('xmlrpcGetPostInfo', [$this, 'getPostInfo']);
        App::core()->behavior()->add('xmlrpcAfterNewPost', [$this, 'editPost']);
        App::core()->behavior()->add('xmlrpcAfterEditPost', [$this, 'editPost']);
    }

    public function getPostInfo(Xmlrpc $x, string $type, array $res): void
    {
        $res = &$res[0];

        $rs = App::core()->meta()->getMetadata([
            'meta_type' => 'tag',
            'post_id'   => $res['postid'], ]);

        $m = [];
        while ($rs->fetch()) {
            $m[] = $rs->meta_id;
        }

        $res['mt_keywords'] = implode(', ', $m);
    }

    // Same function for newPost and editPost
    public function editPost(Xmlrpc $x, int $post_id, Cursor $cur, string $content, array $struct, int $publish): void
    {
        // Check if we have mt_keywords in struct
        if (isset($struct['mt_keywords'])) {
            App::core()->meta()->delPostMeta($post_id, 'tag');

            foreach (App::core()->meta()->splitMetaValues($struct['mt_keywords']) as $m) {
                App::core()->meta()->setPostMeta($post_id, 'tag', $m);
            }
        }
    }
}
