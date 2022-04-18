<?php
/**
 * @note Dotclear\Module\AbstractModules
 * @brief Helper for admin list of modules.
 *
 * @ingroup  Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Exception\ModuleException;
use Dotclear\Helper\ErrorTrait;
use Dotclear\Helper\L10n;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Unzip;
use Dotclear\Helper\Network\Http;
use Exception;

abstract class AbstractModules
{
    use ErrorTrait;

    /** @var array List of enabled modules */
    protected $modules_enabled = [];

    /** @var array List of disabled modules */
    protected $modules_disabled = [];

    /** @var array List of modules versions */
    protected $modules_version = [];

    /** @var null|string Loading process, module id */
    private $id;

    /** @var bool Loading process, in disabled mode */
    private $disabled_mode = false;

    /** @var null|AbstractDefine Loading process, disabled module */
    private $disabled_meta;

    /** @var array Loading process, modules to disable */
    private $to_disable = [];

    private $modules_prepend = [];

    /**
     * Get modules type.
     *
     * @return string The modules type
     */
    abstract public function getModulesType(): string;

    /**
     * Get modules path.
     *
     * If more than one path exists, new module goes in this last path.
     *
     * @return array The modules path
     */
    abstract public function getModulesPath(): array;

    /**
     * Get list of distributed modules (by id).
     *
     * @return array List of distributed modules ids
     */
    abstract public function getDistributedModules(): array;

    /**
     * Check Process specifics on modules load.
     */
    abstract protected function loadModulesProcess(): void;

    /**
     * Check Process specifics on module load.
     *
     * @param string $id Current module id
     */
    abstract protected function loadModuleProcess(string $id): void;

    /**
     * Check Process specifics on modules define load.
     *
     * @param AbstractDefine $define Current module to check
     *
     * @return bool Module is OK
     */
    abstract protected function loadModuleDefineProcess(AbstractDefine $define): bool;

    /**
     * Constructor, load Modules.
     *
     * @param null|string $lang    The language
     * @param bool        $no_load Only create Modules instance without loading modules
     */
    public function __construct(?string $lang = null, bool $no_load = false)
    {
        if ($no_load) {
            return;
        }

        // Loop through each modules root path
        foreach ($this->getModulesPath() as $root) {
            // Check dir
            if (empty($root) || !is_dir($root) || !is_readable($root)) {
                continue;
            }

            // Open dir
            if (false === ($handle = @dir($root))) {
                continue;
            }

            // Loop through current modules root path
            while (false !== ($this->id = $handle->read())) {
                $entry_path = Path::implode($root, $this->id);

                // Check dir
                if ('.' != $this->id && '..' != $this->id && is_dir($entry_path)) {
                    // Module will be disabled
                    $entry_enabled = !file_exists($entry_path . '/_disabled') && !dotclear()->rescue();
                    if (!$entry_enabled) {
                        $this->disabled_mode = true;
                    }

                    // Check module Define
                    $this->loadModuleDefine($entry_path, $this->id);

                    // Add module namespace
                    if ($entry_enabled) {
                        dotclear()->autoload()->addNamespace('Dotclear\\' . $this->getModulesType() . '\\' . $this->id, $entry_path);
                    // Save module in disabled list
                    } elseif (null !== $this->disabled_meta) {
                        $this->disabled_mode               = false;
                        $this->modules_disabled[$this->id] = $this->disabled_meta;
                        $this->disabled_meta               = null;
                    }
                }
                $this->id = null;
            }
            $handle->close();
        }

        // Check modules dependencies
        $this->checkModulesDependencies();

        // Load modules specifics for current Process
        $this->loadModulesProcess();

        // Sort modules
        uasort($this->modules_enabled, [$this, 'defaultSortModules']);

        // Load modules stuff
        foreach ($this->modules_enabled as $id => $define) {
            // Search module Prepend ex: Dotclear\Plugin\MyPloug\Admin\Prepend
            $class       = 'Dotclear\\' . $this->getModulesType() . '\\' . $id . '\\' . dotclear()->processed() . '\Prepend';
            $has_prepend = is_subclass_of($class, 'Dotclear\\Module\\AbstractPrepend');

            // Check module and stop if method not returns True statement
            if ($has_prepend) {
                $this->modules_prepend[$id] = new $class($define);
                if (true !== $this->modules_prepend[$id]->checkModule()) {
                    continue;
                }
            }

            // Load module main l10n
            if ($lang) {
                $this->loadModuleL10N($id, $lang, 'main');
                $this->loadModuleL10N($id, $lang, strtolower(dotclear()->processed()));
            }

            // Load module process specifics (auto register admi nurl, ...)
            $this->loadModuleProcess($id);

            // Load all others stuff from module (menu,favs,behaviors,...)
            if ($has_prepend) {
                $this->modules_prepend[$id]->loadModule();
            }
        }
    }

    public function loadModuleDefine(string $dir, string $id): void
    {
        // Include module Define file
        ob_start();

        try {
            $class  = 'Dotclear\\Module\\' . $this->getModulesType() . '\\Define' . $this->getModulesType();
            $define = new $class($id, $dir . '/define.xml');
        } catch (ModuleException) {
            ob_end_clean();

            return;
        }
        ob_end_clean();

        // Stop on error in module definition
        if ($define->error()->flag()) {
            $this->error()->add($define->error()->dump());

            return;
        }

        // Set module as disabled and stop
        if ($this->disabled_mode) {
            $define->disableModule();
            $this->disabled_meta = $define;

            return;
        }

        if ($this->loadModuleDefineProcess($define) === false) {
            return;
        }

        // Check module install on multiple path (only from ::loadModules() )
        if ($this->id) {
            $module_exists    = array_key_exists($define->id(), $this->modules_version);
            $module_overwrite = $module_exists ? version_compare($this->modules_version[$define->id()], $define->version(), '<') : false;
            if (!$module_exists || $module_overwrite) {
                $this->modules_version[$define->id()] = $define->version();
                $this->modules_enabled[$this->id]     = $define;
            } else {
                $this->error()->add(sprintf(
                    __('Module "%s" is installed twice in "%s" and "%s".'),
                    '<strong>' . $define->name() . '</strong>',
                    '<em>' . Path::real($this->modules_enabled[$define->id()]->root(), false) . '</em>',
                    '<em>' . Path::real($dir, false) . '</em>'
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
     * @param string      $id   The module identifier
     * @param null|string $lang The language code
     * @param string      $file The filename (without extension)
     */
    public function loadModuleL10N(string $id, ?string $lang, string $file): void
    {
        if (!$lang || !isset($this->modules_enabled[$id])) {
            return;
        }

        $lfile = $this->modules_enabled[$id]->root() . '/locales/%s/%s';
        if (L10n::set(sprintf($lfile, $lang, $file)) === false && 'en' != $lang) {
            L10n::set(sprintf($lfile, 'en', $file));
        }
    }

    /**
     * Checks all modules dependencies.
     *
     *     Fills in the following information in module :
     *       * cannot_enable : list reasons why module cannot be enabled. Not set if module can be enabled
     *       * cannot_disable : list reasons why module cannot be disabled. Not set if module can be disabled
     *       * implies : reverse dependencies
     */
    public function checkModulesDependencies(): void
    {
        $modules          = array_merge($this->modules_enabled, $this->modules_disabled);
        $dc_version       = preg_replace('/\-dev.*$/', '', dotclear()->config()->get('core_version'));
        $this->to_disable = [];

        foreach ($modules as $id => $module) {
            // Grab missing dependencies
            $missing = [];
            foreach ($module->requires() as $dep) {
                // Module not present
                if (!isset($modules[$dep[0]]) && 'core' != $dep[0]) {
                    $missing[$dep[0]] = sprintf(__('Requires %s module which is not installed'), $dep[0]);
                // Module present, but version missing
                } elseif (count($dep) > 1 && version_compare(('core' == $dep[0] ? $dc_version : $modules[$dep[0]]->version()), $dep[1]) == -1) {
                    if ('core' == $dep[0]) {
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
                    // Module is disabled
                } elseif ('core' != $dep[0] && !$modules[$dep[0]]->enabled()) {
                    $missing[$dep[0]] = sprintf(__('Requires %s module which is disabled'), $dep[0]);
                } elseif ('core' != $dep[0]) {
                    $modules[$dep[0]]->depParents($id);
                }
            }
            // Set module to disable
            if (count($missing)) {
                $module->depMissing($missing);
                if ($module->enabled()) {
                    $this->to_disable[] = ['id' => $id, 'reason' => $missing];
                }
            }
        }
        // Check modules that cannot be disabled
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
     * Install a Package.
     *
     * @param string          $zip_file The zip file
     * @param AbstractModules $modules  The modules
     *
     * @throws ModuleException
     */
    public function installPackage(string $zip_file, AbstractModules $modules): int
    {
        $zip = new Unzip($zip_file);
        $zip->getList(false, '#(^|/)(__MACOSX|\.svn|\.hg.*|\.git.*|\.DS_Store|\.directory|Thumbs\.db)(/|$)#');

        $zip_root_dir = $zip->getRootDir();
        $define       = '';
        if (false != $zip_root_dir) {
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

                $new_errors = $sandbox->error()->dump();
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
            // Test for update
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
                    && (!dotclear()->production() || dotclear()->version()->compare($new_modules[$id]['version'], $cur_module['version'], '>', true))
                ) {
                    // Delete old module
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
     */
    public function installModules(): array
    {
        $res = ['success' => [], 'failure' => []];
        foreach ($this->modules_enabled as $id => $module) {
            $msg = '';
            $i   = $this->installModule($id, $msg);
            if (true === $i) {
                $res['success'][$id] = true;
            } elseif (false === $i) {
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
     * @param string $id  The identifier
     * @param string $msg The message
     */
    public function installModule(string $id, string &$msg): ?bool
    {
        if (!isset($this->modules_enabled[$id]) || !isset($this->modules_prepend[$id])) {
            return null;
        }

        // Check module version in db
        if (version_compare((string) dotclear()->version()->get($id), (string) $this->modules_enabled[$id]->version(), '>=')) {
            return null;
        }

        try {
            // Do module installation
            $i = $this->modules_prepend[$id]->installModule();

            // Update module version in db
            dotclear()->version()->set($id, $this->modules_enabled[$id]->version());

            return $i ? true : null;
        } catch (Exception $e) {
            $msg = $e->getMessage();

            return false;
        }
    }

    /**
     * Disables the dep modules.
     *
     * @param string $redir_url URL to redirect if modules are to disable
     *
     * @return bool true if a redirection has been performed
     */
    public function disableModulesDependencies(string $redir_url): bool
    {
        if (isset($_GET['dep'])) {
            // Avoid infinite redirects
            return false;
        }
        $reason = [];
        foreach ($this->to_disable as $module) {
            try {
                $this->deactivateModule($module['id']);
                $reason[] = sprintf('<li>%s : %s</li>', $module['id'], join(',', $module['reason']));
            } catch (\Exception) {
            }
        }
        if (count($reason)) {
            $message = sprintf(
                '<p>%s</p><ul>%s</ul>',
                __('The following modules have been disabled :'),
                join('', $reason)
            );
            dotclear()->notice()->addWarningNotice($message, ['divtag' => true, 'with_ts' => false]);
            $url = $redir_url . (str_contains($redir_url, '?') ? '&' : '?') . 'dep=1';
            Http::redirect($url);

            return true;
        }

        return false;
    }

    /**
     * Delete a module.
     *
     * @param string $id       The module identifier
     * @param bool   $disabled Is module disabled
     *
     * @throws ModuleException (description)
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
     * Deactivate a module.
     *
     * @param string $id The identifier
     *
     * @throws ModuleException
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
     * Activate a module.
     *
     * @param string $id The identifier
     *
     * @throws ModuleException
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
     * Reset modules list.
     */
    public function resetModulesList(): void
    {
        $this->modules_enabled  = [];
        $this->modules_disabled = [];
        $this->modules_version  = [];
        $this->error()->reset();
    }

    /**
     * Returns all modules associative array.
     *
     * @return array the modules
     */
    public function getModules(): array
    {
        return $this->modules_enabled;
    }

    /**
     * Returns module <var>$id</var> array.
     *
     * @param string $id The module identifier
     *
     * @return null|object the module
     */
    public function getModule(string $id): ?object
    {
        return $this->modules_enabled[$id] ?? null;
    }

    /**
     * Determines if module exists.
     *
     * @param string $id The module identifier
     *
     * @return bool true if module exists, False otherwise
     */
    public function hasModule(string $id): bool
    {
        return isset($this->modules_enabled[$id]);
    }

    /**
     * Gets the disabled modules.
     *
     * @return array the disabled modules
     */
    public function getDisabledModules(): array
    {
        return $this->modules_disabled;
    }

    /**
     * Check if a module is part of the distribution.
     *
     * @param string $id Module root directory
     *
     * @return bool True if module is part of the distribution
     */
    public function isDistributedModule(string $id): bool
    {
        return in_array($id, $this->getDistributedModules());
    }

    /**
     * Sort modules list by specific field.
     *
     * @param array  $modules Array of modules
     * @param string $field   Field to sort from
     * @param bool   $asc     Sort asc if true, else decs
     *
     * @return array Array of sorted modules
     */
    public function sortModules(array $modules, string $field, bool $asc = true): array
    {
        $origin = $sorter = $final = [];

        foreach ($modules as $id => $module) {
            $properties = $module->properties();
            $origin[]   = $module;
            $sorter[]   = $properties[$field] ?? $field;
        }

        array_multisort($sorter, $asc ? SORT_ASC : SORT_DESC, $origin);

        foreach ($origin as $module) {
            $final[$module->id()] = $module;
        }

        return $final;
    }

    /**
     * Default sort modules.
     *
     * Version A < B = -1, A > B = 1, or compare name
     *
     * @param AbstractDefine $a The module A
     * @param AbstractDefine $b The module B
     *
     * @return int The comparison result
     */
    private function defaultSortModules(AbstractDefine $a, AbstractDefine $b): int
    {
        if ($a->priority() == $b->priority()) {
            return strcasecmp($a->name(), $b->name());
        }

        return ($a->priority() < $b->priority()) ? -1 : 1;
    }
}
