<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pings\Admin;

// Dotclear\Plugin\Pings\Admin\PingsBehavior
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\Pings\Common\PingsAPI;

/**
 * Amdin behaviors for plugin Pings.
 *
 * @ingroup  Plugin Pings Behavior
 */
class PingsBehavior
{
    public function __construct()
    {
        App::core()->behavior()->add('adminPostHeaders', [$this, 'pingJS']);
        App::core()->behavior()->add('adminPostFormItems', [$this, 'pingsFormItems']);
        App::core()->behavior()->add('adminAfterPostCreate', [$this, 'doPings']);
        App::core()->behavior()->add('adminAfterPostUpdate', [$this, 'doPings']);

        // Admin help
        App::core()->behavior()->add('adminPageHelpBlock', function (ArrayObject $blocks): void {
            $found = false;
            foreach ($blocks as $block) {
                if ('core_post' == $block) {
                    $found = true;

                    break;
                }
            }
            if (!$found) {
                return;
            }
            $blocks[] = 'pings_post';
        });
    }

    public function pingJS(): string
    {
        return App::core()->resource()->load('post.js', 'Plugin', 'Pings');
    }

    public function pingsFormItems(ArrayObject $main, ArrayObject $sidebar, ?Record $post, string $type = null): void
    {
        if (!App::core()->blog()->settings()->getGroup('pings')->getSetting('pings_active')) {
            return;
        }

        $pings_uris = App::core()->blog()->settings()->getGroup('pings')->getSetting('pings_uris');
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        $pings_do = GPC::post()->array('pings_do');
        $item     = '<h5 class="ping-services">' . __('Pings') . '</h5>';
        $i        = 0;
        foreach ($pings_uris as $k => $v) {
            $item .= '<p class="ping-services"><label for="pings_do-' . $i . '" class="classic">' .
            Form::checkbox(['pings_do[]', 'pings_do-' . $i], Html::escapeHTML($v), in_array($v, $pings_do), 'check-ping-services') . ' ' .
            Html::escapeHTML($k) . '</label></p>';
            ++$i;
        }
        $sidebar['options-box']['items']['pings'] = $item;
    }

    public function doPings(Cursor $cur, int $post_id): void
    {
        if (GPC::post()->empty('pings_do')) {
            return;
        }

        if (!App::core()->blog()->settings()->getGroup('pings')->getSetting('pings_active')) {
            return;
        }

        $pings_uris = App::core()->blog()->settings()->getGroup('pings')->getSetting('pings_uris');
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        foreach (GPC::post()->array('pings_do') as $uri) {
            if (in_array($uri, $pings_uris)) {
                try {
                    PingsAPI::doPings($uri, App::core()->blog()->name, App::core()->blog()->url);
                } catch (\Exception) {
                }
            }
        }
    }
}
