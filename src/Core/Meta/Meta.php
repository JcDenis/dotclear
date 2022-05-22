<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Meta;

// Dotclear\Core\Meta\Meta
use Dotclear\App;
use Dotclear\Database\Param;
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
 * @ingroup  Core Meta
 */
final class Meta
{
    /**
     * Split meta values.
     *
     * Split up comma-separated values into an array of
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
     * Convert serialized metadata into a meta array.
     *
     * (for instance in dc_post post_meta).
     *
     * @param string $str The serialized metadata
     *
     * @return array The meta array
     */
    public function getMetaArray(string $str): array
    {
        $meta = @unserialize($str);

        return is_array($meta) ? $meta : [];
    }

    /**
     * Convert serialized metadata into a comma-separated meta list for a given type.
     *
     * (for instance in dc_post post_meta)
     *
     * @param string $str  The serialized metadata
     * @param string $type The meta type to retrieve metaIDs from
     *
     * @return string The comma-separated list of meta
     */
    public function getMetaStr(string $str, string $type): string
    {
        $meta = $this->getMetaArray($str);

        return isset($meta[$type]) ? implode(', ', $meta[$type]) : '';
    }

    /**
     * Convert serialized metadata into a "fetchable" metadata record.
     *
     * (for instance in dc_post post_meta)
     *
     * @param string $str  The serialized metadata
     * @param string $type The meta type to retrieve metaIDs from
     *
     * @return StaticRecord The meta recordset
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
     * Check whether the current user is allowed to change post meta.
     *
     * An exception is thrown if user is not allowed.
     *
     * @param int $post_id The post identifier
     *
     * @throws CoreException
     */
    private function checkPermissionsOnPost(int $post_id): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry status'));
        }

        // If user can only publish, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $sql = new SelectStatement(__METHOD__);
            $sql->from(App::core()->prefix() . 'post');
            $sql->column('post_id');
            $sql->where('post_id = ' . $post_id);
            $sql->and('user_id = ' . $sql->quote(App::core()->user()->userID()));
            $rs = $sql->select();

            if ($rs->isEmpty()) {
                throw new CoreException(__('You are not allowed to change this entry status'));
            }
        }
    }

    /**
     * Update serialized post_meta information with meta table information.
     *
     * @param int $post_id The post identifier
     */
    private function updatePostMeta(int $post_id): void
    {
        $sql = new SelectStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'meta');
        $sql->columns([
            'meta_id',
            'meta_type',
        ]);
        $sql->where('post_id = ' . $post_id);
        $rs = $sql->select();

        $meta = [];
        while ($rs->fetch()) {
            $meta[$rs->f('meta_type')][] = $rs->f('meta_id');
        }

        $sql = new UpdateStatement(__METHOD__);
        $sql->set('post_meta = ' . $sql->quote(serialize($meta)));
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('post_id = ' . $post_id);
        $sql->update();

        App::core()->blog()->triggerBlog();
    }

    /**
     * Count posts corresponding to given meta criteria.
     *
     * @see MetaParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The posts count
     */
    public function countPostsByMeta(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $param = new MetaParam($param);
        $param->unset('order');
        $param->unset('limit');

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);

        if ($this->queryPostsByMeta(param: $param, sql: $query)) {
            return App::core()->blog()->posts()->countPosts(param: $param, sql: $query);
        }

        return 0;
    }

    /**
     * Retrieve posts corresponding to given meta criteria.
     *
     * @see MetaParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return null|Record The resulting posts record
     */
    public function getPostsByMeta(?Param $param = null, ?SelectStatement $sql = null): ?Record
    {
        $param = new MetaParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);

        if ($this->queryPostsByMeta(param: $param, sql: $query)) {
            return App::core()->blog()->posts()->getPosts(param: $param, sql: $query);
        }

        return null;
    }

    /**
     * Query posts table.
     *
     * !Waiting getPosts using Param
     *
     * @param MetaParam       $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return bool Can query Posts table
     */
    private function queryPostsByMeta(MetaParam $param, SelectStatement $sql): bool
    {
        if (null === $param->meta_id()) {
            return false;
        }

        $sql->from(App::core()->prefix() . 'meta META');
        $sql->and('META.post_id = P.post_id');
        $sql->and('META.meta_id = ' . $sql->quote($param->meta_id()));

        if (null !== $param->meta_type()) {
            $sql->and('META.meta_type = ' . $sql->quote($param->meta_type()));

            $param->unset('meta_type');
        }

        $param->unset('meta_id');

        return true;
    }

    /**
     * Count comments corresponding to given meta criteria.
     *
     * @see MetaParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The comments count
     */
    public function countCommentsByMeta(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $param = new MetaParam($param);
        $param->unset('order');
        $param->unset('limit');

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);

        if ($this->queryCommentsByMeta(param: $param, sql: $query)) {
            return App::core()->blog()->posts()->countComments(param: $param, sql: $query);
        }

        return 0;
    }

    /**
     * Retrieve comments corresponding to given meta criteria.
     *
     * @see MetaParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return null|Record The resulting comments record
     */
    public function getCommentsByMeta(?Param $param = null, ?SelectStatement $sql = null): ?Record
    {
        $param = new MetaParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);

        if ($this->queryCommentsByMeta(param: $param, sql: $query)) {
            return App::core()->blog()->posts()->getComments(param: $param, sql: $query);
        }

        return null;
    }

    /**
     * Query comments table.
     *
     * @param MetaParam       $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return bool Can query Comments table
     */
    private function queryCommentsByMeta(MetaParam $param, SelectStatement $sql): bool
    {
        if (null !== $param->meta_id()) {
            return false;
        }

        $sql->from(App::core()->prefix() . 'meta META');
        $sql->and('META.post_id = P.post_id');
        $sql->and('META.meta_id = ' . $sql->quote($param->meta_id()));

        if (null !== $param->meta_type()) {
            $sql->and('META.meta_type = ' . $sql->quote($param->meta_type()));

            $param->unset('meta_type');
        }

        return true;
    }

    /**
     * Generic-purpose metadata count.
     *
     * @see MetaParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return int The logs count
     */
    public function countMetadata(?Param $param = null, ?SelectStatement $sql = null): int
    {
        $param = new MetaParam($param);
        $param->unset('order');
        $param->unset('limit');

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->column($query->count($query->unique('M.meta_id')));

        return $this->queryMetadataTable(param: $param, sql: $query)->fInt();
    }

    /**
     * Generic-purpose metadata retrieval.
     *
     * @see MetaParam for optionnal paramaters
     *
     * @param null|Param           $param The parameters
     * @param null|SelectStatement $sql   The SQL statement
     *
     * @return Record The metadata recordset
     */
    public function getMetadata(?Param $param = null, ?SelectStatement $sql = null): Record
    {
        $param = new MetaParam($param);

        $query = $sql ? clone $sql : new SelectStatement(__METHOD__);
        $query->columns([
            'M.meta_id',
            'M.meta_type',
            $query->count('M.post_id', 'count'),
            $query->max('P.post_dt', 'latest'),
            $query->min('P.post_dt', 'oldest'),
        ]);
        $query->group([
            'meta_id',
            'meta_type',
            'P.blog_id',
        ]);
        $query->order($param->order('count DESC'));

        if (!empty($param->limit())) {
            $query->limit($param->limit());
        }

        return $this->queryMetadataTable(param: $param, sql: $query);
    }

    /**
     * Query metadata table.
     *
     * @param MetaParam       $param The log parameters
     * @param SelectStatement $sql   The SQL statement
     *
     * @return Record The result
     */
    private function queryMetadataTable(MetaParam $param, SelectStatement $sql): Record
    {
        $join = new JoinStatement(__METHOD__);
        $join->type('LEFT');
        $join->from(App::core()->prefix() . 'post P');
        $join->on('M.post_id = P.post_id');

        $sql->from(App::core()->prefix() . 'meta M');
        $sql->join($join->statement());
        $sql->where('P.blog_id = ' . $sql->quote(App::core()->blog()->id));

        if (null !== $param->meta_type()) {
            $sql->and('meta_type = ' . $sql->quote($param->meta_type()));
        }

        if (null !== $param->meta_id()) {
            $sql->and('meta_id = ' . $sql->quote($param->meta_id()));
        }

        if (!empty($param->post_id())) {
            $sql->and('P.post_id' . $sql->in($param->post_id()));
        }

        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $user_id = App::core()->user()->userID();

            $and = ['post_status = 1'];
            if (App::core()->blog()->withoutPassword()) {
                $and[] = 'post_password IS NULL';
            }

            $or = [$sql->andGroup($and)];
            if ($user_id) {
                $or[] = 'P.user_id = ' . $sql->quote($user_id);
            }
            $sql->and($sql->orGroup($or));
        }

        if (null !== $param->sql()) {
            $sql->sql($param->sql());
        }

        return $sql->select();
    }

    /**
     * Compute statistics from a metadata recordset.
     *
     * Each record gets enriched with lowercase name,
     * percent and roundpercent columns.
     *
     * @param Record $rs The metadata recordset
     *
     * @return StaticRecord The meta statistics
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
     * Add a metadata to a post.
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
        $sql->from(App::core()->prefix() . 'meta');
        $sql->columns([
            'post_id',
            'meta_id',
            'meta_type',
        ]);
        $sql->line([[
            $post_id,
            $sql->quote($value),
            $sql->quote($type),
        ]]);
        $sql->insert();

        $this->updatePostMeta($post_id);
    }

    /**
     * Remove metadata from a post.
     *
     * @param int         $post_id The post identifier
     * @param null|string $type    The meta type (if null, delete all types)
     * @param null|string $meta_id The meta identifier (if null, delete all values)
     */
    public function delPostMeta(int $post_id, ?string $type = null, ?string $meta_id = null): void
    {
        $this->checkPermissionsOnPost($post_id);

        $sql = new DeleteStatement(__METHOD__);
        $sql->from(App::core()->prefix() . 'meta');
        $sql->where('post_id = ' . $post_id);

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
        $sql->from([
            App::core()->prefix() . 'meta M',
            App::core()->prefix() . 'post P',
        ]);
        $sql->column('M.post_id');
        $sql->where('P.post_id = M.post_id');
        $sql->and('P.blog_id = ' . $sql->quote(App::core()->blog()->id));

        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $sql->and('P.user_id = ' . $sql->quote(App::core()->user()->userID()));
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
            $sqlDel->from(App::core()->prefix() . 'meta');
            $sqlDel->where('post_id' . $sqlDel->in($to_remove));
            $sqlDel->and('meta_id = ' . $sqlDel->quote($meta_id));

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
            $sqlUpd->from(App::core()->prefix() . 'meta');
            $sqlUpd->set('meta_id = ' . $sqlUpd->quote($new_meta_id));
            $sqlUpd->where('post_id' . $sqlUpd->in($to_update));
            $sqlUpd->and('meta_id = ' . $sqlUpd->quote($meta_id));

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
        $sql->column('M.post_id');
        $sql->from([
            App::core()->prefix() . 'meta M',
            App::core()->prefix() . 'post P',
        ]);
        $sql->where('P.post_id = M.post_id');
        $sql->and('P.blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and('meta_id = ' . $sql->quote($meta_id));

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
        $sql->from(App::core()->prefix() . 'meta');
        $sql->where('post_id' . $sql->in($ids, 'int'));
        $sql->and('meta_id = ' . $sql->quote($meta_id));

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
