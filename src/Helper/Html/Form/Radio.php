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
 * HTML Forms radio button creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Radio
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Radio extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string $id The identifier
     */
    public function __construct(?string $id = null, ?bool $checked = null)
    {
        parent::__construct($id, 'radio');
        if (null !== $checked) {
            $this->set('checked', $checked);
        }
    }
}
