<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Categories;

// Dotclear\Core\Blog\Categories\Categories
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\StaticRecord;
use Dotclear\Exception\InsufficientPermissions;
use Dotclear\Exception\InvalidValueReference;
use Dotclear\Exception\MissingOrEmptyValue;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Text;

/**
 * Categories handling methods.
 *
 * @ingroup  Core Category
 */
final class Categories
{
    /**
     * @var CategoriesTree $categoriestree
     *                     CategoriesTree instance
     */
    private $categoriestree;

    /**
     * Get instance.
     *
     * @return CategoriesTree CategoriesTree instance
     */
    private function categoriestree(): CategoriesTree
    {
        if (!($this->categoriestree instanceof CategoriesTree)) {
            $this->categoriestree = new CategoriesTree();
        }

        return $this->categoriestree;
    }

    /**
     * Get the category by its ID.
     *
     * @param int $id The category ID
     *
     * @return Record The category. (StaticRecord)
     */
    public function getCategory(int $id): Record
    {
        $param = new Param();
        $param->set('car_id', $id);

        return $this->getCategories(param: $param);
    }

    /**
     * Retrieve categories.
     *
     * @see CategoriesParam for optionnal parameters
     *
     * @param null|Param $param The parameters
     *
     * @return Record The categories. (StaticRecord)
     */
    public function getCategories(?Param $param = null): Record
    {
        $params = new CategoriesParam($param);

        // Find and use post_type only for posts count
        $c_params = clone $params;
        $params->unset('post_type');
        $counter = $this->getCategoriesPostsCount(param: $c_params);

        if (false === $params->without_empty()) {
            $without_empty = false;
        } else {
            $without_empty = false == App::core()->user()->userID(); // Get all categories if in admin display
        }

        $record = $this->categoriestree()->getChildren(start: $params->start(), sort: 'desc');

        // Get each categories total posts count
        $data  = [];
        $stack = [];
        $level = 0;
        $cols  = $record->columns();
        while ($record->fetch()) {
            $nb_post = $counter[$record->fInt('cat_id')] ?? 0;

            if ($record->fInt('level') > $level) {
                $nb_total                      = $nb_post;
                $stack[$record->fInt('level')] = $nb_post;
            } elseif ($record->fInt('level') == $level) {
                $nb_total                       = $nb_post;
                $stack[$record->fInt('level')] += $nb_post;
            } else {
                $nb_total = $stack[$record->fInt('level') + 1] + $nb_post;
                if (isset($stack[$record->fInt('level')])) {
                    $stack[$record->fInt('level')] += $nb_total;
                } else {
                    $stack[$record->fInt('level')] = $nb_total;
                }
                unset($stack[$record->fInt('level') + 1]);
            }

            if (0 == $nb_total && $without_empty) {
                continue;
            }

            $level = $record->fInt('level');

            $t = [];
            foreach ($cols as $c) {
                $t[$c] = $record->f($c);
            }
            $t['nb_post']  = $nb_post;
            $t['nb_total'] = $nb_total;

            if (0 == $params->level() || 0 < $params->level() && $record->fInt('level') == $params->level()) {
                array_unshift($data, $t);
            }
        }

        // We need to apply filter after counting
        if (null !== $params->cat_id()) {
            $found = false;
            foreach ($data as $v) {
                if ($params->cat_id() == $v['cat_id']) {
                    $found = true;
                    $data  = [$v];

                    break;
                }
            }
            if (!$found) {
                $data = [];
            }
        }

        if (null !== $params->cat_url() && null === $params->cat_id()) {
            $found = false;
            foreach ($data as $v) {
                if ($params->cat_url() == $v['cat_url']) {
                    $found = true;
                    $data  = [$v];

                    break;
                }
            }
            if (!$found) {
                $data = [];
            }
        }

        return StaticRecord::newFromArray($data);
    }

    /**
     * Get the category first parent.
     *
     * @param int $id The category ID
     *
     * @return Record The category parent. (StaticRecord)
     */
    public function getCategoryParent(int $id): Record
    {
        return $this->categoriestree()->getParent(id: $id);
    }

    /**
     * Get the category parents.
     *
     * @param int $id The category ID
     *
     * @return Record The category parents. (StaticRecord)
     */
    public function getCategoryParents(int $id): Record
    {
        return $this->categoriestree()->getParents(id: $id);
    }

    /**
     * Get all category's first children.
     *
     * @param int $id The category ID
     *
     * @return Record The category first children. (StaticRecord)
     */
    public function getCategoryFirstChildren(int $id): Record
    {
        $param = new Param();
        $param->set('start', $id);
        $param->set('level', 0 == $id ? 1 : 2);

        return $this->getCategories(param: $param);
    }

    /**
     * Check if a given category is in a given category's subtree.
     *
     * Comparison is done on categories URL.
     *
     * @param string $url    The category URL
     * @param string $parent The top category URL
     *
     * @return bool True if category URL is in given category subtree
     */
    public function isInCatSubtree(string $url, string $parent): bool
    {
        $param = new Param();
        $param->set('cat_url', $parent);

        // Get cat_id from start_url
        $cat = $this->getCategories(param: $param);
        if ($cat->fetch()) {
            $param->unset('cat_url');
            $param->set('start', $cat->fInt('cat_id'));

            // cat_id found, get cat tree list
            $cats = $this->getCategories(param: $param);
            while ($cats->fetch()) {
                // check if post category is one of the cat or sub-cats
                if ($cats->f('cat_url') === $url) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the categories posts counter.
     *
     * @param CategoriesParam $param The parameters
     *
     * @return array<int,int> The categories counter
     */
    private function getCategoriesPostsCount(CategoriesParam $param): array
    {
        $join = new JoinStatement(__METHOD__);
        $join->from(App::core()->prefix() . 'post P');
        $join->on('C.cat_id = P.cat_id');
        $join->and('P.blog_id = ' . $join->quote(App::core()->blog()->id));

        $sql = new SelectStatement(__METHOD__);
        $sql->columns([
            'C.cat_id',
            $sql->count('P.post_id', 'nb_post'),
        ]);
        $sql->from(App::core()->prefix() . 'category AS C');
        $sql->join($join->statement());
        $sql->where('C.blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->group('C.cat_id');

        if (!App::core()->user()->userID()) {
            $sql->and('P.post_status = 1');
        }

        if (!empty($param->post_type())) {
            $sql->and('P.post_type' . $sql->in($param->post_type()));
        }

        $counters = [];
        $record   = $sql->select();
        while ($record->fetch()) {
            $counters[$record->fInt('cat_id')] = $record->fInt('nb_post');
        }

        return $counters;
    }

    /**
     * Add a new category.
     *
     * Takes a cursor as input and returns the new category ID.
     *
     * @param Cursor $cursor The category cursor
     * @param int    $parent The parent category ID
     *
     * @throws InsufficientPermissions
     *
     * @return int The new category ID
     */
    public function addCategory(Cursor $cursor, int $parent = 0): int
    {
        if (!App::core()->user()->check('categories', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to add categories'));
        }

        $url = [];
        if (0 != $parent) {
            $rs = $this->getCategory(id: $parent);
            if ($rs->isEmpty()) {
                $url = [];
            } else {
                $url[] = $rs->f('cat_url');
            }
        }

        if ('' == $cursor->getField('cat_url')) {
            $url[] = Text::tidyURL($cursor->getField('cat_title'), false);
        } else {
            $url[] = $cursor->getField('cat_url');
        }

        $cursor->setField('cat_url', implode('/', $url));

        $this->getCategoryCursor(cursor: $cursor);
        $cursor->setField('blog_id', (string) App::core()->blog()->id);

        // --BEHAVIOR-- coreBeforeCategoryCreate, Cursor
        App::core()->behavior()->call('coreBeforeCategoryCreate', $cursor);

        $id = $this->categoriestree()->addNode(cursor: $cursor, parent: $parent);
        if (false !== $id) {
            // Update category's cursor
            $rs = $this->getCategory(id: $id);
            if (!$rs->isEmpty()) {
                $cursor->setField('cat_lft', $rs->f('cat_lft'));
                $cursor->setField('cat_rgt', $rs->f('cat_rgt'));
            }
        }

        // --BEHAVIOR-- coreAfterCategoryCreate, Cursor
        App::core()->behavior()->call('coreAfterCategoryCreate', $cursor);

        App::core()->blog()->triggerBlog();

        return (int) $cursor->getField('cat_id');
    }

    /**
     * Update an existing category.
     *
     * @param int    $id     The category ID
     * @param Cursor $cursor The category cursor
     *
     * @throws InsufficientPermissions
     */
    public function updCategory(int $id, Cursor $cursor): void
    {
        if (!App::core()->user()->check('categories', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to update categories'));
        }

        if ('' == $cursor->getField('cat_url')) {
            $url = [];
            $rs  = $this->categoriestree()->getParents(id: $id);
            while ($rs->fetch()) {
                if ($rs->index() == $rs->count() - 1) {
                    $url[] = $rs->f('cat_url');
                }
            }

            $url[] = Text::tidyURL($cursor->getField('cat_title'), false);
            $cursor->setField('cat_url', implode('/', $url));
        }

        $this->getCategoryCursor(cursor: $cursor, id: $id);

        // --BEHAVIOR-- coreBeforeCategoryUpdate, Cursor
        App::core()->behavior()->call('coreBeforeCategoryUpdate', $cursor);

        $cursor->update(
            'WHERE cat_id = ' . (int) $id . ' ' .
            "AND blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' "
        );

        // --BEHAVIOR-- coreAfterCategoryUpdate,Cursor
        App::core()->behavior()->call('coreAfterCategoryUpdate', $cursor);

        App::core()->blog()->triggerBlog();
    }

    /**
     * Set category position.
     *
     * @param int $id    The category ID
     * @param int $left  The category ID before
     * @param int $right The category ID after
     */
    public function updCategoryPosition(int $id, int $left, int $right): void
    {
        $this->categoriestree()->updatePosition($id, $left, $right);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Set the category parent.
     *
     * @param int $id     The category ID
     * @param int $parent The parent category ID
     */
    public function setCategoryParent(int $id, int $parent): void
    {
        $this->categoriestree()->setNodeParent($id, $parent);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Sets the category position.
     *
     * @param int    $id      The category ID
     * @param int    $sibling The sibling category ID
     * @param string $move    The move (before|after)
     */
    public function setCategoryPosition(int $id, int $sibling, string $move): void
    {
        $this->categoriestree()->setNodePosition($id, $sibling, $move);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Delete a category.
     *
     * And keep their children if any.
     *
     * @param int $id The category ID
     *
     * @throws InsufficientPermissions
     * @throws InvalidValueReference
     */
    public function delCategory(int $id): void
    {
        if (!App::core()->user()->check('categories', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to delete categories'));
        }

        $sql = new SelectStatement(__METHOD__);
        $sql->column($sql->count('post_id', 'nb_post'));
        $sql->from(App::core()->prefix() . 'post');
        $sql->where('cat_id = ' . $id);
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));

        $record = $sql->select();
        if (0 < $record->f('nb_post')) {
            throw new InvalidValueReference(__('This category is not empty.'));
        }

        $this->categoriestree()->deleteNode(node: $id);
        App::core()->blog()->triggerBlog();
    }

    /**
     * Reset categories order and relocate them to first level.
     *
     * @throws InsufficientPermissions
     */
    public function resetCategoriesOrder(): void
    {
        if (!App::core()->user()->check('categories', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('You are not allowed to reset categories order'));
        }

        $this->categoriestree()->resetOrder();
        App::core()->blog()->triggerBlog();
    }

    /**
     * Check if the category title and url are unique.
     *
     * @param string   $title The title
     * @param string   $url   The url
     * @param null|int $id    The identifier
     *
     * @throws MissingOrEmptyValue
     *
     * @return string The cateogry URL
     */
    private function checkCategory(string $title, string $url, ?int $id = null): string
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->column('cat_url')
            ->from(App::core()->prefix() . 'category')
            ->where('cat_url = ' . $sql->quote($url))
            ->and('blog_id = ' . $sql->quote(App::core()->blog()->id))
            ->order('cat_url DESC')
        ;

        if (null !== $id) {
            $sql->and('cat_id <> ' . $id);
        }

        $rs = $sql->select();

        if (!$rs->isEmpty()) {
            $sql = new SelectStatement(__METHOD__);
            $sql
                ->column('cat_url')
                ->from(App::core()->prefix() . 'category')
                ->where('cat_url' . $sql->regexp($url))
                ->and('blog_id = ' . $sql->quote(App::core()->blog()->id))
                ->order('cat_url DESC')
            ;

            if (null !== $id) {
                $sql->and('cat_id <> ' . $id);
            }

            $rs = $sql->select();

            if ($rs->isEmpty()) {
                return $url;
            }

            $a = [];
            while ($rs->fetch()) {
                $a[] = $rs->f('cat_url');
            }

            natsort($a);
            $t_url = end($a);

            if (preg_match('/(.*?)([0-9]+)$/', $t_url, $m)) {
                $i   = (int) $m[2];
                $url = $m[1];
            } else {
                $i = 1;
            }

            return $url . ($i + 1);
        }

        // URL is empty?
        if ('' == $url) {
            throw new MissingOrEmptyValue(__('Empty category URL'));
        }

        return $url;
    }

    /**
     * Get the category cursor.
     *
     * @param Cursor   $cursor The category cursor
     * @param null|int $id     The category ID
     *
     * @throws MissingOrEmptyValue
     */
    private function getCategoryCursor(Cursor $cursor, ?int $id = null): void
    {
        if ('' == $cursor->getField('cat_title')) {
            throw new MissingOrEmptyValue(__('You must provide a category title'));
        }

        // If we don't have any cat_url, let's do one
        if ('' == $cursor->getField('cat_url')) {
            $cursor->setField('cat_url', Text::tidyURL($cursor->getField('cat_title'), false));
        }

        // Still empty ?
        if ('' == $cursor->getField('cat_url')) {
            throw new MissingOrEmptyValue(__('You must provide a category URL'));
        }
        $cursor->setField('cat_url', Text::tidyURL($cursor->getField('cat_url'), true));

        // Check if title or url are unique
        $cursor->setField('cat_url', $this->checkCategory(
            title: $cursor->getField('cat_title'),
            url: $cursor->getField('cat_url'),
            id: $id
        ));

        if (null !== $cursor->getField('cat_desc')) {
            $cursor->setField('cat_desc', Html::filter($cursor->getField('cat_desc')));
        }
    }
}
