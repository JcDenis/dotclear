<?php
/**
 * @class Dotclear\Admin\Page\Categories
 * @brief Dotclear admin categories list page
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

use Dotclear\Admin\Page;

use Dotclear\Database\Record;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Categories extends Page
{
    protected $workspaces = ['accessibility'];

    /** @var Record     Categories working on*/
    private $categories;

    protected function getPermissions(): string|null|false
    {
        return 'categories';
    }

    protected function getPagePrepend(): ?bool
    {
        # Remove a categories
        if (!empty($_POST['delete'])) {
            $keys   = array_keys($_POST['delete']);
            $cat_id = (int) $keys[0];

            # Check if category to delete exists
            $category = $this->core->blog->getCategory($cat_id);
            if ($category->isEmpty()) {
                $this->core->notices->addErrorNotice(__('This category does not exist.'));
                $this->core->adminurl->redirect('admin.categories');
            }
            $name = $category->cat_title;
            unset($category);

            try {
                # Delete category
                $this->core->blog->delCategory($cat_id);
                $this->core->notices->addSuccessNotice(sprintf(__('The category "%s" has been successfully deleted.'), Html::escapeHTML($name)));
                $this->core->adminurl->redirect('admin.categories');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        # move post into a category
        if (!empty($_POST['mov']) && !empty($_POST['mov_cat'])) {
            try {
                # Check if category where to move posts exists
                $keys    = array_keys($_POST['mov']);
                $cat_id  = (int) $keys[0];
                $mov_cat = (int) $_POST['mov_cat'][$cat_id];

                $mov_cat = $mov_cat ?: null;
                $name    = '';
                if ($mov_cat !== null) {
                    $category = $this->core->blog->getCategory($mov_cat);
                    if ($category->isEmpty()) {
                        throw new AdminException(__('Category where to move entries does not exist'));
                    }
                    $name = $category->cat_title;
                    unset($category);
                }
                # Move posts
                if ($mov_cat != $cat_id) {
                    $this->core->blog->changePostsCategory($cat_id, $mov_cat);
                }
                $this->core->notices->addSuccessNotice(sprintf(
                    __('The entries have been successfully moved to category "%s"'),
                    Html::escapeHTML($name)
                ));
                $this->core->adminurl->redirect('admin.categories');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        # Update order
        if (!empty($_POST['save_order']) && !empty($_POST['categories_order'])) {
            $categories = json_decode($_POST['categories_order']);

            foreach ($categories as $category) {
                if (!empty($category->item_id) && !empty($category->left) && !empty($category->right)) {
                    $this->core->blog->updCategoryPosition((int) $category->item_id, $category->left, $category->right);
                }
            }

            $this->core->notices->addSuccessNotice(__('Categories have been successfully reordered.'));
            $this->core->adminurl->redirect('admin.categories');
        }

        # Reset order
        if (!empty($_POST['reset'])) {
            try {
                $this->core->blog->resetCategoriesOrder();
                $this->core->notices->addSuccessNotice(__('Categories order has been successfully reset.'));
                $this->core->adminurl->redirect('admin.categories');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        $this->caregories = $this->core->blog->getCategories();

        # Page setup
        if (!$this->core->auth->user_prefs->accessibility->nodragdrop
            && $this->core->auth->check('categories', $this->core->blog->id)
            && $this->caregories->count() > 1) {
            $this->setPageHead(
                static::jsLoad('js/jquery/jquery-ui.custom.js') .
                static::jsLoad('js/jquery/jquery.ui.touch-punch.js') .
                static::jsLoad('js/jquery/jquery.mjs.nestedSortable.js')
            );
        }

        $this
            ->setPageTitle(__('Categories'))
            ->setPageHelp('core_categories')
            ->setPageHead(
                static::jsConfirmClose('form-categories') .
                static::jsLoad('js/_categories.js')
            )
            ->setPageBreadcrumb([
                html::escapeHTML($this->core->blog->name) => '',
                __('Categories')                          => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['del'])) {
            $this->core->notices->success(__('The category has been successfully removed.'));
        }
        if (!empty($_GET['reord'])) {
            $this->core->notices->success(__('Categories have been successfully reordered.'));
        }
        if (!empty($_GET['move'])) {
            $this->core->notices->success(__('Entries have been successfully moved to the category you choose.'));
        }

        $categories_combo = $this->core->combos->getCategoriesCombo($this->caregories);

        echo
        '<p class="top-add"><a class="button add" href="' . $this->core->adminurl->get('admin.category') . '">' . __('New category') . '</a></p>';

        echo
            '<div class="col">';
        if ($this->caregories->isEmpty()) {
            echo '<p>' . __('No category so far.') . '</p>';
        } else {
            echo
            '<form action="' . $this->core->adminurl->get('admin.categories') . '" method="post" id="form-categories">' .
                '<div id="categories">';

            $ref_level = $level = $this->caregories->level - 1;
            while ($this->caregories->fetch()) {
                $attr = 'id="cat_' . $this->caregories->cat_id . '" class="cat-line clearfix"';

                if ($this->caregories->level > $level) {
                    echo str_repeat('<ul><li ' . $attr . '>', $this->caregories->level - $level);
                } elseif ($this->caregories->level < $level) {
                    echo str_repeat('</li></ul>', -($this->caregories->level - $level));
                }

                if ($this->caregories->level <= $level) {
                    echo '</li><li ' . $attr . '>';
                }

                echo
                '<p class="cat-title"><label class="classic" for="cat_' . $this->caregories->cat_id . '"><a href="' .
                $this->core->adminurl->get('admin.category', ['id' => $this->caregories->cat_id]) . '">' . Html::escapeHTML($this->caregories->cat_title) .
                '</a></label> </p>' .
                '<p class="cat-nb-posts">(<a href="' . $this->core->adminurl->get('admin.posts', ['cat_id' => $this->caregories->cat_id]) . '">' .
                sprintf(($this->caregories->nb_post > 1 ? __('%d entries') : __('%d entry')), $this->caregories->nb_post) . '</a>' .
                ', ' . __('total:') . ' ' . $this->caregories->nb_total . ')</p>' .
                '<p class="cat-url">' . __('URL:') . ' <code>' . Html::escapeHTML($this->caregories->cat_url) . '</code></p>';

                echo
                    '<p class="cat-buttons">';
                if ($this->caregories->nb_total > 0) {
                    // remove current category
                    echo
                    '<label for="mov_cat_' . $this->caregories->cat_id . '">' . __('Move entries to') . '</label> ' .
                    Form::combo(['mov_cat[' . $this->caregories->cat_id . ']', 'mov_cat_' . $this->caregories->cat_id], array_filter($categories_combo,
                        function ($cat) {return $cat->value != ($GLOBALS['rs']->cat_id ?? '0');}
                    ), '', '') .
                    ' <input type="submit" class="reset" name="mov[' . $this->caregories->cat_id . ']" value="' . __('OK') . '"/>';

                    $attr_disabled = ' disabled="disabled"';
                    $input_class   = 'disabled ';
                } else {
                    $attr_disabled = '';
                    $input_class   = '';
                }
                echo
                ' <input type="submit"' . $attr_disabled . ' class="' . $input_class . 'delete" name="delete[' . $this->caregories->cat_id . ']" value="' . __('Delete category') . '"/>' .
                    '</p>';

                $level = $this->caregories->level;
            }

            if ($ref_level - $level < 0) {
                echo str_repeat('</li></ul>', -($ref_level - $level));
            }
            echo
                '</div>';

            echo '<div class="clear">';

            if ($this->core->auth->check('categories', $this->core->blog->id) && $this->caregories->count() > 1) {
                if (!$this->core->auth->user_prefs->accessibility->nodragdrop) {
                    echo '<p class="form-note hidden-if-no-js">' . __('To rearrange categories order, move items by drag and drop, then click on “Save categories order” button.') . '</p>';
                }
                echo
                '<p><span class="hidden-if-no-js">' .
                '<input type="hidden" id="categories_order" name="categories_order" value=""/>' .
                '<input type="submit" name="save_order" id="save-set-order" value="' . __('Save categories order') . '" />' .
                    '</span> ';
            } else {
                echo '<p>';
            }

            echo
            '<input type="submit" class="reset" name="reset" value="' . __('Reorder all categories on the top level') . '" />' .
            $this->core->formNonce() . '</p>' .
                '</div></form>';
        }

        echo '</div>';
    }
}
