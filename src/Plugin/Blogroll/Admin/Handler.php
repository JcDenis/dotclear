<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Admin;

// Dotclear\Plugin\Blogroll\Admin\Handler
use Dotclear\App;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\Blogroll\Common\Blogroll;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin page for blogrolls.
 *
 * @ingroup  Plugin Blogroll
 */
class Handler extends AbstractPage
{
    private $br_blogroll;
    private $br_imported   = [];
    private $br_link_title = '';
    private $br_link_href  = '';
    private $br_link_desc  = '';
    private $br_link_lang  = '';
    private $br_cat_title  = '';

    protected function getPermissions(): string|bool
    {
        return 'blogroll';
    }

    protected function getPagePrepend(): ?bool
    {
        if (!GPC::request()->empty('edit') && !GPC::request()->empty('id')) {
            (new HandlerEdit($this->handler))->pageProcess();
        }

        $this->br_blogroll = new Blogroll();
        $default_tab       = '';

        // Import links
        if (!GPC::post()->empty('import_links') && !empty($_FILES['links_file'])) {
            $default_tab = 'import-links';

            try {
                Files::uploadStatus($_FILES['links_file']);
                $ifile = App::core()->config()->get('cache_dir') . '/' . md5(uniqid());
                if (!move_uploaded_file($_FILES['links_file']['tmp_name'], $ifile)) {
                    throw new ModuleException(__('Unable to move uploaded file.'));
                }

                try {
                    $this->br_imported = BlogrollImport::loadFile($ifile);
                    @unlink($ifile);
                } catch (Exception $e) {
                    @unlink($ifile);

                    throw $e;
                }

                if (empty($this->br_imported)) {
                    $this->br_imported = null;

                    throw new ModuleException(__('Nothing to import'));
                }
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        if (!GPC::post()->empty('import_links_do')) {
            foreach (GPC::post()->array('entries') as $idx) {
                $this->br_link_title = Html::escapeHTML(GPC::post()->array('title')[$idx]);
                $this->br_link_href  = Html::escapeHTML(GPC::post()->array('url')[$idx]);
                $this->br_link_desc  = Html::escapeHTML(GPC::post()->array('desc')[$idx]);

                try {
                    $this->br_blogroll->addLink($this->br_link_title, $this->br_link_href, $this->br_link_desc, '');
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                    $default_tab = 'import-links';
                }
            }

            App::core()->notice()->addSuccessNotice(__('links have been successfully imported.'));
            App::core()->adminurl()->redirect('admin.plugin.Blogroll');
        }

        if (!GPC::post()->empty('cancel_import')) {
            App::core()->error()->add(__('Import operation cancelled.'));
            $default_tab = 'import-links';
        }

        // Add link
        if (!GPC::post()->empty('add_link')) {
            $this->br_link_title = Html::escapeHTML(GPC::post()->string('link_title'));
            $this->br_link_href  = Html::escapeHTML(GPC::post()->string('link_href'));
            $this->br_link_desc  = Html::escapeHTML(GPC::post()->string('link_desc'));
            $this->br_link_lang  = Html::escapeHTML(GPC::post()->string('link_lang'));

            try {
                $this->br_blogroll->addLink($this->br_link_title, $this->br_link_href, $this->br_link_desc, $this->br_link_lang);

                App::core()->notice()->addSuccessNotice(__('Link has been successfully created.'));
                App::core()->adminurl()->redirect('admin.plugin.Blogroll');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
                $default_tab = 'add-link';
            }
        }

        // Add category
        if (!GPC::post()->empty('add_cat')) {
            $this->br_cat_title = Html::escapeHTML(GPC::post()->string('cat_title'));

            try {
                $this->br_blogroll->addCategory($this->br_cat_title);
                App::core()->notice()->addSuccessNotice(__('category has been successfully created.'));
                App::core()->adminurl()->redirect('admin.plugin.Blogroll');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
                $default_tab = 'add-cat';
            }
        }

        // Delete link
        if (!GPC::post()->empty('removeaction') && !GPC::post()->empty('remove')) {
            foreach (GPC::post()->array('remove') as $k => $v) {
                try {
                    $this->br_blogroll->delItem((int) $v);
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());

                    break;
                }
            }

            if (!App::core()->error()->flag()) {
                App::core()->notice()->addSuccessNotice(__('Items have been successfully removed.'));
                App::core()->adminurl()->redirect('admin.plugin.Blogroll');
            }
        }

        // Order links
        $order = [];
        if (GPC::post()->empty('links_order') && !GPC::post()->empty('order')) {
            $order = GPC::post()->array('order');
            asort($order);
            $order = array_keys($order);
        } elseif (!GPC::post()->empty('links_order')) {
            $order = explode(',', GPC::post()->string('links_order'));
        }

        if (!GPC::post()->empty('saveorder') && !empty($order)) {
            foreach ($order as $pos => $l) {
                $pos = ((int) $pos) + 1;

                try {
                    $this->br_blogroll->updateOrder((int) $l, (int) $pos);
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }

            if (!App::core()->error()->flag()) {
                App::core()->notice()->addSuccessNotice(__('Items order has been successfully updated'));
                App::core()->adminurl()->redirect('admin.plugin.Blogroll');
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Blogroll'))
            ->setPageHelp('blogroll')
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Blogroll')                              => '',
            ])
            ->setPageHead(
                App::core()->resource()->confirmClose('links-form', 'add-link-form', 'add-category-form') .
                App::core()->resource()->pageTabs($default_tab)
            )
        ;

        if (!App::core()->user()->preferences()->getGroup('accessibility')->getPreference('nodragdrop')) {
            $this->setPageHead(
                App::core()->resource()->load('jquery/jquery-ui.custom.js') .
                App::core()->resource()->load('jquery/jquery.ui.touch-punch.js') .
                App::core()->resource()->load('blogroll.js', 'Plugin', 'Blogroll')
            );
        }

        return true;
    }

    protected function getPageContent(): void
    {
        // Get links
        $rs = null;

        try {
            $rs = $this->br_blogroll->getLinks();
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        echo '<div class="multi-part" id="main-list" title="' . __('Blogroll') . '">';

        if (!$rs || $rs->isEmpty()) {
            echo '<div><p>' . __('The link list is empty.') . '</p></div>';
        } else {
            echo '
            <form action="' . App::core()->adminurl()->root() . '" method="post" id="links-form">
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
                echo '<tr class="line" id="l_' . $rs->field('link_id') . '">' .
                '<td class="handle minimal">' . Form::number(['order[' . $rs->field('link_id') . ']'], [
                    'min'        => 1,
                    'max'        => $rs->count(),
                    'default'    => (string) ($rs->index() + 1),
                    'class'      => 'position',
                    'extra_html' => 'title="' . __('position') . '"',
                ]) .
                '</td>' .
                '<td class="minimal">' . Form::checkbox(
                    ['remove[]'],
                    $rs->field('link_id'),
                    [
                        'extra_html' => 'title="' . __('select this link') . '"',
                    ]
                ) . '</td>';

                if ($rs->integer('is_cat')) {
                    echo '<td colspan="5"><strong><a href="' . App::core()->adminurl()->get('admin.plugin.Blogroll', ['edit' => 1, 'id' => $rs->field('link_id')]) . '">' .
                    Html::escapeHTML($rs->field('link_desc')) . '</a></strong></td>';
                } else {
                    echo '<td><a href="' . App::core()->adminurl()->get('admin.plugin.Blogroll', ['edit' => 1, 'id' => $rs->field('link_id')]) . '">' .
                    Html::escapeHTML($rs->field('link_title')) . '</a></td>' .
                    '<td>' . Html::escapeHTML($rs->field('link_desc')) . '</td>' .
                    '<td>' . Html::escapeHTML($rs->field('link_href')) . '</td>' .
                    '<td>' . Html::escapeHTML($rs->field('link_lang')) . '</td>';
                }

                echo '</tr>';
            }
            echo '
            </tbody>
            </table></div>

            <div class="two-cols">
            <p class="col">' .
            Form::hidden('links_order', '') .
            App::core()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) . '
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
        <form action="' . App::core()->adminurl()->root() . '" method="post" id="add-link-form">
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
        '<p>' . App::core()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
        '<input type="submit" name="add_link" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        '</p>' .
        '</form>' .
        '</div>';

        echo '<div class="multi-part" id="add-cat" title="' . __('Add a category') . '">' .
        '<form action="' . App::core()->adminurl()->root() . '" method="post" id="add-category-form">' .
        '<h3>' . __('Add a new category') . '</h3>' .
        '<p><label for="cat_title" class=" classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label> ' .
        Form::field('cat_title', 30, 255, [
            'default'    => $this->br_cat_title,
            'extra_html' => 'required placeholder="' . __('Title') . '"',
        ]) .
        '</p>' .
        '<p>' . App::core()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
        '<input type="submit" name="add_cat" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        '</p>' .
        '</form>' .
        '</div>';

        echo '<div class="multi-part" id="import-links" title="' . __('Import links') . '">';
        if (null === $this->br_imported) {
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="import-links-form" enctype="multipart/form-data">' .
            '<h3>' . __('Import links') . '</h3>' .
            '<p><label for="links_file" class=" classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('OPML or XBEL File:') . '</label> ' .
            '<input type="file" id="links_file" name="links_file" required /></p>' .
            '<p>' . App::core()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
            '<input type="submit" name="import_links" value="' . __('Import') . '" />' .
            ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';
        } else {
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="import-links-form">' .
            '<h3>' . __('Import links') . '</h3>';
            if (empty($this->br_imported)) {
                echo '<p>' . __('Nothing to import') . '</p>';
            } else {
                echo '<table class="clear maximal"><tr>' .
                '<th colspan="2">' . __('Title') . '</th>' .
                '<th>' . __('Description') . '</th>' .
                    '</tr>';

                $i = 0;
                foreach ($this->br_imported as $entry) {
                    $url   = Html::escapeHTML($entry->link);
                    $title = Html::escapeHTML($entry->title);
                    $desc  = Html::escapeHTML($entry->desc);

                    echo '<tr><td>' . Form::checkbox(['entries[]'], $i) . '</td>' .
                        '<td nowrap><a href="' . $url . '">' . $title . '</a>' .
                        '<input type="hidden" name="url[' . $i . ']" value="' . $url . '" />' .
                        '<input type="hidden" name="title[' . $i . ']" value="' . $title . '" />' .
                        '</td>' .
                        '<td>' . $desc .
                        '<input type="hidden" name="desc[' . $i . ']" value="' . $desc . '" />' .
                        '</td></tr>' . "\n";
                    ++$i;
                }
                echo '</table>' .
                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right">' .
                App::core()->adminurl()->getHiddenFormFields('admin.plugin.Blogroll', [], true) .
                '<input type="submit" name="cancel_import" value="' . __('Cancel') . '" />&nbsp;' .
                '<input type="submit" name="import_links_do" value="' . __('Import') . '" /></p>' .
                    '</div>';
            }
            echo '</form>';
        }
        echo '</div>';
    }
}
