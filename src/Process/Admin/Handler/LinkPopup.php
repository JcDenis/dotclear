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
use Dotclear\Helper\GPC\GPC;
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
        $this
            ->setPageTitle(__('Add a link'))
            ->setPageType('popup')
            ->setPageHead(
                App::core()->resource()->load('_popup_link.js') .
                App::core()->behavior('adminPopupLink')->call(Html::sanitizeURL(GPC::get()->string('plugin_id')))
            )
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        // Languages combo
        $param = new Param();
        $param->set('order', 'asc');

        $rs         = App::core()->blog()->posts()->getLangs(param: $param);
        $lang_combo = App::core()->combo()->getLangsCombo($rs, true);

        echo '<h2 class="page-title">' . __('Add a link') . '</h2>' .

        '<form id="link-insert-form" action="#" method="get">' .
        '<p><label class="required" for="href"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Link URL:') . '</label> ' .
        Form::field('href', 35, 512, [
            'default'    => HTML::escapeHTML(GPC::get()->string('href')),
            'extra_html' => 'required placeholder="' . __('URL') . '"',
        ]) .
        '</p>' .
        '<p><label for="title">' . __('Link title:') . '</label> ' .
        Form::field('title', 35, 512, HTML::escapeHTML(GPC::get()->string('title'))) . '</p>' .
        '<p><label for="hreflang">' . __('Link language:') . '</label> ' .
        Form::combo('hreflang', $lang_combo, GPC::get()->string('hreflang')) .
        '</p>' .

        '</form>' .

        '<p><button type="button" class="reset" id="link-insert-cancel">' . __('Cancel') . '</button> - ' .
        '<button type="button" id="link-insert-ok"><strong>' . __('Insert') . '</strong></button></p>' . "\n";
    }
}
