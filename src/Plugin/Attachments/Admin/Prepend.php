<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Admin;

// Dotclear\Plugin\Attachments\Admin\Prepend
use Dotclear\App;
use Dotclear\Modules\ModulePrepend;

/**
 * Admin prepend for plugin Attachments.
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

        new AttachmentsBehavior();
    }
}
