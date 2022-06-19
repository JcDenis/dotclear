<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Search
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Action\Action\PostAction;
use Dotclear\Process\Admin\Action\Action\CommentAction;
use Dotclear\Process\Admin\Inventory\Inventory\PostInventory;
use Dotclear\Process\Admin\Inventory\Inventory\CommentInventory;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin search list page.
 *
 * @ingroup  Admin Search Handler
 */
class Search extends AbstractPage
{
    private $s_qtype_combo = [];
    private $s_args        = [];
    private $s_count;
    private $s_list;
    private $s_actions;

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        App::core()->behavior('adminSearchPageCombo')->add([$this, 'typeCombo']);
        App::core()->behavior('adminSearchPageHead')->add([$this, 'pageHead']);
        // posts search
        App::core()->behavior('adminSearchPageProcess')->add([$this, 'processPosts']);
        App::core()->behavior('adminSearchPageDisplay')->add([$this, 'displayPosts']);
        // comments search
        App::core()->behavior('adminSearchPageProcess')->add([$this, 'processComments']);
        App::core()->behavior('adminSearchPageDisplay')->add([$this, 'displayComments']);

        $qtype_combo = new ArrayObject();

        // --BEHAVIOR-- adminSearchPageCombo
        App::core()->behavior('adminSearchPageCombo')->call($qtype_combo);

        $this->s_qtype_combo = $qtype_combo->getArrayCopy();
        $q                   = GPC::request()->empty('q') ? GPC::request()->string('qx', null) : GPC::request()->string('q');
        $qtype               = !GPC::request()->empty('qtype') ? GPC::request()->string('qtype') : 'p';
        if (!empty($q) && !in_array($qtype, $this->s_qtype_combo)) {
            $qtype = 'p';
        }

        $page = GPC::get()->empty('page') ? 1 : max(1, GPC::get()->int('page'));
        $nb   = App::core()->listoption()->sort()->getGroup('search')?->getSortLimit() ?? 20;
        if (0 < GPC::get()->int('nb')) {
            $nb = GPC::get()->int('nb');
        }

        $this->s_args = ['q' => $q, 'qtype' => $qtype, 'page' => $page, 'nb' => $nb];

        // --BEHAVIOR-- adminSearchPageHead
        $starting_scripts = $q ? App::core()->behavior('adminSearchPageHead')->call($this->s_args) : '';

        if ($q) {
            // --BEHAVIOR-- adminSearchPageProcess
            App::core()->behavior('adminSearchPageProcess')->call($this->s_args);
        }

        // Page setup
        $this
            ->setPageTitle(__('Search'))
            ->setPageHelp('core_search')
            ->setPageHead($starting_scripts)
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Search')                                => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo '<form action="' . App::core()->adminurl()->get('admin.search') . '" method="get" role="search">' .
        '<div class="fieldset"><h3>' . __('Search options') . '</h3>' .
        '<p><label for="q">' . __('Query:') . ' </label>' .
        Form::field('q', 30, 255, Html::escapeHTML($this->s_args['q'])) . '</p>' .
        '<p><label for="qtype">' . __('In:') . '</label> ' .
        Form::combo('qtype', $this->s_qtype_combo, $this->s_args['qtype']) . '</p>' .
        '<p><input type="submit" value="' . __('Search') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        Form::hidden(['handler'], 'admin.search') .
        '</p>' .
        '</div>' .
        '</form>';

        if ($this->s_args['q'] && !App::core()->error()->flag()) {
            ob_start();

            // --BEHAVIOR-- adminSearchPageDisplay
            App::core()->behavior('adminSearchPageDisplay')->call($this->s_args);

            $res = ob_get_contents();
            ob_end_clean();
            echo $res ?: '<p>' . __('No results found') . '</p>';
        }
    }

    public function typeCombo(ArrayObject $combo)
    {
        $combo[__('Search in entries')]  = 'p';
        $combo[__('Search in comments')] = 'c';
    }

    public function pageHead(array $args)
    {
        if ('p' == $args['qtype']) {
            return App::core()->resource()->load('_posts_list.js');
        }
        if ('c' == $args['qtype']) {
            return App::core()->resource()->load('_comments.js');
        }
    }

    public function processPosts(array $args)
    {
        if ('p' != $args['qtype']) {
            return null;
        }

        $param = new Param();
        $param->set('search', $args['q']);
        $param->set('limit', [(($args['page'] - 1) * $args['nb']), $args['nb']]);
        $param->set('no_content', true);
        $param->set('order', 'post_dt DESC');

        try {
            $this->s_count   = App::core()->blog()->posts()->countPosts(param: $param);
            $this->s_list    = new PostInventory(App::core()->blog()->posts()->getPosts(param: $param), $this->s_count);
            $this->s_actions = new PostAction(App::core()->adminurl()->get('admin.search'), $args);
            if ($this->s_actions->getPagePrepend()) {
                return;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }
    }

    public function displayPosts(array $args)
    {
        if ('p' != $args['qtype'] || null === $this->s_count) {
            return null;
        }

        if (0 < $this->s_count) {
            printf('<h3>' . __('one entry found', '%d entries found', $this->s_count) . '</h3>', $this->s_count);
        }

        $this->s_list->display(
            $args['page'],
            $args['nb'],
            '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
            Form::combo('action', $this->s_actions->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            App::core()->adminurl()->getHiddenFormFields('admin.search', [], true) .
            preg_replace('/%/', '%%', $this->s_actions->getHiddenFields()) .
            '</div>' .
            '</form>'
        );
    }

    public function processComments(array $args)
    {
        if ('c' != $args['qtype']) {
            return null;
        }

        $param = new Param();
        $param->set('search', $args['q']);
        $param->set('limit', [(($args['page'] - 1) * $args['nb']), $args['nb']]);
        $param->set('no_content', true);
        $param->set('order', 'comment_dt DESC');

        try {
            $this->s_count   = App::core()->blog()->comments()->countComments(param: $param);
            $this->s_list    = new CommentInventory(App::core()->blog()->comments()->getComments(param: $param), $this->s_count);
            $this->s_actions = new CommentAction(App::core()->adminurl()->get('admin.search'), $args);
            if ($this->s_actions->getPagePrepend()) {
                return;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }
    }

    public function displayComments(array $args)
    {
        if ('c' != $args['qtype'] || null === $this->s_count) {
            return null;
        }

        if (0 < $this->s_count) {
            printf('<h3>' . __('one comment found', '%d comments found', $this->s_count) . '</h3>', $this->s_count);
        }

        $this->s_list->display(
            $args['page'],
            $args['nb'],
            '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-comments">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
            Form::combo('action', $this->s_actions->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            App::core()->adminurl()->getHiddenFormFields('admin.search', [], true) .
            preg_replace('/%/', '%%', $this->s_actions->getHiddenFields()) .
            '</div>' .
            '</form>'
        );
    }
}
