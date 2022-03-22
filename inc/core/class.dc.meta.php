<?php
/**
 * @brief Dotclear metadata class.
 *
 * Dotclear metadata class instance is provided by dcCore $meta property.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!defined('DC_RC_PATH')) {
    return;
}

class dcMeta
{
    private $core;  ///< <b>dcCore</b> dcCore instance
    private $con;   ///< <b>connection</b>    Database connection object
    private $table; ///< <b>string</b> Media table name

    /**
     * Constructs a new instance.
     *
     * @param      dcCore  $core   The core
     */
    public function __construct(dcCore $core)
    {
        $this->core  = &$core;
        $this->con   = &$this->core->con;
        $this->table = $this->core->prefix . 'meta';
    }

    /**
     * Splits up comma-separated values into an array of
     * unique, URL-proof metadata values.
     *
     * @param      string  $str    Comma-separated metadata
     *
     * @return     array  The array of sanitized metadata
     */
    public function splitMetaValues($str)
    {
        $res = [];
        foreach (explode(',', $str) as $i => $tag) {
            $tag = trim((string) $tag);
            $tag = self::sanitizeMetaID($tag);

            if ($tag != false) {
                $res[$i] = $tag;
            }
        }

        return array_unique($res);
    }

    /**
     * Make a metadata ID URL-proof.
     *
     * @param      string  $str    The metadata ID
     *
     * @return     string
     */
    public static function sanitizeMetaID($str)
    {
        return text::tidyURL($str, false, true);
    }

    /**
     * Converts serialized metadata (for instance in dc_post post_meta)
     * into a meta array.
     *
     * @param      string  $str    The serialized metadata
     *
     * @return     array   The meta array.
     */
    public function getMetaArray($str)
    {
        $meta = @unserialize((string) $str);

        if (!is_array($meta)) {
            return [];
        }

        return $meta;
    }

    /**
     * Converts serialized metadata (for instance in dc_post post_meta)
     * into a comma-separated meta list for a given type.
     *
     * @param      string  $str    The serialized metadata
     * @param      string  $type   The meta type to retrieve metaIDs from
     *
     * @return     string  The comma-separated list of meta.
     */
    public function getMetaStr($str, $type)
    {
        $meta = $this->getMetaArray($str);

        if (!isset($meta[$type])) {
            return '';
        }

        return implode(', ', $meta[$type]);
    }

    /**
     * Converts serialized metadata (for instance in dc_post post_meta)
     * into a "fetchable" metadata record.
     *
     * @param      string  $str    The serialized metadata
     * @param      string  $type   The meta type to retrieve metaIDs from
     *
     * @return     staticRecord  The meta recordset.
     */
    public function getMetaRecordset($str, $type)
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

        return staticRecord::newFromArray($data);
    }

    /**
     * Checks whether the current user is allowed to change post meta
     * An exception is thrown if user is not allowed.
     *
     * @param      mixed     $post_id  The post identifier
     *
     * @throws     Exception
     */
    private function checkPermissionsOnPost($post_id)
    {
        $post_id = (int) $post_id;

        if (!$this->core->auth->check('usage,contentadmin', $this->core->blog->id)) {
            throw new Exception(__('You are not allowed to change this entry status'));
        }

        # If user can only publish, we need to check the post's owner
        if (!$this->core->auth->check('contentadmin', $this->core->blog->id)) {
            $sql = new dcSelectStatement($this->core, 'dcMetaCheckPermissionsOnPost');
            $sql
                ->from($sql->core->prefix . 'post')
                ->column('post_id')
                ->where('post_id = ' . $post_id)
                ->and('user_id = ' . $sql->quote($this->core->auth->userID(), true));

            $rs = $sql->select();

            if ($rs->isEmpty()) {
                throw new Exception(__('You are not allowed to change this entry status'));
            }
        }
    }

    /**
     * Updates serialized post_meta information with dc_meta table information.
     *
     * @param      mixed  $post_id  The post identifier
     */
    private function updatePostMeta($post_id)
    {
        $post_id = (int) $post_id;

        $sql = new dcSelectStatement($this->core, 'dcMetaUpdatePostMeta');
        $sql
            ->from($this->table)
            ->columns([
                'meta_id',
                'meta_type',
            ])
            ->where('post_id = ' . $post_id);

        $rs = $sql->select();

        $meta = [];
        while ($rs->fetch()) {
            $meta[$rs->meta_type][] = $rs->meta_id;
        }

        $post_meta = serialize($meta);

        $cur            = $this->con->openCursor($this->core->prefix . 'post');
        $cur->post_meta = $post_meta;

        $sql = new dcUpdateStatement($this->core, 'dcMetaUpdatePostMeta');
        $sql->where('post_id = ' . $post_id);

        $sql->update($cur);

        $this->core->blog->triggerBlog();
    }

    /**
     * Retrieves posts corresponding to given meta criteria.
     * <b>$params</b> is an array taking the following optional parameters:
     * - meta_id : get posts having meta id
     * - meta_type : get posts having meta type
     *
     * @param      array                    $params      The parameters
     * @param      bool                     $count_only  Only count results
     * @param      dcSelectStatement|null   $sql         Optional dcSqlStatement instance
     *
     * @return     mixed   The resulting posts record.
     */
    public function getPostsByMeta($params = [], $count_only = false, ?dcSelectStatement $sql = null)
    {
        if (!isset($params['meta_id'])) {
            return;
        }

        if (!$sql) {
            $sql = new dcSelectStatement($this->core, 'dcMetaGetPostsByMeta');
        }

        $sql
            ->from($this->table . ' META')
            ->and('META.post_id = P.post_id')
            ->and('META.meta_id = ' . $sql->quote($params['meta_id'], true));

        if (!empty($params['meta_type'])) {
            $sql->and('META.meta_type = ' . $sql->quote($params['meta_type'], true));

            unset($params['meta_type']);
        }

        unset($params['meta_id']);

        return $this->core->blog->getPosts($params, $count_only, $sql);
    }

    /**
     * Retrieves comments corresponding to given meta criteria.
     * <b>$params</b> is an array taking the following optional parameters:
     * - meta_id : get posts having meta id
     * - meta_type : get posts having meta type
     *
     * @param      array                    $params      The parameters
     * @param      bool                     $count_only  Only count results
     * @param      dcSelectStatement|null   $sql         Optional dcSqlStatement instance
     *
     * @return     mixed   The resulting comments record.
     */
    public function getCommentsByMeta($params = [], $count_only = false, ?dcSelectStatement $sql = null)
    {
        if (!isset($params['meta_id'])) {
            return;
        }

        if (!$sql) {
            $sql = new dcSelectStatement($this->core, 'dcMetaGetPostsByMeta');
        }

        $sql
            ->from($this->table . ' META')
            ->and('META.post_id = P.post_id')
            ->and('META.meta_id = ' . $sql->quote($params['meta_id'], true));

        if (!empty($params['meta_type'])) {
            $sql->and('META.meta_type = ' . $sql->quote($params['meta_type'], true));

            unset($params['meta_type']);
        }

        return $this->core->blog->getComments($params, $count_only, $sql);
    }

    /**
     * Generic-purpose metadata retrieval : gets metadatas according to given
     * criteria. <b>$params</b> is an array taking the following
     * optionnal parameters:
     *
     * - type: get metas having the given type
     * - meta_id: if not null, get metas having the given id
     * - post_id: get metas for the given post id
     * - limit: number of max fetched metas
     * - order: results order (default : posts count DESC)
     *
     * @param      array                    $params      The parameters
     * @param      bool                     $count_only  Only counts results
     * @param      dcSelectStatement|null   $sql         Optional dcSqlStatement instance
     *
     * @return     record  The metadata.
     */
    public function getMetadata($params = [], $count_only = false, ?dcSelectStatement $sql = null)
    {
        if (!$sql) {
            $sql = new dcSelectStatement($this->core, 'dcMetaGetMetadata');
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
            ->from($this->table . ' M')
            ->join(
                (new dcJoinStatement($this->core, 'dcMetaGetMetadata'))
                ->type('LEFT')
                ->from($sql->core->prefix . 'post P')
                ->on('M.post_id = P.post_id')
                ->statement()
            )
            ->where('P.blog_id = ' . $sql->quote($this->core->blog->id, true));

        if (isset($params['meta_type'])) {
            $sql->and('meta_type = ' . $sql->quote($params['meta_type'], true));
        }

        if (isset($params['meta_id'])) {
            $sql->and('meta_id = ' . $sql->quote($params['meta_id'], true));
        }

        if (isset($params['post_id'])) {
            $sql->and('P.post_id' . $sql->in($params['post_id']));
        }

        if (!$this->core->auth->check('contentadmin', $this->core->blog->id)) {
            $user_id = $this->core->auth->userID();

            $and = ['post_status = 1'];
            if ($this->core->blog->without_password) {
                $and[] = 'post_password IS NULL';
            }

            $or = [$sql->andGroup($and)];
            if ($user_id) {
                $or[] = 'P.user_id = ' . $sql->quote($user_id, true);
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
                ->order($params['order']);

            if (isset($params['limit'])) {
                $sql->limit($params['limit']);
            }
        }

        $rs = $sql->select();

        return $rs;
    }

    /**
     * Computes statistics from a metadata recordset.
     * Each record gets enriched with lowercase name, percent and roundpercent columns
     *
     * @param      record  $rs     The metadata recordset
     *
     * @return     staticRecord  The meta statistics.
     */
    public function computeMetaStats($rs)
    {
        $rs_static = $rs->toStatic();

        $max = [];
        while ($rs_static->fetch()) {
            $type = $rs_static->meta_type;
            if (!isset($max[$type])) {
                $max[$type] = $rs_static->count;
            } else {
                if ($rs_static->count > $max[$type]) {
                    $max[$type] = $rs_static->count;
                }
            }
        }

        $rs_static->moveStart();
        while ($rs_static->fetch()) {   // @phpstan-ignore-line
            $rs_static->set('meta_id_lower', dcUtils::removeDiacritics(mb_strtolower($rs_static->meta_id)));

            $percent = ((int) $rs_static->count) * 100 / $max[$rs_static->meta_type];

            $rs_static->set('percent', (int) round($percent));
            $rs_static->set('roundpercent', round($percent / 10) * 10);
        }

        return $rs_static;
    }

    /**
     * Adds a metadata to a post.
     *
     * @param      mixed   $post_id  The post identifier
     * @param      mixed   $type     The type
     * @param      mixed   $value    The value
     */
    public function setPostMeta($post_id, $type, $value)
    {
        $this->checkPermissionsOnPost($post_id);

        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        $cur = $this->con->openCursor($this->table);

        $cur->post_id   = (int) $post_id;
        $cur->meta_id   = (string) $value;
        $cur->meta_type = (string) $type;

        $cur->insert();
        $this->updatePostMeta((int) $post_id);
    }

    /**
     * Removes metadata from a post.
     *
     * @param      mixed   $post_id  The post identifier
     * @param      mixed   $type     The meta type (if null, delete all types)
     * @param      mixed   $meta_id  The meta identifier (if null, delete all values)
     */
    public function delPostMeta($post_id, $type = null, $meta_id = null)
    {
        $post_id = (int) $post_id;

        $this->checkPermissionsOnPost($post_id);

        $sql = new dcDeleteStatement($this->core, 'dcMetaDelPostMeta');
        $sql
            ->from($this->table)
            ->where('post_id = ' . $post_id);

        if ($type !== null) {
            $sql->and('meta_type = ' . $sql->quote($type, true));
        }

        if ($meta_id !== null) {
            $sql->and('meta_id = ' . $sql->quote($meta_id, true));
        }

        $sql->delete();

        $this->updatePostMeta((int) $post_id);
    }

    /**
     * Mass updates metadata for a given post_type.
     *
     * @param      string  $meta_id      The old meta value
     * @param      string  $new_meta_id  The new meta value
     * @param      mixed   $type         The type (if null, select all types)
     * @param      mixed   $post_type    The post type (if null, select all types)
     *
     * @return     bool   true if at least 1 post has been impacted
     */
    public function updateMeta($meta_id, $new_meta_id, $type = null, $post_type = null)
    {
        $new_meta_id = self::sanitizeMetaID($new_meta_id);

        if ($new_meta_id == $meta_id) {
            return true;
        }

        $sql = new dcSelectStatement($this->core, 'dcMetaUpdateMeta');
        $sql
            ->from([
                $this->table . ' M',
                $sql->core->prefix . 'post P',
            ])
            ->column('M.post_id')
            ->where('P.post_id = M.post_id')
            ->and('P.blog_id = ' . $sql->quote($this->core->blog->id, true));

        if (!$this->core->auth->check('contentadmin', $this->core->blog->id)) {
            $sql->and('P.user_id = ' . $sql->quote($this->core->auth->userID(), true));
        }
        if ($post_type !== null) {
            $sql->and('P.post_type = ' . $sql->quote($post_type, true));
        }

        if ($type !== null) {
            $sql->and('meta_type = ' . $sql->quote($type, true));
        }

        $to_update = $to_remove = [];

        // Clone $sql object in order to do the same select query but with another meta_id
        $sqlNew = clone $sql;

        $sql->and('meta_id = ' . $sql->quote($meta_id, true));

        $rs = $sql->select();

        while ($rs->fetch()) {
            $to_update[] = $rs->post_id;
        }

        if (empty($to_update)) {
            return false;
        }

        $sqlNew->and('meta_id = ' . $sqlNew->quote($new_meta_id, true));

        $rs = $sqlNew->select();

        while ($rs->fetch()) {
            if (in_array($rs->post_id, $to_update)) {
                $to_remove[] = $rs->post_id;
                unset($to_update[array_search($rs->post_id, $to_update)]);
            }
        }

        # Delete duplicate meta
        if (!empty($to_remove)) {
            $sqlDel = new dcDeleteStatement($this->core, 'dcMetaUpdateMeta');
            $sqlDel
                ->from($this->table)
                ->where('post_id' . $sqlDel->in($to_remove, 'int'))      // Note: will cast all values to integer
                ->and('meta_id = ' . $sqlDel->quote($meta_id, true));

            if ($type !== null) {
                $sqlDel->and('meta_type = ' . $sqlDel->quote($type, true));
            }

            $sqlDel->delete();

            foreach ($to_remove as $post_id) {
                $this->updatePostMeta($post_id);
            }
        }

        # Update meta
        if (!empty($to_update)) {
            $sqlUpd = new dcUpdateStatement($this->core, 'dcMetaUpdateMeta');
            $sqlUpd
                ->from($this->table)
                ->set('meta_id = ' . $sqlUpd->quote($new_meta_id, true))
                ->where('post_id' . $sqlUpd->in($to_update, 'int'))     // Note: will cast all values to integer
                ->and('meta_id = ' . $sqlUpd->quote($meta_id, true));

            if ($type !== null) {
                $sqlUpd->and('meta_type = ' . $sqlUpd->quote($type, true));
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
     * @param      string  $meta_id    The meta identifier
     * @param      mixed   $type       The meta type (if null, select all types)
     * @param      mixed   $post_type  The post type (if null, select all types)
     *
     * @return     array   The list of impacted post_ids
     */
    public function delMeta($meta_id, $type = null, $post_type = null)
    {
        $sql = new dcSelectStatement($this->core, 'dcMetaDelMeta');
        $sql
            ->column('M.post_id')
            ->from([
                $this->table . ' M',
                $sql->core->prefix . 'post P',
            ])
            ->where('P.post_id = M.post_id')
            ->and('P.blog_id = ' . $sql->quote($this->core->blog->id, true))
            ->and('meta_id = ' . $sql->quote($meta_id, true));

        if ($type !== null) {
            $sql->and('meta_type = ' . $sql->quote($type, true));
        }

        if ($post_type !== null) {
            $sql->and('P.post_type = ' . $sql->quote($post_type, true));
        }

        $rs = $sql->select();

        if ($rs->isEmpty()) {
            return [];
        }

        $ids = [];
        while ($rs->fetch()) {
            $ids[] = $rs->post_id;
        }

        $sql = new dcDeleteStatement($this->core, 'dcMetaDelMeta');
        $sql
            ->from($this->table)
            ->where('post_id' . $sql->in($ids, 'int'))
            ->and('meta_id = ' . $sql->quote($meta_id, true));

        if ($type !== null) {
            $sql->and('meta_type = ' . $sql->quote($type, true));
        }

        $sql->delete();

        foreach ($ids as $post_id) {
            $this->updatePostMeta($post_id);
        }

        return $ids;
    }
}