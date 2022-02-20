<?php
/**
 * @class Dotclear\Plugin\Pings\Admin\Prepend
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

use DOtclear\Core\Utils;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Pings\Lib\PingsAPI;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        # Menu and favs
        static::addStandardMenu('Blog', null);
        static::addStandardFavorites(null);

        # Core behaviors
        dotclear()->behavior()->add('coreFirstPublicationEntries', [
            'Dotclear\\Plugin\\Pings\\Lib\\PingsCore', 'doPings'
        ]);

        # Admin behaviors
        dotclear()->behavior()->add('adminPostHeaders', [__CLASS__, 'pingJS']);
        dotclear()->behavior()->add('adminPostFormItems', [__CLASS__, 'pingsFormItems']);
        dotclear()->behavior()->add('adminAfterPostCreate', [__CLASS__, 'doPings']);
        dotclear()->behavior()->add('adminAfterPostUpdate', [__CLASS__, 'doPings']);

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

    public static function installModule(): ?bool
    {
        dotclear()->blog()->settings()->addNamespace('pings');
        dotclear()->blog()->settings()->pings->put('pings_active', 1, 'boolean', 'Activate pings plugin', false, true);
        dotclear()->blog()->settings()->pings->put('pings_auto', 0, 'boolean', 'Auto pings on 1st publication', false, true);
        dotclear()->blog()->settings()->pings->put('pings_uris', ['Ping-o-Matic!' => 'http://rpc.pingomatic.com/'], 'array', 'Pings services URIs', false, true);

        return true;
    }

    public static function pingJS()
    {
        return Utils::jsLoad('?mf=Plugin/Pings/files/js/post.js');
    }

    public static function pingsFormItems($main, $sidebar, $post)
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
