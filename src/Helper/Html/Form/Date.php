<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

/**
 * HTML Forms date field creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Date
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Date extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string $id The identifier
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'date');
        $this
            ->set('size', 10)
            ->set('maxlength', 10)
            ->set('pattern', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
            ->set('placeholder', '1962-05-13')
        ;
        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
