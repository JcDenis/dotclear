<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Meta;

use ArrayObject;
use Dotclear\Database\Record;
use Dotclear\Database\StaticRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Lexical;
use Dotclear\Helper\Text;

/**
 * Meta handling methods.
 *
 * \@note Dotclear\Core\Meta\Meta
 *
 * @ingroup  Core Meta
 */
class Meta
{
    /** @var string Meta table name */
    private $table = 'meta';

    /**
     * Splits up comma-separated values into an array of
     * unique, URL-proof metadata values.
     *
     * @param string $str Comma-separated metadata
     *
     * @return array The array of sanitized metadata
     */
    public function splitMetaValues(string $str): array
    {
        $res = [];
        foreach (explode(',', $str) as $i => $tag) {
            $tag = trim($tag);
            $tag = $this->sanitizeMetaID($tag);

            if (false != $tag) {
                $res[$i] = $tag;
            }
        }

        return array_unique($res);
    }

    /**
     * Make a metadata ID URL-proof.
     *
     * @param string $str The metadata ID
     *
     * @return string The sanitized metadata ID
     */
    public static function sanitizeMetaID(string $str): string
    {
        return Text::tidyURL($str, false, true);
    }

    /**
     * Converts serialized metadata (for instance in dc_post post_meta)
     * into a meta array.
     *
     * @param string $str The serialized metadata
     *
     * @return array the meta array
     */
    public function getMetaArray(string $str): array
    {
        $meta = @unserialize($str);

        return is_array($meta) ? $meta : [];
    }

    /**
     * Converts serialized metadata (for instance in dc_post post_meta)
     * into a comma-separated meta list for a given type.
     *
     * @param string $str  The serialized metadata
     * @param string $type The meta type to retrieve metaIDs from
     *
     * @return string the comma-separated list of meta
     */
    public function getMetaStr(string $str, string $type): string
    {
        $meta = $this->getMetaArray($str);

        return isset($meta[$type]) ? implode(', ', $meta[$type]) : '';
    }

    /**
     * Converts serialized metadata (for instance in dc_post post_meta)
     * into a "fetchable" metadata record.
     *
     * @param string $str  The serialized metadata
     * @param string $type The meta type to retrieve metaIDs from
     *
     * @return StaticRecord the meta recordset
     */
    public function getMetaRecordset(string $str, string $type): StaticRecord
    {
        $meta = $this->getMetaArray($str);
        $data = [];

        if (isset($meta[$type])) {
            foreach ($meta[$type] as $v) {
                $data[] = [
                    'meta_id'       => $v,
                    'meta_type'     => $type,
                    'meta_id_lower' => mb_strtolower($v),
                    'count'         => 0,
                    'percent'       => 0,
                    'roundpercent'  => 0,
                ];
            }
        }

        return StaticRecord::newFromArray($data);
    }

    /**
     * Checks whether the current user is allowed to change post meta
     * An exception is thrown if user is not allowed.
     *
     * @param int $post_id The post identifier
     *
     * @throws CoreException
     */
    private function checkPermissionsOnPost(int $post_id): void
    {
        if (!dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry status'));
        }

        // If user can only publish, we need to check the post's owner
        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $sql = new SelectStatement(__METHOD__);
            $rs  = $sql
                ->from(dotclear()->prefix . 'post')
                ->column('post_id')
                ->where('post_id = ' . $post_id)
                ->and('user_id = ' . $sql->quote(dotclear()->user()->userID()))
                ->select()
            ;

            if ($rs->isEmpty()) {
                throw new CoreException(__('You are not allowed to change this entry status'));
            }
        }
    }

    /**
     * Updates serialized post_meta information with dc_meta table information.
     *
     * @param int $post_id The post identifier
     */
    private function updatePostMeta(int $post_id): void
    {
        $rs = SelectStatement::init(__METHOD__)
            ->from(dotclear()->prefix . $this->table)
            ->columns([
                'meta_id',
                'meta_type',
            ])
            ->where('post_id = ' . $post_id)
            ->select()
        ;

        $meta = [];
        while ($rs->fetch()) {
            $meta[$rs->f('meta_type')][] = $rs->f('meta_id');
        }

        $sql = new UpdateStatement(__METHOD__);
        $sql->set('post_meta = ' . $sql->quote(serialize($meta)))
            ->from(dotclear()->prefix . 'post')
            ->where('post_id = ' . $post_id)
            ->update()
        ;

        dotclear()->blog()->triggerBlog();
    }

    /**
     * Retrieves posts corresponding to given meta criteria.
     * <b>$params</b> is an array taking the following optional parameters:
     * - meta_id : get posts having meta id
     * - meta_type : get posts having meta type.
     *
     * @param array                $params     The parameters
     * @param bool                 $count_only Only count results
     * @param null|SelectStatement $sql        Optional dcSqlStatement instance
     *
     * @return null|Record the resulting posts record
     */
    public function getPostsByMeta(array|ArrayObject $params = [], bool $count_only = false, ?SelectStatement $sql = null): ?Record
    {
        if (!isset($params['meta_id'])) {
            return null;
        }

        if (!$sql) {
            $sql = new SelectStatement(__METHOD__);
        }

        $sql
            ->from(dotclear()->prefix . $this->table . ' META')
            ->and('META.post_id = P.post_id')
            ->and('META.meta_id = ' . $sql->quote($params['meta_id']))
        ;

        if (!empty($params['meta_type'])) {
            $sql->and('META.meta_type = ' . $sql->quote($params['meta_type']));

            unset($params['meta_type']);
        }

        unset($params['meta_id']);

        return dotclear()->blog()->posts()->getPosts($params, $count_only, $sql);
    }

    /**
     * Retrieves comments corresponding to given meta criteria.
     * <b>$params</b> is an array taking the following optional parameters:
     * - meta_id : get posts having meta id
     * - meta_type : get posts having meta type.
     *
     * @param array $params     The parameters
     * @param bool  $count_only Only count results
     *
     * @return null|Record the resulting comments record
     */
    public function getCommentsByMeta(array|ArrayObject $params = [], bool $count_only = false, ?SelectStatement $sql = null): ?Record
    {
        if (!isset($params['meta_id'])) {
            return null;
        }

        if (!$sql) {
            $sql = new SelectStatement(__METHOD__);
        }

        $sql
            ->from(dotclear()->prefix . $this->table . ' META')
            ->and('META.post_id = P.post_id')
            ->and('META.meta_id = ' . $sql->quote($params['meta_id']))
        ;

        if (!empty($params['meta_type'])) {
            $sql->and('META.meta_type = ' . $sql->quote($params['meta_type']));

            unset($params['meta_type']);
        }

        return dotclear()->blog()->comments()->getComments($params, $count_only, $sql);
    }

    /**
     * Generic-purpose metadata retrieval : gets metadatas according to given
     * criteria. <b>$params</b> is an array taking the following
     * optionnal parameters:.
     *
     * - type: get metas having the given type
     * - meta_id: if not null, get metas having the given id
     * - post_id: get metas for the given post id
     * - limit: number of max fetched metas
     * - order: results order (default : posts count DESC)
     *
     * @param array $params     The parameters
     * @param bool  $count_only Only counts results
     *
     * @return Record the metadata recordset
     */
    public function getMetadata(array|ArrayObject $params = [], bool $count_only = false, ?SelectStatement $sql = null): Record
    {
        if (!$sql) {
            $sql = new SelectStatement(__METHOD__);
        }

        if ($count_only) {
            $sql->column($sql->count($sql->unique('M.meta_id')));
        } else {
            $sql->columns([
                'M.meta_id',
                'M.meta_type',
                $sql->count('M.post_id', 'count'),
                $sql->max('P.post_dt', 'latest'),
                $sql->min('P.post_dt', 'oldest'),
            ]);
        }

        $sql
            ->from(dotclear()->prefix . $this->table . ' M')
            ->join(
                JoinStatement::init(__METHOD__)
                    ->type('LEFT')
                    ->from(dotclear()->prefix . 'post P')
                    ->on('M.post_id = P.post_id')
                    ->statement()
            )
            ->where('P.blog_id = ' . $sql->quote(dotclear()->blog()->id))
        ;

        if (isset($params['meta_type'])) {
            $sql->and('meta_type = ' . $sql->quote($params['meta_type']));
        }

        if (isset($params['meta_id'])) {
            $sql->and('meta_id = ' . $sql->quote($params['meta_id']));
        }

        if (isset($params['post_id'])) {
            $sql->and('P.post_id' . $sql->in($params['post_id']));
        }

        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $user_id = dotclear()->user()->userID();

            $and = ['post_status = 1'];
            if (dotclear()->blog()->withoutPassword()) {
                $and[] = 'post_password IS NULL';
            }

            $or = [$sql->andGroup($and)];
            if ($user_id) {
                $or[] = 'P.user_id = ' . $sql->quote($user_id);
            }
            $sql->and($sql->orGroup($or));
        }

        if (!$count_only) {
            if (!isset($params['order'])) {
                $params['order'] = 'count DESC';
            }

            $sql
                ->group([
                    'meta_id',
                    'meta_type',
                    'P.blog_id',
                ])
                ->order($params['order'])
            ;

            if (isset($params['limit'])) {
                $sql->limit($params['limit']);
            }
        }

        return $sql->select();
    }

    /**
     * Computes statistics from a metadata recordset.
     * Each record gets enriched with lowercase name, percent and roundpercent columns.
     *
     * @param Record $rs The metadata recordset
     *
     * @return StaticRecord the meta statistics
     */
    public function computeMetaStats(Record $rs): StaticRecord
    {
        $rs_static = $rs->toStatic();

        $max = [];
        while ($rs_static->fetch()) {
            $type = $rs_static->f('meta_type');
            if (!isset($max[$type])) {
                $max[$type] = $rs_static->fInt('count');
            } else {
                if ($rs_static->fInt('count') > $max[$type]) {
                    $max[$type] = $rs_static->fInt('count');
                }
            }
        }

        $rs_static->moveStart();
        // @phpstan-ignore-next-line (Failed to understand moveStart to index 0)
        while ($rs_static->fetch()) {
            $rs_static->set('meta_id_lower', Lexical::removeDiacritics(mb_strtolower($rs_static->f('meta_id'))));

            $count   = $rs_static->fInt('count');
            $percent = $rs_static->fInt('count') * 100 / $max[$rs_static->f('meta_type')];

            $rs_static->set('percent', (string) round($percent));
            $rs_static->set('roundpercent', (string) (round($percent / 10) * 10));
        }

        return $rs_static;
    }

    /**
     * Adds a metadata to a post.
     *
     * @param int    $post_id The post identifier
     * @param string $type    The type
     * @param string $value   The value
     */
    public function setPostMeta(int $post_id, string $type, string $value): void
    {
        $this->checkPermissionsOnPost($post_id);

        $value = trim($value);
        if ('' === $value) {
            return;
        }

        $sql = new InsertStatement(__METHOD__);
        $sql
            ->from(dotclear()->prefix . $this->table)
            ->columns([
                'post_id',
                'meta_id',
                'meta_type',
            ])
            ->line([[
                $post_id,
                $sql->quote($value),
                $sql->quote($type),
            ]])
            ->insert()
        ;

        $this->updatePostMeta($post_id);
    }

    /**
     * Removes metadata from a post.
     *
     * @param int         $post_id The post identifier
     * @param null|string $type    The meta type (if null, delete all types)
     * @param null|string $meta_id The meta identifier (if null, delete all values)
     */
    public function delPostMeta(int $post_id, ?string $type = null, ?string $meta_id = null): void
    {
        $this->checkPermissionsOnPost($post_id);

        $sql = DeleteStatement::init(__METHOD__)
            ->from(dotclear()->prefix . $this->table)
            ->where('post_id = ' . $post_id)
        ;

        if (null !== $type) {
            $sql->and('meta_type = ' . $sql->quote($type));
        }

        if (null !== $meta_id) {
            $sql->and('meta_id = ' . $sql->quote($meta_id));
        }

        $sql->delete();

        $this->updatePostMeta($post_id);
    }

    /**
     * Mass updates metadata for a given post_type.
     *
     * @param string      $meta_id     The old meta value
     * @param string      $new_meta_id The new meta value
     * @param null|string $type        The type (if null, select all types)
     * @param null|string $post_type   The post type (if null, select all types)
     *
     * @return bool True if at least 1 post has been impacted
     */
    public function updateMeta(string $meta_id, string $new_meta_id, ?string $type = null, ?string $post_type = null): bool
    {
        $new_meta_id = $this->sanitizeMetaID($new_meta_id);

        if ($new_meta_id == $meta_id) {
            return true;
        }

        $sql = new SelectStatement(__METHOD__);
        $sql
            ->from([
                dotclear()->prefix . $this->table . ' M',
                dotclear()->prefix . 'post P',
            ])
            ->column('M.post_id')
            ->where('P.post_id = M.post_id')
            ->and('P.blog_id = ' . $sql->quote(dotclear()->blog()->id))
        ;

        if (!dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {
            $sql->and('P.user_id = ' . $sql->quote(dotclear()->user()->userID()));
        }
        if (null !== $post_type) {
            $sql->and('P.post_type = ' . $sql->quote($post_type));
        }

        if (null !== $type) {
            $sql->and('meta_type = ' . $sql->quote($type));
        }

        $to_update = $to_remove = [];

        // Clone $sql object in order to do the same select query but with another meta_id
        $sqlNew = clone $sql;

        $sql->and('meta_id = ' . $sql->quote($meta_id));

        $rs = $sql->select();

        while ($rs->fetch()) {
            $to_update[] = $rs->fInt('post_id');
        }

        if (empty($to_update)) {
            return false;
        }

        $sqlNew->and('meta_id = ' . $sqlNew->quote($new_meta_id));

        $rs = $sqlNew->select();

        while ($rs->fetch()) {
            if (in_array($rs->fInt('post_id'), $to_update)) {
                $to_remove[] = $rs->fInt('post_id');
                unset($to_update[array_search($rs->fInt('post_id'), $to_update)]);
            }
        }

        // Delete duplicate meta
        if (!empty($to_remove)) {
            $sqlDel = new DeleteStatement(__METHOD__);
            $sqlDel
                ->from(dotclear()->prefix . $this->table)
                ->where('post_id' . $sqlDel->in($to_remove, 'int'))      // Note: will cast all values to integer
                ->and('meta_id = ' . $sqlDel->quote($meta_id))
            ;

            if (null !== $type) {
                $sqlDel->and('meta_type = ' . $sqlDel->quote($type));
            }

            $sqlDel->delete();

            foreach ($to_remove as $post_id) {
                $this->updatePostMeta($post_id);
            }
        }

        // Update meta
        if (!empty($to_update)) {
            $sqlUpd = new UpdateStatement(__METHOD__);
            $sqlUpd
                ->from(dotclear()->prefix . $this->table)
                ->set('meta_id = ' . $sqlUpd->quote($new_meta_id))
                ->where('post_id' . $sqlUpd->in($to_update, 'int'))
                ->and('meta_id = ' . $sqlUpd->quote($meta_id))
            ;

            if (null !== $type) {
                $sqlUpd->and('meta_type = ' . $sqlUpd->quote($type));
            }

            $sqlUpd->update();

            foreach ($to_update as $post_id) {
                $this->updatePostMeta($post_id);
            }
        }

        return true;
    }

    /**
     * Mass delete metadata for a given post_type.
     *
     * @param string      $meta_id   The meta identifier
     * @param null|string $type      The meta type (if null, select all types)
     * @param null|string $post_type The post type (if null, select all types)
     *
     * @return array The list of impacted post_ids
     */
    public function delMeta(string $meta_id, ?string $type = null, ?string $post_type = null): array
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->column('M.post_id')
            ->from([
                dotclear()->prefix . $this->table . ' M',
                dotclear()->prefix . 'post P',
            ])
            ->where('P.post_id = M.post_id')
            ->and('P.blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->and('meta_id = ' . $sql->quote($meta_id))
        ;

        if (null !== $type) {
            $sql->and('meta_type = ' . $sql->quote($type));
        }

        if (null !== $post_type) {
            $sql->and('P.post_type = ' . $sql->quote($post_type));
        }

        $rs = $sql->select();

        if ($rs->isEmpty()) {
            return [];
        }

        $ids = [];
        while ($rs->fetch()) {
            $ids[] = $rs->fInt('post_id');
        }

        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->from(dotclear()->prefix . $this->table)
            ->where('post_id' . $sql->in($ids, 'int'))
            ->and('meta_id = ' . $sql->quote($meta_id))
        ;

        if (null !== $type) {
            $sql->and('meta_type = ' . $sql->quote($type));
        }

        $sql->delete();

        foreach ($ids as $post_id) {
            $this->updatePostMeta($post_id);
        }

        return $ids;
    }
}
