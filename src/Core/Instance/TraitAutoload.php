<?php
/**
 * @class Dotclear\Core\Instance\TraitAutoload
 * @brief Dotclear trait Log
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Utils\Autoload;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitAutoload
{
    /** @var    Autoload   Autoload instance */
    private $autoload;

    /**
     * Get instance
     *
     * @return  Autoload   Autoload instance
     */
    public function autoload(): Autoload
    {
        if (!($this->autoload instanceof Autoload)) {
            $this->autoload = new Autoload('', '', true);
        }

        return $this->autoload;
    }
}
