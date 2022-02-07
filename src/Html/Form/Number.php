<?php
/**
 * @class Number
 * @brief HTML Forms number field creation helpers
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

class Number extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string  $id     The identifier
     */
    public function __construct(string $id = null, ?int $min = null, ?int $max = null, ?int $value = null)
    {
        parent::__construct($id, 'number');
        $this
            ->min($min)
            ->max($max);
        if ($value !== null) {
            $this->value($value);
        }
    }
}