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
 * HTML Forms password field creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Password
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Password extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string $id The identifier
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'password');
        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
