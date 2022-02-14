<?php
/**
 * @class Dotclear\Core\Instance\Categories
 * @brief Dotclear core Categories class
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use ArrayObject;

use Dotclear\Database\StaticRecord;
use Dotclear\Database\Record;
use Dotclear\Database\Cursor;
use Dotclear\Exception\CoreException;
use Dotclear\Html\Html;
use Dotclear\Utils\Text;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Categories
{
    use \Dotclear\Core\Instance\TraitCategoriesTree;

    /**
     * Retrieves categories. <var>$params</var> is an associative array which can
     * take the following parameters:
     *
     * - post_type: Get only entries with given type (default "post")
     * - cat_url: filter on cat_url field
     * - cat_id: filter on cat_id field
     * - start: start with a given category
     * - level: categories level to retrieve
     *
     * @param      ArrayObject|array   $params  The parameters
     *
     * @return     Record  The categories. (StaticRecord)
     */
    public function getCategories(ArrayObject|array $params = []): Record
    {
        $c_params = [];
        if (isset($params['post_type'])) {
            $c_params['post_type'] = $params['post_type'];
            unset($params['post_type']);
        }
        $counter = $this->getCategoriesCounter($c_params);

        if (isset($params['without_empty']) && ($params['without_empty'] == false)) {
            $without_empty = false;
        } else {
            $without_empty = dotclear()->auth()->userID() == false; # Get all categories if in admin display
        }

        $start = isset($params['start']) ? (int) $params['start'] : 0;
        $l     = isset($params['level']) ? (int) $params['level'] : 0;

        $rs = $this->categoriestree()->getChildren($start, null, 'desc');

        # Get each categories total posts count
        $data  = [];
        $stack = [];
        $level = 0;
        $cols  = $rs->columns();
        while ($rs->fetch()) {
            $nb_post = isset($counter[$rs->cat_id]) ? (int) $counter[$rs->cat_id] : 0;

            if ($rs->level > $level) {
                $nb_total          = $nb_post;
                $stack[$rs->level] = (int) $nb_post;
            } elseif ($rs->level == $level) {
                $nb_total = $nb_post;
                $stack[$rs->level] += $nb_post;
            } else {
                $nb_total = $stack[$rs->level + 1] + $nb_post;
                if (isset($stack[$rs->level])) {
                    $stack[$rs->level] += $nb_total;
                } else {
                    $stack[$rs->level] = $nb_total;
                }
                unset($stack[$rs->level + 1]);
            }

            if ($nb_total == 0 && $without_empty) {
                continue;
            }

            $level = $rs->level;

            $t = [];
            foreach ($cols as $c) {
                $t[$c] = $rs->f($c);
            }
            $t['nb_post']  = $nb_post;
            $t['nb_total'] = $nb_total;

            if ($l == 0 || ($l > 0 && $l == $rs->level)) {
                array_unshift($data, $t);
            }
        }

        # We need to apply filter after counting
        if (isset($params['cat_id']) && $params['cat_id'] !== '') {
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

        if (isset($params['cat_url']) && ($params['cat_url'] !== '')
            && !isset($params['cat_id'])) {
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
     * @param      int  $id     The category identifier
     *
     * @return     Record  The category. (StaticRecord)
     */
    public function getCategory(int $id): Record
    {
        return $this->getCategories(['cat_id' => $id]);
    }

    /**
     * Gets the category parents.
     *
     * @param      int  $id     The category identifier
     *
     * @return     Record  The category parents. (StaticRecord)
     */
    public function getCategoryParents(int $id): Record
    {
        return $this->categoriestree()->getParents($id);
    }

    /**
     * Gets the category first parent.
     *
     * @param      int  $id     The category identifier
     *
     * @return     Record  The category parent. (StaticRecord)
     */
    public function getCategoryParent(int $id): Record
    {
        return $this->categoriestree()->getParent($id);
    }

    /**
     * Gets all category's first children.
     *
     * @param      int     $id     The category identifier
     *
     * @return     Record  The category first children. (StaticRecord)
     */
    public function getCategoryFirstChildren(int $id): Record
    {
        return $this->getCategories(['start' => $id, 'level' => $id == 0 ? 1 : 2]);
    }

    /**
     * Returns true if a given category if in a given category's subtree
     *
     * @param      string   $cat_url    The cat url
     * @param      string   $start_url  The top cat url
     *
     * @return     bool     true if cat_url is in given start_url cat subtree
     */
    public function IsInCatSubtree(string $cat_url, string $start_url): bool
    {
        // Get cat_id from start_url
        $cat = $this->getCategories(['cat_url' => $start_url]);
        if ($cat->fetch()) {
            // cat_id found, get cat tree list
            $cats = $this->getCategories(['start' => $cat->cat_id]);
            while ($cats->fetch()) {
                // check if post category is one of the cat or sub-cats
                if ($cats->cat_url === $cat_url) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gets the categories posts counter.
     *
     * @param      array  $params  The parameters
     *
     * @return     array  The categories counter.
     */
    private function getCategoriesCounter(array $params = []): array
    {
        $strReq = 'SELECT  C.cat_id, COUNT(P.post_id) AS nb_post ' .
        'FROM ' . dotclear()->prefix . 'category AS C ' .
        'JOIN ' . dotclear()->prefix . "post P ON (C.cat_id = P.cat_id AND P.blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ) " .
        "WHERE C.blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";

        if (!dotclear()->auth()->userID()) {
            $strReq .= 'AND P.post_status = 1 ';
        }

        if (!empty($params['post_type'])) {
            $strReq .= 'AND P.post_type ' . dotclear()->con()->in($params['post_type']);
        }

        $strReq .= 'GROUP BY C.cat_id ';

        $rs       = dotclear()->con()->select($strReq);
        $counters = [];
        while ($rs->fetch()) {
            $counters[$rs->cat_id] = $rs->nb_post;
        }

        return $counters;
    }

    /**
     * Adds a new category. Takes a cursor as input and returns the new category ID.
     *
     * @param      Cursor        $cur     The category cursor
     * @param      int           $parent  The parent category ID
     *
     * @throws     CoreException
     *
     * @return     int  New category ID
     */
    public function addCategory(Cursor $cur, int $parent = 0): int
    {
        if (!dotclear()->auth()->check('categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to add categories'));
        }

        $url = [];
        if ($parent != 0) {
            $rs = $this->getCategory($parent);
            if ($rs->isEmpty()) {
                $url = [];
            } else {
                $url[] = $rs->cat_url;
            }
        }

        if ($cur->cat_url == '') {
            $url[] = Text::tidyURL($cur->cat_title, false);
        } else {
            $url[] = $cur->cat_url;
        }

        $cur->cat_url = implode('/', $url);

        $this->getCategoryCursor($cur);
        $cur->blog_id = (string) dotclear()->blog()->id;

        # --BEHAVIOR-- coreBeforeCategoryCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreBeforeCategoryCreate', $this, $cur);

        $id = $this->categoriestree()->addNode($cur, $parent);
        if ($id !== null) {
            # Update category's cursor
            $rs = $this->getCategory($id);
            if (!$rs->isEmpty()) {
                $cur->cat_lft = $rs->cat_lft;
                $cur->cat_rgt = $rs->cat_rgt;
            }
        }

        # --BEHAVIOR-- coreAfterCategoryCreate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreAfterCategoryCreate', $this, $cur);

        dotclear()->blog()->triggerBlog();

        return (int) $cur->cat_id;
    }

    /**
     * Updates an existing category.
     *
     * @param      int     $id     The category ID
     * @param      Cursor      $cur    The category cursor
     *
     * @throws     CoreException
     */
    public function updCategory(int $id, Cursor $cur): void
    {
        if (!dotclear()->auth()->check('categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to update categories'));
        }

        if ($cur->cat_url == '') {
            $url = [];
            $rs  = $this->categoriestree()->getParents($id);
            while ($rs->fetch()) {
                if ($rs->index() == $rs->count() - 1) {
                    $url[] = $rs->cat_url;
                }
            }

            $url[]        = Text::tidyURL($cur->cat_title, false);
            $cur->cat_url = implode('/', $url);
        }

        $this->getCategoryCursor($cur, $id);

        # --BEHAVIOR-- coreBeforeCategoryUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreBeforeCategoryUpdate', $this, $cur);

        $cur->update(
            'WHERE cat_id = ' . (int) $id . ' ' .
            "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
        );

        # --BEHAVIOR-- coreAfterCategoryUpdate, Dotclear\Core\Blog, Dotclear\Database\Cursor
        dotclear()->behavior()->call('coreAfterCategoryUpdate', $this, $cur);

        dotclear()->blog()->triggerBlog();
    }

    /**
     * Set category position.
     *
     * @param      int  $id     The category ID
     * @param      int  $left   The category ID before
     * @param      int  $right  The category ID after
     */
    public function updCategoryPosition(int $id, int $left, int $right): void
    {
        $this->categoriestree()->updatePosition($id, $left, $right);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Sets the category parent.
     *
     * @param      int  $id      The category ID
     * @param      int  $parent  The parent category ID
     */
    public function setCategoryParent(int $id, int $parent): void
    {
        $this->categoriestree()->setNodeParent($id, $parent);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Sets the category position.
     *
     * @param      int      $id       The category ID
     * @param      int      $sibling  The sibling category ID
     * @param      string   $move     The move (before|after)
     */
    public function setCategoryPosition(int $id, int $sibling, string $move): void
    {
        $this->categoriestree()->setNodePosition($id, $sibling, $move);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Delete a category.
     *
     * @param      int     $id     The category ID
     *
     * @throws     CoreException
     */
    public function delCategory(int $id): void
    {
        if (!dotclear()->auth()->check('categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to delete categories'));
        }

        $strReq = 'SELECT COUNT(post_id) AS nb_post ' .
        'FROM ' . dotclear()->prefix . 'post ' .
        'WHERE cat_id = ' . (int) $id . ' ' .
        "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";

        $rs = dotclear()->con()->select($strReq);

        if ($rs->nb_post > 0) {
            throw new CoreException(__('This category is not empty.'));
        }

        $this->categoriestree()->deleteNode($id, true);
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Reset categories order and relocate them to first level
     */
    public function resetCategoriesOrder(): void
    {
        if (!dotclear()->auth()->check('categories', dotclear()->blog()->id)) {
            throw new CoreException(__('You are not allowed to reset categories order'));
        }

        $this->categoriestree()->resetOrder();
        dotclear()->blog()->triggerBlog();
    }

    /**
     * Check if the category title and url are unique.
     *
     * @param      string       $title  The title
     * @param      string       $url    The url
     * @param      null|int     $id     The identifier
     *
     * @return     string
     */
    private function checkCategory(string $title, string $url, ?int $id = null): string
    {
        # Let's check if URL is taken...
        $strReq = 'SELECT cat_url FROM ' . dotclear()->prefix . 'category ' .
        "WHERE cat_url = '" . dotclear()->con()->escape($url) . "' " .
        ($id ? 'AND cat_id <> ' . (int) $id . ' ' : '') .
        "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            'ORDER BY cat_url DESC';

        $rs = dotclear()->con()->select($strReq);

        if (!$rs->isEmpty()) {
            if (dotclear()->con()->syntax() == 'mysql') {
                $clause = "REGEXP '^" . dotclear()->con()->escape($url) . "[0-9]+$'";
            } elseif (dotclear()->con()->driver() == 'pgsql') {
                $clause = "~ '^" . dotclear()->con()->escape($url) . "[0-9]+$'";
            } else {
                $clause = "LIKE '" . dotclear()->con()->escape($url) . "%'";
            }
            $strReq = 'SELECT cat_url FROM ' . dotclear()->prefix . 'category ' .
            'WHERE cat_url ' . $clause . ' ' .
            ($id ? 'AND cat_id <> ' . (int) $id . ' ' : '') .
            "AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
                'ORDER BY cat_url DESC ';

            $rs = dotclear()->con()->select($strReq);

            if ($rs->isEmpty()) {
                return $url;
            }

            $a  = [];
            while ($rs->fetch()) {
                $a[] = $rs->cat_url;
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

        # URL is empty?
        if ($url == '') {
            throw new CoreException(__('Empty category URL'));
        }

        return $url;
    }

    /**
     * Gets the category cursor.
     *
     * @param      Cursor       $cur    The category cursor
     * @param      null|int     $id     The category ID
     *
     * @throws     CoreException
     */
    private function getCategoryCursor(Cursor $cur, ?int $id = null): void
    {
        if ($cur->cat_title == '') {
            throw new CoreException(__('You must provide a category title'));
        }

        # If we don't have any cat_url, let's do one
        if ($cur->cat_url == '') {
            $cur->cat_url = Text::tidyURL($cur->cat_title, false);
        }

        # Still empty ?
        if ($cur->cat_url == '') {
            throw new CoreException(__('You must provide a category URL'));
        }
        $cur->cat_url = Text::tidyURL($cur->cat_url, true);

        # Check if title or url are unique
        $cur->cat_url = $this->checkCategory($cur->cat_title, $cur->cat_url, $id);

        if ($cur->cat_desc !== null) {
            $cur->cat_desc = Html::filter($cur->cat_desc);
        }
    }
}
