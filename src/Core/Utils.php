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
    /**
     * Compare two versions with option of using only main numbers.
     *
     * @param  string    $current_version    Current version
     * @param  string    $required_version    Required version
     * @param  string    $operator            Comparison operand
     * @param  boolean    $strict                Use full version
     *
     * @return boolean    True if comparison success
     */
    public static function versionsCompare(string $current_version, string $required_version, string $operator = '>=', bool $strict = true): bool
    {
        if ($strict) {
            $current_version  = preg_replace('!-r(\d+)$!', '-p$1', $current_version);
            $required_version = preg_replace('!-r(\d+)$!', '-p$1', $required_version);
        } else {
            $current_version  = preg_replace('/^([0-9\.]+)(.*?)$/', '$1', $current_version);
            $required_version = preg_replace('/^([0-9\.]+)(.*?)$/', '$1', $required_version);
        }

        return (bool) version_compare($current_version, $required_version, $operator);
    }

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
