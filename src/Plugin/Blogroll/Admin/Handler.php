<?php
/**
 * @class Dotclear\Plugin\Blogroll\Admin\Handler
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
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Dotclear\Plugin\Blogroll\Admin\BlogrollImport;
use Dotclear\Plugin\Blogroll\Admin\HandlerEdit;
use Dotclear\Plugin\Blogroll\Common\Blogroll;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Handler extends AbstractPage
{
    private $br_blogroll;
    private $br_link_title = '';
    private $br_link_href  = '';
    private $br_link_desc  = '';
    private $br_link_lang  = '';
    private $br_cat_title  = '';

    protected $workspaces = ['accessibility'];

    protected function getPermissions(): string|null|false
    {
        return 'blogroll';
    }

    protected function getPagePrepend(): ?bool
    {
        if (!empty($_REQUEST['edit']) && !empty($_REQUEST['id'])) {
            $page_edit = new HandlerEdit($this->handler);

            return $page_edit->pageProcess();
        }

        $this->br_blogrolll = new Blogroll();
        $default_tab = '';

        # Import links
        if (!empty($_POST['import_links']) && !empty($_FILES['links_file'])) {
            $default_tab = 'import-links';

            try {
                Files::uploadStatus($_FILES['links_file']);
                $ifile = dotclear()->config()->cache_dir . '/' . md5(uniqid());
                if (!move_uploaded_file($_FILES['links_file']['tmp_name'], $ifile)) {
                    throw new ModuleException(__('Unable to move uploaded file.'));
                }

                try {
                    $imported = ImportBlogroll::loadFile($ifile);
                    @unlink($ifile);
                } catch (\Exception $e) {
                    @unlink($ifile);

                    throw $e;
                }

                if (empty($imported)) {
                    unset($imported);

                    throw new ModuleException(__('Nothing to import'));
                }
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        if (!empty($_POST['import_links_do'])) {
            foreach ($_POST['entries'] as $idx) {
                $this->br_link_title = Html::escapeHTML($_POST['title'][$idx]);
                $this->br_link_href  = Html::escapeHTML($_POST['url'][$idx]);
                $this->br_link_desc  = Html::escapeHTML($_POST['desc'][$idx]);

                try {
                    $this->br_blogrolll->addLink($this->br_link_title, $this->br_link_href, $this->br_link_desc, '');
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                    $default_tab = 'import-links';
                }
            }

            dotclear()->notice()->addSuccessNotice(__('links have been successfully imported.'));
            dotclear()->adminurl()->redirect('admin.plugin.Blogroll');
        }

        if (!empty($_POST['cancel_import'])) {
            dotclear()->error()->add(__('Import operation cancelled.'));
            $default_tab = 'import-links';
        }

        # Add link
        if (!empty($_POST['add_link'])) {
            $this->br_link_title = Html::escapeHTML($_POST['link_title']);
            $this->br_link_href  = Html::escapeHTML($_POST['link_href']);
            $this->br_link_desc  = Html::escapeHTML($_POST['link_desc']);
            $this->br_link_lang  = Html::escapeHTML($_POST['link_lang']);

            try {
                $this->br_blogrolll->addLink($this->br_link_title, $this->br_link_href, $this->br_link_desc, $this->br_link_lang);

                dotclear()->notice()->addSuccessNotice(__('Link has been successfully created.'));
                dotclear()->adminurl()->redirect('admin.plugin.Blogroll');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
                $default_tab = 'add-link';
            }
        }

        # Add category
        if (!empty($_POST['add_cat'])) {
            $this->br_cat_title = Html::escapeHTML($_POST['cat_title']);

            try {
                $this->br_blogrolll->addCategory($this->br_cat_title);
                dotclear()->notice()->addSuccessNotice(__('category has been successfully created.'));
                dotclear()->adminurl()->redirect('admin.plugin.Blogroll');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
                $default_tab = 'add-cat';
            }
        }

        # Delete link
        if (!empty($_POST['removeaction']) && !empty($_POST['remove'])) {
            foreach ($_POST['remove'] as $k => $v) {
                try {
                    $this->br_blogrolll->delItem((int) $v);
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());

                    break;
                }
            }

            if (!dotclear()->error()->flag()) {
                dotclear()->notice()->addSuccessNotice(__('Items have been successfully removed.'));
                dotclear()->adminurl()->redirect('admin.plugin.Blogroll');
            }
        }

        # Order links
        $order = [];
        if (empty($_POST['links_order']) && !empty($_POST['order'])) {
            $order = $_POST['order'];
            asort($order);
            $order = array_keys($order);
        } elseif (!empty($_POST['links_order'])) {
            $order = explode(',', $_POST['links_order']);
        }

        if (!empty($_POST['saveorder']) && !empty($order)) {
            foreach ($order as $pos => $l) {
                $pos = ((int) $pos) + 1;

                try {
                    $this->br_blogrolll->updateOrder((int) $l, (int) $pos);
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }

            if (!dotclear()->error()->flag()) {
                dotclear()->notice()->addSuccessNotice(__('Items order has been successfully updated'));
                dotclear()->adminurl()->redirect('admin.plugin.Blogroll');
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Blogroll'))
            ->setPageHelp('blogroll')
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Blogroll')                             => '',
            ])
            ->setPageHead(
                dotclear()->resource()->confirmClose('links-form', 'add-link-form', 'add-category-form') .
                dotclear()->resource()->pageTabs($default_tab)
            )
        ;

        if (!dotclear()->user()->preference()->accessibility->nodragdrop) {
            $this->setPageHead(
                dotclear()->resource()->load('jquery/jquery-ui.custom.js') .
                dotclear()->resource()->load('jquery/jquery.ui.touch-punch.js') .
                dotclear()->resource()->load('blogroll.js', 'Plugin', 'Blogroll')
            );
        }

        return true;
    }

    protected function getPageContent(): void
    {
        # Get links
        try {
            $rs = $this->br_blogrolll->getLinks();
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }

        echo
        '<div class="multi-part" id="main-list" title="' . __('Blogroll') . '">';

        if ($rs->isEmpty()) {
            echo '<div><p>' . __('The link list is empty.') . '</p></div>';
        } else {
            echo '
            <form action="' . dotclear()->adminurl()->root() . '" method="post" id="links-form">
            <div class="table-outer">
            <table class="dragable">
            <thead>
            <tr>
              <th colspan="3">' . __('Title') . '</th>
              <th>' . __('Description') . '</th>
              <th>' . __('URL') . '</th>
              <th>' . __('Lang') . '</th>
            </tr>
            </thead>
            <tbody id="links-list">';

            while ($rs->fetch()) {
                echo
                '<tr class="line" id="l_' . $rs->link_id . '">' .
                '<td class="handle minimal">' . Form::number(['order[' . $rs->link_id . ']'], [
                    'min'        => 1,
                    'max'        => $rs->count(),
                    'default'    => (string) ($rs->index() + 1),
                    'class'      => 'position',
                    'extra_html' => 'title="' . __('position') . '"',
                ]) .
                '</td>' .
                '<td class="minimal">' . Form::checkbox(
                    ['remove[]'],
                    $rs->link_id,
                    [
                        'extra_html' => 'title="' . __('select this link') . '"',
                    ]
                ) . '</td>';

                if ($rs->is_cat) {
                    echo
                    '<td colspan="5"><strong><a href="' . dotclear()->adminurl()->get('admin.plugin.Blogroll', ['edit' => 1, 'id' => $rs->link_id]) . '">' .
                    Html::escapeHTML($rs->link_desc) . '</a></strong></td>';
                } else {
                    echo
                    '<td><a href="' . dotclear()->adminurl()->get('admin.plugin.Blogroll', ['edit' => 1, 'id' => $rs->link_id]) . '">' .
                    Html::escapeHTML($rs->link_title) . '</a></td>' .
                    '<td>' . Html::escapeHTML($rs->link_desc) . '</td>' .
                    '<td>' . Html::escapeHTML($rs->link_href) . '</td>' .
                    '<td>' . Html::escapeHTML($rs->link_lang) . '</td>';
                }

                echo '</tr>';
            }
            echo '
            </tbody>
            </table></div>

            <div class="two-cols">
            <p class="col">' .
            Form::hidden('links_order', '') .
            dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) . '
            <input type="submit" name="saveorder" value="' . __('Save order') . '" />
            <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />
            </p>
            <p class="col right"><input id="remove-action" type="submit" class="delete" name="removeaction"
                 value="' . __('Delete selected links') . '"
                 onclick="return window.confirm(' . Html::escapeJS(__('Are you sure you want to delete selected links?')) . ');" /></p>
            </div>
            </form>';

        }

        echo '
        </div>

        <div class="multi-part clear" id="add-link" title="' . __('Add a link') . '">
        <form action="' . dotclear()->adminurl()->root() . '" method="post" id="add-link-form">
        <h3>' . __('Add a new link') . '</h3>
        <p class="col"><label for="link_title" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label> ' .
        Form::field('link_title', 30, 255, [
            'default'    => $this->br_link_title,
            'extra_html' => 'required placeholder="' . __('Title') . '"',
        ]) .
        '</p>' .

        '<p class="col"><label for="link_href" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('URL:') . '</label> ' .
        Form::field('link_href', 30, 255, [
            'default'    => $this->br_link_href,
            'extra_html' => 'required placeholder="' . __('URL') . '"',
        ]) .
        '</p>' .

        '<p class="col"><label for="link_desc">' . __('Description:') . '</label> ' .
        Form::field('link_desc', 30, 255, $this->br_link_desc) .
        '</p>' .

        '<p class="col"><label for="link_lang">' . __('Language:') . '</label> ' .
        Form::field('link_lang', 5, 5, $this->br_link_lang) .
        '</p>' .
        '<p>' . dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
        '<input type="submit" name="add_link" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        '</p>' .
        '</form>' .
        '</div>';

        echo
        '<div class="multi-part" id="add-cat" title="' . __('Add a category') . '">' .
        '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="add-category-form">' .
        '<h3>' . __('Add a new category') . '</h3>' .
        '<p><label for="cat_title" class=" classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label> ' .
        Form::field('cat_title', 30, 255, [
            'default'    => $this->br_cat_title,
            'extra_html' => 'required placeholder="' . __('Title') . '"',
        ]) .
        '</p>' .
        '<p>' .dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
        '<input type="submit" name="add_cat" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        '</p>' .
        '</form>' .
        '</div>';

        echo
        '<div class="multi-part" id="import-links" title="' . __('Import links') . '">';
        if (!isset($imported)) {
            echo
            '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="import-links-form" enctype="multipart/form-data">' .
            '<h3>' . __('Import links') . '</h3>' .
            '<p><label for="links_file" class=" classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('OPML or XBEL File:') . '</label> ' .
            '<input type="file" id="links_file" name="links_file" required /></p>' .
            '<p>' . dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
            '<input type="submit" name="import_links" value="' . __('Import') . '" />' .
            ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';
        } else {
            echo
            '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="import-links-form">' .
            '<h3>' . __('Import links') . '</h3>';
            if (empty($imported)) {
                echo '<p>' . __('Nothing to import') . '</p>';
            } else {
                echo
                '<table class="clear maximal"><tr>' .
                '<th colspan="2">' . __('Title') . '</th>' .
                '<th>' . __('Description') . '</th>' .
                    '</tr>';

                $i = 0;
                foreach ($imported as $entry) {
                    $url   = Html::escapeHTML($entry->link);
                    $title = Html::escapeHTML($entry->title);
                    $desc  = Html::escapeHTML($entry->desc);

                    echo
                    '<tr><td>' . Form::checkbox(['entries[]'], $i) . '</td>' .
                        '<td nowrap><a href="' . $url . '">' . $title . '</a>' .
                        '<input type="hidden" name="url[' . $i . ']" value="' . $url . '" />' .
                        '<input type="hidden" name="title[' . $i . ']" value="' . $title . '" />' .
                        '</td>' .
                        '<td>' . $desc .
                        '<input type="hidden" name="desc[' . $i . ']" value="' . $desc . '" />' .
                        '</td></tr>' . "\n";
                    $i++;
                }
                echo
                '</table>' .
                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right">' .
                dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
                '<input type="submit" name="cancel_import" value="' . __('Cancel') . '" />&nbsp;' .
                '<input type="submit" name="import_links_do" value="' . __('Import') . '" /></p>' .
                    '</div>';
            }
            echo
                '</form>';
        }
        echo '</div>';
    }
}
