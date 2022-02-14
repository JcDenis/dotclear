<?php
/**
 * @class Dotclear\Core\Instance\Nonce
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

use Dotclear\Core\Instance\Nonce;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitNonce
{
    /** @var    Nonce   Nonce instance */
    private $nonce;

    /**
     * Get instance
     *
     * @return  Nonce   Nonce instance
     */
    public function nonce(): Nonce
    {
        if (!($this->nonce instanceof Nonce)) {
            $this->nonce = new Nonce();
        }

        return $this->nonce;
    }
}
