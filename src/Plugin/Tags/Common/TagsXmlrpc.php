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

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}


class TagsXmlrpc
{
    public static function initTags()
    {
        dotclear()->behavior()->add('xmlrpcGetPostInfo', [__CLASS__, 'getPostInfo']);
        dotclear()->behavior()->add('xmlrpcAfterNewPost', [__CLASS__, 'editPost']);
        dotclear()->behavior()->add('xmlrpcAfterEditPost', [__CLASS__, 'editPost']);
    }

    public static function getPostInfo($x, $type, $res)
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
    public static function editPost($x, $post_id, $cur, $content, $struct, $publish)
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
