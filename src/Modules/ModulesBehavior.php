<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules;

// Dotclear\Modules\ModulesBehavior
use Dotclear\App;
use Dotclear\Helper\Mapper\NamedStrings;

/**
 * Admin behaviors for modules manager.
 *
 * @ingroup  Modules Behavior
 */
class ModulesBehavior
{
    /**
     * @var array<string,array> $install
     *                          List of newly installed modules messages
     */
    private $install = [];

    /**
     * Constructor.
     *
     * This method register current Modules manager Admin Behaviors.
     *
     * @param Modules $modules The modules manager
     */
    public function __construct(private Modules $modules)
    {
        if ($this->modules->hasModules()) {
            App::core()->behavior('adminBeforeGetHomePage')->add([$this, 'adminBeforeGetHomePage']);
            App::core()->behavior('adminBeforeGetHomePageContent')->add([$this, 'adminBeforeGetHomePageContent']);
        }

        App::core()->behavior('adminBeforeCheckStoreUpdate')->add([$this, 'adminBeforeCheckStoreUpdate']);
    }

    /**
     * Do admin home page modules check.
     *
     * Check modules depedencies and try to install new modules
     */
    public function adminBeforeGetHomePage(): void
    {
        if ($this->modules->disableDependencies(App::core()->adminurl()->get('admin.home'))) {
            exit;
        }

        $this->install = $this->modules->installModules();
    }

    /**
     * Display admin home page modules check results.
     */
    public function adminBeforeGetHomePageContent(): void
    {
        $type = '<strong>' . $this->modules->getName() . ':</strong> ';

        if (!empty($this->install['success'])) {
            echo '<div class="success">' . $type . __('Following modules have been installed:') . '<ul>';
            foreach ($this->install['success'] as $k => $v) {
                $info = implode(' - ', $this->modules->getSettingsUrls($k, true));
                echo '<li>' . $k . ('' !== $info ? ' → ' . $info : '') . '</li>';
            }
            echo '</ul></div>';
        }
        if (!empty($this->install['failure'])) {
            echo '<div class="error">' . $type . __('Following plugins have not been installed:') . '<ul>';
            foreach ($this->install['failure'] as $k => $v) {
                echo '<li>' . $k . ' (' . $v . ')</li>';
            }
            echo '</ul></div>';
        }

        // Errors modules notifications
        if (App::core()->user()->isSuperAdmin()) {
            if ($this->modules->error()->flag()) {
                echo '<div class="error" id="module-errors" class="error"><p>' . $type . __('Errors have occured with following modules:') . '</p> ' .
                '<ul><li>' . implode("</li>\n<li>", $this->modules->error()->dump()) . '</li></ul></div>';
            }
        }
    }

    /**
     * Check modules manager repository update.
     *
     * @param string       $type   The modules type
     * @param NamedStrings $update The list of updates
     */
    public function adminBeforeCheckStoreUpdate(string $type, NamedStrings $update): void
    {
        if ($this->modules->getType() == $type) {
            foreach ($this->modules->store()->get(true) as $id => $module) {
                $udpate->set($id, $module->name());
            }
        }
    }
}
