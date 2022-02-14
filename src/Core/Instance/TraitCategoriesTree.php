<?php
/**
 * @class Dotclear\Core\Instance\TraitCategoriesTree
 * @brief Dotclear trait Categories nested tree
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\CategoriesTree;

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
