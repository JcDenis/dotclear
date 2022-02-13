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

use Dotclear\Admin\Favorites;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitPrependAdmin
{
    /** @var    array   Preformated list of favorites modules */
    protected static $favorites = [];

    # Install module is optionnal on Admin process
    public static function checkModule(): bool
    {
        return true;
    }

    # Install module is optionnal on Admin process
    public static function installModule(): ?bool
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
    protected static function addStandardMenu(?string $menu = null, ?string $permissions = ''): void
    {
        if (!$menu || !isset(dotclear()->menu[$menu])) {
            $menu = 'Plugins';
        }
        if ($permissions === '') {
            $permissons = static::$define->permissions();
        }

        dotclear()->menu[$menu]->addItem(
            static::$define->name(),
            dotclear()->adminurl->get('admin.plugin.' . static::$define->id()),
            [
                '?mf=' . static::$define->type() . '/' . static::$define->id() . '/icon.svg',
                '?mf=' . static::$define->type() . '/' . static::$define->id() . '/icon-dark.svg',
            ],
            dotclear()->adminurl->called() == 'admin.plugin.' . static::$define->id(),
            $permissions === null ? dotclear()->auth->isSuperAdmin() : dotclear()->auth->check($permissions, dotclear()->blog->id)
        );
    }

    /**
     * Helper to add a standard admin favorites item
     */
    protected static function addStandardFavorites(?string $permissions = null): void
    {
        # call once for all modules
        if (empty(static::$favorties)) {
            dotclear()->behavior()->add('adminDashboardFavorites', function (Favorites $favs): void {
                foreach (static::$favorites as $id => $values) {
                    $favs->register($id, $values);
                }
            });
        }

        $url = '?mf=' . static::$define->type() . '/' . static::$define->id() . '/icon%s.svg';

        static::$favorites[static::$define->id()] = [
            'title'       => static::$define->name(),
            'url'         => dotclear()->adminurl->get('admin.plugin.' . static::$define->id()),
            'small-icon'  => [sprintf($url, ''), sprintf($url, '-dark')],
            'large-icon'  => [sprintf($url, ''), sprintf($url, '-dark')],
            'permissions' => $permissions ?: static::$define->permissions(),
        ];
    }
}
