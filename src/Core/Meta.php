<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Text;
use Dotclear\Interface\Core\ConnectionInterface;
use Dotclear\Interface\Core\MetaInterface;
use Exception;

/**
 * @brief   Meta handler.
 *
 * Dotclear metadata class instance is provided by App::meta() method.
 */
class Meta implements MetaInterface
{
    /**
     * Database connection handler.
     *
     * @var     ConnectionInterface     $con
     */
    protected ConnectionInterface $con;

    /**
     * The mate table name with prefix.
     *
     * @var     string  $table
     */
    private string $table;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->con   = App::con();
        $this->table = $this->con->prefix() . self::META_TABLE_NAME;
    }

    public function openMetaCursor(): Cursor
    {
        return $this->con->openCursor($this->table);
    }

    public function splitMetaValues(string $str): array
    {
        $res = [];
        foreach (explode(',', $str) as $i => $tag) {
            $tag = self::sanitizeMetaID(trim((string) $tag));
            if ($tag) {
                $res[$i] = $tag;
            }
        }

        return array_unique($res);
    }

    public static function sanitizeMetaID(string $str): string
    {
        return Text::tidyURL($str, false, true);
    }

    public function getMetaArray(?string $str): array
    {
        if (!$str) {
            return [];
        }

        $meta = @unserialize((string) $str);
        if (!is_array($meta)) {
            return [];
        }

        return $meta;
    }

    public function getMetaStr(?string $str, string $type): string
    {
        if (!$str) {
            return '';
        }

        $meta = $this->getMetaArray($str);
        if (!isset($meta[$type])) {
            return '';
        }

        return implode(', ', $meta[$type]);
    }

    public function getMetaRecordset(?string $str, string $type): MetaRecord
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

        return MetaRecord::newFromArray($data);
    }

    /**
     * Checks whether the current user is allowed to change post meta
     * An exception is thrown if user is not allowed.
     *
     * @param   int|string  $post_id    The post identifier
     *
     * @throws  Exception
     */
    private function checkPermissionsOnPost(int|string $post_id): void
    {
        $post_id = (int) $post_id;

        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            throw new Exception(__('You are not allowed to change this entry status'));
        }

        # If user can only publish, we need to check the post's owner
        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $sql = new SelectStatement();
            $sql
                ->from($this->con->prefix() . App::blog()::POST_TABLE_NAME)
                ->column('post_id')
                ->where('post_id = ' . $post_id)
                ->and('user_id = ' . $sql->quote(App::auth()->userID()));

            $rs = $sql->select();

            if ($rs->isEmpty()) {
                throw new Exception(__('You are not allowed to change this entry status'));
            }
        }
    }

    /**
     * Updates serialized post_meta information with dc_meta table information.
     *
     * @param   int|string  $post_id    The post identifier
     */
    private function updatePostMeta(int|string $post_id): void
    {
        $post_id = (int) $post_id;

        $sql = new SelectStatement();
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

        $cur            = App::blog()->openPostCursor();
        $cur->post_meta = $post_meta;

        $sql = new UpdateStatement();
        $sql->where('post_id = ' . $post_id);

        $sql->update($cur);

        App::blog()->triggerBlog();
    }

    public function getPostsByMeta(array $params = [], bool $count_only = false, ?SelectStatement $ext_sql = null): MetaRecord
    {
        if (!isset($params['meta_id'])) {
            return MetaRecord::newFromArray([]);
        }

        $sql = $ext_sql ? clone $ext_sql : new SelectStatement();

        $sql
            ->from($this->table . ' META')
            ->and('META.post_id = P.post_id')
            ->and('META.meta_id = ' . $sql->quote($params['meta_id']));

        if (!empty($params['meta_type'])) {
            $sql->and('META.meta_type = ' . $sql->quote($params['meta_type']));

            unset($params['meta_type']);
        }

        unset($params['meta_id']);

        return App::blog()->getPosts($params, $count_only, $sql);
    }

    public function getCommentsByMeta(array $params = [], bool $count_only = false, ?SelectStatement $ext_sql = null): MetaRecord
    {
        if (!isset($params['meta_id'])) {
            return MetaRecord::newFromArray([]);
        }

        $sql = $ext_sql ? clone $ext_sql : new SelectStatement();

        $sql
            ->from($this->table . ' META')
            ->and('META.post_id = P.post_id')
            ->and('META.meta_id = ' . $sql->quote($params['meta_id']));

        if (!empty($params['meta_type'])) {
            $sql->and('META.meta_type = ' . $sql->quote($params['meta_type']));

            unset($params['meta_type']);
        }

        return App::blog()->getComments($params, $count_only, $sql);
    }

    public function getMetadata(array $params = [], bool $count_only = false, ?SelectStatement $ext_sql = null): MetaRecord
    {
        $sql = $ext_sql ? clone $ext_sql : new SelectStatement();

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
            ->from($sql->as($this->table, 'M'))
            ->join(
                (new JoinStatement())
                ->left()
                ->from($sql->as($this->con->prefix() . App::blog()::POST_TABLE_NAME, 'P'))
                ->on('M.post_id = P.post_id')
                ->statement()
            )
            ->where('P.blog_id = ' . $sql->quote(App::blog()->id()));

        if (isset($params['meta_type'])) {
            $sql->and('meta_type = ' . $sql->quote($params['meta_type']));
        }

        if (isset($params['meta_id'])) {
            $sql->and('meta_id = ' . $sql->quote($params['meta_id']));
        }

        if (isset($params['post_id'])) {
            $sql->and('P.post_id' . $sql->in($params['post_id']));
        }

        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $user_id = App::auth()->userID();

            $and = ['post_status = ' . (string) App::blog()::POST_PUBLISHED];
            if (App::blog()->withoutPassword()) {
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
                ->order($params['order']);

            if (isset($params['limit'])) {
                $sql->limit($params['limit']);
            }
        }

        return $sql->select() ?? MetaRecord::newFromArray([]);
    }

    public function computeMetaStats(MetaRecord $rs): MetaRecord
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
            $rs_static->set('meta_id_lower', Text::removeDiacritics(mb_strtolower($rs_static->meta_id)));

            $percent = ((int) $rs_static->count) * 100 / $max[$rs_static->meta_type];

            $rs_static->set('percent', (int) round($percent));
            $rs_static->set('roundpercent', round($percent / 10) * 10);
        }

        return $rs_static;
    }

    public function setPostMeta(int|string $post_id, ?string $type, ?string $value): void
    {
        $this->checkPermissionsOnPost($post_id);

        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        $cur = $this->openMetaCursor();

        $cur->post_id   = (int) $post_id;
        $cur->meta_id   = (string) $value;
        $cur->meta_type = (string) $type;

        $cur->insert();
        $this->updatePostMeta((int) $post_id);
    }

    public function delPostMeta(int|string $post_id, ?string $type = null, ?string $meta_id = null): void
    {
        $post_id = (int) $post_id;

        $this->checkPermissionsOnPost($post_id);

        $sql = new DeleteStatement();
        $sql
            ->from($this->table)
            ->where('post_id = ' . $post_id);

        if ($type !== null) {
            $sql->and('meta_type = ' . $sql->quote($type));
        }

        if ($meta_id !== null) {
            $sql->and('meta_id = ' . $sql->quote($meta_id));
        }

        $sql->delete();

        $this->updatePostMeta((int) $post_id);
    }

    public function updateMeta(string $meta_id, string $new_meta_id, ?string $type = null, ?string $post_type = null): bool
    {
        $new_meta_id = self::sanitizeMetaID($new_meta_id);

        if ($new_meta_id == $meta_id) {
            return true;
        }

        $sql = new SelectStatement();
        $sql
            ->from([
                $sql->as($this->table, 'M'),
                $sql->as($this->con->prefix() . App::blog()::POST_TABLE_NAME, 'P'),
            ])
            ->column('M.post_id')
            ->where('P.post_id = M.post_id')
            ->and('P.blog_id = ' . $sql->quote(App::blog()->id()));

        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $sql->and('P.user_id = ' . $sql->quote(App::auth()->userID()));
        }
        if ($post_type !== null) {
            $sql->and('P.post_type = ' . $sql->quote($post_type));
        }

        if ($type !== null) {
            $sql->and('meta_type = ' . $sql->quote($type));
        }

        $to_update = $to_remove = [];

        // Clone $sql object in order to do the same select query but with another meta_id
        $sqlNew = clone $sql;

        $sql->and('meta_id = ' . $sql->quote($meta_id));

        $rs = $sql->select();

        while ($rs->fetch()) {
            $to_update[] = $rs->post_id;
        }

        if (empty($to_update)) {
            return false;
        }

        $sqlNew->and('meta_id = ' . $sqlNew->quote($new_meta_id));

        $rs = $sqlNew->select();

        while ($rs->fetch()) {
            if (in_array($rs->post_id, $to_update)) {
                $to_remove[] = $rs->post_id;
                unset($to_update[array_search($rs->post_id, $to_update)]);
            }
        }

        # Delete duplicate meta
        if (!empty($to_remove)) {
            $sqlDel = new DeleteStatement();
            $sqlDel
                ->from($this->table)
                ->where('post_id' . $sqlDel->in($to_remove, 'int'))      // Note: will cast all values to integer
                ->and('meta_id = ' . $sqlDel->quote($meta_id));

            if ($type !== null) {
                $sqlDel->and('meta_type = ' . $sqlDel->quote($type));
            }

            $sqlDel->delete();

            foreach ($to_remove as $post_id) {
                $this->updatePostMeta($post_id);
            }
        }

        # Update meta
        if (!empty($to_update)) {
            $sqlUpd = new UpdateStatement();
            $sqlUpd
                ->from($this->table)
                ->set('meta_id = ' . $sqlUpd->quote($new_meta_id))
                ->where('post_id' . $sqlUpd->in($to_update, 'int'))     // Note: will cast all values to integer
                ->and('meta_id = ' . $sqlUpd->quote($meta_id));

            if ($type !== null) {
                $sqlUpd->and('meta_type = ' . $sqlUpd->quote($type));
            }

            $sqlUpd->update();

            foreach ($to_update as $post_id) {
                $this->updatePostMeta($post_id);
            }
        }

        return true;
    }

    public function delMeta(string $meta_id, ?string $type = null, ?string $post_type = null): array
    {
        $sql = new SelectStatement();
        $sql
            ->column('M.post_id')
            ->from([
                $sql->as($this->table, 'M'),
                $sql->as($this->con->prefix() . App::blog()::POST_TABLE_NAME, 'P'),
            ])
            ->where('P.post_id = M.post_id')
            ->and('P.blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('meta_id = ' . $sql->quote($meta_id));

        if ($type !== null) {
            $sql->and('meta_type = ' . $sql->quote($type));
        }

        if ($post_type !== null) {
            $sql->and('P.post_type = ' . $sql->quote($post_type));
        }

        $rs = $sql->select();

        if ($rs->isEmpty()) {
            return [];
        }

        $ids = [];
        while ($rs->fetch()) {
            $ids[] = $rs->post_id;
        }

        $sql = new DeleteStatement();
        $sql
            ->from($this->table)
            ->where('post_id' . $sql->in($ids, 'int'))
            ->and('meta_id = ' . $sql->quote($meta_id));

        if ($type !== null) {
            $sql->and('meta_type = ' . $sql->quote($type));
        }

        $sql->delete();

        foreach ($ids as $k => $post_id) {
            $this->updatePostMeta($post_id);
            $ids[$k] = (int) $post_id;
        }

        return $ids;
    }
}
