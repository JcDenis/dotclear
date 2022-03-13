<?php
/**
 * @class Dotclear\Plugin\Blogroll\Admin\HandlerEdit
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBlogroll
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Admin;

use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Dotclear\Plugin\Blogroll\Common\Blogroll;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class HandlerEdit extends AbstractPage
{
    private $br_blogroll;
    private $br_id         = 0;
    private $br_has_rs     = false;
    private $br_is_cat     = false;
    private $br_link_title = '';
    private $br_link_href  = '';
    private $br_link_desc  = '';
    private $br_link_lang  = '';
    private $br_link_xfn   = '';

    protected function getPermissions(): string|null|false
    {
        return 'blogroll';
    }

    protected function getPagePrepend(): ?bool
    {
        $this->br_blogroll = new Blogroll();
        $this->br_id = (int) $_REQUEST['id'];

        $rs = null;

        try {
            $rs = $this->br_blogroll->getLink($this->br_id);
            $this->br_has_rs = !$rs->isEmpty();
            $this->br_is_cat = (bool) $rs->is_cat;
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }

        if (!dotclear()->error()->flag() && $this->br_has_rs) {
            $this->br_link_title = $rs->link_title;
            $this->br_link_href  = $rs->link_href;
            $this->br_link_desc  = $rs->link_desc;
            $this->br_link_lang  = $rs->link_lang;
            $this->br_link_xfn   = $rs->link_xfn;
        } else {
            dotclear()->error()->add(__('No such link or title'));
        }

        # Update a link
        if ($this->br_has_rs && !$this->br_is_cat && !empty($_POST['edit_link'])) {
            $this->br_link_title = Html::escapeHTML($_POST['link_title']);
            $this->br_link_href  = Html::escapeHTML($_POST['link_href']);
            $this->br_link_desc  = Html::escapeHTML($_POST['link_desc']);
            $this->br_link_lang  = Html::escapeHTML($_POST['link_lang']);

            $this->br_link_xfn = '';

            if (!empty($_POST['identity'])) {
                $this->br_link_xfn .= $_POST['identity'];
            } else {
                if (!empty($_POST['friendship'])) {
                    $this->br_link_xfn .= ' ' . $_POST['friendship'];
                }
                if (!empty($_POST['physical'])) {
                    $this->br_link_xfn .= ' met';
                }
                if (!empty($_POST['professional'])) {
                    $this->br_link_xfn .= ' ' . implode(' ', $_POST['professional']);
                }
                if (!empty($_POST['geographical'])) {
                    $this->br_link_xfn .= ' ' . $_POST['geographical'];
                }
                if (!empty($_POST['family'])) {
                    $this->br_link_xfn .= ' ' . $_POST['family'];
                }
                if (!empty($_POST['romantic'])) {
                    $this->br_link_xfn .= ' ' . implode(' ', $_POST['romantic']);
                }
            }

            try {
                $this->br_blogroll->updateLink($this->br_id, $this->br_link_title, $this->br_link_href, $this->br_link_desc, $this->br_link_lang, trim((string) $this->br_link_xfn));
                dotclear()->notice()->addSuccessNotice(__('Link has been successfully updated'));
                dotclear()->adminurl()->redirect('admin.plugin.Blogroll', ['edit' => 1, 'id' => $this->br_id]);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Update a category
        if ($this->br_has_rs && $this->br_is_cat && !empty($_POST['edit_cat'])) {
            $this->br_link_desc = Html::escapeHTML($_POST['link_desc']);

            try {
                $this->br_blogroll->updateCategory($this->br_id, $this->br_link_desc);
                dotclear()->notice()->addSuccessNotice(__('Category has been successfully updated'));
                dotclear()->adminurl()->redirect('admin.plugin.Blogroll', ['edit' => 1, 'id' => $this->br_id]);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Blogroll'))
            ->setPageHelp('blogroll')
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Blogroll')                             => dotclear()->adminurl()->get('admin.plugin.Blogroll'),
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        # Languages combo
        $links      = $this->br_blogroll->getLangs(['order' => 'asc']);
        $lang_combo = dotclear()->combo()->getLangsCombo($links, true);

        echo '<p><a class="back" href="' . dotclear()->adminurl()->get('admin.plugin.Blogroll') . '">' . __('Return to blogroll') . '</a></p>';

        if ($this->br_has_rs && $this->br_is_cat) {
            echo
            '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
            '<h3>' . __('Edit category') . '</h3>' .

            '<p><label for="link_desc" class="required classic"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label> ' .
            Form::field('link_desc', 30, 255, [
                'default'    => Html::escapeHTML($this->br_link_desc),
                'extra_html' => 'required placeholder="' . __('Title') . '" lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"',
            ]) .

            Form::hidden('edit', 1) .
            Form::hidden('id', $this->br_id) .
            dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
            '<input type="submit" name="edit_cat" value="' . __('Save') . '"/></p>' .
                '</form>';
        }
        if ($this->br_has_rs && !$this->br_is_cat) {
            echo
            '<form action="' . dotclear()->adminurl()->root() . '" method="post" class="two-cols fieldset">' .

            '<div class="col30 first-col">' .
            '<h3>' . __('Edit link') . '</h3>' .

            '<p><label for="link_title" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label> ' .
            Form::field('link_title', 30, 255, [
                'default'    => Html::escapeHTML($this->br_link_title),
                'extra_html' => 'required placeholder="' . __('Title') . '" lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"',
            ]) .
            '</p>' .

            '<p><label for="link_href" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('URL:') . '</label> ' .
            Form::url('link_href', [
                'size'       => 30,
                'default'    => Html::escapeHTML($this->br_link_href),
                'extra_html' => 'required placeholder="' . __('URL') . '"',
            ]) .
            '</p>' .

            '<p><label for="link_desc">' . __('Description:') . '</label> ' .
            Form::field(
                'link_desc',
                30,
                255,
                [
                    'default'    => Html::escapeHTML($this->br_link_desc),
                    'extra_html' => 'lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"',
                ]
            ) . '</p>' .

            '<p><label for="link_lang">' . __('Language:') . '</label> ' .
            Form::combo('link_lang', $lang_combo, $this->br_link_lang) .
            '</p>' .

            '</div>' .

            # XFN nightmare
            '<div class="col70 last-col">' .
            '<h3>' . __('XFN information') . '</h3>' .
            '<p class="clear form-note">' . __('More information on <a href="https://en.wikipedia.org/wiki/XHTML_Friends_Network">Wikipedia</a> website') . '</p>' .

            '<div class="table-outer">' .
            '<table class="noborder">' .

            '<tr class="line">' .
            '<th>' . __('_xfn_Me') . '</th>' .
            '<td><p>' . '<label class="classic">' .
            Form::checkbox(['identity'], 'me', ($this->br_link_xfn == 'me')) . ' ' .
            __('_xfn_Another link for myself') . '</label></p></td>' .
            '</tr>' .

            '<tr class="line">' .
            '<th>' . __('_xfn_Friendship') . '</th>' .
            '<td><p>' .
            '<label class="classic">' . Form::radio(
                ['friendship'],
                'contact',
                str_contains($this->br_link_xfn, 'contact')
            ) . __('_xfn_Contact') . '</label> ' .
            '<label class="classic">' . Form::radio(
                ['friendship'],
                'acquaintance',
                str_contains($this->br_link_xfn, 'acquaintance')
            ) . __('_xfn_Acquaintance') . '</label> ' .
            '<label class="classic">' . Form::radio(
                ['friendship'],
                'friend',
                str_contains($this->br_link_xfn, 'friend')
            ) . __('_xfn_Friend') . '</label> ' .
            '<label class="classic">' . Form::radio(['friendship'], '') . __('None') . '</label>' .
            '</p></td>' .
            '</tr>' .

            '<tr class="line">' .
            '<th>' . __('_xfn_Physical') . '</th>' .
            '<td><p>' .
            '<label class="classic">' . Form::checkbox(
                ['physical'],
                'met',
                str_contains($this->br_link_xfn, 'met')
            ) . __('_xfn_Met') . '</label>' .
            '</p></td>' .
            '</tr>' .

            '<tr class="line">' .
            '<th>' . __('_xfn_Professional') . '</th>' .
            '<td><p>' .
            '<label class="classic">' . Form::checkbox(
                ['professional[]'],
                'co-worker',
                str_contains($this->br_link_xfn, 'co-worker')
            ) . __('_xfn_Co-worker') . '</label> ' .
            '<label class="classic">' . Form::checkbox(
                ['professional[]'],
                'colleague',
                str_contains($this->br_link_xfn, 'colleague')
            ) . __('_xfn_Colleague') . '</label>' .
            '</p></td>' .
            '</tr>' .

            '<tr class="line">' .
            '<th>' . __('_xfn_Geographical') . '</th>' .
            '<td><p>' .
            '<label class="classic">' . Form::radio(
                ['geographical'],
                'co-resident',
                str_contains($this->br_link_xfn, 'co-resident')
            ) . __('_xfn_Co-resident') . '</label> ' .
            '<label class="classic">' . Form::radio(
                ['geographical'],
                'neighbor',
                str_contains($this->br_link_xfn, 'neighbor')
            ) . __('_xfn_Neighbor') . '</label> ' .
            '<label class="classic">' . Form::radio(['geographical'], '') . __('None') . '</label>' .
            '</p></td>' .
            '</tr>' .

            '<tr class="line">' .
            '<th>' . __('_xfn_Family') . '</th>' .
            '<td><p>' .
            '<label class="classic">' . Form::radio(
                ['family'],
                'child',
                str_contains($this->br_link_xfn, 'child')
            ) . __('_xfn_Child') . '</label> ' .
            '<label class="classic">' . Form::radio(
                ['family'],
                'parent',
                str_contains($this->br_link_xfn, 'parent')
            ) . __('_xfn_Parent') . '</label> ' .
            '<label class="classic">' . Form::radio(
                ['family'],
                'sibling',
                str_contains($this->br_link_xfn, 'sibling')
            ) . __('_xfn_Sibling') . '</label> ' .
            '<label class="classic">' . Form::radio(
                ['family'],
                'spouse',
                str_contains($this->br_link_xfn, 'spouse')
            ) . __('_xfn_Spouse') . '</label> ' .
            '<label class="classic">' . Form::radio(
                ['family'],
                'kin',
                str_contains($this->br_link_xfn, 'kin')
            ) . __('_xfn_Kin') . '</label> ' .
            '<label class="classic">' . Form::radio(['family'], '') . __('None') . '</label>' .
            '</p></td>' .
            '</tr>' .

            '<tr class="line">' .
            '<th>' . __('_xfn_Romantic') . '</th>' .
            '<td><p>' .
            '<label class="classic">' . Form::checkbox(
                ['romantic[]'],
                'muse',
                str_contains($this->br_link_xfn, 'muse')
            ) . __('_xfn_Muse') . '</label> ' .
            '<label class="classic">' . Form::checkbox(
                ['romantic[]'],
                'crush',
                str_contains($this->br_link_xfn, 'crush')
            ) . __('_xfn_Crush') . '</label> ' .
            '<label class="classic">' . Form::checkbox(
                ['romantic[]'],
                'date',
                str_contains($this->br_link_xfn, 'date')
            ) . __('_xfn_Date') . '</label> ' .
            '<label class="classic">' . Form::checkbox(
                ['romantic[]'],
                'sweetheart',
                str_contains($this->br_link_xfn, 'sweetheart')
            ) . __('_xfn_Sweetheart') . '</label> ' .
            '</p></td>' .
            '</tr>' .
            '</table></div>' .

            '</div>' .
            '<p class="clear">' .
            Form::hidden('edit', 1) .
            Form::hidden('id', $this->br_id) .
            dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
            '<input type="submit" name="edit_link" value="' . __('Save') . '"/></p>' .

                '</form>';
        }
    }
}
