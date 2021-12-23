<?php
/**
 * @class Dotclear\Admin\Page\Posts
 * @brief Dotclear admin posts list page
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

use Dotclear\Admin\Page;
use Dotclear\Admin\Action\PostAction;
use Dotclear\Admin\Catalog\PostCatalog;
use Dotclear\Admin\Filter\PostFilter;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Posts extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->check('usage,contentadmin');

        /* Actions
        -------------------------------------------------------- */
        $posts_actions_page = new PostAction($this->core, $this->core->adminurl->get('admin.posts'));

        if ($posts_actions_page->process()) {
            return;
        }

        /* Filters
        -------------------------------------------------------- */
        $post_filter = new PostFilter($this->core);

        # get list params
        $params = $post_filter->params();

        # lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'post_title' => 'post_title',
            'cat_title'  => 'cat_title',
            'user_id'    => 'P.user_id'];

        # --BEHAVIOR-- adminPostsSortbyLexCombo
        $core->callBehavior('adminPostsSortbyLexCombo', [& $sortby_lex]);

        $params['order'] = (array_key_exists($post_filter->sortby, $sortby_lex) ?
            $this->core->con->lexFields($sortby_lex[$post_filter->sortby]) :
            $post_filter->sortby) . ' ' . $post_filter->order;

        $params['no_content'] = true;

        /* List
        -------------------------------------------------------- */
        $post_list = null;

        try {
            $posts     = $this->core->blog->getPosts($params);
            $counter   = $this->core->blog->getPosts($params, true);
            $post_list = new PostCatalog($this->core, $posts, $counter->f(0));
        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());
        }

        /* DISPLAY
        -------------------------------------------------------- */

        $this->open(__('Posts'),
            static::jsLoad('js/_posts_list.js') . $post_filter->js(),
            $this->breadcrumb(
                [
                    Html::escapeHTML($this->core->blog->name) => '',
                    __('Posts')                         => ''
                ])
        );
        if (!empty($_GET['upd'])) {
            static::success(__('Selected entries have been successfully updated.'));
        } elseif (!empty($_GET['del'])) {
            static::success(__('Selected entries have been successfully deleted.'));
        }
        if (!$this->core->error->flag()) {
            echo '<p class="top-add"><a class="button add" href="' . $this->core->adminurl->get('admin.post') . '">' . __('New post') . '</a></p>';

            # filters
            $post_filter->display('admin.posts');

            # Show posts
            $post_list->display($post_filter->page, $post_filter->nb,
                '<form action="' . $this->core->adminurl->get('admin.posts') . '" method="post" id="form-entries">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
                Form::combo('action', $posts_actions_page->getCombo()) .
                '<input id="do-action" type="submit" value="' . __('ok') . '" disabled /></p>' .
                $this->core->adminurl->getHiddenFormFields('admin.posts', $post_filter->values()) .
                $this->core->formNonce() .
                '</div>' .
                '</form>',
                $post_filter->show()
            );
        }

        $this->helpBlock('core_posts');
        $this->close();
    }
}
