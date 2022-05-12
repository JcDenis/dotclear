<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ThemeEditor\Admin;

// Dotclear\Plugin\ThemeEditor\Admin\ThemeEditor
use Dotclear\App;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;
use Exception;

/**
 * ThemeEditor handler.
 *
 * @ingroup  Plugin ThemeEditor
 */
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
        // Default template set
        $this->tplset_theme = Path::implodeSrc('Process', 'Public', 'templates', App::core()->config()->get('template_default'));
        $this->tplset_name  = App::core()->config()->get('template_default');

        // Current theme
        $module = App::core()->themes()->getModule((string) App::core()->blog()->settings()->get('system')->get('theme'));
        if (!$module) {
            throw new AdminException('Blog theme is not set');
        }
        $this->user_theme = Path::real($module->root(), false);

        // Current theme template set
        if ($module->templateset()) {
            $this->tplset_theme = Path::implodeSrc('Process', 'Public', 'templates', $module->templateset());
            $this->tplset_name  = $module->templateset();
        }

        // Parent theme
        $parent = App::core()->themes()->getModule((string) $module->parent());
        if (null != $parent) {
            $this->parent_theme = Path::real($parent->root());
            $this->parent_name  = $parent->name();
        }

        $this->findTemplates();
        $this->findStyles();
        $this->findScripts();
        $this->findLocales();
        $this->findCodes();
    }

    public function filesList(string $type, string $item = '%1$s', bool $split = true): string
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
                if (str_starts_with($v, $this->user_theme)) {
                    $li = sprintf('<li class="default-file">%s</li>', $item);
                    $list_theme .= sprintf($li, $k, Html::escapeHTML($k));
                } elseif ($this->parent_theme && str_starts_with($v, $this->parent_theme)) {
                    $li = sprintf('<li class="parent-file">%s</li>', $item);
                    $list_parent .= sprintf($li, $k, Html::escapeHTML($k));
                } else {
                    $li = sprintf('<li>%s</li>', $item);
                    $list_tpl .= sprintf($li, $k, Html::escapeHTML($k));
                }
            }
            $list .= ('' != $list_theme ? sprintf('<li class="group-file">' . __('From theme:') . '<ul>%s</ul></li>', $list_theme) : '');
            $list .= ('' != $list_parent ? sprintf(
                '<li class="group-file">' . __('From parent:') . ' %s<ul>%s</ul></li>',
                $this->parent_name,
                $list_parent
            ) : '');
            $list .= ('' != $list_tpl ? sprintf(
                '<li class="group-file">' . __('From template set:') . ' %s<ul>%s</ul></li>',
                $this->tplset_name,
                $list_tpl
            ) : '');
        } else {
            foreach ($files as $k => $v) {
                if (str_starts_with($v, $this->user_theme)) {
                    $li = sprintf('<li class="default-file">%s</li>', $item);
                } elseif ($this->parent_theme && str_starts_with($v, $this->parent_theme)) {
                    $li = sprintf('<li class="parent-file">%s</li>', $item);
                } else {
                    $li = sprintf('<li>%s</li>', $item);
                }
                $list .= sprintf($li, $k, Html::escapeHTML($k));
            }
        }

        return sprintf('<ul>%s</ul>', $list);
    }

    public function getFileContent(string $type, string $f): array
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
            'f'    => $f,
        ];
    }

    public function writeFile(string $type, string $f, string $content): void
    {
        $files = $this->getFilesFromType($type);

        if (!isset($files[$f])) {
            throw new AdminException(__('File does not exist.'));
        }

        try {
            $dest = $this->getDestinationFile($type, $f);

            if (false == $dest) {
                throw new Exception();
            }

            if ('tpl' == $type && !is_dir(dirname($dest))) {
                Files::makeDir(dirname($dest));
            }

            if ('po' == $type && !is_dir(dirname($dest))) {
                Files::makeDir(dirname($dest));
            }

            $fp = @fopen($dest, 'wb');
            if (!$fp) {
                throw new Exception();
            }

            $content = preg_replace('/(\r?\n)/m', "\n", $content);
            $content = preg_replace('/\r/m', "\n", $content);

            fwrite($fp, $content);
            fclose($fp);

            // Updating inner files list
            $this->updateFileInList($type, $f, $dest);
        } catch (\Exception) {
            throw new AdminException(sprintf(__('Unable to write file %s. Please check your theme files and folders permissions.'), $f));
        }
    }

    public function deletableFile(string $type, string $f): bool
    {
        if ('tpl' != $type) {
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

    public function deleteFile(string $type, string $f): void
    {
        if ('tpl' != $type) {
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
        } catch (\Exception) {
            throw new AdminException(sprintf(__('Unable to delete file %s. Please check your theme files and folders permissions.'), $f));
        }
    }

    protected function getDestinationFile(string $type, string $f): string|false
    {
        if ('tpl' == $type) {
            $dest = $this->user_theme . '/templates/tpl/' . $f;
        } elseif ('po' == $type) {
            $dest = $this->user_theme . '/locales/' . $f;
        } else {
            $dest = $this->user_theme . '/' . $f;
        }

        if (file_exists($dest) && is_writable($dest)) {
            return $dest;
        }

        if ('tpl' == $type && !is_dir(dirname($dest))) {
            if (is_writable($this->user_theme)) {
                return $dest;
            }
        }

        if ('po' == $type && !is_dir(dirname($dest))) {
            if (is_writable($this->user_theme)) {
                return $dest;
            }
        }

        if (is_writable(dirname($dest))) {
            return $dest;
        }

        return false;
    }

    protected function getFilesFromType(string $type): array
    {
        return match ($type) {
            'tpl'   => $this->tpl,
            'css'   => $this->css,
            'js'    => $this->js,
            'po'    => $this->po,
            'php'   => $this->php,
            default => [],
        };
    }

    protected function updateFileInList(string $type, string $f, string $file): void
    {
        $list = match ($type) {
            'tpl'   => $this->tpl,
            'css'   => $this->css,
            'js'    => $this->js,
            'po'    => $this->po,
            'php'   => $this->php,
            default => [],
        };

        if (!empty($list)) {
            $list[$f] = $file;
        }
    }

    protected function findTemplates(): void
    {
        $this->tpl = array_merge(
            $this->getFilesInDir($this->tplset_theme),
            $this->getFilesInDir($this->parent_theme . '/templates/tpl')
        );
        $this->tpl_model = $this->tpl;

        $this->tpl = array_merge($this->tpl, $this->getFilesInDir($this->user_theme . '/templates/tpl'));

        // Then we look in 'default-templates' plugins directory
        $plugins = App::core()->plugins()->getModules();
        foreach ($plugins as $p) {
            // Looking in default-templates directory
            $this->tpl       = array_merge($this->getFilesInDir($p->root() . '/templates/tpl'), $this->tpl);
            $this->tpl_model = array_merge($this->getFilesInDir($p->root() . '/templates/tpl'), $this->tpl_model);
            // Looking in default-templates/tplset directory
            $this->tpl       = array_merge($this->getFilesInDir($p->root() . '/templates/' . $this->tplset_name), $this->tpl);
            $this->tpl_model = array_merge($this->getFilesInDir($p->root() . '/templates/' . $this->tplset_name), $this->tpl_model);
        }

        uksort($this->tpl, [$this, 'sortFilesHelper']);
    }

    protected function findStyles(): void
    {
        $this->css = $this->getFilesInDir($this->user_theme . '/resources', 'css');
        $this->css = array_merge($this->css, $this->getFilesInDir($this->user_theme . '/resources/style', 'css', 'resources/css/'));
        $this->css = array_merge($this->css, $this->getFilesInDir($this->user_theme . '/resources/css', 'css', 'resources/css/'));
    }

    protected function findScripts(): void
    {
        $this->js = $this->getFilesInDir($this->user_theme . '/resources', 'js');
        $this->js = array_merge($this->js, $this->getFilesInDir($this->user_theme . '/resources/js', 'js', 'resources/js/'));
    }

    protected function findLocales(): void
    {
        $langs = L10n::getISOcodes(true, true);
        foreach ($langs as $k => $v) {
            if ($this->parent_theme) {
                $this->po = array_merge($this->po, $this->getFilesInDir($this->parent_theme . '/locales/' . $v, 'po', $v . '/'));
            }
            $this->po = array_merge($this->po, $this->getFilesInDir($this->user_theme . '/locales/' . $v, 'po', $v . '/'));
        }
    }

    protected function findCodes(): void
    {
        $this->php = $this->getFilesInDir($this->user_theme, 'php');
    }

    protected function getFilesInDir(string $dir, ?string $ext = null, string $prefix = '', ?string $model = null): array
    {
        $dir = Path::real($dir);
        if (!$dir || !is_dir($dir) || !is_readable($dir)) {
            return [];
        }

        $d   = dir($dir);
        $res = [];
        while (false !== ($f = $d->read())) {
            if (is_file($dir . '/' . $f) && !preg_match('/^\./', $f) && (!$ext || preg_match('/\.' . preg_quote($ext) . '$/i', $f))) {
                if (!$model || preg_match('/^' . preg_quote($model) . '$/i', $f)) {
                    $res[$prefix . $f] = $dir . '/' . $f;
                }
            }
        }

        return $res;
    }

    protected function sortFilesHelper(string $a, string $b): int
    {
        if ($a == $b) {
            return 0;
        }

        $ext_a = Files::getExtension($a);
        $ext_b = Files::getExtension($b);

        return strcmp($ext_a . '.' . $a, $ext_b . '.' . $b);
    }
}
