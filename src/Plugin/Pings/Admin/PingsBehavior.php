<?php
/**
 * @class Dotclear\Plugin\Pings\Admin\PingsBehavior
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginPings
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pings\Admin;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Plugin\Pings\Common\PingsAPI;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class PingsBehavior
{
    public function __construct()
    {
        dotclear()->behavior()->add('adminPostHeaders', [$this, 'pingJS']);
        dotclear()->behavior()->add('adminPostFormItems', [$this, 'pingsFormItems']);
        dotclear()->behavior()->add('adminAfterPostCreate', [$this, 'doPings']);
        dotclear()->behavior()->add('adminAfterPostUpdate', [$this, 'doPings']);

        # Admin help
        dotclear()->behavior()->add('adminPageHelpBlock', function ($blocks) {
            $found = false;
            foreach ($blocks as $block) {
                if ($block == 'core_post') {
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

    public function pingJS()
    {
        return dotclear()->resource()->load('post.js', 'Plugin', 'Pings');
    }

    public function pingsFormItems($main, $sidebar, $post)
    {
        if (!dotclear()->blog()->settings()->pings->pings_active) {
            return;
        }

        $pings_uris = dotclear()->blog()->settings()->pings->pings_uris;
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        if (!empty($_POST['pings_do']) && is_array($_POST['pings_do'])) {
            $pings_do = $_POST['pings_do'];
        } else {
            $pings_do = [];
        }

        $item = '<h5 class="ping-services">' . __('Pings') . '</h5>';
        $i    = 0;
        foreach ($pings_uris as $k => $v) {
            $item .= '<p class="ping-services"><label for="pings_do-' . $i . '" class="classic">' .
            Form::checkbox(['pings_do[]', 'pings_do-' . $i], Html::escapeHTML($v), in_array($v, $pings_do), 'check-ping-services') . ' ' .
            Html::escapeHTML($k) . '</label></p>';
            $i++;
        }
        $sidebar['options-box']['items']['pings'] = $item;
    }

    public function doPings($cur, $post_id)
    {
        if (empty($_POST['pings_do']) || !is_array($_POST['pings_do'])) {
            return;
        }

        if (!dotclear()->blog()->settings()->pings->pings_active) {
            return;
        }

        $pings_uris = dotclear()->blog()->settings()->pings->pings_uris;
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        foreach ($_POST['pings_do'] as $uri) {
            if (in_array($uri, $pings_uris)) {
                try {
                    PingsAPI::doPings($uri, dotclear()->blog()->name, dotclear()->blog()->url);
                } catch (\Exception $e) {
                }
            }
        }
    }
}
