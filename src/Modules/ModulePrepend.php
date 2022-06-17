<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules;

// Dotclear\Modules\ModulePrepend
use Dotclear\App;
use Dotclear\Process\Admin\Favorite\Favorite;
use Dotclear\Process\Admin\Favorite\FavoriteItem;
use Dotclear\Process\Admin\Menu\MenuItem;

/**
 * Module Prepend.
 *
 * Generic class for module that require prepend actions,
 * this class is avaibale in all Process.
 *
 * @ingroup  Module
 */
class ModulePrepend
{
    /**
     * @var FavoriteItem $favorite
     *                   Module Favorites (On Admin Process only)
     */
    private $favorite;

    /**
     * Constructor.
     *
     * @param ModuleDefine $define Module Define instance
     */
    public function __construct(private ModuleDefine $define)
    {
    }

    /**
     * Get module definitions.
     *
     * @return ModuleDefine Module Define instance
     */
    protected function define(): ModuleDefine
    {
        return $this->define;
    }

    /**
     * Check Module during process (Amdin, Public, Install, ...).
     *
     * Module can check their specifics requirements here.
     *
     * @return bool False to stop module loading, True to go on
     */
    public function checkModule(): bool
    {
        return true;
    }

    /**
     * Load Module during process (Amdin, Public, Install, ...).
     *
     * For exemple, if module required Prepend class
     * for backend (Admin) to load admin menu, etc...
     */
    public function loadModule(): void
    {
    }

    // / @name Public specific methods
    // @{
    /**
     * Add template path.
     *
     * On Public Process, if module has a "templates" path,
     * add it to templateset paths.
     */
    public function addTemplatePath(): void
    {
        if (!App::core()->processed('Public') || !is_dir($this->define()->root() . '/templates/')) {
            return;
        }

        App::core()->behavior()->add('publicBeforeGetDocument', function () {
            $tplset = App::core()->themes()->getModule((string) App::core()->blog()->settings()->getGroup('system')->getSetting('theme'))->templateset();
            App::core()->template()->setPath(
                App::core()->template()->getPath(),
                $this->define()->root() . '/templates/' . (!empty($tplset) && is_dir($this->define()->root() . '/templates/' . $tplset) ? $tplset : App::core()->config()->get('template_default'))
            );
        });
    }
    // @}

    // / @name Admin specific methods
    // @{
    /**
     * Install Module during process (Amdin, Public, Install, ...).
     *
     * For exemple, if module required Prepend class
     * to set up settings, database table, etc...
     * For now only Admin process support install method.
     *
     * @return bool True on success
     */
    public function installModule(): ?bool
    {
        return null;
    }

    /**
     * Helper to add a standard admin menu item
     * according to module define properties.
     *
     * $permissions can be:
     *  * null = superAdmin,
     *  * string = commaseparated list of permissions,
     *  * empty string = follow module define permissions
     *
     * @param null|string $menu        The menu name
     * @param null|string $permissions The permissions
     */
    protected function addStandardMenu(?string $menu = null, ?string $permissions = ''): void
    {
        if (!App::core()->processed('Admin') || !App::core()->adminurl()->exists('admin.' . $this->define()->type(true) . '.' . $this->define()->id())) {
            return;
        }

        if (!$menu || null === App::core()->menus()->getGroup($menu)) {
            $menu = 'Plugins';
        }
        if ('' === $permissions) {
            $permissions = $this->define()->permissions();
        }

        App::core()->menus()->getGroup($menu)?->addItem(new MenuItem(
            title: $this->define()->name(),
            url: App::core()->adminurl()->get('admin.' . $this->define()->type(true) . '.' . $this->define()->id()),
            icons: [
                $this->define()->type() . '/' . $this->define()->id() . '/icon.svg',
                $this->define()->type() . '/' . $this->define()->id() . '/icon-dark.svg',
            ],
            permission: $permissions
        ));
    }

    /**
     * Helper to add a standard admin Favorite item.
     *
     * If permission is not set, defined module permissions are used
     *
     * @param null|string $permissions Special permissions to show Favorite
     */
    protected function addStandardFavorites(?string $permissions = null): void
    {
        if (!App::core()->processed('Admin') || !App::core()->adminurl()->exists('admin.' . $this->define()->type(true) . '.' . $this->define()->id())) {
            return;
        }

        App::core()->behavior()->add('adminAfterSetDefaultFavoriteItems', function (Favorite $favorite): void {
            $favorite->AddItem($this->favorite);
        });

        $url = $this->define()->type() . '/' . $this->define()->id() . '/icon%s.svg';

        $this->favorite = new FavoriteItem(
            id: $this->define()->id(),
            title: $this->define()->name(),
            url: App::core()->adminurl()->get('admin.' . $this->define()->type(true) . '.' . $this->define()->id()),
            icons: [sprintf($url, ''), sprintf($url, '-dark')],
            permission: $permissions ?: $this->define()->permissions(),
        );
    }
    // @}

    // / @name Theme specific methods
    // @{
    /**
     * Helper to check if current blog theme is this module.
     *
     * @return bool True if blog theme is this module
     */
    protected function isTheme()
    {
        return 'Theme' == $this->define()->type() && App::core()->blog()->settings()->getGroup('system')->getSetting('theme') == $this->define()->id();
    }
    // @}
}
