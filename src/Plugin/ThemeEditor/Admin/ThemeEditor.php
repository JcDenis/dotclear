<?php
/**
 * @class Dotclear\Plugin\ThemeEditor\Admin\ThemeEditor
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginThemeEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ThemeEditor\Admin;

use Dotclear\Exception\AdminException;

use Dotclear\File\Path;
use Dotclear\File\Files;
use Dotclear\Html\Html;
use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class ThemeEditor
{
    protected $user_theme;
    protected $parent_theme;
    protected $tplset_theme;

    protected $parent_name;
    protected $tplset_name;

    protected $tpl_model;

    public $tpl = [];
    public $css = [];
    public $js  = [];
    public $po  = [];
    public $php = [];

    public function __construct()
    {
        # Default template set
        $this->tplset_theme = Path::real(root_path('Public', 'Template', dotclear()->config()->template_default));
        $this->tplset_name  = Path::real(dotclear()->config()->template_default);

        # Current theme
        $module = dotclear()->themes->getModule((string) dotclear()->blog()->settings()->system->theme);
        if (!$module) {
            throw new AdminException('Blog theme is not set');
        }
        $this->user_theme   = Path::real($module->root());

        # Current theme template set
        if ($module->templateset()) {
            $this->tplset_theme = Path::real(root_path('Public', 'Template', $module->templateset()));
            $this->tplset_name  = $module->templateset();
        }

        # Parent theme
        $parent = dotclear()->themes->getModule((string) $module->parent());
        if ($parent != null) {
            $this->parent_theme = Path::real($parent->root());
            $this->parent_name  = $parent->name();
        }

        $this->findTemplates();
        $this->findStyles();
        $this->findScripts();
        $this->findLocales();
        $this->findCodes();
    }

    public function filesList($type, $item = '%1$s', $split = true)
    {
        $files = $this->getFilesFromType($type);

        if (empty($files)) {
            return '<p>' . __('No file') . '</p>';
        }

        $list = '';
        if ($split) {
            $list_theme  = ''; // Files from current theme
            $list_parent = ''; // Files from parent of current theme
            $list_tpl    = ''; // Files from template set used by current theme
            foreach ($files as $k => $v) {
                if (strpos($v, $this->user_theme) === 0) {
                    $li = sprintf('<li class="default-file">%s</li>', $item);
                    $list_theme .= sprintf($li, $k, Html::escapeHTML($k));
                } elseif ($this->parent_theme && strpos($v, $this->parent_theme) === 0) {
                    $li = sprintf('<li class="parent-file">%s</li>', $item);
                    $list_parent .= sprintf($li, $k, Html::escapeHTML($k));
                } else {
                    $li = sprintf('<li>%s</li>', $item);
                    $list_tpl .= sprintf($li, $k, Html::escapeHTML($k));
                }
            }
            $list .= ($list_theme != '' ? sprintf('<li class="group-file">' . __('From theme:') . '<ul>%s</ul></li>', $list_theme) : '');
            $list .= ($list_parent != '' ? sprintf('<li class="group-file">' . __('From parent:') . ' %s<ul>%s</ul></li>',
                $this->parent_name, $list_parent) : '');
            $list .= ($list_tpl != '' ? sprintf('<li class="group-file">' . __('From template set:') . ' %s<ul>%s</ul></li>',
                $this->tplset_name, $list_tpl) : '');
        } else {
            foreach ($files as $k => $v) {
                if (strpos($v, $this->user_theme) === 0) {
                    $li = sprintf('<li class="default-file">%s</li>', $item);
                } elseif ($this->parent_theme && strpos($v, $this->parent_theme) === 0) {
                    $li = sprintf('<li class="parent-file">%s</li>', $item);
                } else {
                    $li = sprintf('<li>%s</li>', $item);
                }
                $list .= sprintf($li, $k, Html::escapeHTML($k));
            }
        }

        return sprintf('<ul>%s</ul>', $list);
    }

    public function getFileContent($type, $f)
    {
        $files = $this->getFilesFromType($type);

        if (!isset($files[$f])) {
            throw new AdminException(__('File does not exist.'));
        }

        $F = $files[$f];
        if (!is_readable($F)) {
            throw new AdminException(sprintf(__('File %s is not readable'), $f));
        }

        return [
            'c'    => file_get_contents($F),
            'w'    => $this->getDestinationFile($type, $f) !== false,
            'type' => $type,
            'f'    => $f
        ];
    }

    public function writeFile($type, $f, $content)
    {
        $files = $this->getFilesFromType($type);

        if (!isset($files[$f])) {
            throw new AdminException(__('File does not exist.'));
        }

        try {
            $dest = $this->getDestinationFile($type, $f);

            if ($dest == false) {
                throw new \Exception();
            }

            if ($type == 'tpl' && !is_dir(dirname($dest))) {
                Files::makeDir(dirname($dest));
            }

            if ($type == 'po' && !is_dir(dirname($dest))) {
                Files::makeDir(dirname($dest));
            }

            $fp = @fopen($dest, 'wb');
            if (!$fp) {
                throw new \Exception();
            }

            $content = preg_replace('/(\r?\n)/m', "\n", $content);
            $content = preg_replace('/\r/m', "\n", $content);

            fwrite($fp, $content);
            fclose($fp);

            # Updating inner files list
            $this->updateFileInList($type, $f, $dest);
        } catch (\Exception $e) {
            throw new AdminException(sprintf(__('Unable to write file %s. Please check your theme files and folders permissions.'), $f));
        }
    }

    public function deletableFile($type, $f)
    {
        if ($type != 'tpl') {
            // Only tpl files may be deleted
            return false;
        }

        $files = $this->getFilesFromType($type);
        if (isset($files[$f])) {
            $dest = $this->getDestinationFile($type, $f);
            if ($dest) {
                if (file_exists($dest) && is_writable($dest)) {
                    // Is there a model (parent theme or template set) ?
                    if (isset($this->tpl_model[$f])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function deleteFile($type, $f)
    {
        if ($type != 'tpl') {
            // Only tpl files may be deleted
            return;
        }

        $files = $this->getFilesFromType($type);
        if (!isset($files[$f])) {
            throw new AdminException(__('File does not exist.'));
        }

        try {
            $dest = $this->getDestinationFile($type, $f);
            if ($dest) {
                // File exists and may be deleted
                unlink($dest);

                // Updating template files list
                $this->findTemplates();
            }
        } catch (\Exception $e) {
            throw new AdminException(sprintf(__('Unable to delete file %s. Please check your theme files and folders permissions.'), $f));
        }
    }

    protected function getDestinationFile($type, $f)
    {
        if ($type == 'tpl') {
            $dest = $this->user_theme . '/tpl/' . $f;
        } elseif ($type == 'po') {
            $dest = $this->user_theme . '/locales/' . $f;
        } else {
            $dest = $this->user_theme . '/' . $f;
        }

        if (file_exists($dest) && is_writable($dest)) {
            return $dest;
        }

        if ($type == 'tpl' && !is_dir(dirname($dest))) {
            if (is_writable($this->user_theme)) {
                return $dest;
            }
        }

        if ($type == 'po' && !is_dir(dirname($dest))) {
            if (is_writable($this->user_theme)) {
                return $dest;
            }
        }

        if (is_writable(dirname($dest))) {
            return $dest;
        }

        return false;
    }

    protected function getFilesFromType($type)
    {
        switch ($type) {
            case 'tpl':
                return $this->tpl;
            case 'css':
                return $this->css;
            case 'js':
                return $this->js;
            case 'po':
                return $this->po;
            case 'php':
                return $this->php;
            default:
                return [];
        }
    }

    protected function updateFileInList($type, $f, $file)
    {
        switch ($type) {
            case 'tpl':
                $list = &$this->tpl;

                break;
            case 'css':
                $list = &$this->css;

                break;
            case 'js':
                $list = &$this->js;

                break;
            case 'po':
                $list = &$this->po;

                break;
            case 'php':
                $list = &$this->php;

                break;
            default:
                return;
        }

        $list[$f] = $file;
    }

    protected function findTemplates()
    {
        $this->tpl = array_merge(
            $this->getFilesInDir($this->tplset_theme),
            $this->getFilesInDir($this->parent_theme . '/tpl')
        );
        $this->tpl_model = $this->tpl;

        $this->tpl = array_merge($this->tpl, $this->getFilesInDir($this->user_theme . '/tpl'));

        # Then we look in 'default-templates' plugins directory
        $plugins = dotclear()->plugins->getModules();
        foreach ($plugins as $p) {
            // Looking in default-templates directory
            $this->tpl       = array_merge($this->getFilesInDir($p->root() . '/default-templates'), $this->tpl);
            $this->tpl_model = array_merge($this->getFilesInDir($p->root() . '/default-templates'), $this->tpl_model);
            // Looking in default-templates/tplset directory
            $this->tpl       = array_merge($this->getFilesInDir($p->root() . '/default-templates/' . $this->tplset_name), $this->tpl);
            $this->tpl_model = array_merge($this->getFilesInDir($p->root() . '/default-templates/' . $this->tplset_name), $this->tpl_model);
        }

        uksort($this->tpl, [$this, 'sortFilesHelper']);
    }

    protected function findStyles()
    {
        $this->css = $this->getFilesInDir($this->user_theme . '/files', 'css');
        $this->css = array_merge($this->css, $this->getFilesInDir($this->user_theme . '/files/style', 'css', 'files/style/'));
        $this->css = array_merge($this->css, $this->getFilesInDir($this->user_theme . '/files/css', 'css', 'files/css/'));
    }

    protected function findScripts()
    {
        $this->js = $this->getFilesInDir($this->user_theme . '/files', 'js');
        $this->js = array_merge($this->js, $this->getFilesInDir($this->user_theme . '/files/js', 'js', 'files/js/'));
    }

    protected function findLocales()
    {
        $langs = L10n::getISOcodes(true, true);
        foreach ($langs as $k => $v) {
            if ($this->parent_theme) {
                $this->po = array_merge($this->po, $this->getFilesInDir($this->parent_theme . '/locales/' . $v, 'po', $v . '/'));
            }
            $this->po = array_merge($this->po, $this->getFilesInDir($this->user_theme . '/locales/' . $v, 'po', $v . '/'));
        }
    }

    protected function findCodes()
    {
        $this->php = $this->getFilesInDir($this->user_theme, 'php');
    }

    protected function getFilesInDir($dir, $ext = null, $prefix = '', $model = null)
    {
        $dir = Path::real($dir);
        if (!$dir || !is_dir($dir) || !is_readable($dir)) {
            return [];
        }

        $d   = dir($dir);
        $res = [];
        while (($f = $d->read()) !== false) {
            if (is_file($dir . '/' . $f) && !preg_match('/^\./', $f) && (!$ext || preg_match('/\.' . preg_quote($ext) . '$/i', $f))) {
                if (!$model || preg_match('/^' . preg_quote($model) . '$/i', $f)) {
                    $res[$prefix . $f] = $dir . '/' . $f;
                }
            }
        }

        return $res;
    }

    protected function sortFilesHelper($a, $b)
    {
        if ($a == $b) {
            return 0;
        }

        $ext_a = Files::getExtension($a);
        $ext_b = Files::getExtension($b);

        return strcmp($ext_a . '.' . $a, $ext_b . '.' . $b);
    }
}
