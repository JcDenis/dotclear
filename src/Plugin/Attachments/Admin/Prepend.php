<?php
/**
 * @note Dotclear\Plugin\Attachments\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginAttachments
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

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
