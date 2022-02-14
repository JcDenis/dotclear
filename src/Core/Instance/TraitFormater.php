<?php
/**
 * @class Dotclear\Core\Instance\TraitFormater
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

use Dotclear\Core\Instance\Formater;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitFormater
{
    /** @var    Formater   Formater instance */
    private $formater;

    /**
     * Get instance
     *
     * @return  Formater   Formater instance
     */
    public function formater(): Formater
    {
        if (!($this->formater instanceof Formater)) {
            $this->formater = new Formater();
        }

        return $this->formater;
    }
}
