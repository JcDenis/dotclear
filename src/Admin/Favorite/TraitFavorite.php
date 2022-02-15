<?php
/**
 * @class Dotclear\Admin\Favorite\TraitFavorite
 * @brief Dotclear trait admin combos
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Favorite;

use Dotclear\Admin\Favorite\Favorite;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitFavorite
{
    /** @var    Favorite   Favorite instance */
    private $favorite;

    /**
     * Get instance
     *
     * @return  Favorite   Favorite instance
     */
    public function favorite(): Favorite
    {
        if (!($this->favorite instanceof Favorite)) {
            $this->favorite = new Favorite();
        }

        return $this->favorite;
    }
}
