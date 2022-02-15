<?php
/**
 * @class Dotclear\Admin\Menu\TraitSummary
 * @brief Dotclear trait admin combos
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Menu;

use Dotclear\Admin\Menu\Summary;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitSummary
{
    /** @var    Summary   Summary instance */
    private $summary;

    /**
     * Get instance
     *
     * @return  Summary   Summary instance
     */
    public function summary(): Summary
    {
        if (!($this->summary instanceof Summary)) {
            $this->summary = new Summary();
        }

        return $this->summary;
    }
}
