<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

/**
 * Admin prepend for plugin Attachments.
 *
 * \Dotclear\Plugin\Attachments\Admin\Prepend
 *
 * @ingroup  Plugin Attachments
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        if (!dotclear()->media()) {
            return;
        }

        new AttachmentsBehavior();
    }
}
