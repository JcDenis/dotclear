<?php
/**
 * @class Dotclear\Plugin\Pings\Admin\Handler
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

use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Dotclear\Plugin\Pings\Common\PingsAPI;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Handler extends AbstractPage
{
    private $pings_uris = [];

    protected $namespaces = ['pings'];

    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        try {
            # Pings URIs are managed globally (for all blogs)
            $this->pings_uris = dotclear()->blog()->settings()->pings->getGlobal('pings_uris');
            if (!$this->pings_uris) {
                $this->pings_uris = [];
            }

            if (isset($_POST['pings_srv_name'])) {
                $pings_srv_name = is_array($_POST['pings_srv_name']) ? $_POST['pings_srv_name'] : [];
                $pings_srv_uri  = is_array($_POST['pings_srv_uri']) ? $_POST['pings_srv_uri'] : [];
                $this->pings_uris     = [];

                foreach ($pings_srv_name as $k => $v) {
                    if (trim((string) $v) && trim((string) $pings_srv_uri[$k])) {
                        $this->pings_uris[trim((string) $v)] = trim((string) $pings_srv_uri[$k]);
                    }
                }
                # Settings for all blogs
                dotclear()->blog()->settings()->pings->put('pings_active', !empty($_POST['pings_active']), null, null, true, true);
                dotclear()->blog()->settings()->pings->put('pings_uris', $this->pings_uris, null, null, true, true);
                # Settings for current blog only
                dotclear()->blog()->settings()->pings->put('pings_auto', !empty($_POST['pings_auto']), null, null, true, false);

                dotclear()->notice()->addSuccessNotice(__('Settings have been successfully updated.'));
                dotclear()->adminurl()->redirect('admin.plugin.Pings');
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }

        # Page setup
        $this
            ->setPageTitle(__('Pings'))
            ->setPageHelp('pings')
            ->setPageBreadcrumb([
                __('Plugins')             => '',
                __('Pings configuration') => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo
        '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
        '<p><label for="pings_active" class="classic">' . Form::checkbox('pings_active', 1, dotclear()->blog()->settings()->pings->pings_active) .
        __('Activate pings extension') . '</label></p>';

        $i = 0;
        foreach ($this->pings_uris as $n => $u) {
            echo
            '<p><label for="pings_srv_name-' . $i . '" class="classic">' . __('Service name:') . '</label> ' .
            Form::field(['pings_srv_name[]', 'pings_srv_name-' . $i], 20, 128, Html::escapeHTML($n)) . ' ' .
            '<label for="pings_srv_uri-' . $i . '" class="classic">' . __('Service URI:') . '</label> ' .
            Form::url(['pings_srv_uri[]', 'pings_srv_uri-' . $i], [
                'size'    => 40,
                'default' => Html::escapeHTML($u),
            ]);

            if (!empty($_GET['test'])) {
                try {
                    PingsAPI::doPings($u, 'Example site', 'http://example.com');
                    echo ' <img src="?df=images/check-on.png" alt="OK" />';
                } catch (\Exception $e) {
                    echo ' <img src="?df=images/check-off.png" alt="' . __('Error') . '" /> ' . $e->getMessage();
                }
            }

            echo '</p>';
            $i++;
        }

        echo
        '<p><label for="pings_srv_name2" class="classic">' . __('Service name:') . '</label> ' .
        Form::field(['pings_srv_name[]', 'pings_srv_name2'], 20, 128) . ' ' .
        '<label for="pings_srv_uri2" class="classic">' . __('Service URI:') . '</label> ' .
        Form::url(['pings_srv_uri[]', 'pings_srv_uri2'], 40) .
        '</p>' .

        '<p><label for="pings_auto" class="classic">' . Form::checkbox('pings_auto', 1, dotclear()->blog()->settings()->pings->pings_auto) .
        __('Auto pings all services on first publication of entry (current blog only)') . '</label></p>' .

        '<p><input type="submit" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Pings', [], true) . '</p>' .
            '</form>';

        echo '<p><a class="button" href="' . dotclear()->adminurl()->get('admin.plugin.Pings', ['test' => 1]) . '">' . __('Test ping services') . '</a></p>';
    }
}
