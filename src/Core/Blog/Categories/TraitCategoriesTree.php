<?php
/**
 * @class Dotclear\Core\Blog\Categories\TraitCategoriesTree
 * @brief Dotclear trait blog categories nested tree
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Categories;

use Dotclear\Core\Blog\Categories\CategoriesTree;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitCategoriesTree
{
    /** @var    CategoriesTree   CategoriesTree instance */
    private $categoriestree;

    /**
     * Get instance
     *
     * @return  CategoriesTree   CategoriesTree instance
     */
    protected function categoriestree(): CategoriesTree
    {
        if (!($this->categoriestree instanceof CategoriesTree)) {
            $this->categoriestree = new CategoriesTree();
        }

        return $this->categoriestree;
    }
}
