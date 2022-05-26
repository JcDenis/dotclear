<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Category
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\FormSelectOption;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin category page.
 *
 * @ingroup  Admin Category Handler
 */
class Category extends AbstractPage
{
    private $cat_id;
    private $cat_title           = '';
    private $cat_url             = '';
    private $cat_desc            = '';
    private $cat_parent          = 0;
    private $cat_siblings        = [];
    private $cat_allowed_parents = [];

    protected function getPermissions(): string|bool
    {
        return 'categories';
    }

    protected function getPagePrepend(): ?bool
    {
        // Getting existing category
        $rs      = null;
        $parents = null;

        if (!empty($_REQUEST['id'])) {
            try {
                $rs = App::core()->blog()->categories()->getCategory(id: (int) $_REQUEST['id']);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }

            if (!App::core()->error()->flag() && !$rs->isEmpty()) {
                $this->cat_id    = $rs->fInt('cat_id');
                $this->cat_title = $rs->f('cat_title');
                $this->cat_url   = $rs->f('cat_url');
                $this->cat_desc  = $rs->f('cat_desc');
            }
            unset($rs);

            // Getting hierarchy information
            $parents          = App::core()->blog()->categories()->getCategoryParents(id: $this->cat_id);
            $rs               = App::core()->blog()->categories()->getCategoryParent(id: $this->cat_id);
            $this->cat_parent = $rs->isEmpty() ? 0 : $rs->fInt('cat_id');
            unset($rs);

            // Allowed parents list
            $param = new Param();
            $param->set('start', $this->cat_id);

            $children                  = App::core()->blog()->categories()->getCategories(param: $param);
            $this->cat_allowed_parents = [__('Top level') => 0];

            $p = [];
            while ($children->fetch()) {
                $p[$children->fInt('cat_id')] = 1;
            }

            $rs = App::core()->blog()->categories()->getCategories();
            while ($rs->fetch()) {
                if (!isset($p[$rs->fInt('cat_id')])) {
                    $this->cat_allowed_parents[] = new FormSelectOption(
                        str_repeat('&nbsp;&nbsp;', $rs->fInt('level') - 1) . ($rs->fInt('level') - 1 == 0 ? '' : '&bull; ') . Html::escapeHTML($rs->f('cat_title')),
                        $rs->fInt('cat_id')
                    );
                }
            }
            unset($rs);

            // Allowed siblings list
            $rs = App::core()->blog()->categories()->getCategoryFirstChildren(id: $this->cat_parent);
            while ($rs->fetch()) {
                if ($rs->fint('cat_id') != $this->cat_id) {
                    $this->cat_siblings[Html::escapeHTML($rs->f('cat_title'))] = $rs->fInt('cat_id');
                }
            }
            unset($rs);
        }

        // Changing parent
        if (null !== $this->cat_id && isset($_POST['cat_parent'])) {
            $new_parent = (int) $_POST['cat_parent'];
            if ($this->cat_parent != $new_parent) {
                try {
                    App::core()->blog()->categories()->setCategoryParent(
                        id: $this->cat_id,
                        parent: $new_parent
                    );
                    App::core()->notice()->addSuccessNotice(__('The category has been successfully moved'));
                    App::core()->adminurl()->redirect('admin.categories');
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }
        }

        // Changing sibling
        if (null !== $this->cat_id && isset($_POST['cat_sibling'])) {
            try {
                App::core()->blog()->categories()->setCategoryPosition(
                    id: $this->cat_id,
                    sibling: (int) $_POST['cat_sibling'],
                    move: $_POST['cat_move']
                );
                App::core()->notice()->addSuccessNotice(__('The category has been successfully moved'));
                App::core()->adminurl()->redirect('admin.categories');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Create or update a category
        if (isset($_POST['cat_title'])) {
            $cur = App::core()->con()->openCursor(App::core()->prefix() . 'category');

            $cur->setField('cat_title', $this->cat_title = $_POST['cat_title']);

            if (isset($_POST['cat_desc'])) {
                $cur->setField('cat_desc', $this->cat_desc = $_POST['cat_desc']);
            }

            if (isset($_POST['cat_url'])) {
                $cur->setField('cat_url', $this->cat_url = $_POST['cat_url']);
            } else {
                $cur->setField('cat_url', $this->cat_url);
            }

            try {
                // Update category
                if (null !== $this->cat_id) {
                    // --BEHAVIOR-- adminBeforeCategoryUpdate
                    App::core()->behavior()->call('adminBeforeCategoryUpdate', $cur, $this->cat_id);

                    App::core()->blog()->categories()->updCategory(
                        id: (int) $_POST['id'],
                        cursor: $cur
                    );

                    // --BEHAVIOR-- adminAfterCategoryUpdate
                    App::core()->behavior()->call('adminAfterCategoryUpdate', $cur, $this->cat_id);

                    App::core()->notice()->addSuccessNotice(__('The category has been successfully updated.'));

                    App::core()->adminurl()->redirect('admin.category', ['id' => $_POST['id']]);
                }
                // Create category
                else {
                    // --BEHAVIOR-- adminBeforeCategoryCreate
                    App::core()->behavior()->call('adminBeforeCategoryCreate', $cur);

                    $id = App::core()->blog()->categories()->addCategory(
                        cursor: $cur,
                        parent: (int) $_POST['new_cat_parent']
                    );

                    // --BEHAVIOR-- adminAfterCategoryCreate
                    App::core()->behavior()->call('adminAfterCategoryCreate', $cur, $id);

                    App::core()->notice()->addSuccessNotice(sprintf(
                        __('The category "%s" has been successfully created.'),
                        Html::escapeHTML($cur->getField('cat_title'))
                    ));
                    App::core()->adminurl()->redirect('admin.categories');
                }
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $title = null !== $this->cat_id ? Html::escapeHTML($this->cat_title) : __('New category');

        $elements = [
            Html::escapeHTML(App::core()->blog()->name) => '',
            __('Categories')                            => App::core()->adminurl()->get('admin.categories'),
        ];
        if (null !== $this->cat_id) {
            while ($parents->fetch()) {
                $elements[Html::escapeHTML($parents->f('cat_title'))] = App::core()->adminurl()->get('admin.category', ['id' => $parents->f('cat_id')]);
            }
        }
        $elements[$title] = '';

        $category_editor = App::core()->user()->getOption('editor');
        $rte_flag        = true;
        $rte_flags       = App::core()->user()->preference()->get('interface')->get('rte_flags');
        if (is_array($rte_flags) && in_array('cat_descr', $rte_flags)) {
            $rte_flag = $rte_flags['cat_descr'];
        }

        $this
            ->setPageTitle($title)
            ->setPageHelp('core_category')
            ->setPageBreadcrumb($elements)
            ->setPageHead(
                App::core()->resource()->confirmClose('category-form') .
                App::core()->resource()->load('_category.js') .
                ($rte_flag ? App::core()->behavior()->call('adminPostEditor', $category_editor['xhtml'], 'category', ['#cat_desc'], 'xhtml') : '')
            )
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            App::core()->notice()->success(__('Category has been successfully updated.'));
        }

        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="category-form">' .
        '<h3>' . __('Category information') . '</h3>' .
        '<p><label class="required" for="cat_title"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Name:') . '</label> ' .
        Form::field('cat_title', 40, 255, [
            'default'    => Html::escapeHTML($this->cat_title),
            'extra_html' => 'required placeholder="' . __('Name') . '" lang="' . App::core()->blog()->settings()->get('system')->get('lang') . '" spellcheck="true"',
        ]) .
            '</p>';
        if (null === $this->cat_id) {
            $rs = App::core()->blog()->categories()->getCategories();
            echo '<p><label for="new_cat_parent">' . __('Parent:') . ' ' .
            '<select id="new_cat_parent" name="new_cat_parent" >' .
            '<option value="0">' . __('(none)') . '</option>';
            while ($rs->fetch()) {
                echo '<option value="' . $rs->f('cat_id') . '" ' . (!empty($_POST['new_cat_parent']) && $rs->f('cat_id') == $_POST['new_cat_parent'] ? 'selected="selected"' : '') . '>' .
                str_repeat('&nbsp;&nbsp;', $rs->fInt('level') - 1) . (0                                                  == $rs->fInt('level') - 1 ? '' : '&bull; ') . Html::escapeHTML($rs->f('cat_title')) . '</option>';
            }
            echo '</select></label></p>';
            unset($rs);
        }
        echo '<div class="lockable">' .
        '<p><label for="cat_url">' . __('URL:') . '</label> '
        . Form::field('cat_url', 40, 255, Html::escapeHTML($this->cat_url)) .
        '</p>' .
        '<p class="form-note warn" id="note-cat-url">' .
        __('Warning: If you set the URL manually, it may conflict with another category.') . '</p>' .
        '</div>' .

        '<p class="area"><label for="cat_desc">' . __('Description:') . '</label> ' .
        Form::textarea(
            'cat_desc',
            50,
            8,
            [
                'default'    => Html::escapeHTML($this->cat_desc),
                'extra_html' => 'lang="' . App::core()->blog()->settings()->get('system')->get('lang') . '" spellcheck="true"',
            ]
        ) .
        '</p>' .

        '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        (null !== $this->cat_id ? Form::hidden('id', $this->cat_id) : '') .
        App::core()->adminurl()->getHiddenFormFields('admin.category', [], true) .
            '</p>' .
            '</form>';

        if (null !== $this->cat_id) {
            echo '<h3 class="border-top">' . __('Move this category') . '</h3>' .
            '<div class="two-cols">' .
            '<div class="col">' .

            '<form action="' . App::core()->adminurl()->root() . '" method="post" class="fieldset">' .
            '<h4>' . __('Category parent') . '</h4>' .
            '<p><label for="cat_parent" class="classic">' . __('Parent:') . '</label> ' .
            Form::combo('cat_parent', $this->cat_allowed_parents, (string) $this->cat_parent) . '</p>' .
            '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
            App::core()->adminurl()->getHiddenFormFields('admin.category', ['id' => $this->cat_id], true) . '</p>' .
                '</form>' .
                '</div>';

            if (0 < count($this->cat_siblings)) {
                echo '<div class="col">' .
                '<form action="' . App::core()->adminurl()->root() . '" method="post" class="fieldset">' .
                '<h4>' . __('Category sibling') . '</h4>' .
                '<p><label class="classic" for="cat_sibling">' . __('Move current category') . '</label> ' .
                Form::combo(
                    'cat_move',
                    [__('before') => 'before', __('after') => 'after'],
                    ['extra_html' => 'title="' . __('position: ') . '"']
                ) . ' ' .
                Form::combo('cat_sibling', $this->cat_siblings) . '</p>' .
                '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
                App::core()->adminurl()->getHiddenFormFields('admin.category', ['id' => $this->cat_id], true) . '</p>' .
                    '</form>' .
                    '</div>';
            }

            echo '</div>';
        }
    }
}
