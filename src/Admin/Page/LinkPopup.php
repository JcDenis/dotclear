<?php
/**
 * @class Dotclear\Admin\Page\LinkPopup
 * @brief Dotclear admin generic link popup page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;


use Dotclear\Admin\Page;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class LinkPopup extends Page
{
    protected function getPermissions(): string|null|false
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
                static::jsLoad('js/_popup_link.js') .
                dotclear()->behavior()->call('adminPopupLink', $plugin_id)
            )
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        $href      = !empty($_GET['href']) ? $_GET['href'] : '';
        $hreflang  = !empty($_GET['hreflang']) ? $_GET['hreflang'] : '';
        $title     = !empty($_GET['title']) ? $_GET['title'] : '';

        # Languages combo
        $rs         = dotclear()->blog()->posts()->getLangs(['order' => 'asc']);
        $lang_combo = dotclear()->combos->getLangsCombo($rs, true);

        echo
        '<h2 class="page-title">' . __('Add a link') . '</h2>' .

        '<form id="link-insert-form" action="#" method="get">' .
        '<p><label class="required" for="href"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Link URL:') . '</label> ' .
        Form::field('href', 35, 512, [
            'default'    => Html::escapeHTML($href),
            'extra_html' => 'required placeholder="' . __('URL') . '"'
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
