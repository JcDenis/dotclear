<?php
/**
 * @class Dotclear\Admin\Preference\TraitPreference
 * @brief Dotclear trait admin combos
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Preference;

use Dotclear\Admin\Preference\Preference;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitPreference
{
    /** @var    Preference   Preference instance */
    private $preference;

    /**
     * Get instance
     *
     * @return  Preference   Preference instance
     */
    public function preference(): Preference
    {
        if (!($this->preference instanceof Preference)) {
            $this->preference = new Preference();
        }

        return $this->preference;
    }
}
