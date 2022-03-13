<?php
/**
 * @class Dotclear\Html\Form\Date
 * @brief HTML Forms date field creation helpers
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

class Date extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string  $id     The identifier
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'date');
        $this
            ->size(10)
            ->maxlength(10)
            ->pattern('[0-9]{4}-[0-9]{2}-[0-9]{2}')
            ->placeholder('1962-05-13');
        if ($value !== null) {
            $this->value($value);
        }
    }
}
