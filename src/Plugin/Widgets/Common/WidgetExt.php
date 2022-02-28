<?php
/**
 * @class Dotclear\Plugin\Widgets\Lib\WidgetExt
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Lib;

use Dotclear\Plugin\Widgets\Lib\Widget;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class WidgetExt extends Widget
{
    public const ALL_PAGES   = 0; // Widget displayed on every page
    public const HOME_ONLY   = 1; // Widget displayed on home page only
    public const EXCEPT_HOME = 2; // Widget displayed on every page but home page

    public function addTitle($title = '')
    {
        return $this->setting('title', __('Title (optional)') . ' :', $title);
    }

    public function addHomeOnly()
    {
        return $this->setting(
            'homeonly',
            __('Display on:'),
            self::ALL_PAGES,
            'combo',
            [__('All pages') => self::ALL_PAGES, __('Home page only') => self::HOME_ONLY, __('Except on home page') => self::EXCEPT_HOME]
        );
    }

    public function checkHomeOnly($type, $alt_not_home = 1, $alt_home = 0)
    {
        /* @phpstan-ignore-next-line */
        if (($this->homeonly == self::HOME_ONLY && !dotclear()->url()->isHome($type) && $alt_not_home) || ($this->homeonly == self::EXCEPT_HOME && (dotclear()->url()->isHome($type) || $alt_home))) {
            return false;
        }

        return true;
    }

    public function addContentOnly($content_only = 0)
    {
        return $this->setting('content_only', __('Content only'), $content_only, 'check');
    }

    public function addClass($class = '')
    {
        return $this->setting('class', __('CSS class:'), $class);
    }

    public function addOffline($offline = 0)
    {
        return $this->setting('offline', __('Offline'), $offline, 'check');
    }

    public function isOffline()
    {
        return $this->settings['offline']['value'] ?? false;
    }
}
