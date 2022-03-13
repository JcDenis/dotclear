<?php
/**
 * @class Dotclear\Html\Form\Url
 * @brief HTML Forms url field creation helpers
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage html.form
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Html\Form;

use Dotclear\Html\Form\Input;

class Url extends Input
{
    /**
     * Constructs a new instance.
     *
     * @param      string  $id     The identifier
     */
    public function __construct(string $id = null)
    {
        parent::__construct($id, 'url');
    }
}
