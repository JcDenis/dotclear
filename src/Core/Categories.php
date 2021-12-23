<?php
/**
 * @class Dotclear\Core\Categories
 * @brief Dotclear core categories tree class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Core\Core;

use Dotclear\Database\NestedTree;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Categories extends NestedTree
{
    protected $f_left  = 'cat_lft';
    protected $f_right = 'cat_rgt';
    protected $f_id    = 'cat_id';

    protected $core;
    protected $blog_id;

    /**
     * Constructs a new instance.
     *
     * @param      Core  $core   The core
     */
    public function __construct(Core $core)
    {
        $this->core          = &$core;
        $this->con           = &$core->con;
        $this->blog_id       = $core->blog->id;
        $this->table         = $core->prefix . 'category';
        $this->add_condition = ['blog_id' => "'" . $this->con->escape($this->blog_id) . "'"];
    }

    /**
     * Gets the category children.
     *
     * @param      int     $start   The start
     * @param      mixed   $id      The identifier
     * @param      string  $sort    The sort
     * @param      array   $fields  The fields
     *
     * @return     record  The children.
     */
    public function getChildren($start = 0, $id = null, $sort = 'asc', $fields = [])
    {
        $fields = array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields);

        return parent::getChildren($start, $id, $sort, $fields);
    }

    /**
     * Gets the parents.
     *
     * @param      int     $id      The category identifier
     * @param      array   $fields  The fields
     *
     * @return     record  The parents.
     */
    public function getParents($id, $fields = [])
    {
        $fields = array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields);

        return parent::getParents($id, $fields);
    }

    /**
     * Gets the parent.
     *
     * @param      integer  $id      The category identifier
     * @param      array    $fields  The fields
     *
     * @return     record  The parent.
     */
    public function getParent($id, $fields = [])
    {
        $fields = array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields);

        return parent::getParent($id, $fields);
    }
}
