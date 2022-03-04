<?php
/**
 * @class Dotclear\Process\Admin\Page\Page\Category
 * @brief Dotclear admin category page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Page\Page;

use Dotclear\Process\Admin\Page\Page;
use Dotclear\Html\Form;
use Dotclear\Html\FormSelectOption;
use Dotclear\Html\Html;

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
                $rs = dotclear()->blog()->categories()->getCategory((int) $_REQUEST['id']);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }

            if (!dotclear()->error()->flag() && !$rs->isEmpty()) {
                $this->cat_id    = (int) $rs->cat_id;
                $this->cat_title = $rs->cat_title;
                $this->cat_url   = $rs->cat_url;
                $this->cat_desc  = $rs->cat_desc;
            }
            unset($rs);

            # Getting hierarchy information
            $parents    = dotclear()->blog()->categories()->getCategoryParents($this->cat_id);
            $rs         = dotclear()->blog()->categories()->getCategoryParent($this->cat_id);
            $this->cat_parent = $rs->isEmpty() ? 0 : (int) $rs->cat_id;
            unset($rs);

            # Allowed parents list
            $children        = dotclear()->blog()->categories()->getCategories(['start' => $this->cat_id]);
            $this->allowed_parents = [__('Top level') => 0];

            $p = [];
            while ($children->fetch()) {
                $p[$children->cat_id] = 1;
            }

            $rs = dotclear()->blog()->categories()->getCategories();
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
            $rs = dotclear()->blog()->categories()->getCategoryFirstChildren($this->cat_parent);
            while ($rs->fetch()) {
                if ($rs->cat_id != $this->cat_id) {
                    $this->siblings[Html::escapeHTML($rs->cat_title)] = $rs->cat_id;
                }
            }
            unset($rs);
        }

        # Changing parent
        if ($this->cat_id && isset($_POST['cat_parent'])) {
            $new_parent = (int) $_POST['cat_parent'];
            if ($this->cat_parent != $new_parent) {
                try {
                    dotclear()->blog()->categories()->setCategoryParent($this->cat_id, $new_parent);
                    dotclear()->notice()->addSuccessNotice(__('The category has been successfully moved'));
                    dotclear()->adminurl()->redirect('admin.categories');
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }
        }

        # Changing sibling
        if ($this->cat_id && isset($_POST['cat_sibling'])) {
            try {
                dotclear()->blog()->categories()->setCategoryPosition($this->cat_id, (int) $_POST['cat_sibling'], $_POST['cat_move']);
                dotclear()->notice()->addSuccessNotice(__('The category has been successfully moved'));
                dotclear()->adminurl()->redirect('admin.categories');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Create or update a category
        if (isset($_POST['cat_title'])) {
            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'category');

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
                    dotclear()->behavior()->call('adminBeforeCategoryUpdate', $cur, $this->cat_id);

                    dotclear()->blog()->categories()->updCategory((int) $_POST['id'], $cur);

                    # --BEHAVIOR-- adminAfterCategoryUpdate
                    dotclear()->behavior()->call('adminAfterCategoryUpdate', $cur, $this->cat_id);

                    dotclear()->notice()->addSuccessNotice(__('The category has been successfully updated.'));

                    dotclear()->adminurl()->redirect('admin.category', ['id' => $_POST['id']]);
                }
                # Create category
                else {
                    # --BEHAVIOR-- adminBeforeCategoryCreate
                    dotclear()->behavior()->call('adminBeforeCategoryCreate', $cur);

                    $id = dotclear()->blog()->categories()->addCategory($cur, (int) $_POST['new_cat_parent']);

                    # --BEHAVIOR-- adminAfterCategoryCreate
                    dotclear()->behavior()->call('adminAfterCategoryCreate', $cur, $id);

                    dotclear()->notice()->addSuccessNotice(sprintf(__('The category "%s" has been successfully created.'),
                        Html::escapeHTML($cur->cat_title)));
                    dotclear()->adminurl()->redirect('admin.categories');
                }
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }


        # Page setup
        $title = $this->cat_id ? Html::escapeHTML($this->cat_title) : __('New category');

        $elements = [
            Html::escapeHTML(dotclear()->blog()->name) => '',
            __('Categories')                       => dotclear()->adminurl()->get('admin.categories')
        ];
        if ($this->cat_id) {
            while ($parents->fetch()) {
                $elements[Html::escapeHTML($parents->cat_title)] = dotclear()->adminurl()->get('admin.category', ['id' => $parents->cat_id]);
            }
        }
        $elements[$title] = '';

        $category_editor = dotclear()->user()->getOption('editor');
        $rte_flag        = true;
        $rte_flags       = @dotclear()->user()->preference()->interface->rte_flags;
        if (is_array($rte_flags) && in_array('cat_descr', $rte_flags)) {
            $rte_flag = $rte_flags['cat_descr'];
        }

        $this
            ->setPageTitle($title)
            ->setPageHelp('core_category')
            ->setPageBreadcrumb($elements)
            ->setPageHead(
                static::jsConfirmClose('category-form') .
                dotclear()->filer()->load('_category.js') .
                ($rte_flag ? dotclear()->behavior()->call('adminPostEditor', $category_editor['xhtml'], 'category', ['#cat_desc'], 'xhtml') : '')
            );
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            dotclear()->notice()->success(__('Category has been successfully updated.'));
        }

        echo
        '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="category-form">' .
        '<h3>' . __('Category information') . '</h3>' .
        '<p><label class="required" for="cat_title"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Name:') . '</label> ' .
        Form::field('cat_title', 40, 255, [
            'default'    => Html::escapeHTML($this->cat_title),
            'extra_html' => 'required placeholder="' . __('Name') . '" lang="' . dotclear()->blog()->settings()->system->lang . '" spellcheck="true"'
        ]) .
            '</p>';
        if (!$this->cat_id) {
            $rs = dotclear()->blog()->categories()->getCategories();
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
                'extra_html' => 'lang="' . dotclear()->blog()->settings()->system->lang  . '" spellcheck="true"'
            ]) .
        '</p>' .

        '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        ($this->cat_id ? Form::hidden('id', $this->cat_id) : '') .
        dotclear()->adminurl()->getHiddenFormFields('admin.category', [], true) .
            '</p>' .
            '</form>';

        if ($this->cat_id) {
            echo
            '<h3 class="border-top">' . __('Move this category') . '</h3>' .
            '<div class="two-cols">' .
            '<div class="col">' .

            '<form action="' . dotclear()->adminurl()->root() . '" method="post" class="fieldset">' .
            '<h4>' . __('Category parent') . '</h4>' .
            '<p><label for="cat_parent" class="classic">' . __('Parent:') . '</label> ' .
            Form::combo('cat_parent', $this->allowed_parents, (string) $this->cat_parent) . '</p>' .
            '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
            dotclear()->adminurl()->getHiddenFormFields('admin.category', ['id' => $this->cat_id], true) . '</p>' .
                '</form>' .
                '</div>';

            if (count($this->siblings) > 0) {
                echo
                '<div class="col">' .
                '<form action="' . dotclear()->adminurl()->root() . '" method="post" class="fieldset">' .
                '<h4>' . __('Category sibling') . '</h4>' .
                '<p><label class="classic" for="cat_sibling">' . __('Move current category') . '</label> ' .
                Form::combo('cat_move', [__('before') => 'before', __('after') => 'after'],
                    ['extra_html' => 'title="' . __('position: ') . '"']) . ' ' .
                Form::combo('cat_sibling', $this->siblings) . '</p>' .
                '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
                dotclear()->adminurl()->getHiddenFormFields('admin.category', ['id' => $this->cat_id], true) . '</p>' .
                    '</form>' .
                    '</div>';
            }

            echo '</div>';
        }
    }
}
