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
 * @class File
 * @brief HTML Forms file field creation helpers
 */
class File extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string|array{0: string, 1?: string}|null     $id       The identifier
     * @param      string                                       $value    The value
     */
    public function __construct(string|array|null $id = null, ?string $value = null)
    {
        parent::__construct($id, 'file');
        if ($value !== null) {
            $this->value($value);
        }
    }
}
