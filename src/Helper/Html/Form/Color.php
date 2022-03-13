<?php
/**
 * @class Dotclear\Html\Form\Color
 * @brief HTML Forms color field creation helpers
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage html.form.
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Html\Form;

use Dotclear\Html\Form\Input;

class Color extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string  $id     The identifier
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'color');
        $this
            ->size(7)
            ->maxlength(7);
        if ($value !== null) {
            $this->value($value);
        }
    }
}
