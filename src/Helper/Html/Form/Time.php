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
 * @class Time
 * @brief HTML Forms time field creation helpers
 */
class Time extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string|list{0: string, 1?: string}|null      $id         The identifier
     * @param      string                                       $value      The value
     */
    public function __construct(string|array|null $id = null, ?string $value = null)
    {
        parent::__construct($id, 'time');
        $this
            ->size(5)
            ->maxlength(5)
            ->pattern('[0-9]{2}:[0-9]{2}')
            ->placeholder('14:45');

        if ($value !== null) {
            $this->value($value);
        }
    }
}
