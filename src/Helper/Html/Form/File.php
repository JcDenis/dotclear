<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\File

/**
 * HTML Forms file field creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class File extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param string      $id    The identifier
     * @param null|string $value The value
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct($id, 'file');
        if (null !== $value) {
            $this->set('value', $value);
        }
    }
}
