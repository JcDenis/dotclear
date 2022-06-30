<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media;

// Dotclear\Core\Media\PostMedia
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;

/**
 * Post media handling.
 *
 * This class handles Dotclear media items.
 *
 * @ingroup  Core Media Post
 */
final class PostMedia
{
    /**
     * Get media items attached to a blog post.
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return Record The post media
     */
    public function getPostMedia(?Param $param = null, ?SelectStatement $sql = null): Record
    {
        $params = new Param($param);
        $query  = $sql ? clone $sql : new SelectStatement();

        $join = new JoinStatement();
        $join->type('INNER');
        $join->from(App::core()->getPrefix() . 'post_media PM');
        $join->on('M.media_id = PM.media_id');

        $query->columns([
            'M.media_file',
            'M.media_id',
            'M.media_path',
            'M.media_title',
            'M.media_meta',
            'M.media_dt',
            'M.media_creadt',
            'M.media_upddt',
            'M.media_private',
            'M.user_id',
            'PM.post_id',
        ]);
        $query->from(App::core()->getPrefix() . 'media M');
        $query->join($join->statement());

        if (!empty($params->columns())) {
            $query->columns($params->columns());
        }

        if (!empty($params->from())) {
            $query->from($params->from());
        }

        if ($params->isset('link_type')) {
            $query->where('PM.link_type' . $query->in($params->get('link_type')));
        } else {
            $query->where('PM.link_type = ' . $query->quote('attachment'));
        }

        if ($params->isset('post_id')) {
            $query->and('PM.post_id' . $query->in($params->get('post_id')));
        }
        if ($params->isset('media_id')) {
            $query->and('M.media_id' . $query->in($params->get('media_id')));
        }
        if ($params->isset('media_path')) {
            $query->and('M.media_path' . $query->in($params->get('media_path')));
        }

        if (!empty($params->sql())) {
            $query->sql($params->sql());
        }

        return $query->select();
    }

    /**
     * Attache a media to a post.
     *
     * @param int    $post  The post ID
     * @param int    $media The media ID
     * @param string $type  The link type (default: attachment)
     */
    public function addPostMedia(int $post, int $media, string $type = 'attachment'): void
    {
        $param = new Param();
        $param->set('post_id', $post);
        $param->set('media_id', $media);
        $param->set('link_type', $type);

        $items = $this->getPostMedia(param: $param);
        if (!$items->isEmpty()) {
            return;
        }

        $sql = new InsertStatement();
        $sql->from(App::core()->getPrefix() . 'post_media');
        $sql->columns([
            'post_id',
            'media_id',
            'link_type',
        ]);
        $sql->line([[
            $post,
            $media,
            $sql->quote($type),
        ]]);
        $sql->insert();

        App::core()->blog()->triggerBlog();
    }

    /**
     * Detache a media from a post.
     *
     * @param int         $post  The post ID
     * @param int         $media The media ID
     * @param null|string $type  The link type
     */
    public function removePostMedia(int $post, int $media, ?string $type = null): void
    {
        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'post_media');
        $sql->where('post_id = ' . $post);
        $sql->and('media_id = ' . $media);

        if (null != $type) {
            $sql->and('link_type = ' . $sql->quote($type));
        }
        $sql->delete();

        App::core()->blog()->triggerBlog();
    }
}
