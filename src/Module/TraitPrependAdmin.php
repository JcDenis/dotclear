<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

// Dotclear\Module\TraitPrependAdmin
use Dotclear\App;
use Dotclear\Process\Admin\Favorite\Favorite;

/**
 * Module admin trait Prepend.
 *
 * @ingroup  Module
 */
trait TraitPrependAdmin
{
    /**
     * @var array<string,mixed> $favorites
     *                          Module Favorites
     */
    private $favorites = [];

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
        if (!App::core()->adminurl()->exists('admin.' . $this->define()->type(true) . '.' . $this->define()->id())) {
            return;
        }
        if (!$menu || !isset(App::core()->summary()[$menu])) {
            $menu = 'Plugins';
        }
        if ('' === $permissions) {
            $permissions = $this->define()->permissions();
        }

        App::core()->summary()[$menu]->addItem(
            $this->define()->name(),
            App::core()->adminurl()->get('admin.' . $this->define()->type(true) . '.' . $this->define()->id()),
            [
                '?df=' . $this->define()->type() . '/' . $this->define()->id() . '/icon.svg',
                '?df=' . $this->define()->type() . '/' . $this->define()->id() . '/icon-dark.svg',
            ],
            App::core()->adminurl()->is('admin.' . $this->define()->type(true) . '.' . $this->define()->id()),
            null === $permissions ? App::core()->user()->isSuperAdmin() : App::core()->user()->check($permissions, App::core()->blog()->id)
        );
    }

    /**
     * Helper to add a standard admin favorites item.
     *
     * If permissions is not set, defined module permissions are used
     *
     * @param null|string $permissions Special permissions to show Favorite
     */
    protected function addStandardFavorites(?string $permissions = null): void
    {
        if (!App::core()->adminurl()->exists('admin.' . $this->define()->type(true) . '.' . $this->define()->id())) {
            return;
        }

        App::core()->behavior()->add('adminDashboardFavorites', function (Favorite $favs): void {
            $favs->register($this->define()->id(), $this->favorites);
        });

        $url = '?df=' . $this->define()->type() . '/' . $this->define()->id() . '/icon%s.svg';

        $this->favorites = [
            'title'       => $this->define()->name(),
            'url'         => App::core()->adminurl()->get('admin.' . $this->define()->type(true) . '.' . $this->define()->id()),
            'small-icon'  => [sprintf($url, ''), sprintf($url, '-dark')],
            'large-icon'  => [sprintf($url, ''), sprintf($url, '-dark')],
            'permissions' => $permissions ?: $this->define()->permissions(),
        ];
    }
}
