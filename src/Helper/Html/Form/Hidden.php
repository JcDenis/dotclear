<?php
/**
 * @class Dotclear\Html\Form\Hidden
 * @brief HTML Forms hidden field creation helpers
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

class Hidden extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string  $id     The identifier
     */
    public function __construct(string $id = null, ?string $value = null)
    {
        // Label should not be rendered for an input type="hidden"
        parent::__construct($id, 'hidden', false);
        if ($value !== null) {
            $this->value($value);
        }
    }
}

/** Backwards compatibility */
class_alias('Clearbricks\Html\Form\Hidden', 'formHidden');
