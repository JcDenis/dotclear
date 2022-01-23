<?php
/**
 * @class Dotclear\Admin\Page\Category
 * @brief Dotclear admin category page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;
use Dotclear\Core\Settings;

use Dotclear\Admin\Page;
use Dotclear\Admin\Notices;
use Dotclear\Admin\Combos;

use Dotclear\Html\Html;
use Dotclear\Html\Form;
use Dotclear\Html\FormSelectOption;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Category extends Page
{
    private $cat_id          = '';
    private $cat_title       = '';
    private $cat_url         = '';
    private $cat_desc        = '';
    private $cat_parent      = 0;
    private $siblings        = [];
    private $allowed_parents = [];

    protected function getPermissions(): string|null|false
    {
        return 'categories';
    }

    protected function getPagePrepend(): ?bool
    {
        # Getting existing category
        $rs              = null;
        $parents         = null;

        if (!empty($_REQUEST['id'])) {
            try {
                $rs = $this->core->blog->getCategory((int) $_REQUEST['id']);
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }

            if (!$this->core->error->flag() && !$rs->isEmpty()) {
                $this->cat_id    = (int) $rs->cat_id;
                $this->cat_title = $rs->cat_title;
                $this->cat_url   = $rs->cat_url;
                $this->cat_desc  = $rs->cat_desc;
            }
            unset($rs);

            # Getting hierarchy information
            $parents    = $this->core->blog->getCategoryParents($this->cat_id);
            $rs         = $this->core->blog->getCategoryParent($this->cat_id);
            $this->cat_parent = $rs->isEmpty() ? 0 : (int) $rs->cat_id;
            unset($rs);

            # Allowed parents list
            $children        = $this->core->blog->getCategories(['start' => $this->cat_id]);
            $this->allowed_parents = [__('Top level') => 0];

            $p = [];
            while ($children->fetch()) {
                $p[$children->cat_id] = 1;
            }

            $rs = $this->core->blog->getCategories();
            while ($rs->fetch()) {
                if (!isset($p[$rs->cat_id])) {
                    $this->allowed_parents[] = new FormSelectOption(
                        str_repeat('&nbsp;&nbsp;', $rs->level - 1) . ($rs->level - 1 == 0 ? '' : '&bull; ') . Html::escapeHTML($rs->cat_title),
                        $rs->cat_id
                    );
                }
            }
            unset($rs);

            # Allowed siblings list
            $rs = $this->core->blog->getCategoryFirstChildren($this->cat_parent);
            while ($rs->fetch()) {
                if ($rs->cat_id != $this->cat_id) {
                    $this->siblings[Html::escapeHTML($rs->cat_title)] = $rs->cat_id;
                }
            }
            unset($rs);
        }

        # Changing parent
        if ($this->cat_id && isset($_POST['cat_parent'])) {
            $new_parent = (integer) $_POST['cat_parent'];
            if ($this->cat_parent != $new_parent) {
                try {
                    $this->core->blog->setCategoryParent($this->cat_id, $new_parent);
                    $this->core->notices->addSuccessNotice(__('The category has been successfully moved'));
                    $this->core->adminurl->redirect('admin.categories');
                } catch (Exception $e) {
                    $this->core->error->add($e->getMessage());
                }
            }
        }

        # Changing sibling
        if ($this->cat_id && isset($_POST['cat_sibling'])) {
            try {
                $this->core->blog->setCategoryPosition($this->cat_id, (integer) $_POST['cat_sibling'], $_POST['cat_move']);
                $this->core->notices->addSuccessNotice(__('The category has been successfully moved'));
                $this->core->adminurl->redirect('admin.categories');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        # Create or update a category
        if (isset($_POST['cat_title'])) {
            $cur = $this->core->con->openCursor($this->core->prefix . 'category');

            $cur->cat_title = $this->cat_title = $_POST['cat_title'];

            if (isset($_POST['cat_desc'])) {
                $cur->cat_desc = $this->cat_desc = $_POST['cat_desc'];
            }

            if (isset($_POST['cat_url'])) {
                $cur->cat_url = $this->cat_url = $_POST['cat_url'];
            } else {
                $cur->cat_url = $this->cat_url;
            }

            try {
                # Update category
                if ($this->cat_id) {
                    # --BEHAVIOR-- adminBeforeCategoryUpdate
                    $this->core->behaviors->call('adminBeforeCategoryUpdate', $cur, $this->cat_id);

                    $this->core->blog->updCategory((int) $_POST['id'], $cur);

                    # --BEHAVIOR-- adminAfterCategoryUpdate
                    $this->core->behaviors->call('adminAfterCategoryUpdate', $cur, $this->cat_id);

                    $this->core->notices->addSuccessNotice(__('The category has been successfully updated.'));

                    $this->core->adminurl->redirect('admin.category', ['id' => $_POST['id']]);
                }
                # Create category
                else {
                    # --BEHAVIOR-- adminBeforeCategoryCreate
                    $this->core->behaviors->call('adminBeforeCategoryCreate', $cur);

                    $id = $this->core->blog->addCategory($cur, (integer) $_POST['new_cat_parent']);

                    # --BEHAVIOR-- adminAfterCategoryCreate
                    $this->core->behaviors->call('adminAfterCategoryCreate', $cur, $id);

                    $this->core->notices->addSuccessNotice(sprintf(__('The category "%s" has been successfully created.'),
                        Html::escapeHTML($cur->cat_title)));
                    $this->core->adminurl->redirect('admin.categories');
                }
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }


        # Page setup
        $title = $this->cat_id ? Html::escapeHTML($this->cat_title) : __('New category');

        $elements = [
            Html::escapeHTML($this->core->blog->name) => '',
            __('Categories')                    => $this->core->adminurl->get('admin.categories')
        ];
        if ($this->cat_id) {
            while ($parents->fetch()) {
                $elements[Html::escapeHTML($parents->cat_title)] = $this->core->adminurl->get('admin.category', ['id' => $parents->cat_id]);
            }
        }
        $elements[$title] = '';

        $category_editor = $this->core->auth->getOption('editor');
        $rte_flag        = true;
        $rte_flags       = @$this->core->auth->user_prefs->interface->rte_flags;
        if (is_array($rte_flags) && in_array('cat_descr', $rte_flags)) {
            $rte_flag = $rte_flags['cat_descr'];
        }

        $this
            ->setPageTitle($title)
            ->setPageHelp('core_category')
            ->setPageBreadcrumb($elements)
            ->setPageHead(
                static::jsConfirmClose('category-form') .
                static::jsLoad('js/_category.js') .
                ($rte_flag ? $this->core->behaviors->call('adminPostEditor', $category_editor['xhtml'], 'category', ['#cat_desc'], 'xhtml') : '')
            );
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            $this->core->notices->success(__('Category has been successfully updated.'));
        }

        $blog_settings = new Settings($this->core, $this->core->blog->id);
        $blog_lang     = $blog_settings->system->lang;

        echo
        '<form action="' . $this->core->adminurl->get('admin.category') . '" method="post" id="category-form">' .
        '<h3>' . __('Category information') . '</h3>' .
        '<p><label class="required" for="cat_title"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Name:') . '</label> ' .
        Form::field('cat_title', 40, 255, [
            'default'    => Html::escapeHTML($this->cat_title),
            'extra_html' => 'required placeholder="' . __('Name') . '" lang="' . $blog_lang . '" spellcheck="true"'
        ]) .
            '</p>';
        if (!$this->cat_id) {
            $rs = $this->core->blog->getCategories();
            echo
            '<p><label for="new_cat_parent">' . __('Parent:') . ' ' .
            '<select id="new_cat_parent" name="new_cat_parent" >' .
            '<option value="0">' . __('(none)') . '</option>';
            while ($rs->fetch()) {
                echo '<option value="' . $rs->cat_id . '" ' . (!empty($_POST['new_cat_parent']) && $_POST['new_cat_parent'] == $rs->cat_id ? 'selected="selected"' : '') . '>' .
                str_repeat('&nbsp;&nbsp;', $rs->level - 1) . ($rs->level - 1 == 0 ? '' : '&bull; ') . Html::escapeHTML($rs->cat_title) . '</option>';
            }
            echo
                '</select></label></p>';
            unset($rs);
        }
        echo
        '<div class="lockable">' .
        '<p><label for="cat_url">' . __('URL:') . '</label> '
        . Form::field('cat_url', 40, 255, Html::escapeHTML($this->cat_url)) .
        '</p>' .
        '<p class="form-note warn" id="note-cat-url">' .
        __('Warning: If you set the URL manually, it may conflict with another category.') . '</p>' .
        '</div>' .

        '<p class="area"><label for="cat_desc">' . __('Description:') . '</label> ' .
        Form::textarea('cat_desc', 50, 8,
            [
                'default'    => Html::escapeHTML($this->cat_desc),
                'extra_html' => 'lang="' . $blog_lang . '" spellcheck="true"'
            ]) .
        '</p>' .

        '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        ($this->cat_id ? Form::hidden('id', $this->cat_id) : '') .
        $this->core->formNonce() .
            '</p>' .
            '</form>';

        if ($this->cat_id) {
            echo
            '<h3 class="border-top">' . __('Move this category') . '</h3>' .
            '<div class="two-cols">' .
            '<div class="col">' .

            '<form action="' . $this->core->adminurl->get('admin.category') . '" method="post" class="fieldset">' .
            '<h4>' . __('Category parent') . '</h4>' .
            '<p><label for="cat_parent" class="classic">' . __('Parent:') . '</label> ' .
            Form::combo('cat_parent', $this->allowed_parents, (string) $this->cat_parent) . '</p>' .
            '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
            Form::hidden(['id'], $this->cat_id) . $this->core->formNonce() . '</p>' .
                '</form>' .
                '</div>';

            if (count($this->siblings) > 0) {
                echo
                '<div class="col">' .
                '<form action="' . $this->core->adminurl->get('admin.category') . '" method="post" class="fieldset">' .
                '<h4>' . __('Category sibling') . '</h4>' .
                '<p><label class="classic" for="cat_sibling">' . __('Move current category') . '</label> ' .
                Form::combo('cat_move', [__('before') => 'before', __('after') => 'after'],
                    ['extra_html' => 'title="' . __('position: ') . '"']) . ' ' .
                Form::combo('cat_sibling', $this->siblings) . '</p>' .
                '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
                Form::hidden(['id'], $this->cat_id) . $this->core->formNonce() . '</p>' .
                    '</form>' .
                    '</div>';
            }

            echo '</div>';
        }
    }
}
