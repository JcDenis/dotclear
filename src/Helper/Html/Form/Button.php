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
 * @class Button
 * @brief HTML Forms input type=button field creation helpers
 */
class Button extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string|array{0: string, 1?: string}|null     $id       The identifier
     * @param      string                                       $value    The value
     */
    public function __construct($id = null, ?string $value = null)
    {
        parent::__construct($id, 'button');
        if ($value !== null) {
            $this->value($value);
        }
    }
}
