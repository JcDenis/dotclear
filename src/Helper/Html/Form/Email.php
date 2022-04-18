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
 * HTML Forms email field creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Email
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Email extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string $id The identifier
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'email');
        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
