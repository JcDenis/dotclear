<?php
/**
 * @class Dotclear\Core\Instance\TraitSession
 * @brief Dotclear trait Session
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Session;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitSession
{
    /** @var    Session   Session instance */
    private $session;

    /**
     * Get instance
     *
     * @return  Session   Session instance
     */
    public function session(): Session
    {
        if (!($this->session instanceof Session)) {
            $this->session = new Session();
        }

        return $this->session;
    }
}
