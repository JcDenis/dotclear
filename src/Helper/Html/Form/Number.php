<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\Number

/**
 * HTML Forms number field creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Number extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string   $id    The identifier
     * @param null|int $min   The min
     * @param null|int $max   The max
     * @param null|int $value The value
     */
    public function __construct(string $id = null, ?int $min = null, ?int $max = null, ?int $value = null)
    {
        parent::__construct($id, 'number');
        $this
            ->set('min', $min)
            ->set('max', $max)
        ;
        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
