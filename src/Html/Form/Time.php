<?php
/**
 * @class Dotclear\Html\Form\Time
 * @brief HTML Forms time field creation helpers
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage html.form
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Html\Form;

use Dotclear\Html\Form\Input;

class Time extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string  $id     The identifier
     */
    public function __construct(?string $id = null, ?string $value = null)
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
