<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Favorite;

// Dotclear\Process\Admin\Favorite\FavoriteItem
use Dotclear\App;
use Dotclear\Exception\InvalidValueReference;

/**
 * Admin favorite item helper.
 *
 * @ingroup  Admin Favorite Item
 */
final class FavoriteItem
{
    public readonly bool $active;
    public readonly bool $show;

    /**
     * Constructor.
     *
     * @param string       $id         The id
     * @param string       $title      The title
     * @param string       $url        The url
     * @param array|string $icons      The image(s)
     * @param mixed        $permission The permission callback
     * @param mixed        $activation The activation callback
     * @param mixed        $dashboard  The dashboard callback
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $url,
        public readonly string|array $icons = 'images/menu/no-icon.svg',
        public readonly mixed $permission = null,
        public readonly mixed $activation = null,
        public readonly mixed $dashboard = null,
    ) {
        if (empty($this->id)) {
            throw new InvalidValueReference(__('Favorite item has no id'));
        }
        if (empty($this->title)) {
            throw new InvalidValueReference(__('Favorite item has no title'));
        }

        // Use value sets on item creation
        if (is_bool($this->activation)) {
            $active = $this->activation;
        // Use callback if defined to match whether favorite is active or not
        } elseif (!empty($this->activation) && is_callable($this->activation)) {
            $active = call_user_func($this->activation);
        // Or use called handler
        } else {
            parse_str(parse_url($this->url, PHP_URL_QUERY), $url);
            $active = App::core()->adminurl()->is($url['handler'] ?: null);
        }

        $show = App::core()->user()->isSuperAdmin();
        if (null !== $this->permission) {
            if (is_bool($this->permission)) {
                $show = $this->permission;
            } elseif (is_string($this->permission)) {
                $show = App::core()->user()->check($this->permission, App::core()->blog()->id);
            } else {
                $show = false;
            }
        }

        $this->active = (bool) $active;
        $this->show   = $show;
    }
}
