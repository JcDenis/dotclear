<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Comments;

// Dotclear\Core\Comments\CommentsParam
use Dotclear\Database\Param;

/**
 * Comments query parameter helper.
 *
 * @ingroup  Core Comment Param
 */
final class CommentsParam extends Param
{
    /**
     * Don't retrieve comment content.
     *
     * @return bool True not to get content
     */
    public function no_content(): bool
    {
        return $this->getCleanedValue('no_content', 'bool', false);
    }

    /**
     * Get only entries with given type(s) (default no type).
     *
     * @return array<int,string> The entries(s) type(s)
     */
    public function post_type(): array
    {
        return $this->getCleanedValues('post_type', 'string');
    }

    /**
     * Get comments belonging to given post_id.
     *
     * @return null|int The post id
     */
    public function post_id(): ?int
    {
        return $this->getCleanedValue('post_id', 'int');
    }

    /**
     * Get comments belonging to entries of given category ID.
     *
     * @return null|int The category id
     */
    public function cat_id(): ?int
    {
        return $this->getCleanedValue('cat_id', 'int');
    }

    /**
     * Get comment with given ID (or IDs).
     *
     * @return array<int,int> The comment(s) id(s)
     */
    public function comment_id(): array
    {
        return $this->getCleanedValues('comment_id', 'int');
    }

    /**
     * Get comments with given comment site.
     *
     * @return null|string The comment site
     */
    public function comment_site(): ?string
    {
        return $this->getCleanedValue('comment_site', 'string');
    }

    /**
     * Get comments with given comment email.
     *
     * @return null|string The comment emai
     */
    public function comment_email(): ?string
    {
        return $this->getCleanedValue('comment_email', 'string');
    }

    /**
     * Get comments with given comment status.
     *
     * @return null|int The comment status
     */
    public function comment_status(): ?int
    {
        return $this->getCleanedValue('comment_status', 'int');
    }

    /**
     * Get comments without given comment status.
     *
     * @return null|int The comment status
     */
    public function comment_status_not(): ?int
    {
        return $this->getCleanedValue('comment_status_not', 'int');
    }

    /**
     * Get only comments (0) or trackbacks (1).
     *
     * @return null|int The comment trackback usage
     */
    public function comment_trackback(): ?int
    {
        return in_array($this->getCleanedValue('comment_trackback', 'int', 2), [0, 1]) ? $this->getCleanedValue('comment_trackback', 'int') : null;
    }

    /**
     * Get comments with given IP address.
     *
     * @return null|string The comment IP
     */
    public function comment_ip(): ?string
    {
        return $this->getCleanedValue('comment_ip', 'string');
    }

    /**
     * Search comments by author.
     *
     * @return null|string The comment author search
     */
    public function q_author(): ?string
    {
        return $this->getCleanedValue('q_author', 'string');
    }

    /**
     * Get search parameter.
     *
     * @return null|string The search parameter
     */
    public function search(): ?string
    {
        return $this->getCleanedValue('search', 'string');
    }
}
