<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Posts;

// Dotclear\Core\Blog\Posts\Posts
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtDate;
use Dotclear\Core\RsExt\RsExtPost;
use Dotclear\Database\Record;
use Dotclear\Database\Cursor;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Text;
use Exception;

/**
 * Posts handling methods.
 *
 * @ingroup  Core Post
 */
class Posts
{
    /**
     * Retrieve entries.
     *
     * <b>$params</b> is an array taking the following optionnal parameters:
     *
     * - no_content: Don't retrieve entry content (excerpt and content)
     * - post_type: Get only entries with given type (default "post", array for many types and '' for no type)
     * - post_id: (integer or array) Get entry with given post_id
     * - post_url: Get entry with given post_url field
     * - user_id: (int) Get entries belonging to given user ID
     * - cat_id: (string or array) Get entries belonging to given category ID
     * - cat_id_not: deprecated (use cat_id with "id ?not" instead)
     * - cat_url: (string or array) Get entries belonging to given category URL
     * - cat_url_not: deprecated (use cat_url with "url ?not" instead)
     * - post_status: (int) Get entries with given post_status
     * - post_selected: (bool) Get select flaged entries
     * - post_year: (int) Get entries with given year
     * - post_month: (int) Get entries with given month
     * - post_day: (int) Get entries with given day
     * - post_lang: Get entries with given language code
     * - search: Get entries corresponding of the following search string
     * - columns: (array) More columns to retrieve
     * - join: Append a JOIN clause for the FROM statement in query
     * - sql: Append SQL string at the end of the query
     * - from: Append another FROM source in query
     * - order: Order of results (default "ORDER BY post_dt DES")
     * - limit: Limit parameter
     * - sql_only : return the sql request instead of results. Only ids are selected
     * - exclude_post_id : (integer or array) Exclude entries with given post_id
     *
     * Please note that on every cat_id or cat_url, you can add ?not to exclude
     * the category and ?sub to get subcategories.
     *
     * @param array           $params     Parameters
     * @param bool            $count_only Only counts results
     * @param SelectStatement $sql        Optional SelectStatement instance
     *
     * @return Record|string A record with some more capabilities or the SQL request
     */
    public function getPosts(ArrayObject|array $params = [], bool $count_only = false, ?SelectStatement $sql = null): string|Record
    {
        $params = new ArrayObject($params);

        // --BEHAVIOR-- coreBlogBeforeGetPosts ArrayObject
        App::core()->behavior()->call('coreBlogBeforeGetPosts', $params);

        if (!$sql) {
            $sql = new SelectStatement(__METHOD__);
        }

        if ($count_only) {
            $sql->column($sql->count($sql->unique('P.post_id')));
        } elseif (!empty($params['sql_only'])) {
            $sql->column('P.post_id');
        } else {
            if (empty($params['no_content'])) {
                $sql->columns([
                    'post_excerpt',
                    'post_excerpt_xhtml',
                    'post_content',
                    'post_content_xhtml',
                    'post_notes',
                ]);
            }

            if (!empty($params['columns']) && is_array($params['columns'])) {
                $sql->columns($params['columns']);
            }
            $sql->columns([
                'P.post_id',
                'P.blog_id',
                'P.user_id',
                'P.cat_id',
                'post_dt',
                'post_creadt',
                'post_upddt',
                'post_format',
                'post_password',
                'post_url',
                'post_lang',
                'post_title',
                'post_type',
                'post_meta',
                'post_status',
                'post_firstpub',
                'post_selected',
                'post_position',
                'post_open_comment',
                'post_open_tb',
                'nb_comment',
                'nb_trackback',
                'U.user_name',
                'U.user_firstname',
                'U.user_displayname',
                'U.user_email',
                'U.user_url',
                'C.cat_title',
                'C.cat_url',
                'C.cat_desc',
            ]);
        }

        $sql
            ->from(App::core()->prefix . 'post P', false, true)
            ->join(
                JoinStatement::init(__METHOD__)
                    ->type('INNER')
                    ->from(App::core()->prefix . 'user U')
                    ->on('U.user_id = P.user_id')
                    ->statement()
            )
            ->join(
                JoinStatement::init(__METHOD__)
                    ->type('LEFT OUTER')
                    ->from(App::core()->prefix . 'category C')
                    ->on('P.cat_id = C.cat_id')
                    ->statement()
            )
        ;

        if (!empty($params['join'])) {
            $sql->join($params['join']);
        }

        if (!empty($params['from'])) {
            $sql->from($params['from']);
        }

        if (!empty($params['where'])) {
            // Cope with legacy code
            $sql->where($params['where']);
        } else {
            $sql->where('P.blog_id = ' . $sql->quote(App::core()->blog()->id, true));
        }

        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $user_id = App::core()->user()->userID();

            $and = ['post_status = 1'];
            if (App::core()->blog()->withoutPassword()) {
                $and[] = 'post_password IS NULL';
            }
            $or = [$sql->andGroup($and)];
            if ($user_id) {
                $or[] = 'P.user_id = ' . $sql->quote($user_id, true);
            }
            $sql->and($sql->orGroup($or));
        }

        // Adding parameters
        if (isset($params['post_type'])) {
            if (is_array($params['post_type']) || '' != $params['post_type']) {
                $sql->and('post_type' . $sql->in($params['post_type']));
            }
        } else {
            $sql->and('post_type = ' . $sql->quote('post'));
        }

        if (isset($params['post_id']) && '' !== $params['post_id']) {
            if (is_array($params['post_id'])) {
                array_walk($params['post_id'], function (&$v, $k) {
                    if (null !== $v) {
                        $v = (int) $v;
                    }
                });
            } else {
                $params['post_id'] = [(int) $params['post_id']];
            }
            $sql->and('P.post_id' . $sql->in($params['post_id']));
        }

        if (isset($params['exclude_post_id']) && '' !== $params['exclude_post_id']) {
            if (is_array($params['exclude_post_id'])) {
                array_walk($params['exclude_post_id'], function (&$v, $k) {
                    if (null !== $v) {
                        $v = (int) $v;
                    }
                });
            } else {
                $params['exclude_post_id'] = [(int) $params['exclude_post_id']];
            }
            $sql->and('P.post_id NOT' . $sql->in($params['exclude_post_id']));
        }

        if (isset($params['post_url']) && '' !== $params['post_url']) {
            $sql->and('post_url = ' . $sql->quote($params['post_url'], true));
        }

        if (!empty($params['user_id'])) {
            $sql->and('U.user_id = ' . $sql->quote($params['user_id'], true));
        }

        if (isset($params['cat_id']) && '' !== $params['cat_id']) {
            if (!is_array($params['cat_id'])) {
                $params['cat_id'] = [$params['cat_id']];
            }
            if (!empty($params['cat_id_not'])) {
                array_walk($params['cat_id'], function (&$v, $k) {
                    $v = $v . ' ?not';
                });
            }

            $sql->and($this->getPostsCategoryFilter($params['cat_id'], 'cat_id'));
        } elseif (isset($params['cat_url']) && '' !== $params['cat_url']) {
            if (!is_array($params['cat_url'])) {
                $params['cat_url'] = [$params['cat_url']];
            }
            if (!empty($params['cat_url_not'])) {
                array_walk($params['cat_url'], function (&$v, $k) {
                    $v = $v . ' ?not';
                });
            }

            $sql->and($this->getPostsCategoryFilter($params['cat_url'], 'cat_url'));
        }

        // Other filters
        if (isset($params['post_status'])) {
            $sql->and('post_status = ' . (int) $params['post_status']);
        }

        if (isset($params['post_firstpub'])) {
            $sql->and('post_firstpub = ' . (int) $params['post_firstpub']);
        }

        if (isset($params['post_selected'])) {
            $sql->and('post_selected = ' . (int) $params['post_selected']);
        }

        if (!empty($params['post_year'])) {
            $sql->and($sql->dateFormat('post_dt', '%Y') . ' = ' . $sql->quote(sprintf('%04d', $params['post_year'])));
        }

        if (!empty($params['post_month'])) {
            $sql->and($sql->dateFormat('post_dt', '%m') . ' = ' . $sql->quote(sprintf('%02d', $params['post_month'])));
        }

        if (!empty($params['post_day'])) {
            $sql->and($sql->dateFormat('post_dt', '%d') . ' = ' . $sql->quote(sprintf('%02d', $params['post_day'])));
        }

        if (!empty($params['post_lang'])) {
            $sql->and('P.post_lang = ' . $sql->quote($params['post_lang'], true));
        }

        if (!empty($params['search'])) {
            $words = Text::splitWords($params['search']);

            if (!empty($words)) {
                // --BEHAVIOR-- corePostSearch
                if (App::core()->behavior()->has('corePostSearch')) {
                    App::core()->behavior()->call('corePostSearch', [&$words, &$params, $sql]);
                }

                foreach ($words as $i => $w) {
                    $words[$i] = $sql->like('post_words', '%' . $sql->escape($w) . '%');
                }
                $sql->and($words);
            }
        }

        if (isset($params['media'])) {
            $sqlExists = SelectStatement::init(__METHOD__)
                ->from(App::core()->prefix . 'post_media M')
                ->column('M.post_id')
                ->where('M.post_id = P.post_id')
            ;

            if (isset($params['link_type'])) {
                $sqlExists->and('M.link_type' . $sqlExists->in($params['link_type']));
            }

            $sql->and(('0' == $params['media'] ? 'NOT ' : '') . 'EXISTS (' . $sqlExists->statement() . ')');
        }

        if (!empty($params['sql'])) {
            $sql->sql($params['sql']);
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $sql->order($sql->escape($params['order']));
            } else {
                $sql->order('post_dt DESC');
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        if (!empty($params['sql_only'])) {
            return $sql->statement();
        }

        $rs = $sql->select();
        $rs->extend(new RsExtPost());

        // --BEHAVIOR-- coreBlogGetPosts
        App::core()->behavior()->call('coreBlogGetPosts', $rs);

        $alt = new ArrayObject(['rs' => $rs, 'params' => $params, 'count_only' => $count_only]);

        // --BEHAVIOR-- coreBlogAfterGetPosts
        App::core()->behavior()->call('coreBlogAfterGetPosts', $rs, $alt);

        return isset($alt['rs']) && $alt['rs'] instanceof Record ? $alt['rs'] : $rs;
    }

    /**
     * Return next or previous post.
     *
     * This returns a record with post id, title and date
     * for next or previous post according to the post ID.
     * $dir could be 1 (next post) or -1 (previous post).
     *
     * @param Record $post                 The post Record
     * @param int    $dir                  The search direction
     * @param bool   $restrict_to_category Restrict to same category
     * @param bool   $restrict_to_lang     Restrict to same language
     *
     * @return null|Record The next or previous post
     */
    public function getNextPost(Record $post, int $dir, bool $restrict_to_category = false, bool $restrict_to_lang = false): ?Record
    {
        $dt      = $post->f('post_dt');
        $post_id = $post->fInt('post_id');

        if (0 < $dir) {
            $sign  = '>';
            $order = 'ASC';
        } else {
            $sign  = '<';
            $order = 'DESC';
        }

        $params['post_type'] = $post->f('post_type');
        $params['limit']     = 1;
        $params['order']     = 'post_dt ' . $order . ', P.post_id ' . $order;
        $params['sql']       = 'AND ( ' .
        "   (post_dt = '" . App::core()->con()->escape($dt) . "' AND P.post_id " . $sign . ' ' . $post_id . ') ' .
        '   OR post_dt ' . $sign . " '" . App::core()->con()->escape($dt) . "' " .
            ') ';

        if ($restrict_to_category) {
            $params['sql'] .= $post->f('cat_id') ? 'AND P.cat_id = ' . $post->fInt('cat_id') . ' ' : 'AND P.cat_id IS NULL ';
        }

        if ($restrict_to_lang) {
            $params['sql'] .= $post->f('post_lang') ? 'AND P.post_lang = \'' . App::core()->con()->escape($post->f('post_lang')) . '\' ' : 'AND P.post_lang IS NULL ';
        }

        $rs = $this->getPosts($params);

        return $rs->isEmpty() ? null : $rs;
    }

    /**
     * Retrieves different languages and post count on blog, based on post_lang field.
     *
     * <b>$params</b> is an array taking the following optionnal parameters:
     *
     * - post_type: Get only entries with given type (default "post", '' for no type)
     * - lang: retrieve post count for selected lang
     * - order: order statement (default post_lang DESC)
     *
     * @param array|ArrayObject $params The parameters
     *
     * @return Record The langs
     */
    public function getLangs(array|ArrayObject $params = []): Record
    {
        $strReq = 'SELECT COUNT(post_id) as nb_post, post_lang ' .
        'FROM ' . App::core()->prefix . 'post ' .
        "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
            "AND post_lang <> '' " .
            'AND post_lang IS NOT NULL ';

        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= 'AND ((post_status = 1 ';

            if (App::core()->blog()->withoutPassword()) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (App::core()->user()->userID()) {
                $strReq .= "OR user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        if (isset($params['post_type'])) {
            if ('' != $params['post_type']) {
                $strReq .= "AND post_type = '" . App::core()->con()->escape($params['post_type']) . "' ";
            }
        } else {
            $strReq .= "AND post_type = 'post' ";
        }

        if (isset($params['lang'])) {
            $strReq .= "AND post_lang = '" . App::core()->con()->escape($params['lang']) . "' ";
        }

        $strReq .= 'GROUP BY post_lang ';

        $order = 'desc';
        if (!empty($params['order']) && preg_match('/^(desc|asc)$/i', $params['order'])) {
            $order = $params['order'];
        }
        $strReq .= 'ORDER BY post_lang ' . $order . ' ';

        return App::core()->con()->select($strReq);
    }

    /**
     * Return a record with all distinct blog dates and post count.
     *
     * <b>$params</b> is an array taking the following optionnal parameters:.
     *
     * - type: (day|month|year) Get days, months or years
     * - year: (int) Get dates for given year
     * - month: (int) Get dates for given month
     * - day: (int) Get dates for given day
     * - cat_id: (int) Category ID filter
     * - cat_url: Category URL filter
     * - post_lang: lang of the posts
     * - next: Get date following match
     * - previous: Get date before match
     * - order: Sort by date "ASC" or "DESC"
     *
     * @param array $params The parameters
     *
     * @return Record The dates
     */
    public function getDates(array $params = []): Record
    {
        $dt_f  = '%Y-%m-%d';
        $dt_fc = '%Y%m%d';
        if (isset($params['type'])) {
            if ('year' == $params['type']) {
                $dt_f  = '%Y-01-01';
                $dt_fc = '%Y0101';
            } elseif ('month' == $params['type']) {
                $dt_f  = '%Y-%m-01';
                $dt_fc = '%Y%m01';
            }
        }
        $dt_f  .= ' 00:00:00';
        $dt_fc .= '000000';

        $cat_field = $catReq = $limit = '';

        if (isset($params['cat_id']) && '' !== $params['cat_id']) {
            $catReq    = 'AND P.cat_id = ' . (int) $params['cat_id'] . ' ';
            $cat_field = ', C.cat_url ';
        } elseif (isset($params['cat_url']) && '' !== $params['cat_url']) {
            $catReq    = "AND C.cat_url = '" . App::core()->con()->escape($params['cat_url']) . "' ";
            $cat_field = ', C.cat_url ';
        }
        if (!empty($params['post_lang'])) {
            $catReq = 'AND P.post_lang = \'' . $params['post_lang'] . '\' ';
        }

        $strReq = 'SELECT DISTINCT(' . App::core()->con()->dateFormat('post_dt', $dt_f) . ') AS dt ' .
        $cat_field .
        ',COUNT(P.post_id) AS nb_post ' .
        'FROM ' . App::core()->prefix . 'post P LEFT JOIN ' . App::core()->prefix . 'category C ' .
        'ON P.cat_id = C.cat_id ' .
        "WHERE P.blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
            $catReq;

        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= 'AND ((post_status = 1 ';

            if (App::core()->blog()->withoutPassword()) {
                $strReq .= 'AND post_password IS NULL ';
            }
            $strReq .= ') ';

            if (App::core()->user()->userID()) {
                $strReq .= "OR P.user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "')";
            } else {
                $strReq .= ') ';
            }
        }

        if (!empty($params['post_type'])) {
            $strReq .= 'AND post_type' . App::core()->con()->in($params['post_type']) . ' ';
        } else {
            $strReq .= "AND post_type = 'post' ";
        }

        if (!empty($params['year'])) {
            $strReq .= 'AND ' . App::core()->con()->dateFormat('post_dt', '%Y') . " = '" . sprintf('%04d', $params['year']) . "' ";
        }

        if (!empty($params['month'])) {
            $strReq .= 'AND ' . App::core()->con()->dateFormat('post_dt', '%m') . " = '" . sprintf('%02d', $params['month']) . "' ";
        }

        if (!empty($params['day'])) {
            $strReq .= 'AND ' . App::core()->con()->dateFormat('post_dt', '%d') . " = '" . sprintf('%02d', $params['day']) . "' ";
        }

        // Get next or previous date
        if (!empty($params['next']) || !empty($params['previous'])) {
            if (!empty($params['next'])) {
                $pdir            = ' > ';
                $params['order'] = 'asc';
                $dt              = $params['next'];
            } else {
                $pdir            = ' < ';
                $params['order'] = 'desc';
                $dt              = $params['previous'];
            }

            $dt = Clock::format(format: 'YmdHis', date: $dt);

            $strReq .= 'AND ' . App::core()->con()->dateFormat('post_dt', $dt_fc) . $pdir . "'" . $dt . "' ";
            $limit = App::core()->con()->limit(1);
        }

        $strReq .= 'GROUP BY dt ' . $cat_field;

        $order = 'desc';
        if (!empty($params['order']) && preg_match('/^(desc|asc)$/i', $params['order'])) {
            $order = $params['order'];
        }

        $strReq .= 'ORDER BY dt ' . $order . ' ' .
            $limit;

        $rs = App::core()->con()->select($strReq);
        $rs->extend(new RsExtDate());

        return $rs;
    }

    /**
     * Create a new entry.
     *
     * Takes a cursor as input and returns the new entry ID.
     *
     * @param Cursor $cur The post cursor
     *
     * @throws CoreException
     */
    public function addPost(Cursor $cur): int
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to create an entry'));
        }

        App::core()->con()->writeLock(App::core()->prefix . 'post');

        try {
            // Get ID
            $rs = App::core()->con()->select(
                'SELECT MAX(post_id) ' .
                'FROM ' . App::core()->prefix . 'post '
            );

            $cur->setField('post_id', $rs->fInt() + 1);
            $cur->setField('blog_id', (string) App::core()->blog()->id);
            $cur->setField('post_creadt', Clock::database());
            $cur->setField('post_upddt', Clock::database());

            // Post excerpt and content
            $this->getPostContent($cur, $cur->getField('post_id'));
            $this->getPostCursor($cur);

            $cur->setField('post_url', $this->getPostURL($cur->getField('post_url'), $cur->getField('post_dt'), $cur->getField('post_title'), $cur->getField('post_id')));

            if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
                $cur->setField('post_status', -2);
            }

            // --BEHAVIOR-- coreBeforePostCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
            App::core()->behavior()->call('coreBeforePostCreate', $this, $cur);

            $cur->insert();
            App::core()->con()->unlock();
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        // --BEHAVIOR-- coreAfterPostCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        App::core()->behavior()->call('coreAfterPostCreate', $this, $cur);

        App::core()->blog()->triggerBlog();

        $this->firstPublicationEntries($cur->getField('post_id'));

        return $cur->getField('post_id');
    }

    /**
     * Update an existing post.
     *
     * @param int    $id  The post identifier
     * @param Cursor $cur The post cursor
     *
     * @throws CoreException
     */
    public function updPost(int $id, Cursor $cur): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to update entries'));
        }

        if (empty($id)) {
            throw new CoreException(__('No such entry ID'));
        }

        // Post excerpt and content
        $this->getPostContent($cur, $id);
        $this->getPostCursor($cur);

        if (null !== $cur->getField('post_url')) {
            $cur->setField('post_url', $this->getPostURL($cur->getField('post_url'), $cur->getField('post_dt'), $cur->getField('post_title'), $id));
        }

        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            $cur->unsetField('post_status');
        }

        $cur->setField('post_upddt', Clock::database());

        // If user is only "usage", we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq = 'SELECT post_id ' .
            'FROM ' . App::core()->prefix . 'post ' .
            'WHERE post_id = ' . $id . ' ' .
            "AND user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' ";

            $rs = App::core()->con()->select($strReq);

            if ($rs->isEmpty()) {
                throw new CoreException(__('You are not allowed to edit this entry'));
            }
        }

        // --BEHAVIOR-- coreBeforePostUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        App::core()->behavior()->call('coreBeforePostUpdate', $this, $cur);

        $cur->update('WHERE post_id = ' . $id . ' ');

        // --BEHAVIOR-- coreBeforePostUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        App::core()->behavior()->call('coreBeforePostUpdate', $this, $cur);

        App::core()->blog()->triggerBlog();

        $this->firstPublicationEntries($id);
    }

    /**
     * Update post status.
     *
     * @param int $id     The identifier
     * @param int $status The status
     */
    public function updPostStatus(int $id, int $status): void
    {
        $this->updPostsStatus([$id], $status);
    }

    /**
     * Update posts status.
     *
     * @param array|ArrayObject $ids    The identifiers
     * @param int               $status The status
     *
     * @throws CoreException
     */
    public function updPostsStatus(array|ArrayObject $ids, int $status): void
    {
        if (!App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry status'));
        }

        $posts_ids = App::core()->blog()->cleanIds($ids);

        $strReq = "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
        'AND post_id' . App::core()->con()->in($posts_ids);

        // If user can only publish, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= "AND user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' ";
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix . 'post');

        $cur->setField('post_status', $status);
        $cur->setField('post_upddt', Clock::database());

        $cur->update($strReq);
        App::core()->blog()->triggerBlog();

        $this->firstPublicationEntries($posts_ids);
    }

    /**
     * Updates post selection.
     *
     * @param int  $id       The identifier
     * @param bool $selected The selected flag
     */
    public function updPostSelected(int $id, bool $selected): void
    {
        $this->updPostsSelected([$id], $selected);
    }

    /**
     * Update posts selection.
     *
     * @param array|ArrayObject $ids      The identifiers
     * @param bool              $selected The selected flag
     *
     * @throws CoreException
     */
    public function updPostsSelected(array|ArrayObject $ids, bool $selected): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry category'));
        }

        $posts_ids = App::core()->blog()->cleanIds($ids);

        $strReq = "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
        'AND post_id' . App::core()->con()->in($posts_ids);

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= "AND user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' ";
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix . 'post');

        $cur->setField('post_selected', (int) $selected);
        $cur->setField('post_upddt', Clock::database());

        $cur->update($strReq);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Update post category.
     *
     * <b>$cat_id</b> can be null.
     *
     * @param int      $id     The identifier
     * @param null|int $cat_id The cat identifier
     */
    public function updPostCategory(int $id, int|null $cat_id): void
    {
        $this->updPostsCategory([$id], $cat_id);
    }

    /**
     * Update posts category.
     *
     * <b>$cat_id</b> can be null.
     *
     * @param array|ArrayObject $ids    The identifiers
     * @param null|int          $cat_id The cat identifier
     *
     * @throws CoreException
     */
    public function updPostsCategory(array|ArrayObject $ids, int|null $cat_id): void
    {
        if (!App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change this entry category'));
        }

        $posts_ids = App::core()->blog()->cleanIds($ids);

        $strReq = "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
        'AND post_id' . App::core()->con()->in($posts_ids);

        // If user is only usage, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= "AND user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' ";
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix . 'post');

        $cur->setField('cat_id', $cat_id ?: null);
        $cur->setField('post_upddt', Clock::database());

        $cur->update($strReq);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Update posts category.
     *
     * <b>$new_cat_id</b> can be null.
     *
     * @param int      $old_cat_id The old cat identifier
     * @param null|int $new_cat_id The new cat identifier
     *
     * @throws CoreException
     */
    public function changePostsCategory(int $old_cat_id, ?int $new_cat_id): void
    {
        if (!App::core()->user()->check('contentadmin,categories', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to change entries category'));
        }

        $cur = App::core()->con()->openCursor(App::core()->prefix . 'post');

        $cur->setField('cat_id', $new_cat_id ?: null);
        $cur->setField('post_upddt', Clock::database());

        $cur->update(
            'WHERE cat_id = ' . $old_cat_id . ' ' .
            "AND blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' "
        );
        App::core()->blog()->triggerBlog();
    }

    /**
     * Delete a post.
     *
     * @param int $id The post identifier
     */
    public function delPost(int $id): void
    {
        $this->delPosts([$id]);
    }

    /**
     * Delete multiple posts.
     *
     * @param array|ArrayObject $ids The posts identifiers
     *
     * @throws CoreException
     */
    public function delPosts(array|ArrayObject $ids): void
    {
        if (!App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete entries'));
        }

        $posts_ids = App::core()->blog()->cleanIds($ids);

        if (empty($posts_ids)) {
            throw new CoreException(__('No such entry ID'));
        }

        $strReq = 'DELETE FROM ' . App::core()->prefix . 'post ' .
        "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
        'AND post_id' . App::core()->con()->in($posts_ids);

        // If user can only delete, we need to check the post's owner
        if (!App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
            $strReq .= "AND user_id = '" . App::core()->con()->escape(App::core()->user()->userID()) . "' ";
        }

        App::core()->con()->execute($strReq);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Publishe all entries flaged as "scheduled".
     */
    public function publishScheduledEntries(): void
    {
        $strReq = 'SELECT post_id, post_dt ' .
        'FROM ' . App::core()->prefix . 'post ' .
        'WHERE post_status = -1 ' .
        "AND blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' ";

        $rs = App::core()->con()->select($strReq);
        if ($rs->isEmpty()) {
            return;
        }

        $to_change = new ArrayObject();
        while ($rs->fetch()) {
            if (Clock::ts() >= Clock::ts(date: $rs->f('post_dt'))) {
                $to_change[] = $rs->fInt('post_id');
            }
        }
        if (count($to_change)) {
            // --BEHAVIOR-- coreBeforeScheduledEntriesPublish, Dotclear\Core\Blog, array
            App::core()->behavior()->call('coreBeforeScheduledEntriesPublish', $this, $to_change);

            $strReq = 'UPDATE ' . App::core()->prefix . 'post SET ' .
            'post_status = 1 ' .
            "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
            'AND post_id' . App::core()->con()->in((array) $to_change) . ' ';
            App::core()->con()->execute($strReq);
            App::core()->blog()->triggerBlog();

            // --BEHAVIOR-- coreAfterScheduledEntriesPublish, Dotclear\Core\Blog, array
            App::core()->behavior()->call('coreAfterScheduledEntriesPublish', $this, $to_change);

            $this->firstPublicationEntries($to_change);
        }
    }

    /**
     * First publication mecanism (on post create, update, publish, status).
     *
     * @param array|ArrayObject|int $ids The posts identifiers
     */
    public function firstPublicationEntries(int|array|ArrayObject $ids): void
    {
        $posts = $this->getPosts([
            'post_id'       => App::core()->blog()->cleanIds($ids),
            'post_status'   => 1,
            'post_firstpub' => 0,
        ]);

        $to_change = new ArrayObject();
        while ($posts->fetch()) {
            $to_change[] = $posts->fInt('post_id');
        }

        if (count($to_change)) {
            $strReq = 'UPDATE ' . App::core()->prefix . 'post ' .
            'SET post_firstpub = 1 ' .
            "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
            'AND post_id' . App::core()->con()->in((array) $to_change) . ' ';
            App::core()->con()->execute($strReq);

            // --BEHAVIOR-- coreFirstPublicationEntries, Dotclear\Core\Blog\Posts\Posts, array
            App::core()->behavior()->call('coreFirstPublicationEntries', $this, $to_change);
        }
    }

    /**
     * Retrieve all users having posts on current blog.
     *
     * @param string $post_type post_type filter (post)
     */
    public function getPostsUsers(string $post_type = 'post'): Record
    {
        $strReq = 'SELECT P.user_id, user_name, user_firstname, ' .
        'user_displayname, user_email ' .
        'FROM ' . App::core()->prefix . 'post P, ' . App::core()->prefix . 'user U ' .
        'WHERE P.user_id = U.user_id ' .
        "AND blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' ";

        if ($post_type) {
            $strReq .= "AND post_type = '" . App::core()->con()->escape($post_type) . "' ";
        }

        $strReq .= 'GROUP BY P.user_id, user_name, user_firstname, user_displayname, user_email ';

        return App::core()->con()->select($strReq);
    }

    /**
     * Parse category query part.
     *
     * @return string The category query part
     */
    private function getPostsCategoryFilter(array $arr, string $field = 'cat_id'): string
    {
        $field = 'cat_id' == $field ? 'cat_id' : 'cat_url';

        $sub     = [];
        $not     = [];
        $queries = [];

        foreach ($arr as $v) {
            $v    = trim($v);
            $args = preg_split('/\s*[?]\s*/', $v, -1, PREG_SPLIT_NO_EMPTY);
            $id   = array_shift($args);
            $args = array_flip($args);

            if (isset($args['not'])) {
                $not[$id] = 1;
            }
            if (isset($args['sub'])) {
                $sub[$id] = 1;
            }
            if ('cat_id' == $field) {
                if (preg_match('/^null$/i', $id)) {
                    $queries[$id] = 'P.cat_id IS NULL';
                } else {
                    $queries[$id] = 'P.cat_id = ' . (int) $id;
                }
            } else {
                $queries[$id] = "C.cat_url = '" . App::core()->con()->escape($id) . "' ";
            }
        }

        if (!empty($sub)) {
            $rs = App::core()->con()->select(
                'SELECT cat_id, cat_url, cat_lft, cat_rgt FROM ' . App::core()->prefix . 'category ' .
                "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
                'AND ' . $field . ' ' . App::core()->con()->in(array_keys($sub))
            );

            while ($rs->fetch()) {
                $queries[$rs->f($field)] = '(C.cat_lft BETWEEN ' . $rs->fInt('cat_lft') . ' AND ' . $rs->fInt('cat_rgt') . ')';
            }
        }

        // Create queries
        $sql = [
            0 => [], // wanted categories
            1 => [], // excluded categories
        ];

        foreach ($queries as $id => $q) {
            $sql[(int) isset($not[$id])][] = $q;
        }

        $sql[0] = implode(' OR ', $sql[0]);
        $sql[1] = implode(' OR ', $sql[1]);

        if ($sql[0]) {
            $sql[0] = '(' . $sql[0] . ')';
        } else {
            unset($sql[0]);
        }

        if ($sql[1]) {
            $sql[1] = '(P.cat_id IS NULL OR NOT(' . $sql[1] . '))';
        } else {
            unset($sql[1]);
        }

        return implode(' AND ', $sql);
    }

    /**
     * Get the post cursor.
     *
     * @param Cursor $cur     The post cursor
     * @param int    $post_id The post identifier
     *
     * @throws CoreException
     */
    private function getPostCursor(Cursor $cur, int $post_id = null): void
    {
        if ('' == $cur->getField('post_title')) {
            throw new CoreException(__('No entry title'));
        }

        if ('' == $cur->getField('post_content')) {
            throw new CoreException(__('No entry content'));
        }

        if ('' === $cur->getField('post_password')) {
            $cur->setField('post_password', null);
        }

        if ('' == $cur->getField('post_dt')) {
            $cur->setField('post_dt', Clock::database());
        }

        $post_id = is_int($post_id) ? $post_id : $cur->getField('post_id');

        if ('' == $cur->getField('post_content_xhtml')) {
            throw new CoreException(__('No entry content'));
        }

        // Words list
        if (null !== $cur->getField('post_title')
            && null !== $cur->getField('post_excerpt_xhtml')
            && null !== $cur->getField('post_content_xhtml')
        ) {
            $words = $cur->getField('post_title') . ' ' .
            $cur->getfield('post_excerpt_xhtml') . ' ' .
            $cur->getField('post_content_xhtml');

            $cur->setField('post_words', implode(' ', Text::splitWords($words)));
        }

        if ($cur->isField('post_firstpub')) {
            $cur->unsetField('post_firstpub');
        }
    }

    /**
     * Get the post content.
     *
     * @param Cursor $cur     The post cursor
     * @param int    $post_id The post identifier
     */
    private function getPostContent(Cursor $cur, int $post_id): void
    {
        $post_excerpt       = $cur->getfield('post_excerpt');
        $post_excerpt_xhtml = $cur->getfield('post_excerpt_xhtml');
        $post_content       = $cur->getfield('post_content');
        $post_content_xhtml = $cur->getfield('post_content_xhtml');

        $this->setPostContent(
            $post_id,
            $cur->getfield('post_format'),
            $cur->getfield('post_lang'),
            $post_excerpt,
            $post_excerpt_xhtml,
            $post_content,
            $post_content_xhtml
        );

        $cur->setfield('post_excerpt', $post_excerpt);
        $cur->setfield('post_excerpt_xhtml', $post_excerpt_xhtml);
        $cur->setfield('post_content', $post_content);
        $cur->setfield('post_content_xhtml', $post_content_xhtml);
    }

    /**
     * Create post HTML content, taking format and lang into account.
     *
     * @param null|int    $post_id       The post identifier
     * @param string      $format        The format
     * @param string      $lang          The language
     * @param null|string $excerpt       The excerpt
     * @param null|string $excerpt_xhtml The excerpt xhtml
     * @param string      $content       The content
     * @param string      $content_xhtml The content xhtml
     */
    public function setPostContent(?int $post_id, string $format, string $lang, ?string &$excerpt, ?string &$excerpt_xhtml, string &$content, string &$content_xhtml): void
    {
        if ('wiki' == $format) {
            App::core()->wiki()->initWikiPost();
            App::core()->wiki()->setOpt('note_prefix', 'pnote-' . ($post_id ?? ''));
            $tag = match (App::core()->blog()->settings()->get('system')->get('note_title_tag')) {
                1       => 'h3',
                2       => 'p',
                default => 'h4',
            };
            App::core()->wiki()->setOpt('note_str', '<div class="footnotes"><' . $tag . ' class="footnotes-title">' .
                __('Notes') . '</' . $tag . '>%s</div>');
            App::core()->wiki()->setOpt('note_str_single', '<div class="footnotes"><' . $tag . ' class="footnotes-title">' .
                __('Note') . '</' . $tag . '>%s</div>');
            if (str_starts_with($lang, 'fr')) {
                App::core()->wiki()->setOpt('active_fr_syntax', 1);
            }
        }

        if ($excerpt) {
            $excerpt_xhtml = App::core()->formater()->callEditorFormater('LegacyEditor', $format, $excerpt);
            $excerpt_xhtml = Html::filter($excerpt_xhtml);
        } else {
            $excerpt_xhtml = '';
        }

        if ($content) {
            $content_xhtml = App::core()->formater()->callEditorFormater('LegacyEditor', $format, $content);
            $content_xhtml = Html::filter($content_xhtml);
        } else {
            $content_xhtml = '';
        }

        // --BEHAVIOR-- coreAfterPostContentFormat, array
        App::core()->behavior()->call('coreAfterPostContentFormat', [
            'excerpt'       => &$excerpt,
            'content'       => &$content,
            'excerpt_xhtml' => &$excerpt_xhtml,
            'content_xhtml' => &$content_xhtml,
        ]);
    }

    /**
     * Return URL for a post according to blog setting <b>post_url_format</b>.
     *
     * It will try to guess URL and append some figures if needed.
     *
     * @param null|string $url        The url
     * @param null|string $post_dt    The post dt
     * @param null|string $post_title The post title
     * @param null|int    $post_id    The post identifier
     *
     * @return string The post url
     */
    public function getPostURL(?string $url, ?string $post_dt, ?string $post_title, ?int $post_id): string
    {
        $url = trim((string) $url);

        // Date on post URLs always use core timezone (should not changed if blog timezone changed)
        $url_patterns = [
            '{y}'  => Clock::format(format: 'Y', date: $post_dt),
            '{m}'  => Clock::format(format: 'm', date: $post_dt),
            '{d}'  => Clock::format(format: 'd', date: $post_dt),
            '{t}'  => Text::tidyURL((string) $post_title),
            '{id}' => (int) $post_id,
        ];

        // If URL is empty, we create a new one
        if ('' == $url) {
            // Transform with format
            $url = str_replace(
                array_keys($url_patterns),
                array_values($url_patterns),
                App::core()->blog()->settings()->get('system')->get('post_url_format')
            );
        } else {
            $url = Text::tidyURL($url);
        }

        // Let's check if URL is taken...
        $strReq = 'SELECT post_url FROM ' . App::core()->prefix . 'post ' .
        "WHERE post_url = '" . App::core()->con()->escape($url) . "' " .
        'AND post_id <> ' . (int) $post_id . ' ' .
        "AND blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
            'ORDER BY post_url DESC';

        $rs = App::core()->con()->select($strReq);

        if (!$rs->isEmpty()) {
            if (App::core()->con()->syntax() == 'mysql') {
                $clause = "REGEXP '^" . App::core()->con()->escape(preg_quote($url)) . "[0-9]+$'";
            } elseif (App::core()->con()->driver() == 'pgsql') {
                $clause = "~ '^" . App::core()->con()->escape(preg_quote($url)) . "[0-9]+$'";
            } else {
                $clause = "LIKE '" .
                App::core()->con()->escape(preg_replace(['/%/', '/_/', '/!/'], ['!%', '!_', '!!'], $url)) . "%' ESCAPE '!'";
            }
            $strReq = 'SELECT post_url FROM ' . App::core()->prefix . 'post ' .
            'WHERE post_url ' . $clause . ' ' .
            'AND post_id <> ' . (int) $post_id . ' ' .
            "AND blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' " .
                'ORDER BY post_url DESC ';

            $rs = App::core()->con()->select($strReq);
            $a  = [];
            while ($rs->fetch()) {
                $a[] = $rs->f('post_url');
            }

            natsort($a);
            $t_url = end($a);

            if ($a && preg_match('/(.*?)([0-9]+)$/', $t_url, $m)) {
                $i   = (int) $m[2];
                $url = $m[1];
            } else {
                $i = 1;
            }

            return $url . ($i + 1);
        }

        // URL is empty?
        if ('' == $url) {
            throw new CoreException(__('Empty entry URL'));
        }

        return $url;
    }
}
