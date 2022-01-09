<?php
/**
 * @class Dotclear\Plugin\LegacyEditor\Define
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginLegacyEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor;

use Dotclear\Module\AbstractDefine;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Define extends AbstractDefine
{
    public static function getPermissions(): ?string
    {
        return 'usage,contentadmin';
    }
    public static function getName(): string
    {
        return __('Legacy editor');
    }

    public static function getDescription(): string
    {
        return __('dotclear legacy editor');
    }

    public static function getAuthor(): string
    {
        return 'dotclear Team';
    }

    public static function getVersion(): string
    {
        return '0.1.4';
    }

    public static function getRequires(): array
    {
        return [['core', '3.0-dev']];
    }

    public static function getSettings(): array
    {
        return [
            'pref' => '#user-options.user_options_edition',
        ];
    }
}
