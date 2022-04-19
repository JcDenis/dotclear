<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Categories;

use Dotclear\Database\NestedTree;
use Dotclear\Database\Record;

/**
 * Categories tree handling.
 *
 * \Dotclear\Core\Blog\Categories\CategoriesTree
 *
 * @ingroup  Core Category
 */
class CategoriesTree extends NestedTree
{
    protected $f_left  = 'cat_lft';
    protected $f_right = 'cat_rgt';
    protected $f_id    = 'cat_id';

    protected $blog_id;

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        parent::__construct(dotclear()->con());

        $this->blog_id       = dotclear()->blog()->id;
        $this->table         = dotclear()->prefix . 'category';
        $this->add_condition = ['blog_id' => "'" . dotclear()->con()->escape($this->blog_id) . "'"];
    }

    /**
     * Gets the category children.
     *
     * @param int      $start  The start
     * @param null|int $id     The identifier
     * @param string   $sort   The sort
     * @param array    $fields The fields
     *
     * @return Record the children
     */
    public function getChildren(int $start = 0, ?int $id = null, string $sort = 'asc', array $fields = []): Record
    {
        $fields = array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields);

        return parent::getChildren($start, $id, $sort, $fields);
    }

    /**
     * Gets the parents.
     *
     * @param int   $id     The identifier
     * @param array $fields The fields
     *
     * @return Record the parents
     */
    public function getParents(int $id, array $fields = []): Record
    {
        $fields = array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields);

        return parent::getParents($id, $fields);
    }

    /**
     * Gets the parent.
     *
     * @param int   $id     The identifier
     * @param array $fields The fields
     *
     * @return Record the parent
     */
    public function getParent(int $id, array $fields = []): Record
    {
        $fields = array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields);

        return parent::getParent($id, $fields);
    }
}
