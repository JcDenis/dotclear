<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\LinkPopup
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;

/**
 * Admin generic link popup page.
 *
 * @ingroup  Admin Handler
 */
class LinkPopup extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $plugin_id = !empty($_GET['plugin_id']) ? Html::sanitizeURL($_GET['plugin_id']) : '';

        $this
            ->setPageTitle(__('Add a link'))
            ->setPageType('popup')
            ->setPageHead(
                App::core()->resource()->load('_popup_link.js') .
                App::core()->behavior()->call('adminPopupLink', $plugin_id)
            )
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        $href     = !empty($_GET['href']) ? $_GET['href'] : '';
        $hreflang = !empty($_GET['hreflang']) ? $_GET['hreflang'] : '';
        $title    = !empty($_GET['title']) ? $_GET['title'] : '';

        // Languages combo
        $param = new Param();
        $param->set('order', 'asc');
        $rs         = App::core()->blog()->posts()->getLangs(param: $param);
        $lang_combo = App::core()->combo()->getLangsCombo($rs, true);

        echo '<h2 class="page-title">' . __('Add a link') . '</h2>' .

        '<form id="link-insert-form" action="#" method="get">' .
        '<p><label class="required" for="href"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Link URL:') . '</label> ' .
        Form::field('href', 35, 512, [
            'default'    => Html::escapeHTML($href),
            'extra_html' => 'required placeholder="' . __('URL') . '"',
        ]) .
        '</p>' .
        '<p><label for="title">' . __('Link title:') . '</label> ' .
        Form::field('title', 35, 512, Html::escapeHTML($title)) . '</p>' .
        '<p><label for="hreflang">' . __('Link language:') . '</label> ' .
        Form::combo('hreflang', $lang_combo, $hreflang) .
        '</p>' .

        '</form>' .

        '<p><button type="button" class="reset" id="link-insert-cancel">' . __('Cancel') . '</button> - ' .
        '<button type="button" id="link-insert-ok"><strong>' . __('Insert') . '</strong></button></p>' . "\n";
    }
}
