<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules;

// Dotclear\Modules\Modules
use Dotclear\App;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\ErrorTrait;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Unzip;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Modules manager.
 *
 * @ingroup  Module
 */
class Modules
{
    // Error handler
    use ErrorTrait;

    /**
     * @var null|ModuleDefine $current_meta
     *                        Current module definition
     */
    private $current_define;

    /**
     * @var bool $current_disabled
     *           Current processed module in (dis)abled mode
     */
    private $current_disabled = false;

    /**
     * @var string $id
     *             Current processed module id
     */
    private $current_id = '';

    /**
     * @var array<string,ModuleDefine> $modules_disabled
     *                                 List of disabled modules
     */
    private $modules_disabled = [];

    /**
     * @var array<string,ModuleDefine> $modules_enabled
     *                                 List of enabled modules
     */
    private $modules_enabled = [];

    /**
     * @var array<string,ModulePrepend> $modules_prepend
     *                                  List of loaded modules prepend class
     */
    private $modules_prepend = [];

    /**
     * @var array<string,string> $modules_version
     *                           List of modules versions
     */
    private $modules_version = [];

    /**
     * @var array<string,array> $to_disable
     *                          List of modules to disable and the reasons
     */
    private $to_disable = [];

    // / @name modules loading methods
    // @{
    /**
     * Constructor.
     *
     * @param string $type    The modules type (Plugin, Theme, ...)
     * @param string $lang    The language to cope with (can be empty)
     * @param bool   $no_load Only create Modules instance without loading them
     * @param string $name    The modules manager name
     * @param string $group   The modules manager menu group
     * @param bool   $admin   Allow admin to view modules manager
     */
    public function __construct(
        private string $type = 'Plugin',
        private string $lang = '',
        private bool $no_load = false,
        private string $name = '',
        private string $group = '',
        bool $admin = false
    ) {
        if ($this->no_load) {
            return;
        }

        $this->loadModules();

        if (App::core()->processed('Admin')) {
            $this->register($admin);
        }
    }

    /**
     * Register modules manager on admin url,menu,favs.
     *
     * This method is only available in Admin Process.
     *
     * @param bool $admin Allow admin to view modules manager
     */
    protected function register(bool $admin): void
    {
        if (!$admin && !App::core()->user()->isSuperAdmin() || !App::core()->user()->check('admin', App::core()->blog()->id)) {
            return;
        }

        $name    = empty($this->name) ? __('Plugins management') : $this->name;
        $icons   = ['images/menu/' . $this->getType(true) . 's.svg', 'images/menu/' . $this->getType(true) . 's-dark.svg'];
        $handler = 'admin.' . $this->getType(true);

        App::core()->adminurl()->register(
            $handler,
            'Dotclear\\Modules\\' . $this->getType() . '\\' . $this->getType() . 'Handler'
        );
        App::core()->summary()->register(
            empty($this->group) ? 'System' : $this->group,
            $name,
            $handler,
            $icons,
            true
        );
        App::core()->favorite()->register($this->getType(), [
            'title'      => $name,
            'url'        => App::core()->adminurl()->get($handler),
            'small-icon' => $icons,
            'large-icon' => $icons,
        ]);
    }

    /**
     * Load modules.
     */
    protected function loadModules()
    {
        // Loop through each modules root path
        foreach ($this->getPaths() as $root) {
            if (empty($root) || !is_dir($root) || !is_readable($root) || false === ($handle = dir($root))) {
                continue;
            }

            // Loop through current modules root path
            while (false !== ($this->current_id = $handle->read())) {
                $dir = Path::implode($handle->path, $this->current_id);
                if (in_array($this->current_id, ['.', '..']) || !is_dir($dir)) {
                    continue;
                }

                // Module will be disabled
                $enabled = !file_exists($dir . '/_disabled') && !App::core()->rescue();
                if (!$enabled) {
                    $this->current_disabled = true;
                }

                // Check module Define
                $this->loadDefine($dir, $this->current_id);

                // Add module namespace
                if ($enabled) {
                    App::core()->autoload()->addNamespace('Dotclear\\' . $this->getType() . '\\' . $this->current_id, $dir);
                // Save module in disabled list
                } elseif (null !== $this->current_define) {
                    $this->modules_disabled[$this->current_id] = $this->current_define;
                }

                $this->current_define   = null;
                $this->current_disabled = false;
                $this->current_id       = '';
            }
            $handle->close();
        }

        // Check modules dependencies
        $this->checkDependencies();

        // Sort modules
        $sorter = fn (ModuleDefine $a, ModuleDefine $b): int => $a->priority() == $b->priority() ? strcasecmp($a->name(), $b->name()) : ($a->priority() < $b->priority() ? -1 : 1);
        uasort($this->modules_enabled, $sorter);

        // Load modules stuff
        foreach ($this->modules_enabled as $id => $define) {
            $this->loadModule($define);
        }
    }

    /**
     * Get modules type.
     *
     * @param bool $lowercase Rturn type in lowercase
     *
     * @return string The modules type
     */
    public function getType(bool $lowercase = false): string
    {
        return $lowercase ? strtolower($this->type) : $this->type;
    }

    /**
     * Get language code to cope with.
     *
     * @return null|string The language code
     */
    public function getlang(): ?string
    {
        return $this->lang;
    }

    /**
     * Get modules root directory paths.
     *
     * If a module directory is set for current blog,
     * it will be added to the end of paths.
     *
     * @return array<int,string> The paths
     */
    public function getPaths(): array
    {
        $paths = App::core()->config()->get($this->getType(true) . '_dirs');

        if (App::core()->blog()) {
            $path = trim((string) App::core()->blog()->settings()->get('system')->get('module_' . $this->getType(true) . '_dir'));
            if (!empty($path) && false !== ($dir = Path::real(str_starts_with('\\', $path) ? $path : Path::implodeRoot($path), true))) {
                $paths[] = $dir;
            }
        }

        return $paths ?: [];
    }

    /**
     * Try to load a module definition.
     *
     * @param string $dir Module directory path
     * @param string $id  Module id
     */
    public function loadDefine(string $dir, string $id): void
    {
        // Include module Define file
        ob_start();

        try {
            $define = new ModuleDefine($this->getType(), $id, $dir);
        } catch (Exception) {
            ob_end_clean();

            return;
        }
        ob_end_clean();

        // Stop on error in module definition
        if (true === $define->error()->flag()) {
            $this->error()->add(implode("\n", $define->error()->dump()));

            return;
        }

        // Set module as disabled and stop
        if (true === $this->current_disabled) {
            $define->disableModule();
            $this->current_define = $define;

            return;
        }

        // Check module permissions
        if (App::core()->processed('Admin')
            && (
                '' == $define->permissions() && !App::core()->user()->isSuperAdmin()
                || $define->permissions() && !App::core()->user()->check($define->permissions(), App::core()->blog()->id)
            )
        ) {
            return;
        }

        // Check module install on multiple path (only from ::loadModules() )
        if (!empty($this->current_id)) {
            if (!array_key_exists($define->id(), $this->modules_version)
                || version_compare($this->modules_version[$define->id()], $define->version(), '<')
            ) {
                $this->modules_version[$define->id()]     = $define->version();
                $this->modules_enabled[$this->current_id] = $define;
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
     * Checks all modules dependencies.
     *
     *     Fills in the following information in module :
     *       * cannot_enable : list reasons why module cannot be enabled. Not set if module can be enabled
     *       * cannot_disable : list reasons why module cannot be disabled. Not set if module can be disabled
     *       * implies : reverse dependencies
     */
    public function checkDependencies(): void
    {
        $modules          = array_merge($this->modules_enabled, $this->modules_disabled);
        $dc_version       = preg_replace('/\-dev.*$/', '', App::core()->config()->get('core_version'));
        $this->to_disable = [];

        // Loop through ALL known modules
        foreach ($modules as $id => $module) {
            // Grab missing dependencies
            $missing = [];
            foreach ($module->requires() as $r_id => $r_version) {
                // Module not present
                if (!isset($modules[$r_id]) && 'core' != strtolower($r_id)) {
                    $missing[] = sprintf(__('Requires %s module which is not installed'), $r_id);
                // Module present, but version missing
                } elseif (-1 == version_compare(('core' == strtolower($r_id) ? $dc_version : $modules[$r_id]->version()), $r_version)) {
                    if ('core' == strtolower($r_id)) {
                        $missing[] = sprintf(
                            __('Requires Dotclear version %s, but version %s is installed'),
                            $r_version,
                            $dc_version
                        );
                    } else {
                        $missing[] = sprintf(
                            __('Requires %s module version %s, but version %s is installed'),
                            $r_id,
                            $r_version,
                            $modules[$r_id]->version()
                        );
                    }
                    // Module is disabled
                } elseif ('core' != strtolower($r_id) && !$modules[$r_id]->enabled()) {
                    $missing[] = sprintf(__('Requires %s module which is disabled'), $r_id);
                } elseif ('core' != strtolower($r_id)) {
                    $modules[$r_id]->depParents($id);
                }
            }
            // Set module to disable
            if (count($missing)) {
                $module->depMissing($missing);
                if ($module->enabled()) {
                    $this->to_disable[$id] = $missing;
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
     * Load additionnal module.
     *
     * @param ModuleDefine $define Module definition
     */
    public function loadModule(ModuleDefine $define): void
    {
        // Search module Prepend in Dotclear\Plugin\MyPlugin\Admin\Prepend who should extend Dotclear\Modules\ModulePrepend
        $class       = 'Dotclear\\' . $this->getType() . '\\' . $define->id() . '\\' . App::core()->processed() . '\Prepend';
        $has_prepend = is_subclass_of($class, 'Dotclear\\Modules\\ModulePrepend');

        // Check module and stop if method not returns True statement
        if ($has_prepend) {
            $this->modules_prepend[$define->id()] = new $class($define);
            if (true !== $this->modules_prepend[$define->id()]->checkModule()) {
                return;
            }
        }

        // Load module main l10n
        $this->loadModuleL10N($define->id(), $this->getLang(), 'main');
        $this->loadModuleL10N($define->id(), $this->getLang(), strtolower(App::core()->processed()));

        // If module has an Admin Page, create an admin url
        if (App::core()->processed('Admin')) {
            $class = 'Dotclear\\' . $this->getType() . '\\' . $define->id() . '\\Admin\\' . 'Handler';
            if (is_subclass_of($class, 'Dotclear\\Process\\Admin\\Page\\AbstractPage')) {
                App::core()->adminurl()->register('admin.' . $this->getType(true) . '.' . $define->id(), $class);
            }
        }

        // Load all others stuff from module (menu,favs,behaviors,...)
        if ($has_prepend) {
            $this->modules_prepend[$define->id()]->loadModule();
        }
    }

    /**
     * Search for file $file in language $lang for module $id.
     *
     * $file should not have any extension.
     *
     * @param string $id   The module identifier
     * @param string $lang The language code
     * @param string $file The filename (without extension)
     */
    public function loadModuleL10N(string $id, string $lang, string $file): void
    {
        if ('' == $lang || !isset($this->modules_enabled[$id])) {
            return;
        }

        $locales = $this->modules_enabled[$id]->root() . '/locales/%s/%s';
        if (false === L10n::set(sprintf($locales, $lang, $file)) && 'en' != $lang) {
            L10n::set(sprintf($locales, 'en', $file));
        }
    }
    // @}

    // / @name Modules reading methods
    // @{
    /**
     * Get all modules associative array.
     *
     * @return array<string,ModuleDefine> The modules list
     */
    public function getModules(): array
    {
        return $this->modules_enabled;
    }

    /**
     * Get module $id definition.
     *
     * @param string $id The module identifier
     *
     * @return null|object The module definition
     */
    public function getModule(string $id): ?object
    {
        return $this->modules_enabled[$id] ?? null;
    }

    /**
     * Check if module exists.
     *
     * @param string $id The module identifier
     *
     * @return bool True if module exists, False otherwise
     */
    public function hasModule(string $id): bool
    {
        return isset($this->modules_enabled[$id]);
    }

    /**
     * Get list of disabled modules.
     *
     * @return array<string,ModuleDefine> The disabled modules
     */
    public function getDisabledModules(): array
    {
        return $this->modules_disabled;
    }

    /**
     * Get list of distributed modules (by id).
     *
     * @return array<int,string> List of distributed modules ids
     */
    public function getDistributedModules(): array
    {
        return App::core()->config()->get($this->getType(true) . '_official');
    }

    /**
     * Check if a module is part of the distribution.
     *
     * @param string $id The module identifier
     *
     * @return bool True if module is part of the distribution
     */
    public function isDistributedModule(string $id): bool
    {
        return in_array($id, $this->getDistributedModules());
    }
    // @}

    // / @name Modules management methods
    // @{
    /**
     * Install a Package.
     *
     * @param string $zip_file The zip file
     *
     * @throws ModuleException
     */
    public function installPackage(string $zip_file): int
    {
        $zip = new Unzip($zip_file);
        $zip->getList(false, '#(^|/)(__MACOSX|\.svn|\.hg.*|\.git.*|\.DS_Store|\.directory|Thumbs\.db)(/|$)#');

        $zip_root_dir = $zip->getRootDir();
        $define       = '';
        if (false != $zip_root_dir) {
            $target      = dirname($zip_file);
            $destination = $target . '/' . $zip_root_dir;
            $define      = $zip_root_dir . '/module.conf.php';
            $has_define  = $zip->hasFile($define);
        } else {
            $target      = dirname($zip_file) . '/' . preg_replace('/\.([^.]+)$/', '', basename($zip_file));
            $destination = $target;
            $define      = 'module.conf.php';
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

                $sandbox = clone $this;
                $zip->unzip($define, $target . '/module.conf.php');

                $sandbox->resetModulesList();
                $sandbox->loadDefine($target, basename($destination));
                unlink($target . '/module.conf.php');

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
            $sandbox = clone $this;
            $zip->unzip($define, $target . '/module.conf.php');

            $sandbox->resetModulesList();
            $sandbox->loadDefine($target, basename($destination));
            unlink($target . '/module.conf.php');
            $new_modules = $sandbox->getModules();

            if (!empty($new_modules)) {
                $tmp        = array_keys($new_modules);
                $id         = $tmp[0];
                $cur_module = $this->getModule($id);
                if (!empty($cur_module)
                    && (!App::core()->production() || App::core()->version()->compare($new_modules[$id]['version'], $cur_module['version'], '>', true))
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
     * Install all modules having a Prepend class.
     *
     * @return array<string,array> The install result
     */
    public function installModules(): array
    {
        $res = ['success' => [], 'failure' => []];
        foreach ($this->modules_enabled as $id => $module) {
            $msg = $this->installModule($id);
            if (true === $msg) {
                $res['success'][$id] = true;
            } elseif (is_string($msg)) {
                $res['failure'][$id] = $msg;
            }
        }

        return $res;
    }

    /**
     * Install module having a Prepend class.
     *
     * This method returns :
     * - False if nothing done,
     * - True on install success
     * - A message, if an error occured
     *
     * @param string $id The identifier
     *
     * @return bool|string The error message or install success
     */
    public function installModule(string $id): bool|string
    {
        if (!isset($this->modules_enabled[$id]) || !isset($this->modules_prepend[$id])) {
            return false;
        }

        // Check module version in db
        if (version_compare(App::core()->version()->get($id), $this->modules_enabled[$id]->version(), '>=')) {
            return false;
        }

        try {
            // Do module installation
            $i = $this->modules_prepend[$id]->installModule();

            // Update module version in db
            App::core()->version()->set($id, $this->modules_enabled[$id]->version());

            return $i ? true : false;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Disables the dep modules.
     *
     * @param string $redir_url URL to redirect if modules are to disable
     *
     * @return bool true if a redirection has been performed
     */
    public function disableDependencies(string $redir_url): bool
    {
        if (isset($_GET['dep'])) {
            // Avoid infinite redirects
            return false;
        }
        $messages = [];
        foreach ($this->to_disable as $id => $reason) {
            try {
                $this->deactivateModule($id);
                $messages[] = sprintf('<li>%s : %s</li>', $id, join(',', $reason));
            } catch (Exception) {
            }
        }
        if (count($messages)) {
            $message = sprintf(
                '<p>%s</p><ul>%s</ul>',
                __('The following modules have been disabled :'),
                join('', $messages)
            );
            App::core()->notice()->addWarningNotice($message, ['divtag' => true, 'with_ts' => false]);
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
     * @throws ModuleException
     */
    public function deleteModule(string $id, bool $disabled = false): void
    {
        $modules = $disabled ? $this->modules_disabled : $this->modules_enabled;

        if (!isset($modules[$id])) {
            throw new ModuleException(__('No such module.'));
        }

        try {
            if (!Files::deltree($modules[$id]->root())) {
                throw new ModuleException(__('Cannot remove module files'));
            }
        } catch (Exception) {
            throw new ModuleException(__('Cannot remove module files.'));
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

        try {
            if (false === file_put_contents($this->modules_enabled[$id]->root() . '/_disabled', '')) {
                throw new ModuleException(__('Cannot deactivate module.'));
            }
        } catch (Exception) {
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

        try {
            if (false === unlink($this->modules_disabled[$id]->root() . '/_disabled')) {
                throw new ModuleException(__('Cannot activate module.'));
            }
        } catch (Exception) {
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
    // @}

    // / @name Theme specific methods
    // @{
    /**
     * Get current theme path.
     *
     * Return an array of theme and parent theme paths
     *
     * @param null|string $suffix Optionnal sub folder
     *
     * @return array List of theme path
     */
    public function getThemePath(?string $suffix = null): array
    {
        $suffix = $suffix ? '/' . $suffix : '';
        $path   = [];

        if (null !== App::core()->blog()) {
            $theme = $this->getModule((string) App::core()->blog()->settings()->get('system')->get('theme'));
            if (!$theme) {
                $theme = $this->getModule(App::core()->config()->get('theme_default'));
            }
            if (!$theme) {
                return [];
            }
            $path[$theme->id()] = $theme->root() . $suffix;

            if ($theme->parent()) {
                $parent = $this->getModule((string) $theme->parent());
                if ($parent) {
                    $theme = $this->getModule(App::core()->config()->get('theme_default'));
                }
                $path[$parent->id()] = $parent->root() . $suffix;
            }
        }

        return $path;
    }
    // @}
}
