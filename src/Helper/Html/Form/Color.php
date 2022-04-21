<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\Color

/**
 * HTML Forms color field creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Color extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string  $id    The identifier
     * @param ?string $value
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'color');
        $this
            ->set('size', 7)
            ->set('maxlength', 7)
        ;
        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
