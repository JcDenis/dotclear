<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Categories
use Dotclear\App;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Database\Record;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Admin categories list page.
 *
 * @ingroup  Admin Category Handler
 */
class Categories extends AbstractPage
{
    /**
     * @var Record $categories
     *             Categories working on
     */
    private $categories;

    protected function getPermissions(): string|bool
    {
        return 'categories';
    }

    protected function getPagePrepend(): ?bool
    {
        // Remove a categories
        if (!GPC::post()->empty('delete')) {
            $keys   = array_keys(GPC::post()->array('delete'));
            $cat_id = (int) $keys[0];

            // Check if category to delete exists
            $category = App::core()->blog()->categories()->getCategory(id: $cat_id);
            if ($category->isEmpty()) {
                App::core()->notice()->addErrorNotice(__('This category does not exist.'));
                App::core()->adminurl()->redirect('admin.categories');
            }
            $name = $category->f('cat_title');
            unset($category);

            try {
                // Delete category
                App::core()->blog()->categories()->delCategory(id: $cat_id);
                App::core()->notice()->addSuccessNotice(sprintf(__('The category "%s" has been successfully deleted.'), Html::escapeHTML($name)));
                App::core()->adminurl()->redirect('admin.categories');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // move post into a category
        if (!GPC::post()->empty('mov') && !GPC::post()->empty('mov_cat')) {
            try {
                // Check if category where to move posts exists
                $keys    = array_keys(GPC::post()->array('mov'));
                $cat_id  = (int) $keys[0];
                $mov_cat = (int) GPC::post()->array('mov_cat')[$cat_id];

                $mov_cat = $mov_cat ?: null;
                $name    = '';
                if (null !== $mov_cat) {
                    $category = App::core()->blog()->categories()->getCategory(id: $mov_cat);
                    if ($category->isEmpty()) {
                        throw new AdminException(__('Category where to move entries does not exist'));
                    }
                    $name = $category->f('cat_title');
                    unset($category);
                }
                // Move posts
                if ($mov_cat != $cat_id) {
                    App::core()->blog()->posts()->changePostsCategory(old: $cat_id, new: $mov_cat);
                }
                App::core()->notice()->addSuccessNotice(sprintf(
                    __('The entries have been successfully moved to category "%s"'),
                    Html::escapeHTML($name)
                ));
                App::core()->adminurl()->redirect('admin.categories');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Update order
        if (!GPC::post()->empty('save_order') && !GPC::post()->empty('categories_order')) {
            $categories = json_decode(GPC::post()->string('categories_order'));

            foreach ($categories as $category) {
                if (!empty($category->item_id) && !empty($category->left) && !empty($category->right)) {
                    App::core()->blog()->categories()->updCategoryPosition(
                        id: (int) $category->item_id,
                        left: $category->left,
                        right: $category->right
                    );
                }
            }

            App::core()->notice()->addSuccessNotice(__('Categories have been successfully reordered.'));
            App::core()->adminurl()->redirect('admin.categories');
        }

        // Reset order
        if (!GPC::post()->empty('reset')) {
            try {
                App::core()->blog()->categories()->resetCategoriesOrder();
                App::core()->notice()->addSuccessNotice(__('Categories order has been successfully reset.'));
                App::core()->adminurl()->redirect('admin.categories');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        $this->categories = App::core()->blog()->categories()->getCategories();

        // Page setup
        if (!App::core()->user()->preference()->get('accessibility')->get('nodragdrop')
            && App::core()->user()->check('categories', App::core()->blog()->id)
            && 1 < $this->categories->count()) {
            $this->setPageHead(
                App::core()->resource()->load('jquery/jquery-ui.custom.js') .
                App::core()->resource()->load('jquery/jquery.ui.touch-punch.js') .
                App::core()->resource()->load('jquery/jquery.mjs.nestedSortable.js')
            );
        }

        $this
            ->setPageTitle(__('Categories'))
            ->setPageHelp('core_categories')
            ->setPageHead(
                App::core()->resource()->confirmClose('form-categories') .
                App::core()->resource()->load('_categories.js')
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Categories')                            => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!GPC::get()->empty('del')) {
            App::core()->notice()->success(__('The category has been successfully removed.'));
        } elseif (!GPC::get()->empty('reord')) {
            App::core()->notice()->success(__('Categories have been successfully reordered.'));
        } elseif (!GPC::get()->empty('move')) {
            App::core()->notice()->success(__('Entries have been successfully moved to the category you choose.'));
        }

        $categories_combo = App::core()->combo()->getCategoriesCombo($this->categories);

        echo '<p class="top-add"><a class="button add" href="' . App::core()->adminurl()->get('admin.category') . '">' . __('New category') . '</a></p>';

        echo '<div class="col">';
        if ($this->categories->isEmpty()) {
            echo '<p>' . __('No category so far.') . '</p>';
        } else {
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-categories">' .
                '<div id="categories">';

            $ref_level = $level = $this->categories->fInt('level') - 1;
            while ($this->categories->fetch()) {
                $attr = 'id="cat_' . $this->categories->f('cat_id') . '" class="cat-line clearfix"';

                if ($this->categories->fInt('level') > $level) {
                    echo str_repeat('<ul><li ' . $attr . '>', $this->categories->fInt('level') - $level);
                } elseif ($this->categories->fInt('level') < $level) {
                    echo str_repeat('</li></ul>', -($this->categories->fInt('level') - $level));
                }

                if ($this->categories->fInt('level') <= $level) {
                    echo '</li><li ' . $attr . '>';
                }

                echo '<p class="cat-title"><label class="classic" for="cat_' . $this->categories->f('cat_id') . '"><a href="' .
                App::core()->adminurl()->get('admin.category', ['id' => $this->categories->f('cat_id')]) . '">' . Html::escapeHTML($this->categories->f('cat_title')) .
                '</a></label> </p>' .
                '<p class="cat-nb-posts">(<a href="' . App::core()->adminurl()->get('admin.posts', ['cat_id' => $this->categories->f('cat_id')]) . '">' .
                sprintf((1 < $this->categories->fInt('nb_post') ? __('%d entries') : __('%d entry')), $this->categories->fInt('nb_post')) . '</a>' .
                ', ' . __('total:') . ' ' . $this->categories->f('nb_total') . ')</p>' .
                '<p class="cat-url">' . __('URL:') . ' <code>' . Html::escapeHTML($this->categories->f('cat_url')) . '</code></p>';

                echo '<p class="cat-buttons">';
                if (0 < $this->categories->fInt('nb_total')) {
                    $fn_cat_id = $this->categories->f('cat_id');
                    // remove current category
                    echo '<label for="mov_cat_' . $this->categories->f('cat_id') . '">' . __('Move entries to') . '</label> ' .
                    Form::combo(['mov_cat[' . $this->categories->f('cat_id') . ']', 'mov_cat_' . $this->categories->f('cat_id')], array_filter(
                        $categories_combo,
                        fn ($cat) => $cat->value != ($fn_cat_id ?? '0')
                    ), '', '') .
                    ' <input type="submit" class="reset" name="mov[' . $this->categories->f('cat_id') . ']" value="' . __('OK') . '"/>';

                    $attr_disabled = ' disabled="disabled"';
                    $input_class   = 'disabled ';
                } else {
                    $attr_disabled = '';
                    $input_class   = '';
                }
                echo ' <input type="submit"' . $attr_disabled . ' class="' . $input_class . 'delete" name="delete[' . $this->categories->f('cat_id') . ']" value="' . __('Delete category') . '"/>' .
                    '</p>';

                $level = $this->categories->fInt('level');
            }

            if (0 > $ref_level - $level) {
                echo str_repeat('</li></ul>', -($ref_level - $level));
            }
            echo '</div>';

            echo '<div class="clear">';

            if (App::core()->user()->check('categories', App::core()->blog()->id) && 1 < $this->categories->count()) {
                if (!App::core()->user()->preference()->get('accessibility')->get('nodragdrop')) {
                    echo '<p class="form-note hidden-if-no-js">' . __('To rearrange categories order, move items by drag and drop, then click on “Save categories order” button.') . '</p>';
                }
                echo '<p><span class="hidden-if-no-js">' .
                '<input type="hidden" id="categories_order" name="categories_order" value=""/>' .
                '<input type="submit" name="save_order" id="save-set-order" value="' . __('Save categories order') . '" />' .
                    '</span> ';
            } else {
                echo '<p>';
            }

            echo '<input type="submit" class="reset" name="reset" value="' . __('Reorder all categories on the top level') . '" />' .
            App::core()->adminurl()->getHiddenFormFields('admin.categories', [], true) . '</p>' .
                '</div></form>';
        }

        echo '</div>';
    }
}
