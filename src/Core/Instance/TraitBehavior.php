<?php
/**
 * @class Dotclear\UtCore\Instanceils\TraitBehavior
 * @brief Dotclear trait Behavior
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Behavior;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitBehavior
{
    /** @var    Behavior    Behavior instance */
    private $behavior;

    /**
     * Get instance
     *
     * @return  Behavior    Behavior instance
     */
    public function behavior(): Behavior
    {
        if (!($this->behavior instanceof Behavior)) {
            $this->behavior = new Behavior();
        }

        return $this->behavior;
    }
}
