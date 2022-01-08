<?php
/**
 * @class Dotclear\Core\Modules
 * @brief Dotclear modules generic class
 *
 * Namespace of a plugin looks like: Dotclear\Plugin\MyPlugin\Admin
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Exception;
use Dotclear\Exception\CoreException;

use Dotclear\Core\Core;

use Dotclear\Core\Admin\Notices;

use Dotclear\Html\Html;
use Dotclear\Utils\L10n;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Modules
{
    /** @var    Core    Core instance */
    public $core;

    /** @var    string  Current process */
    protected $process;

    /** @var    string  Modules type */
    protected $type;

    /** @var    bool    Safe mode */
    protected $safe_mode = false;

    protected $id = null;
    protected $mroot = null;

    # Modules
    /** @var    array   List of registered modules */
    protected $modules       = [];
    protected $disabled      = [];
    protected $errors        = [];
    protected $modules_names = [];
    protected $all_modules   = [];
    protected $disabled_mode = false;
    protected $disabled_meta = [];
    protected $to_disable    = [];

    public function __construct(Core $core, string $type)
    {
        $this->core = $core;
        $this->type = ucFirst($type);
        $this->process = DOTCLEAR_PROCESS;
        $this->ns = 'Dotclear\\' . $this->type;
        $this->safe_mode = isset($_SESSION['sess_safe_mode']) && $_SESSION['sess_safe_mode'];
    }

    /**
     * Loads modules. <var>$path</var> could be a separated list of paths
     * (path separator depends on your OS).
     *
     * <var>$lang</var> indicates if we need to load a lang file on plugin
     * loading.
     *
     * @param   string          $path   The path
     * @param   string|null     $lang   The language
     */
    public function loadModules(string $path, ?string $lang = null): void
    {
        $this->path = explode(PATH_SEPARATOR, $path);

        foreach ($this->path as $root) {
            if (!is_dir($root) || !is_readable($root)) {
                continue;
            }

            if (substr($root, -1) != '/') {
                $root .= '/';
            }

            if (($d = @dir($root)) === false) {
                continue;
            }

            while (($entry = $d->read()) !== false) {
                $full_entry = $root . $entry;

                if ($entry != '.' && $entry != '..' && is_dir($full_entry)) {
                    $this->disabled_mode = file_exists($full_entry . '/_disabled') && $this->safe_mode;

                    $this->core->autoloader->addNamespace($this->ns . '\\' . $entry, $full_entry);
                    $class = $this->ns . '\\' . $entry . '\\Define';

                    if (class_exists($class) && is_subclass_of($class, 'Dotclear\\Module\\AbstractDefine')) {
                        $this->id       = $entry;
                        $this->mroot    = $full_entry;

                        $this->checkModule($class::getProperties());

                        if ($this->disabled_mode) {
                            $this->disabled[$entry]    = $this->disabled_meta;
                            $this->all_modules[$entry] = $this->disabled[$entry];
                        } else {
                            $this->all_modules[$entry] = $this->modules[$entry];
                        }

                        $this->disabled_mode = false;
                        $this->id            = null;
                        $this->mroot         = null;
                    }
                }
            }
            $d->close();
        }

        $this->checkDependencies();

        # Sort plugins
        uasort($this->modules, [$this, 'sortModules']);

        foreach ($this->modules as $id => $m) {
            # ex: Dotclear\Plugin\MyPloug\Admin\Prepend
            $class = implode('\\', [$this->ns, $id, $this->process, 'Prepend']);
            # Load translation and Prepend
            if (class_exists($class) && is_subclass_of($class, 'Dotclear\\Module\\AbstractPrepend')) {
                $r = $class::loadModule($this->core);

                # If _prepend.php file returns null (ie. it has a void return statement)
                if (is_null($r)) {
                    $ignored[] = $id;

                    continue;
                }
                unset($r);
            }

            $this->loadModuleL10N($id, $lang, 'main');
/*            if ($this->process == 'Admin') {
                $this->core->adminurl->register('admin.plugin.' . $id, 'plugin.php', ['p' => $id]);
            }
*/
        }


//        pdump($this->modules_names, $this->modules, $this->all_modules);
//!...
    }

    protected function checkModule(array $properties): void
    {
        # Check module type
        if (ucfirst($properties['type']) != $this->type) {
            $this->errors[] = sprintf(
                __('Module "%s" has type "%s" that mismatch required module type "%s".'),
                '<strong>' . Html::escapeHTML($this->id) . '</strong>',
                '<em>' . Html::escapeHTML($properties['type']) . '</em>',
                '<em>' . Html::escapeHTML($this->type) . '</em>'
            );

            return;
        }

        # Check module perms on admin side
        $permissions = $properties['permissions'];
        if ($this->process == 'Admin') {
            if ($permissions == '' && !$this->core->auth->isSuperAdmin()) {
                return;
            } elseif (!$this->core->auth->check((string) $permissions, $this->core->blog->id)) {
                return;
            }
        }

        # Check module install on multiple path
        if ($this->id) {
            $module_exists    = array_key_exists($properties['name'], $this->modules_names);
            $module_overwrite = $module_exists ? version_compare($this->modules_names[$properties['name']], $properties['version'], '<') : false;
            if (!$module_exists || $module_overwrite) {
                $this->modules_names[$properties['name']] = $properties['version'];
                $this->modules[$this->id]   = array_merge(
                    $properties,
                    [
                        'root'          => $this->mroot,
                        'name'          => $properties['name'],
                        'desc'          => $properties['desc'],
                        'author'        => $properties['author'],
                        'version'       => $properties['version'],
                        'root_writable' => is_writable($this->mroot ?? ''),
                    ]
                );
            } else {
                $path1          = path::real($this->moduleInfo($properties['name'], 'root') ?? '');
                $path2          = path::real($this->mroot ?? '');
                $this->errors[] = sprintf(
                    __('Module "%s" is installed twice in "%s" and "%s".'),
                    '<strong>' . $properties['name'] . '</strong>',
                    '<em>' . $path1 . '</em>',
                    '<em>' . $path2 . '</em>'
                );
            }
        }
    }

    /**
     * Checks all modules dependencies
     *
     *     Fills in the following information in module :
     *       * cannot_enable : list reasons why module cannot be enabled. Not set if module can be enabled
     *       * cannot_disable : list reasons why module cannot be disabled. Not set if module can be disabled
     *       * implies : reverse dependencies
     */
    public function checkDependencies()
    {
        $dc_version       = preg_replace('/\-dev.*$/', '', DOTCLEAR_VERSION);
        $this->to_disable = [];
        foreach ($this->all_modules as $k => &$m) {
            if (isset($m['requires'])) {
                $missing = [];
                foreach ($m['requires'] as &$dep) {
                    if (!is_array($dep)) {
                        $dep = [$dep];
                    }
                    # grab missing dependencies
                    if (!isset($this->all_modules[$dep[0]]) && ($dep[0] != 'core')) {
                        // module not present
                        $missing[$dep[0]] = sprintf(__('Requires %s module which is not installed'), $dep[0]);
                    } elseif ((count($dep) > 1) && version_compare(($dep[0] == 'core' ? $dc_version : $this->all_modules[$dep[0]]['version']), $dep[1]) == -1) {
                        # module present, but version missing
                        if ($dep[0] == 'core') {
                            $missing[$dep[0]] = sprintf(
                                __('Requires Dotclear version %s, but version %s is installed'),
                                $dep[1],
                                $dc_version
                            );
                        } else {
                            $missing[$dep[0]] = sprintf(
                                __('Requires %s module version %s, but version %s is installed'),
                                $dep[0],
                                $dep[1],
                                $this->all_modules[$dep[0]]['version']
                            );
                        }
                    } elseif (($dep[0] != 'core') && !$this->all_modules[$dep[0]]['enabled']) {
                        # module disabled
                        $missing[$dep[0]] = sprintf(__('Requires %s module which is disabled'), $dep[0]);
                    }
                    $this->all_modules[$dep[0]]['implies'][] = $k;
                }
                if (count($missing)) {
                    $m['cannot_enable'] = $missing;
                    if (!empty($m['enabled'])) {
                        $this->to_disable[] = ['name' => $k, 'reason' => $missing];
                    }
                }
            }
        }
        # Check modules that cannot be disabled
        foreach ($this->modules as $k => &$m) {
            if (isset($m['implies']) && !empty($m['enabled'])) {
                foreach ($m['implies'] as $im) {
                    if (isset($this->all_modules[$im]) && !empty($this->all_modules[$im]['enabled'])) {
                        $m['cannot_disable'][] = $im;
                    }
                }
            }
        }
    }

    /**
     * Reset modules list
     */
    public function resetModulesList(): void
    {
        $this->modules       = [];
        $this->modules_names = [];
        $this->errors        = [];
    }

    /**
     * Gets the errors.
     *
     * @return  array   The errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Returns all modules associative array
     *
     * @return     array  The modules.
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Returns module <var>$id</var> array
     *
     * @param   string  $id     The module identifier
     *
     * @return  mixed   The module.
     */
    public function getModule(string $id): array
    {
        return isset($this->modules[$id]) ? $this->modules[$id] : [];
    }

    /**
     * Determines if module exists.
     *
     * @param      string  $id     The module identifier
     *
     * @return     bool  True if module exists, False otherwise.
     */
    public function moduleExists(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    /**
     * Gets the disabled modules.
     *
     * @return     array  The disabled modules.
     */
    public function getDisabledModules(): array
    {
        return $this->disabled;
    }

    /**
     * Returns a module information that could be:
     * - root
     * - name
     * - desc
     * - author
     * - version
     * - permissions
     * - priority
     * - …
     *
     * @param      string  $id     The module identifier
     * @param      string  $info   The information
     *
     * @return     mixed
     */
    public function moduleInfo(string $id, string $info): mixed
    {
        return $this->modules[$id][$info] ?? null;
    }

    private function sortModules(array $a, array $b): int
    {
        if (!isset($a['priority']) || !isset($b['priority'])) {
            return 1;
        }
        if ($a['priority'] == $b['priority']) {
            return strcasecmp($a['name'], $b['name']);
        }

        return ($a['priority'] < $b['priority']) ? -1 : 1;
    }

    /**
     * This method will search for file <var>$file</var> in language
     * <var>$lang</var> for module <var>$id</var>.
     *
     * <var>$file</var> should not have any extension.
     *
     * @param   string          $id     The module identifier
     * @param   string|null     $lang   The language code
     * @param   string          $file   The filename (without extension)
     */
    public function loadModuleL10N(string $id, ?string $lang, string $file): void
    {
        if (!$lang || !isset($this->modules[$id])) {
            return;
        }

        $lfile = $this->modules[$id]['root'] . '/locales/%s/%s';
        if (L10n::set(sprintf($lfile, $lang, $file)) === false && $lang != 'en') {
            L10n::set(sprintf($lfile, 'en', $file));
        }
    }
}
