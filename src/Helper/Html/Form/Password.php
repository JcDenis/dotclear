<?php

/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

/**
 * @class Password
 * @brief HTML Forms password field creation helpers
 */
class Password extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string|list{0: string, 1?: string}|null      $id       The identifier
     * @param      string                                       $value    The value
     */
    public function __construct(string|array|null $id = null, ?string $value = null)
    {
        parent::__construct($id, 'password');
        // Default attributes for a password, may be supercharge after
        $this->autocorrect('off');
        $this->spellcheck(false);
        $this->autocapitalize('off');
        if ($value !== null) {
            $this->value($value);
        }
    }
}
