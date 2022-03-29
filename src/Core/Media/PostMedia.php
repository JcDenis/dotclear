<?php
/**
 * @class Dotclear\Core\Media\PostMedia
 * @brief Dotclear core post media class
 *
 * This class handles Dotclear media items.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media;

use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;

class PostMedia
{
    /** @var    string  Post media table name */
    protected $table = 'post_media';

    /**
     * Returns media items attached to a blog post.
     *
     * @param   array   $params     The parameters
     *
     * @return  Record              The post media.
     */
    public function getPostMedia(array $params = []): Record
    {
        $sql = new SelectStatement('dcPostMediaGetPostMedia');
        $sql
            ->columns([
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

        if (!empty($params['columns']) && is_array($params['columns'])) {
            $sql->columns($params['columns']);
        }

        $sql
            ->from(dotclear()->prefix . 'media M')
            ->join(
                (new JoinStatement('dcPostMediaGetPostMedia'))
                ->type('INNER')
                ->from(dotclear()->prefix . $this->table . ' PM')
                ->on('M.media_id = PM.media_id')
                ->statement()
            );

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
     * Attaches a media to a post.
     *
     * @param   int     $post_id    The post identifier
     * @param   int     $media_id   The media identifier
     * @param   string  $link_type  The link type (default: attachment)
     */
    public function addPostMedia(int $post_id, int $media_id, string $link_type = 'attachment'): void
    {
        $f = $this->getPostMedia(['post_id' => $post_id, 'media_id' => $media_id, 'link_type' => $link_type]);

        if (!$f->isEmpty()) {
            return;
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . $this->table);
        $cur->setField('post_id', $post_id);
        $cur->setField('media_id', $media_id);
        $cur->setField('link_type', $link_type);

        $cur->insert();
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Detaches a media from a post.
     *
     * @param   int             $post_id    The post identifier
     * @param   int             $media_id   The media identifier
     * @param   string|null     $link_type  The link type
     */
    public function removePostMedia(int $post_id, int $media_id, ?string $link_type = null): void
    {
        $sql = new DeleteStatement('dcPostMediaRemovePostMedia');
        $sql
            ->from(dotclear()->prefix . $this->table)
            ->where('post_id = ' . $post_id)
            ->and('media_id = ' . $media_id);

        if (null != $link_type) {
            $sql->and('link_type = ' . $sql->quote($link_type, true));
        }
        $sql->delete();

        dotclear()->blog()->triggerBlog();
    }
}
