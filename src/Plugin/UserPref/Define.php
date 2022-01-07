<?php
/**
 * @class Dotclear\Plugin\UserPref\Define
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginUserPref
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\UserPref;

use Dotclear\Module\AbstractDefine;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Define extends AbstractDefine
{
    public static function getName(): string
    {
        return __('user:preferences');
    }

    public static function getDescription(): string
    {
        return __('Manage every user preference directive');
    }

    public static function getAuthor(): string
    {
        return 'Franck Paul';
    }

    public static function getVersion(): string
    {
        return '0.3';
    }

    public static function getRequires(): array
    {
        return [['core', '3.0-dev']];
    }
}
