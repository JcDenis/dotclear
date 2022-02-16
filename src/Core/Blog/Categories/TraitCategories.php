<?php
/**
 * @class Dotclear\Core\Blog\Categories\TraitCategories
 * @brief Dotclear trait blog categories
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Categories;

use Dotclear\Core\Blog\Categories\Categories;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitCategories
{
    /** @var    Categories   Categories instance */
    private $categories;

    /**
     * Get instance
     *
     * @return  Categories   Categories instance
     */
    public function categories(): Categories
    {
        if (!($this->categories instanceof Categories)) {
            $this->categories = new Categories();
        }

        return $this->categories;
    }
}
