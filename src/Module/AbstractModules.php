<?php
/**
 * @class Dotclear\Module\AbstractModules
 * @brief Helper for admin list of modules.
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Exception;
use Dotclear\Exception\ModuleException;

use Dotclear\Module\AbstractDefine;

use Dotclear\Core\Error;

use Dotclear\Html\Html;
use Dotclear\Utils\L10n;
use Dotclear\Network\Http;
use Dotclear\File\Files;
use Dotclear\File\Path;
use Dotclear\File\Zip\Unzip;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

abstract class AbstractModules
{
    /** @var    Error   Error instance */
    public $error;

    /** @var    bool    Safe mode is active */
    protected $safe_mode;

    /** @var    array   List of enabled modules */
    protected $modules_enabled = [];

    /** @var    array   List of disabled modules */
    protected $modules_disabled = [];

    /** @var    array   List of modules versions */
    protected $modules_version = [];

    /** @var    string|null     Loading process, module id */
    private $id = null;

    /** @var    bool            Loading process, in disabled mode */
    private $disabled_mode = false;

    /** @var    AbstractDefine  Loading process, disabled module */
    private $disabled_meta;

    /** @var    array           Loading process, modules to disable */
    private $to_disable = [];

    public function __construct()
    {
        $this->error     = new Error();
        $this->safe_mode = isset($_SESSION['sess_safe_mode']) && $_SESSION['sess_safe_mode'];
    }

    /**
     * Get modules type
     *
     * @return  string  The modules type
     */
    abstract public function getModulesType(): string;

    /**
     * Get modules path
     *
     * @return  string  The modules path
     */
    abstract public function getModulesPath(): array;

    /**
     * Get list of distributed modules (by id)
     *
     * @return  array   List of distributed modules ids
     */
    abstract public function getDistributedModules(): array;

    /**
     * Check Process specifics on modules load
     */
    abstract protected function loadModulesProcess(): void;

    /**
     * Check Process specifics on modules define load
     *
     * @param   AbstractDefine  $define     Current module to check
     *
     * @return  bool                        Module is OK
     */
    abstract protected function loadModuleDefineProcess(AbstractDefine $define): bool;


    /**
     * Loads modules.
     *
     * <var>$lang</var> indicates if we need to load a lang file on module
     * loading.
     *
     * @param   string|null     $lang   The language
     */
    public function loadModules(?string $lang = null): void
    {
        # Loop through each modules root path
        foreach ($this->getModulesPath() as $root) {
            # Check dir
            if (!is_dir($root) || !is_readable($root)) {
                continue;
            }

            # Open dir
            if (($handle = @dir($root)) === false) {
                continue;
            }

            # Loop through current modules root path
            while (($this->id = $handle->read()) !== false) {
                $entry_path = dcCore()::path($root, $this->id);

                # Check dir
                if ($this->id != '.' && $this->id != '..' && is_dir($entry_path)) {

                    # Module will be disabled
                    $entry_enabled = !file_exists($entry_path . '/_disabled') && !$this->safe_mode;
                    if (!$entry_enabled) {
                        $this->disabled_mode = true;
                    }

                    # Check module Define
                    $this->loadModuleDefine($entry_path, $this->id);

                    # Add module namespace
                    if ($entry_enabled) {
                        dcCore()->autoloader->addNamespace(dcCore()::ns('Dotclear', $this->getModulesType(), $this->id), $entry_path);
                    # Save module in disabled list
                    } else {
                        $this->disabled_mode       = false;
                        $this->modules_disabled[$this->id] = $this->disabled_meta;
                    }
                }
                $this->id = null;
            }
            $handle->close();
        }

        # Check modules dependencies
        $this->checkModuleDependencies();

        # Load modules specifics for current Process
        $this->loadModulesProcess();

        # Sort modules
        uasort($this->modules_enabled, [$this, 'defaultSortModules']);

        # Load modules stuff
        foreach ($this->modules_enabled as $id => $module) {
            # Search module Prepend ex: Dotclear\Plugin\MyPloug\Admin\Prepend
            $class = dcCore()::ns('Dotclear', $this->getModulesType(), $id, DOTCLEAR_PROCESS, 'Prepend');
            $has_prepend = is_subclass_of($class, 'Dotclear\\Module\\AbstractPrepend');

            # Check module and stop if method not returns True statement
            if ($has_prepend) {
                if (true !== $class::checkModule()) {
                    continue;
                }
            }

            # Load module main l10n
            $this->loadModuleL10N($id, $lang, 'main');

            # Auto register main module Admin Page URL if exists
            if (DOTCLEAR_PROCESS == 'Admin') {
                $page = dcCore()::ns('Dotclear', $this->getModulesType(), $id, DOTCLEAR_PROCESS, 'Page');
                if (is_subclass_of($page, 'Dotclear\\Module\\AbstractPage')) {
                    dcCore()->adminurl->register('admin.plugin.' . $id, $page);
                }
            }

            # Load others stuff from module
            if ($has_prepend) {
                $class::loadModule();
            }

            //! todo: here or elsewhere, load module 'parent' Prepend
            //! see: old inc/core/class.dc.themes.php|dcThemes::loadNsFile()
        }
    }

    public function loadModuleDefine(string $dir, string $id): void
    {
        # Include module Define file
        ob_start();
        try {
            $class = dcCore()::ns('Dotclear', 'Module', $this->getModulesType(), 'Define' . $this->getModulesType());
            $define = new $class($id, $dir . '/Define.php');
        } catch (ModuleException) {
            ob_end_clean();

            return;
        }
        ob_end_clean();

        # Stop on error in module definition
        if ($define->error->flag()) {
            call_user_func_array([$this->error, 'add'], $define->error->getErrors());

            return;
        }

        # Set module as disabled and stop
        if ($this->disabled_mode) {
            $define->disableModule();
            $this->disabled_meta = $define;

            return;
        }

        if ($this->loadModuleDefineProcess($define) === false) {
            return;
        }

        # Check module install on multiple path (only from ::loadModules() )
        if ($this->id) {
            $module_exists    = array_key_exists($define->id(), $this->modules_version);
            $module_overwrite = $module_exists ? version_compare($this->modules_version[$define->id()], $define->version(), '<') : false;
            if (!$module_exists || $module_overwrite) {
                $this->modules_version[$define->id()] = $define->version();
                $this->modules_enabled[$this->id]     = $define;
            } else {
                $this->error->add(sprintf(
                    __('Module "%s" is installed twice in "%s" and "%s".'),
                    '<strong>' . $define->name() . '</strong>',
                    '<em>' . Path::real($this->modules_enabled[$define->id()]->root()) . '</em>',
                    '<em>' . Path::real($dir) . '</em>'
                ));
            }
        }
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
        if (!$lang || !isset($this->modules_enabled[$id])) {
            return;
        }

        $lfile = $this->modules_enabled[$id]->root() . '/locales/%s/%s';
        if (L10n::set(sprintf($lfile, $lang, $file)) === false && $lang != 'en') {
            L10n::set(sprintf($lfile, 'en', $file));
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
    public function checkModuleDependencies(): void
    {
        $modules          = array_merge($this->modules_enabled, $this->modules_disabled);
        $dc_version       = preg_replace('/\-dev.*$/', '', DOTCLEAR_CORE_VERSION);
        $this->to_disable = [];

        foreach ($modules as $id => $module) {
            # Grab missing dependencies
            $missing = [];
            foreach ($module->requires() as $dep) {
                # Module not present
                if (!isset($modules[$dep[0]]) && $dep[0] != 'core') {
                    $missing[$dep[0]] = sprintf(__('Requires %s module which is not installed'), $dep[0]);
                # Module present, but version missing
                } elseif ((count($dep) > 1) && version_compare(($dep[0] == 'core' ? $dc_version : $modules[$dep[0]]->version()), $dep[1]) == -1) {
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
                            $modules[$dep[0]]->version()
                        );
                    }
                # Module is disabled
                } elseif (($dep[0] != 'core') && !$modules[$dep[0]]->enabled()) {
                    $missing[$dep[0]] = sprintf(__('Requires %s module which is disabled'), $dep[0]);
                }
                if ($dep[0] != 'core') {
                    $modules[$dep[0]]->depParents($id);
                }
            }
            # Set module to disable
            if (count($missing)) {
                $module->depMissing($missing);
                if ($module->enabled()) {
                    $this->to_disable[] = ['id' => $id, 'reason' => $missing];
                }
            }
        }
        # Check modules that cannot be disabled
        foreach ($this->modules_enabled as $id => $module) {
            if ($module->enabled()) {
                foreach ($module->depParents() as $im) {
                    if (isset($modules[$im]) && $modules[$im]->enabled()) {
                        $module->depChildren($im);
                    }
                }
            }
        }
    }

    /**
     * Install a Package
     *
     * @param   string              $zip_file   The zip file
     * @param   AbstractModules     $modules    The modules
     *
     * @throws  Exception
     *
     * @return  int
     */
    public static function installPackage(string $zip_file, AbstractModules $modules): int
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

            throw new ModuleException(__('Empty module zip file.'));
        }

        if (!$has_define) {
            $zip->close();
            unlink($zip_file);

            throw new ModuleException(__('The zip file does not appear to be a valid Dotclear module.'));
        }

        $ret_code = 1;

        if (!is_dir($destination)) {
            try {
                Files::makeDir($destination, true);

                $sandbox = clone $modules;
                $zip->unzip($define, $target . '/Define.php');

                $sandbox->resetModulesList();
                $sandbox->loadModuleDefine($target, basename($destination));
                unlink($target . '/Define.php');

                $new_errors = $sandbox->getErrors();
                if (!empty($new_errors)) {
                    $new_errors = implode(" \n", $new_errors);

                    throw new ModuleException($new_errors);
                }

                Files::deltree($destination);
            } catch (Exception $e) {
                $zip->close();
                unlink($zip_file);
                Files::deltree($destination);

                throw new ModuleException($e->getMessage());
            }
        } else {
            # Test for update
            $sandbox = clone $modules;
            $zip->unzip($define, $target . '/Define.php');

            $sandbox->resetModulesList();
            $sandbox->loadModuleDefine($target, basename($destination));
            unlink($target . '/Define.php');
            $new_modules = $sandbox->getModules();

            if (!empty($new_modules)) {
                $tmp        = array_keys($new_modules);
                $id         = $tmp[0];
                $cur_module = $modules->getModule($id);
                if (!empty($cur_module)
                    && (defined('DOTCLEAR_MODE_DEV') && DOTCLEAR_MODE_DEV === true
                        || Utils::versionsCompare($new_modules[$id]['version'], $cur_module['version'], '>', true))
                ) {
                    # Delete old module
                    if (!Files::deltree($destination)) {
                        throw new ModuleException(__('An error occurred during module deletion.'));
                    }
                    $ret_code = 2;
                } else {
                    $zip->close();
                    unlink($zip_file);

                    throw new ModuleException(sprintf(__('Unable to upgrade "%s". (older or same version)'), basename($destination)));
                }
            } else {
                $zip->close();
                unlink($zip_file);

                throw new ModuleException(sprintf(__('Unable to read new Define.php file')));
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
     * @see AbstractModules::installModule
     *
     * @return  array
     */
    public function installModules(): array
    {
        $res = ['success' => [], 'failure' => []];
        foreach ($this->modules_enabled as $id => $module) {
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
        if (!isset($this->modules_enabled[$id])) {
            return null;
        }

        # Check module version in db
        if (version_compare((string) dcCore()->getVersion($id), (string) $this->modules_enabled[$id]->version(), '>=')) {
            return null;
        }

        # Search module install class
        $class = dcCore()::ns('Dotclear', $this->getModulesType(), $id, DOTCLEAR_PROCESS, 'Prepend');
        if (!is_subclass_of($class, 'Dotclear\\Module\\AbstractPrepend')) {
            return null;
        }

        try {
            # Do module installation
            $i = $class::installModule();

            # Update module version in db
            dcCore()->setVersion($id, $this->modules_enabled[$id]->version());

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
    public function disableModulesDependencies(string $redir_url): bool
    {
        if (isset($_GET['dep'])) {
            # Avoid infinite redirects
            return false;
        }
        $reason = [];
        foreach ($this->to_disable as $module) {
            try {
                $this->deactivateModule($module['id']);
                $reason[] = sprintf('<li>%s : %s</li>', $module['id'], join(',', $module['reason']));
            } catch (Exception $e) {
            }
        }
        if (count($reason)) {
            $message = sprintf(
                '<p>%s</p><ul>%s</ul>',
                __('The following modules have been disabled :'),
                join('', $reason)
            );
            dcCore()->notices->addWarningNotice($message, ['divtag' => true, 'with_ts' => false]);
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
     * @throws  ModuleException  (description)
     */
    public function deleteModule(string $id, bool $disabled = false): void
    {
        $modules = $disabled ? $this->modules_disabled : $this->modules_enabled;

        if (!isset($modules[$id])) {
            throw new ModuleException(__('No such module.'));
        }

        if (!Files::deltree($modules[$id]->root())) {
            throw new ModuleException(__('Cannot remove module files'));
        }
    }

    /**
     * Deactivate a module
     *
     * @param   string  $id     The identifier
     *
     * @throws  ModuleException
     */
    public function deactivateModule(string $id): void
    {
        if (!isset($this->modules_enabled[$id])) {
            throw new ModuleException(__('No such module.'));
        }

        if (!$this->modules_enabled[$id]->writable()) {
            throw new ModuleException(__('Cannot deactivate module.'));
        }

        if (@file_put_contents($this->modules_enabled[$id]->root() . '/_disabled', '')) {
            throw new ModuleException(__('Cannot deactivate module.'));
        }
    }

    /**
     * Activate a module
     *
     * @param   string  $id     The identifier
     *
     * @throws  ModuleException
     */
    public function activateModule(string $id): void
    {
        if (!isset($this->modules_disabled[$id])) {
            throw new ModuleException(__('No such module.'));
        }

        if (!$this->modules_disabled[$id]->writable()) {
            throw new ModuleException(__('Cannot activate modle.'));
        }

        if (@unlink($this->modules_disabled[$id]->root() . '/_disabled') === false) {
            throw new ModuleException(__('Cannot activate module.'));
        }
    }

    /**
     * Reset modules list
     */
    public function resetModulesList(): void
    {
        $this->modules_enabled  = [];
        $this->modules_disabled = [];
        $this->modules_version  = [];
        $this->error->reset();
    }

    /**
     * Returns all modules associative array
     *
     * @return  array   The modules.
     */
    public function getModules(): array
    {
        return $this->modules_enabled;
    }

    /**
     * Returns module <var>$id</var> array
     *
     * @param   string  $id     The module identifier
     *
     * @return  AbstractDefine|null     The module.
     */
    public function getModule(string $id): ?AbstractDefine
    {
        return $this->modules_enabled[$id] ?? null;
    }

    /**
     * Determines if module exists.
     *
     * @param   string  $id     The module identifier
     *
     * @return  bool    True if module exists, False otherwise.
     */
    public function hasModule(string $id): bool
    {
        return isset($this->modules_enabled[$id]);
    }

    /**
     * Gets the disabled modules.
     *
     * @return  array   The disabled modules.
     */
    public function getDisabledModules(): array
    {
        return $this->modules_disabled;
    }

    /**
     * Check if a module is part of the distribution.
     *
     * @param   string  $id     Module root directory
     *
     * @return  bool            True if module is part of the distribution
     */
    public function isDistributedModule(string $id): bool
    {
        return in_array($id, $this->getDistributedModules());
    }

    /**
     * Sort modules list by specific field.
     *
     * @param   array   $modules    Array of modules
     * @param   string  $field      Field to sort from
     * @param   bool    $asc        Sort asc if true, else decs
     *
     * @return  array               Array of sorted modules
     */
    public static function sortModules($modules, $field, $asc = true)
    {
        $origin = $sorter = $final = [];

        foreach ($modules as $id => $module) {
            $properties = $module->properties();
            $origin[] = $module;
            $sorter[] = $properties[$field] ?? $field;
        }

        array_multisort($sorter, $asc ? SORT_ASC : SORT_DESC, $origin);

        foreach ($origin as $module) {
            $final[$module->id()] = $module;
        }

        return $final;
    }

    /**
     * Default sort modules
     *
     * Version A < B = -1, A > B = 1, or compare name
     *
     * @param   AbstractDefine  $a  The module A
     * @param   AbstractDefine  $b  The module B
     *
     * @return  int                 The comparison result
     */
    private function defaultSortModules(AbstractDefine $a, AbstractDefine $b): int
    {
        if ($a->priority() == $b->priority()) {
            return strcasecmp($a->name(), $b->name());
        }

        return ($a->priority() < $b->priority()) ? -1 : 1;
    }
}
