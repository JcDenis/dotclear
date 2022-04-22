<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\Radio

/**
 * HTML Forms radio button creation helpers.
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
     * @param string    $id      The identifier
     * @param null|bool $checked Is checked
     */
    public function __construct(?string $id = null, ?bool $checked = null)
    {
        parent::__construct($id, 'radio');
        if (null !== $checked) {
            $this->set('checked', $checked);
        }
    }
}
