<?php
/**
 * @class Dotclear\Core\PostMedia
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

namespace Dotclear\Core;

use Dotclear\Core\Core;

use Dotclear\Database\Connection;
use Dotclear\Database\Record;

class PostMedia
{
    /** @var Core           Core instance */
    protected $core;

    /** @var Connection     Connection instance */
    protected $con;

    /** @var string         post media table name */
    protected $table;

    /**
     * Constructs a new instance.
     *
     * @param      Core  $core   The core
     */
    public function __construct(Core $core)
    {
        $this->core  = $core;
        $this->con   = $core->con;
        $this->table = $this->core->prefix . 'post_media';
    }

    /**
     * Returns media items attached to a blog post.
     *
     * @param      array   $params  The parameters
     *
     * @return     Record  The post media.
     */
    public function getPostMedia(array $params = []): Record
    {
        $strReq = 'SELECT M.media_file, M.media_id, M.media_path, M.media_title, M.media_meta, M.media_dt, ' .
            'M.media_creadt, M.media_upddt, M.media_private, M.user_id, PM.post_id ';

        if (!empty($params['columns']) && is_array($params['columns'])) {
            $strReq .= implode(', ', $params['columns']) . ', ';
        }

        $strReq .= 'FROM ' . $this->core->prefix . 'media M ' .
        'INNER JOIN ' . $this->table . ' PM ON (M.media_id = PM.media_id) ';

        if (!empty($params['from'])) {
            $strReq .= $params['from'] . ' ';
        }

        $where = [];
        if (isset($params['post_id'])) {
            $where[] = 'PM.post_id ' . $this->con->in($params['post_id']);
        }
        if (isset($params['media_id'])) {
            $where[] = 'M.media_id ' . $this->con->in($params['media_id']);
        }
        if (isset($params['media_path'])) {
            $where[] = 'M.media_path ' . $this->con->in($params['media_path']);
        }
        if (isset($params['link_type'])) {
            $where[] = 'PM.link_type ' . $this->con->in($params['link_type']);
        } else {
            $where[] = "PM.link_type='attachment'";
        }

        $strReq .= 'WHERE ' . join('AND ', $where) . ' ';

        if (isset($params['sql'])) {
            $strReq .= $params['sql'];
        }

        $rs = $this->con->select($strReq);

        return $rs;
    }

    /**
     * Attaches a media to a post.
     *
     * @param      int   $post_id    The post identifier
     * @param      int   $media_id   The media identifier
     * @param      string  $link_type  The link type (default: attachment)
     */
    public function addPostMedia(int $post_id, int $media_id, string $link_type = 'attachment'): void
    {
        $post_id  = (int) $post_id;
        $media_id = (int) $media_id;

        $f = $this->getPostMedia(['post_id' => $post_id, 'media_id' => $media_id, 'link_type' => $link_type]);

        if (!$f->isEmpty()) {
            return;
        }

        $cur            = $this->con->openCursor($this->table);
        $cur->post_id   = $post_id;
        $cur->media_id  = $media_id;
        $cur->link_type = $link_type;

        $cur->insert();
        $this->core->blog->triggerBlog();
    }

    /**
     * Detaches a media from a post.
     *
     * @param      mixed   $post_id    The post identifier
     * @param      mixed   $media_id   The media identifier
     * @param      mixed   $link_type  The link type
     */
    public function removePostMedia($post_id, $media_id, $link_type = null): void
    {
        $post_id  = (integer) $post_id;
        $media_id = (integer) $media_id;

        $strReq = 'DELETE FROM ' . $this->table . ' ' .
            'WHERE post_id = ' . $post_id . ' ' .
            'AND media_id = ' . $media_id . ' ';
        if ($link_type != null) {
            $strReq .= "AND link_type = '" . $this->con->escape($link_type) . "'";
        }
        $this->con->execute($strReq);
        $this->core->blog->triggerBlog();
    }
}
