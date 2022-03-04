<?php
/**
 * @class Dotclear\Core\Utils
 * @brief Dotclear core utils class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use ArrayObject;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Utils
{
    //! to delete? @see Admin\Filer
    private static function appendVersion(string $src, ?string $v = ''): string
    {
        return $src .
            (strpos($src, '?') === false ? '?' : '&amp;') .
            'v=' . (!dotclear()->production() ? md5(uniqid()) : ($v ?: dotclear()->config()->core_version));
    }

    //! to delete? @see Admin\Filer
    public static function cssLoad(string $src, string $media = 'screen', string $v = null): string
    {
        $escaped_src = Html::escapeHTML($src);
        if ($v !== null) {
            $escaped_src = self::appendVersion($escaped_src, $v);
        }

        return '<link rel="stylesheet" href="' . $escaped_src . '" type="text/css" media="' . $media . '" />' . "\n";
    }

    //! to delete? @see Admin\Filer
    public static function jsLoad(string $src, string $v = null): string
    {
        $escaped_src = Html::escapeHTML($src);
        if ($v !== null) {
            $escaped_src = self::appendVersion($escaped_src, $v);
        }

        return '<script src="' . $escaped_src . '"></script>' . "\n";
    }

    //! to delete? @see Admin\Filer
    public static function jsJson(string $id, mixed $vars): string
    {
        // Use echo self::jsLoad(dotclear()->blog()->public_url . '/util.js'); to use the JS dotclear.getData() decoder in public mode
        $ret = '<script type="application/json" id="' . Html::escapeHTML($id) . '-data">' . "\n" .
            json_encode($vars, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . "\n" . '</script>';

        return $ret;
    }
}
