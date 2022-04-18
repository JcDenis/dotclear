<?php
/**
 * @note Dotclear\Core\Nonce\Nonce
 * @brief Dotclear core nonce class
 *
 * @ingroup  Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Nonce;

use Dotclear\Helper\Html\Form;

class Nonce
{
    /**
     * Gets the nonce.
     *
     * @return string the nonce
     */
    public function get(): string
    {
        return dotclear()->user()->cryptLegacy(session_id());
    }

    /**
     * Check the nonce.
     *
     * @param string $secret The nonce
     *
     * @return bool The success
     */
    public function check(string $secret): bool
    {
        return preg_match('/^([0-9a-f]{40,})$/i', $secret) ? $this->get() == $secret : false;
    }

    /**
     * Get the nonce HTML code.
     *
     * @return null|string HTML hidden form for nonce
     */
    public function form(): ?string
    {
        return session_id() ? Form::hidden(['xd_check'], $this->get()) : null;
    }
}
