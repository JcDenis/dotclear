<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Categories;

// Dotclear\Core\Blog\Categories\CategoriesTree
use Dotclear\App;
use Dotclear\Database\NestedTree;
use Dotclear\Database\Record;

/**
 * Categories tree handling.
 *
 * @ingroup  Core Category
 */
class CategoriesTree extends NestedTree
{
    /**
     * @var string $f_left
     *             The left category field name
     */
    protected $f_left  = 'cat_lft';

    /**
     * @var string $f_right
     *             The right category field name
     */
    protected $f_right = 'cat_rgt';

    /**
     * @var string $f_id
     *             The category id field name
     */
    protected $f_id = 'cat_id';

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        parent::__construct(App::core()->con());

        $this->table         = App::core()->prefix . 'category';
        $this->add_condition = ['blog_id' => "'" . App::core()->con()->escape(App::core()->blog()->id) . "'"];
    }

    /**
     * Gets the category children.
     *
     * @param int      $start  The start
     * @param null|int $id     The identifier
     * @param string   $sort   The sort
     * @param array    $fields The fields
     *
     * @return Record The children
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
     * @return Record The parents
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
     * @return Record The parent
     */
    public function getParent(int $id, array $fields = []): Record
    {
        $fields = array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields);

        return parent::getParent($id, $fields);
    }
}
