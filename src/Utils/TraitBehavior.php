<?php
/**
 * @class Dotclear\Utils\TraitBehavior
 * @brief Dotclear trait Behavior
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Utils;

use Dotclear\Utils\Behavior;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitBehavior
{
    /** @var    Configuration   Configuration instance */
    private $behavior;

    /**
     * Get instance
     */
    public function behavior()
    {
        if (!($this->behavior instanceof Behavior)) {
            $this->behavior = new Behavior();
        }

        return $this->behavior;
    }
}
