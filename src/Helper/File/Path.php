<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\File;

// Dotclear\Helper\File\Path

/**
 * Basic path handling tool.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper File
 */
class Path
{
    /**
     * Returns the real path of a file.
     *
     * If parameter $strict is true, file should exist. Returns false if
     * file does not exist.
     *
     * @param string $p      The filename
     * @param bool   $strict File should exists
     *
     * @return false|string The real path or false
     */
    public static function real(string $p, bool $strict = true): string|false
    {
        $os = (DIRECTORY_SEPARATOR == '\\') ? 'win' : 'nix';

        // Absolute path?
        if ('win' == $os) {
            $_abs = preg_match('/^\w+:/', $p);
        } else {
            $_abs = substr($p, 0, 1) == '/';
        }

        // Standard path form
        if ('win' == $os) {
            $p = str_replace('\\', '/', $p);
        }

        // Adding root if !$_abs
        if (!$_abs) {
            $p = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $p;
        }

        // Clean up
        $p = preg_replace('|/+|', '/', $p);

        if (strlen($p) > 1) {
            $p = preg_replace('|/$|', '', $p);
        }

        $_start = '';
        if ('win' == $os) {
            [$_start, $p] = explode(':', $p);
            $_start .= ':/';
        } else {
            $_start = '/';
        }
        $p = substr($p, 1);

        // Go through
        $P   = explode('/', $p);
        $res = [];

        for ($i = 0; count($P) > $i; ++$i) {
            if ('.' == $P[$i]) {
                continue;
            }

            if ('..' == $P[$i]) {
                if (count($res) > 0) {
                    array_pop($res);
                }
            } else {
                array_push($res, $P[$i]);
            }
        }

        $p = $_start . implode('/', $res);

        // @todo \@ is not sufficient to avoid E_WARNING on some config
        if ($strict && !@file_exists($p)) {
            return false;
        }

        return $p;
    }

    /**
     * Returns a clean file path.
     *
     * @param null|string $p The file path
     *
     * @return string The cleaned path
     */
    public static function clean(?string $p): string
    {
        $p = preg_replace(['|^\.\.|', '|/\.\.|', '|\.\.$|'], '', (string) $p);   // Remove double point (upper directory)
        $p = preg_replace('|/{2,}|', '/', (string) $p);                          // Replace double slashes by one

        return preg_replace('|/$|', '', (string) $p);                              // Remove trailing slash
    }

    /**
     * Path information.
     *
     * Returns an array of information:
     * - dirname
     * - basename
     * - extension
     * - base (basename without extension)
     *
     * @param string $f The file path
     *
     * @return array The file stat
     */
    public static function info(string $f): array
    {
        $p   = pathinfo($f);
        $res = [];

        $res['dirname']   = (string) $p['dirname'];
        $res['basename']  = (string) $p['basename'];
        $res['extension'] = $p['extension'] ?? '';
        $res['base']      = preg_replace('/\.' . preg_quote($res['extension'], '/') . '$/', '', $res['basename']);

        return $res;
    }

    /**
     * Full path with root.
     *
     * Returns a path with root concatenation unless path begins with a slash
     *
     * @param string $p    The file path
     * @param string $root The root path
     *
     * @return string The concatened path
     */
    public static function fullFromRoot(string $p, string $root): string
    {
        return str_starts_with($p, '/') ? $p : $root . '/' . $p;
    }

    /**
     * Implode path chunks from base directory.
     *
     * This check if dotclear is installed in standalone mode
     * or with composer and return a base directory.
     *
     * @param string ...$args The path chunks
     *
     * @return string The cleaned joined path
     */
    public static function implodeBase(string ...$args): string
    {
        // check if dotclear core run under composer or standalone
        $composer = str_ends_with(self::implodeRoot('..', '..', '..'), 'vendor');
        // find base directory path
        $base     = ($composer ? self::implodeRoot('..', '..', '..', '..') : self::ImplodeRoot('..'));

        array_unshift($args, $base);

        return self::real(implode(DIRECTORY_SEPARATOR, $args), false);
    }

    /**
     * Implode path chunks from Dotclear root directory.
     *
     * Dotclear root directory path is the "src" directory path.
     *
     * @param string ...$args The path chunks
     *
     * @return string The cleaned joined path
     */
    public static function implodeRoot(string ...$args): string
    {
        array_unshift($args, __DIR__, '..', '..');

        return self::real(implode(DIRECTORY_SEPARATOR, $args), false);
    }

    /**
     * Implode path chunks.
     *
     * @param string ...$args The path chunks
     *
     * @return string The cleaned joined path
     */
    public static function implode(string ...$args): string
    {
        return self::real(implode(DIRECTORY_SEPARATOR, $args), false);
    }
}
