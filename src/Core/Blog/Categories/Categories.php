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
use ArrayObject;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\StaticRecord;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Text;

/**
 * Categories handling methods.
 *
 * @ingroup  Core Category
 */
class Categories
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
    protected function categoriestree(): CategoriesTree
    {
        if (!($this->categoriestree instanceof CategoriesTree)) {
            $this->categoriestree = new CategoriesTree();
        }

        return $this->categoriestree;
    }

    /**
     * Retrieves categories. <var>$params</var> is an associative array which can
     * take the following parameters:.
     *
     * - post_type: Get only entries with given type (default "post")
     * - cat_url: filter on cat_url field
     * - cat_id: filter on cat_id field
     * - start: start with a given category
     * - level: categories level to retrieve
     *
     * @param array|ArrayObject $params The parameters
     *
     * @return Record The categories. (StaticRecord)
     */
    public function getCategories(array|ArrayObject $params = []): Record
    {
        $c_params = [];
        if (isset($params['post_type'])) {
            $c_params['post_type'] = $params['post_type'];
            unset($params['post_type']);
        }
        $counter = $this->getCategoriesCounter($c_params);

        if (isset($params['without_empty']) && (false == $params['without_empty'])) {
            $without_empty = false;
        } else {
            $without_empty = false == dotclear()->user()->userID(); // Get all categories if in admin display
        }

        $start = isset($params['start']) ? (int) $params['start'] : 0;
        $l     = isset($params['level']) ? (int) $params['level'] : 0;

        $rs = $this->categoriestree()->getChildren($start, null, 'desc');

        // Get each categories total posts count
        $data  = [];
        $stack = [];
        $level = 0;
        $cols  = $rs->columns();
        while ($rs->fetch()) {
            $nb_post = isset($counter[$rs->f('cat_id')]) ? (int) $counter[$rs->f('cat_id')] : 0;

            if ($rs->f('level') > $level) {
                $nb_total               = $nb_post;
                $stack[$rs->f('level')] = (int) $nb_post;
            } elseif ($rs->f('level') == $level) {
                $nb_total = $nb_post;
                $stack[$rs->f('level')] += $nb_post;
            } else {
                $nb_total = $stack[$rs->f('level') + 1] + $nb_post;
                if (isset($stack[$rs->f('level')])) {
                    $stack[$rs->f('level')] += $nb_total;
                } else {
                    $stack[$rs->f('level')] = $nb_total;
                }
                unset($stack[$rs->f('level') + 1]);
            }

            if (0 == $nb_total && $without_empty) {
                continue;
            }

            $level = $rs->f('level');

            $t = [];
            foreach ($cols as $c) {
                $t[$c] = $rs->f($c);
            }
            $t['nb_post']  = $nb_post;
            $t['nb_total'] = $nb_total;

            if (0 == $l || 0 < $l && $rs->f('level') == $l) {
                array_unshift($data, $t);
            }
        }

        // We need to apply filter after counting
        if (isset($params['cat_id']) && '' !== $params['cat_id']) {
            $found = false;
            foreach ($data as $v) {
                if ($v['cat_id'] == $params['cat_id']) {
                    $found = true;
                    $data  = [$v];

                    break;
                }
            }
            if (!$found) {
                $data = [];
            }
        }

        if (isset($params['cat_url']) && '' !== $params['cat_url'] && !isset($params['cat_id'])) {
            $found = false;
            foreach ($data as $v) {
                if ($v['cat_url'] == $params['cat_url']) {
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
     * Gets the category by its ID.
     *
     * @param int $id The category identifier
     *
     * @return Record The category. (StaticRecord)
     */
    public function getCategory(int $id): Record
    {
        return $this->getCategories(['cat_id' => $id]);
    }

    /**
     * Gets the category parents.
     *
     * @param int $id The category identifier
     *
     * @return Record The category parents. (StaticRecord)
     */
    public function getCategoryParents(int $id): Record
    {
        return $this->categoriestree()->getParents($id);
    }

    /**
     * Gets the category first parent.
     *
     * @param int $id The category identifier
     *
     * @return Record The category parent. (StaticRecord)
     */
    public function getCategoryParent(int $id): Record
    {
        return $this->categoriestree()->getParent($id);
    }

    /**
     * Gets all category's first children.
     *
     * @param int $id The category identifier
     *
     * @return Record The category first children. (StaticRecord)
     */
    public function getCategoryFirstChildren(int $id): Record
    {
        return $this->getCategories(['start' => $id, 'level' => 0 == $id ? 1 : 2]);
    }

    /**
     * Returns true if a given category if in a given category's subtree.
     *
     * @param string $cat_url   The cat url
     * @param string $start_url The top cat url
     *
     * @return bool True if cat_url is in given start_url cat subtree
     */
    public function IsInCatSubtree(string $cat_url, string $start_url): bool
    {
        // Get cat_id from start_url
        $cat = $this->getCategories(['cat_url' => $start_url]);
        if ($cat->fetch()) {
            // cat_id found, get cat tree list
            $cats = $this->getCategories(['start' => $cat->f('cat_id')]);
            while ($cats->fetch()) {
                // check if post category is one of the cat or sub-cats
                if ($cats->f('cat_url') === $cat_url) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gets the categories posts counter.
     *
     * @param array|ArrayObject $params The parameters
     *
     * @return array<int, int> the categories counter
     */
    private function getCategoriesCounter(array|ArrayObject $params = []): array
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->columns([
                'C.cat_id',
                $sql->count('P.post_id', 'nb_post'),
            ])
            ->from(dotclear()->prefix . 'category AS C')
            ->join(
                JoinStatement::init(__METHOD__)
                    ->from(dotclear()->prefix . 'post P')
                    ->on('C.cat_id = P.cat_id')
                    ->and('P.blog_id = ' . $sql->quote(dotclear()->blog()->id))
                    ->statement()
            )
            ->where('C.blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->group('C.cat_id')
        ;

        if (!dotclear()->user()->userID()) {
            $sql->and('P.post_status = 1');
        }

        if (!empty($params['post_type'])) {
            $sql->and('P.post_type' . $sql->in($params['post_type']));
        }

        $rs       = $sql->select();
        $counters = [];
        while ($rs->fetch()) {
            $counters[$rs->fInt('cat_id')] = $rs->fInt('nb_post');
        }

        return $counters;
    }

    /**
     * Adds a new category. Takes a cursor as input and returns the new category ID.
     *
     * @param Cursor $cur    The category cursor
     * @param int    $parent The parent category ID
     *
     * @throws CoreException
     *
     * @return int New category ID
     */
    public function addCategory(Cursor $cur, int $parent = 0): int
    {
        if (!dotclear()->user()->check('categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to add categories'));
        }

        $url = [];
        if (0 != $parent) {
            $rs = $this->getCategory($parent);
            if ($rs->isEmpty()) {
                $url = [];
            } else {
                $url[] = $rs->f('cat_url');
            }
        }

        if ('' == $cur->getField('cat_url')) {
            $url[] = Text::tidyURL($cur->getField('cat_title'), false);
        } else {
            $url[] = $cur->getField('cat_url');
        }

        $cur->setField('cat_url', implode('/', $url));

        $this->getCategoryCursor($cur);
        $cur->setField('blog_id', (string) dotclear()->blog()->id);

        // --BEHAVIOR-- coreBeforeCategoryCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreBeforeCategoryCreate', $this, $cur);

        $id = $this->categoriestree()->addNode($cur, $parent);
        if (false !== $id) {
            // Update category's cursor
            $rs = $this->getCategory($id);
            if (!$rs->isEmpty()) {
                $cur->setField('cat_lft', $rs->f('cat_lft'));
                $cur->setField('cat_rgt', $rs->f('cat_rgt'));
            }
        }

        // --BEHAVIOR-- coreAfterCategoryCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreAfterCategoryCreate', $this, $cur);

        dotclear()->blog()->triggerBlog();

        return (int) $cur->getField('cat_id');
    }

    /**
     * Updates an existing category.
     *
     * @param int    $id  The category ID
     * @param Cursor $cur The category cursor
     *
     * @throws CoreException
     */
    public function updCategory(int $id, Cursor $cur): void
    {
        if (!dotclear()->user()->check('categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to update categories'));
        }

        if ('' == $cur->getField('cat_url')) {
            $url = [];
            $rs  = $this->categoriestree()->getParents($id);
            while ($rs->fetch()) {
                if ($rs->index() == $rs->count() - 1) {
                    $url[] = $rs->f('cat_url');
                }
            }

            $url[] = Text::tidyURL($cur->getField('cat_title'), false);
            $cur->setField('cat_url', implode('/', $url));
        }

        $this->getCategoryCursor($cur, $id);

        // --BEHAVIOR-- coreBeforeCategoryUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreBeforeCategoryUpdate', $this, $cur);

        $cur->update(
            'WHERE cat_id = ' . (int) $id . ' ' .
            "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
        );

        // --BEHAVIOR-- coreAfterCategoryUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreAfterCategoryUpdate', $this, $cur);

        dotclear()->blog()->triggerBlog();
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
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Sets the category parent.
     *
     * @param int $id     The category ID
     * @param int $parent The parent category ID
     */
    public function setCategoryParent(int $id, int $parent): void
    {
        $this->categoriestree()->setNodeParent($id, $parent);
        dotclear()->blog()->triggerBlog();
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
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Delete a category.
     *
     * @param int $id The category ID
     *
     * @throws CoreException
     */
    public function delCategory(int $id): void
    {
        if (!dotclear()->user()->check('categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete categories'));
        }

        $sql = new SelectStatement(__METHOD__);
        $rs  = $sql
            ->column($sql->count('post_id', 'nb_post'))
            ->from(dotclear()->prefix . 'post')
            ->where('cat_id = ' . $id)
            ->and('blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->select()
        ;

        if (0 < $rs->f('nb_post')) {
            throw new CoreException(__('This category is not empty.'));
        }

        $this->categoriestree()->deleteNode($id, true);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Reset categories order and relocate them to first level.
     */
    public function resetCategoriesOrder(): void
    {
        if (!dotclear()->user()->check('categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to reset categories order'));
        }

        $this->categoriestree()->resetOrder();
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Check if the category title and url are unique.
     *
     * @param string   $title The title
     * @param string   $url   The url
     * @param null|int $id    The identifier
     *
     * @return string The cateogry URL
     */
    private function checkCategory(string $title, string $url, ?int $id = null): string
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->column('cat_url')
            ->from(dotclear()->prefix . 'category')
            ->where('cat_url = ' . $sql->quote($url))
            ->and('blog_id = ' . $sql->quote(dotclear()->blog()->id))
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
                ->from(dotclear()->prefix . 'category')
                ->where('cat_url' . $sql->regexp($url))
                ->and('blog_id = ' . $sql->quote(dotclear()->blog()->id))
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
            throw new CoreException(__('Empty category URL'));
        }

        return $url;
    }

    /**
     * Gets the category cursor.
     *
     * @param Cursor   $cur The category cursor
     * @param null|int $id  The category ID
     *
     * @throws CoreException
     */
    private function getCategoryCursor(Cursor $cur, ?int $id = null): void
    {
        if ('' == $cur->getField('cat_title')) {
            throw new CoreException(__('You must provide a category title'));
        }

        // If we don't have any cat_url, let's do one
        if ('' == $cur->getField('cat_url')) {
            $cur->setField('cat_url', Text::tidyURL($cur->getField('cat_title'), false));
        }

        // Still empty ?
        if ('' == $cur->getField('cat_url')) {
            throw new CoreException(__('You must provide a category URL'));
        }
        $cur->setField('cat_url', Text::tidyURL($cur->getField('cat_url'), true));

        // Check if title or url are unique
        $cur->setField('cat_url', $this->checkCategory($cur->getField('cat_title'), $cur->getField('cat_url'), $id));

        if (null !== $cur->getField('cat_desc')) {
            $cur->setField('cat_desc', Html::filter($cur->getField('cat_desc')));
        }
    }
}
