<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\Hidden

/**
 * HTML Forms hidden field creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Hidden extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string  $id    The identifier
     * @param ?string $value
     */
    public function __construct(string $id = null, ?string $value = null)
    {
        // Label should not be rendered for an input type="hidden"
        parent::__construct($id, 'hidden', false);
        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
