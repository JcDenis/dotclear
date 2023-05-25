<?php
/**
 * @brief Plugin pings My module class.
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 *
 * @since 2.27
 */
declare(strict_types=1);

namespace Dotclear\Plugin\pings;

use dcCore;
use Dotclear\Module\MyPlugin;

class My extends MyPlugin
{
    protected static function checkCustomContext(int $context): ?bool
    {
        return match($context) {
            self::MANAGE, self::MENU => // only super admin can manage pings
                defined('DC_CONTEXT_ADMIN')
                    && dcCore::app()->auth->isSuperAdmin(),

            default => null,
        };
    }
}