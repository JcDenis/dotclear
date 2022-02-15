<?php
/**
 * @class Dotclear\Admin\ListOption\TraitListOption
 * @brief Dotclear trait admin list options (preference)
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\ListOption;

use Dotclear\Admin\ListOption\ListOption;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitListOption
{
    /** @var    ListOption   ListOption instance */
    private $listoption;

    /**
     * Get instance
     *
     * @return  ListOption   ListOption instance
     */
    public function listoption(): ListOption
    {
        if (!($this->listoption instanceof ListOption)) {
            $this->listoption = new ListOption();
        }

        return $this->listoption;
    }
}
