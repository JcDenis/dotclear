<?php
/**
 * @class Dotclear\Admin\Filer
 * @brief Dotclear admin menu helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\File\Files;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Filer
{
    /** @var    array   Stack to keep track of loaded files */
    private static $stack  = [];

    public static function url(string $src, ?string $type = null, ?string $id = null): string
    {
        if (0 === strpos($src, 'http')) {
            return $src;
        }

        $url = dotclear()->config()->admin_url;
        $url .= strpos($url, '?') === false ? '?' : '';

        if ('var' == $type) {
            $url .= 'vf=' . $src;
        } elseif (!empty($type) && !empty($id)) {
            $url .= 'mf=' . implode('/', [$type, $id, 'Admin', 'files', $src]);
        } else {
            $url .= 'df=' . $src;
        }

        return $url;
    }

    public static function load(string $src, ?string $mtype = null, ?string $mid = null, ?string $option = null): string
    {
        return self::parse($src, $mtype, $mid, $option, false);
    }

    public static function preload(string $src, ?string $mtype = null, ?string $mid = null, ?string $option = null): string
    {
        return self::parse($src, $mtype, $mid, $option, true);
    }

    private static function parse(string $src, ?string $mtype = null, ?string $mid = null, ?string $option = null, bool $preload = false): string
    {
        $ext = Files::getExtension($src);
        if (!in_array($ext, ['js','css'])) {
            return '';
        }

        $url = self::url($src, $mtype, $mid);
        if (isset(self::$stack[$preload ? 'preload' : 'load'][$url])) {
            return '';
        }
        self::$stack[$preload ? 'preload' : 'load'][$url] = true;

        $url = Html::escapeHTML($url);
        $url .= '&amp;v=' . (!dotclear()->production() ? md5(uniqid()) : dotclear()->config()->core_version);

        if ($preload) {
            return '<link rel="preload" href="' . $url . '" as="' . ($option ?: 'style') . '" />' . "\n";
        } elseif ($ext == 'css') {
            return '<link rel="stylesheet" href="' . $url . '" type="text/css" media="' . ($option ?: 'screen') . '" />' . "\n";
        } else {
            return '<script src="' . $url . '"></script>' . "\n";
        }
    }
}
