<?php
/**
 * @class Dotclear\Plugin\AboutConfig\Define
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAboutConfig
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\AboutConfig;

use Dotclear\Module\AbstractDefine;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Define extends AbstractDefine
{
    public static function getName(): string
    {
        return __('about:config');
    }

    public static function getDescription(): string
    {
        return __('Manage every blog configuration directive');
    }

    public static function getAuthor(): string
    {
        return 'Olivier Meunier';
    }

    public static function getVersion(): string
    {
        return '0.5';
    }

    public static function getRequires(): array
    {
        return [['core', '3.0-dev']];
    }
}
