<?php
/**
 * @class Dotclear\Module\TraitPrependPublic
 * @brief Dotclear Module public trait Prepend
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Process\Admin\Favorite\Favorite;

trait TraitPrependAdmin
{
    /** @var    array   Module Favorites */
    private $favorites = [];

    /**
     * Module check is optionnal on Admin process
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    public function checkModule(): bool
    {
        return true;
    }

    /**
     * Module install is optionnal on Admin process
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    public function installModule(): ?bool
    {
        return null;
    }

    /**
     * Helper to add a standard admin menu item
     * according to module define properties
     *
     * $permissions can be:
     *  * null = superAdmin,
     *  * string = commaseparated list of permissions,
     *  * empty string = follow module define permissions
     *
     * @param   string|null     $menu           The menu name
     * @param   string|null     $permissons     The permissions
     */
    protected function addStandardMenu(?string $menu = null, ?string $permissions = ''): void
    {
        if (!dotclear()->adminurl()->exists('admin.plugin.' . $this->define()->id())) {
            return;
        }
        if (!$menu || !isset(dotclear()->summary()[$menu])) {
            $menu = 'Plugins';
        }
        if ($permissions === '') {
            $permissons = $this->define()->permissions();
        }

        dotclear()->summary()[$menu]->addItem(
            $this->define()->name(),
            dotclear()->adminurl()->get('admin.plugin.' . $this->define()->id()),
            [
                '?df=' . $this->define()->type() . '/' . $this->define()->id() . '/icon.svg',
                '?df=' . $this->define()->type() . '/' . $this->define()->id() . '/icon-dark.svg',
            ],
            dotclear()->adminurl()->called() == 'admin.plugin.' . $this->define()->id(),
            $permissions === null ? dotclear()->user()->isSuperAdmin() : dotclear()->user()->check($permissions, dotclear()->blog()->id)
        );
    }

    /**
     * Helper to add a standard admin favorites item
     *
     * If permissions is not set, defined module permissions are used
     *
     * @param   string|null     $permissions    Special permissions to show Favorite
     */
    protected function addStandardFavorites(?string $permissions = null): void
    {
        if (!dotclear()->adminurl()->exists('admin.plugin.' . $this->define()->id())) {
            return;
        }

        dotclear()->behavior()->add('adminDashboardFavorites', function (Favorite $favs): void {
            $favs->register($this->define()->id(), $this->favorties);
        });

        $url = '?df=' . $this->define()->type() . '/' . $this->define()->id() . '/icon%s.svg';

        $this->favorties = [
            'title'       => $this->define()->name(),
            'url'         => dotclear()->adminurl()->get('admin.plugin.' . $this->define()->id()),
            'small-icon'  => [sprintf($url, ''), sprintf($url, '-dark')],
            'large-icon'  => [sprintf($url, ''), sprintf($url, '-dark')],
            'permissions' => $permissions ?: $this->define()->permissions(),
        ];
    }
}
