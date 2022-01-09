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
use Dotclear\Core\Utils;

use Dotclear\Core\Admin\Notices;

//use Dotclear\Module\AbstractDefine;
//use Dotclear\Module\AbstractPrepend;
//use Dotclear\Module\AbstractConfig;
//use Dotclear\Module\AbstractInstall;
//use Dotclear\Module\AbstractPage;

use Dotclear\Html\Html;
use Dotclear\Utils\L10n;
use Dotclear\Network\Http;
use Dotclear\File\Files;
use Dotclear\File\Zip\Unzip;

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
                    $this->disabled_mode = file_exists($full_entry . '/_disabled') || $this->safe_mode;

                    $this->core->autoloader->addNamespace($this->ns . '\\' . $entry, $full_entry);
                    $class = $this->ns . '\\' . $entry . '\\Define';

                    if (class_exists($class) && is_subclass_of($class, 'Dotclear\\Module\\AbstractDefine')) {
                        $this->id       = $entry;
                        $this->mroot    = $full_entry;

                        $properties = $class::getProperties();

                        if ($this->disabled_mode) {
                            $this->disabled_meta = array_merge(
                                $properties,
                                [
                                    'root'          => $this->mroot,
                                    'enabled'       => false,
                                    'root_writable' => is_writable($this->mroot),
                                ]
                            );
                        } else {
                            $this->checkModule($properties);
                        }

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

        # Check modules dependencies
        $this->checkDependencies();

        # Sort modules
        uasort($this->modules, [$this, 'sortModules']);

        # Load modules stuff
        foreach ($this->modules as $id => $m) {
            # Search module Prepend ex: Dotclear\Plugin\MyPloug\Admin\Prepend
            $class = implode('\\', [$this->ns, $id, $this->process, 'Prepend']);
            $has_prepend = class_exists($class) && is_subclass_of($class, 'Dotclear\\Module\\AbstractPrepend');

            # Check module and stop if method not returns True statement
            if ($has_prepend) {
                if (true !== $class::checkModule($this->core)) {
                    continue;
                }
            }

            # Load module main l10n
            $this->loadModuleL10N($id, $lang, 'main');

            # Auto register main module Admin Page if exists
            if ($this->process == 'Admin') {
                $page = implode('\\', [$this->ns, $id, $this->process, 'Page']);
                if (class_exists($page) && is_subclass_of($page, 'Dotclear\\Module\\AbstractPage')) {
                    $this->core->adminurl->register('admin.plugin.' . $id, $page);
                }
            }

            # Load others stuff from module
            if ($has_prepend) {
                $class::loadModule($this->core);
            }
        }
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
                        'enabled'       => $this->disabled_mode ? false : (isset($properties['version']) ? (bool) $properties['version'] : true),
                        'root_writable' => is_writable($this->mroot ?? ''),
                        'type'          => $this->type
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
    public function checkDependencies(): void
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

    public function requireDefine(string $dir, string $id, bool $is_clone = false): void
    {
        if (!file_exists($dir . '/Define.php')) {
            return;
        }

        $this->id = $id;
        ob_start();

        $ns = $is_clone ? 'C_l_o_n_e' : $this->ns;

        # bad hack to prevent duplicate class in the same namespace.
        if ($is_clone) {
            file_put_contents($dir . '/Define.php', str_replace($this->ns, 'C_l_o_n_e', file_get_contents($dir . '/Define.php')));
        }

        require $dir . '/Define.php';

        $class = implode('\\', [$ns, $id, 'Define']);
        if (!class_exists($class) || !is_subclass_of($class, 'Dotclear\\Module\\AbstractDefine')) {
            $this->errors[] = sprintf(
                __('Module "%s" is not a valid module.'),
                '<strong>' . $id . '</strong>'
            );
        } else {
            $this->checkModule($class::getProperties());
        }

        # revert back (for third party called)
        if ($is_clone) {
            file_put_contents($dir . '/Define.php', str_replace('C_l_o_n_e', $this->ns, file_get_contents($dir . '/Define.php')));
        }

        ob_end_clean();
        $this->id = null;
    }

    /**
     * Install a Package
     *
     * @param      string     $zip_file  The zip file
     * @param      dcModules  $modules   The modules
     *
     * @throws     Exception
     *
     * @return     int
     */
    public static function installPackage(string $zip_file, Modules &$modules): int
    {
        $zip = new Unzip($zip_file);
        $zip->getList(false, '#(^|/)(__MACOSX|\.svn|\.hg.*|\.git.*|\.DS_Store|\.directory|Thumbs\.db)(/|$)#');

        $zip_root_dir = $zip->getRootDir();
        $define       = '';
        if ($zip_root_dir != false) {
            $target      = dirname($zip_file);
            $destination = $target . '/' . $zip_root_dir;
            $define      = $zip_root_dir . '/Define.php';
            $has_define  = $zip->hasFile($define);
        } else {
            $target      = dirname($zip_file) . '/' . preg_replace('/\.([^.]+)$/', '', basename($zip_file));
            $destination = $target;
            $define      = 'Define.php';
            $has_define  = $zip->hasFile($define);
        }

        if ($zip->isEmpty()) {
            $zip->close();
            unlink($zip_file);

            throw new CoreException(__('Empty module zip file.'));
        }

        if (!$has_define) {
            $zip->close();
            unlink($zip_file);

            throw new CoreException(__('The zip file does not appear to be a valid Dotclear module.'));
        }

        $ret_code = 1;

        if (!is_dir($destination)) {
            try {
                Files::makeDir($destination, true);

                $sandbox = clone $modules;
                $zip->unzip($define, $target . '/Define.php');

                $sandbox->resetModulesList();
                $sandbox->requireDefine($target, basename($destination), true);
                unlink($target . '/Define.php');

                $new_errors = $sandbox->getErrors();
                if (!empty($new_errors)) {
                    $new_errors = implode(" \n", $new_errors);

                    throw new CoreException($new_errors);
                }

                Files::deltree($destination);
            } catch (Exception $e) {
                $zip->close();
                unlink($zip_file);
                Files::deltree($destination);

                throw new CoreException($e->getMessage());
            }
        } else {
            # test for update
            $sandbox = clone $modules;
            $zip->unzip($define, $target . '/Define.php');

            $sandbox->resetModulesList();
            $sandbox->requireDefine($target, basename($destination), true);
            unlink($target . '/Define.php');
            $new_modules = $sandbox->getModules();

            if (!empty($new_modules)) {
                $tmp        = array_keys($new_modules);
                $id         = $tmp[0];
                $cur_module = $modules->getModule($id);
                if (!empty($cur_module) && (defined('DC_DEV') && DC_DEV === true || Utils::versionsCompare($new_modules[$id]['version'], $cur_module['version'], '>', true))) {
                    # delete old module
                    if (!Files::deltree($destination)) {
                        throw new CoreException(__('An error occurred during module deletion.'));
                    }
                    $ret_code = 2;
                } else {
                    $zip->close();
                    unlink($zip_file);

                    throw new CoreException(sprintf(__('Unable to upgrade "%s". (older or same version)'), basename($destination)));
                }
            } else {
                $zip->close();
                unlink($zip_file);

                throw new CoreException(sprintf(__('Unable to read new Define.php file')));
            }
        }
        $zip->unzipAll($target);
        $zip->close();
        unlink($zip_file);

        return $ret_code;
    }

    /**
     * This method installs all modules having a _install file.
     *
     * @see Modules::installModule
     *
     * @return  array
     */
    public function installModules(): array
    {
        $res = ['success' => [], 'failure' => []];
        foreach ($this->modules as $id => &$m) {
            $msg = '';
            $i = $this->installModule($id, $msg);
            if ($i === true) {
                $res['success'][$id] = true;
            } elseif ($i === false) {
                $res['failure'][$id] = $msg;
            }
        }

        return $res;
    }

    /**
     * This method installs module with ID <var>$id</var> and having a _install
     * file. This file should throw exception on failure or true if it installs
     * successfully.
     *
     * <var>$msg</var> is an out parameter that handle installer message.
     *
     * @param   string  $id     The identifier
     * @param   string  $msg    The message
     *
     * @return  bool|null
     */
    public function installModule(string $id, string &$msg): ?bool
    {
        if (!isset($this->modules[$id])) {
            return null;
        }

        # Check module version in db
        if (version_compare((string) $this->core->getVersion($id), (string) $this->modules[$id]['version'], '>=')) {
            return null;
        }

        # Search module install class
        $class = implode('\\', [$this->ns, $id, $this->process, 'Prepend']);
        if (!class_exists($class) || !is_subclass_of($class, 'Dotclear\\Module\\AbstractPrepend')) {
            return null;
        }

        try {
            # Do module installation
            $i = $class::installModule($this->core);

            # Update module version in db
            $this->core->setVersion($id, $this->modules[$id]['version']);

            return $i ? true : null;
        } catch (Exception $e) {
            $msg = $e->getMessage();

            return false;
        }
    }

    /**
     * Disables the dep modules.
     *
     * @param   string  $redir_url  URL to redirect if modules are to disable
     *
     * @return  bool                true if a redirection has been performed
     */
    public function disableDepModules(string $redir_url): bool
    {
        if (isset($_GET['dep'])) {
            // Avoid infinite redirects
            return false;
        }
        $reason = [];
        foreach ($this->to_disable as $module) {
            try {
                $this->deactivateModule($module['name']);
                $reason[] = sprintf('<li>%s : %s</li>', $module['name'], join(',', $module['reason']));
            } catch (Exception $e) {
            }
        }
        if (count($reason)) {
            $message = sprintf(
                '<p>%s</p><ul>%s</ul>',
                __('The following modules have been disabled :'),
                join('', $reason)
            );
            Notices::addWarningNotice($message, ['divtag' => true, 'with_ts' => false]);
            $url = $redir_url . (strpos($redir_url, '?') ? '&' : '?') . 'dep=1';
            Http::redirect($url);

            return true;
        }

        return false;
    }

    /**
     * Delete a module
     *
     * @param   string  $id         The module identifier
     * @param   bool    $disabled   Is module disabled
     *
     * @throws  CoreException  (description)
     */
    public function deleteModule(string $id, bool $disabled = false): void
    {
        if ($disabled) {
            $p = &$this->disabled;
        } else {
            $p = &$this->modules;
        }

        if (!isset($p[$id])) {
            throw new CoreException(__('No such module.'));
        }

        if (!Files::deltree($p[$id]['root'])) {
            throw new CoreException(__('Cannot remove module files'));
        }
    }

    /**
     * Deactivate a module
     *
     * @param   string  $id     The identifier
     *
     * @throws  CoreException
     */
    public function deactivateModule(string $id): void
    {
        if (!isset($this->modules[$id])) {
            throw new CoreException(__('No such module.'));
        }

        if (!$this->modules[$id]['root_writable']) {
            throw new CoreException(__('Cannot deactivate plugin.'));
        }

        if (@file_put_contents($this->modules[$id]['root'] . '/_disabled', '')) {
            throw new CoreException(__('Cannot deactivate plugin.'));
        }
    }

    /**
     * Activate a module
     *
     * @param   string  $id     The identifier
     *
     * @throws  CoreException
     */
    public function activateModule(string $id): void
    {
        if (!isset($this->disabled[$id])) {
            throw new CoreException(__('No such module.'));
        }

        if (!$this->disabled[$id]['root_writable']) {
            throw new CoreException(__('Cannot activate plugin.'));
        }

        if (@unlink($this->disabled[$id]['root'] . '/_disabled') === false) {
            throw new CoreException(__('Cannot activate plugin.'));
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
