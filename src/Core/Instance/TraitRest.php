<?php
/**
 * @class Dotclear\Core\Instance\TraitRest
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

use Dotclear\Core\Instance\Rest;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitRest
{
    /** @var    Rest   Rest instance */
    private $rest;

    /**
     * Get instance
     *
     * @return  Rest   Rest instance
     */
    public function rest(): Rest
    {
        if (!($this->rest instanceof Rest)) {
            $this->rest = new Rest();
        }

        return $this->rest;
    }
}
