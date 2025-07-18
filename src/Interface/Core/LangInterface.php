<?php

/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Interface\Core;

/**
 * @brief   Lang handler interface.
 *
 * @since   2.28
 */
interface LangInterface
{
    /**
     * The default lang (code).
     *
     * @var     string  DEFAULT_LANG
     */
    public const DEFAULT_LANG = 'en';

    /**
     * Get the lang.
     *
     * @return  string  The lang code.
     */
    public function getLang(): string;

    /**
     * Set the lang.
     *
     * @param   string  $lang     The lang code
     */
    public function setLang(string $lang): void;
}
