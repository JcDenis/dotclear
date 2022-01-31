<?php
/**
 * @class Dotclear\Plugin\Attachments\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginAttachments
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

use Dotclear\Plugin\Attachments\Public\Attachments;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        new Attachments();
    }
}
