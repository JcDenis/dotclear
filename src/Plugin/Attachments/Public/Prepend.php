<?php
/**
 * @note Dotclear\Plugin\Attachments\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @ingroup  PluginAttachments
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        if (!dotclear()->media()) {
            return;
        }

        new AttachmentsTemplate();
    }
}
