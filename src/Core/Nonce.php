<?php
/**
 * @brief Nonce core class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use dcCore;
use Dotclear\Helper\Html\Form\Hidden;

class Nonce
{
    /** @var    string  The nonce pattern */
    public const NONCE_PATTERN = '/^([0-9a-f]{40,})$/i';

    /**
     * Get the nonce.
     *
     * @deprecated since 2.27, use dcCore::app()->nonce->get() instead
     *
     * @return  string  The nonce
     */
    public function get(): string
    {
        return dcCore::app()->auth->cryptLegacy(session_id());
    }

    /**
     * Check the nonce.
     *
     * @param   string  $secret  The nonce
     *
     * @return  bool    True on success
     */
    public function check(string $secret): bool
    {
        // 40 alphanumeric characters min
        if (!preg_match(self::NONCE_PATTERN, $secret)) {
            return false;
        }

        return $secret == dcCore::app()->auth->cryptLegacy(session_id());
    }

    /**
     * Get the nonce Form element.
     *
     * @param   bool    $render     Should render element?
     *
     * @return  Hidden  The form element
     */
    public function form(): Hidden
    {
        return new Hidden(['xd_check'], session_id() ? $this->get() : '');
    }
}