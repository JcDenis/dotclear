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
 * HTML Forms hidden field creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Hidden
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
     * @param string $id The identifier
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
