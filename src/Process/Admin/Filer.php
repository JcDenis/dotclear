<?php
/**
 * @class Dotclear\Process\Admin\Filer
 * @brief Dotclear admin file url helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin;

use Dotclear\File\Files;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Filer
{
    public static $query = 'df';

    /** @var    array   Stack to keep track of loaded files */
    private static $stack  = [];

    public static function url(string $src, ?string $type = null, ?string $id = null, ?string $ext = null): string
    {
        if (0 === strpos($src, 'http')) {
            return $src;
        }

        if ($ext) {
            $src = $ext . '/' . $src;
        }

        if ('var' == $type) {
            $src = 'var/' . $src;
        } elseif (!empty($type) && !empty($id)) {
            $src = implode('/', [$type, $id, $src]);
        }

        return dotclear()->config()->admin_url . (strpos($src, '?') === false ? '?' : '') . self::$query .'=' . $src;
    }

    public static function css(string $src, ?string $type = null, ?string $id = null, ?string $version = null): string
    {
        return self::parse($src, $type, $id, null, false, 'css', $version);
    }

    public static function js(string $src, ?string $type = null, ?string $id = null, ?string $version = null): string
    {
        return self::parse($src, $type, $id, null, false, 'js', $version);
    }

    public static function load(string $src, ?string $type = null, ?string $id = null, ?string $option = null, ?string $version = null): string
    {
        return self::parse($src, $type, $id, $option, false, null, $version);
    }

    public static function preload(string $src, ?string $type = null, ?string $id = null, ?string $option = null, ?string $version = null): string
    {
        return self::parse($src, $type, $id, $option, true, null, $version);
    }

    private static function parse(string $src, ?string $type = null, ?string $id = null, ?string $option = null, bool $preload = false, ?string $ext = null, ?string $version = null): string
    {
        $src_ext = Files::getExtension($src);
        if (!$ext) {
            $ext = $src_ext;
        }

        if (!in_array($ext, ['js','css'])) {
            return '';
        }

        $url = self::url($src, $type, $id, $ext);
        if (isset(self::$stack[$preload ? 'preload' : 'load'][$url])) {
            return '';
        }
        self::$stack[$preload ? 'preload' : 'load'][$url] = true;

        $url = Html::escapeHTML($url);

        $url .= '&amp;v=' . ($version ?? (!dotclear()->production() ? md5(uniqid()) : dotclear()->config()->core_version));

        if ($preload) {
            return '<link rel="preload" href="' . $url . '" as="' . ($option ?: 'style') . '" />' . "\n";
        } elseif ($src_ext == 'css') {
            return '<link rel="stylesheet" href="' . $url . '" type="text/css" media="' . ($option ?: 'screen') . '" />' . "\n";
        } else {
            return '<script src="' . $url . '"></script>' . "\n";
        }
    }

    public static function serve(): void
    {
        if (empty($_GET[self::$query])) {
            return;
        }

        $src  = $_GET[self::$query];
        $dirs = [];

        # Check if it in Var path
        $var_src  = explode('/', $src);
        $var_path = dotclear()->config()->var_dir;
        if (empty($dirs) && 1 < count($var_src) && array_shift($var_src) == 'var' && !empty($var_path) && is_dir($var_path)) {
            $dirs[] = $var_path;
            $src    = implode('/', $var_src);
        }

        # Try to find module id and type
        # Admin url should be ?df=ModuleType/ModuleId/a_sub_folder/a_file.ext
        $module_src = explode('/', $src);
        if (empty($dirs) && 2 < count($module_src)) {
            $module_type = array_shift($module_src);
            $module_id   = array_shift($module_src);

            # Check module type
            $modules_class = root_ns('Module', $module_type, 'Admin', 'Modules' . $module_type);
            if (is_subclass_of($modules_class, 'Dotclear\\Module\\AbstractModules')) {
                $modules = new $modules_class();
                # Chek if module exists
                $modules_paths   = $modules->getModulesPath();
                foreach($modules_paths as $modules_path) {
                    if (is_dir(implode_path($modules_path, $module_id))) {
                        $dirs[] = implode_path($modules_path, $module_id, 'Admin', 'files');
                        $dirs[] = implode_path($modules_path, $module_id, 'Common', 'files');
                        $dirs[] = implode_path($modules_path, $module_id); // required for icons
                        $src    = implode('/', $module_src);

                        break;
                    }
                }
            }
        }

        # List other available file paths
        $dirs[] = root_path('Process', 'Admin', 'files');
        $dirs[] = root_path('Core', 'files', 'css');
        $dirs[] = root_path('Core', 'files', 'js');

        # Search dirs
        Files::serveFile($src, $dirs, dotclear()->config()->file_sever_type);
    }

    public static function json(string $id, mixed $vars): string
    {
        $ret = '<script type="application/json" id="' . Html::escapeHTML($id) . '-data">' . "\n" .
            json_encode($vars, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . "\n" . '</script>';

        return $ret;
    }
}
