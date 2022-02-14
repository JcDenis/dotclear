<?php
/**
 * @class Dotclear\Core\Instance\TraitCategories
 * @brief Dotclear trait Categories
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Categories;

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
