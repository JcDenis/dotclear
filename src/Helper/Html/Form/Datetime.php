<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\Datetime

/**
 * HTML Forms datetime field creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Datetime extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string      $id    The identifier
     * @param null|string $value The value
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'datetime-local');
        $this
            ->set('size', 16)
            ->set('maxlength', 16)
            ->set('pattern', '[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}')
            ->set('placeholder', '1962-05-13T14:45')
        ;
        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
