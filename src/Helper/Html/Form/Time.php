<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\Time

/**
 * HTML Forms time field creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Time extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string  $id    The identifier
     * @param ?string $value
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'time');
        $this
            ->set('size', 5)
            ->set('maxlength', 5)
            ->set('pattern', '[0-9]{2}:[0-9]{2}')
            ->set('placeholder', '14:45')
        ;

        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
