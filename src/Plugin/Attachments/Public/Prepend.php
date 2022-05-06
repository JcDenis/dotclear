<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Public;

// Dotclear\Plugin\Attachments\Public\Prepend
use Dotclear\App;
use Dotclear\Modules\ModulePrepend;

/**
 * Public prepend for plugin Attachments.
 *
 * @ingroup  Plugin Attachments
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        if (!App::core()->media()) {
            return;
        }

        new AttachmentsTemplate();
    }
}
