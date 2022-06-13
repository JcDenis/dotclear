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
class PostMedia
{
    /**
     * Get media items attached to a blog post.
     *
     * @param array $params The parameters
     *
     * @return Record The post media
     */
    public function getPostMedia(array $params = []): Record
    {
        $join = new JoinStatement();
        $join->type('INNER');
        $join->from(App::core()->prefix() . 'post_media PM');
        $join->on('M.media_id = PM.media_id');

        $sql = new SelectStatement();
        $sql->columns([
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
        $sql->from(App::core()->prefix() . 'media M');
        $sql->join($join->statement());

        if (!empty($params['columns']) && is_array($params['columns'])) {
            $sql->columns($params['columns']);
        }

        if (!empty($params['from'])) {
            $sql->from($params['from']);
        }

        if (isset($params['link_type'])) {
            $sql->where('PM.link_type' . $sql->in($params['link_type']));
        } else {
            $sql->where('PM.link_type = ' . $sql->quote('attachment'));
        }

        if (isset($params['post_id'])) {
            $sql->and('PM.post_id' . $sql->in($params['post_id']));
        }
        if (isset($params['media_id'])) {
            $sql->and('M.media_id' . $sql->in($params['media_id']));
        }
        if (isset($params['media_path'])) {
            $sql->and('M.media_path' . $sql->in($params['media_path']));
        }

        if (isset($params['sql'])) {
            $sql->sql($params['sql']);
        }

        return $sql->select();
    }

    /**
     * Attache a media to a post.
     *
     * @param int    $post_id   The post identifier
     * @param int    $media_id  The media identifier
     * @param string $link_type The link type (default: attachment)
     */
    public function addPostMedia(int $post_id, int $media_id, string $link_type = 'attachment'): void
    {
        $f = $this->getPostMedia(['post_id' => $post_id, 'media_id' => $media_id, 'link_type' => $link_type]);

        if (!$f->isEmpty()) {
            return;
        }

        $sql = new InsertStatement();
        $sql->from(App::core()->prefix() . 'post_media');
        $sql->columns([
            'post_id',
            'media_id',
            'link_type',
        ]);
        $sql->line([[
            $post_id,
            $media_id,
            $sql->quote($link_type),
        ]]);
        $sql->insert();

        App::core()->blog()->triggerBlog();
    }

    /**
     * Detache a media from a post.
     *
     * @param int         $post_id   The post identifier
     * @param int         $media_id  The media identifier
     * @param null|string $link_type The link type
     */
    public function removePostMedia(int $post_id, int $media_id, ?string $link_type = null): void
    {
        $sql = new DeleteStatement();
        $sql->from(App::core()->prefix() . 'post_media');
        $sql->where('post_id = ' . $post_id);
        $sql->and('media_id = ' . $media_id);

        if (null != $link_type) {
            $sql->and('link_type = ' . $sql->quote($link_type, true));
        }
        $sql->delete();

        App::core()->blog()->triggerBlog();
    }
}
