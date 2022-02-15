<?php
/**
 * @class Dotclear\Admin\Combo\TraitCombo
 * @brief Dotclear trait admin combos
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Combo;

use Dotclear\Admin\Combo\Combo;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitCombo
{
    /** @var    Combo   Combo instance */
    private $combo;

    /**
     * Get instance
     *
     * @return  Combo   Combo instance
     */
    public function combo(): Combo
    {
        if (!($this->combo instanceof Combo)) {
            $this->combo = new Combo();
        }

        return $this->combo;
    }
}
