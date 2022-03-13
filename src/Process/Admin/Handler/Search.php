<?php
/**
 * @class Dotclear\Process\Admin\Handler\Search
 * @brief Dotclear admin search list page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use ArrayObject;

use Dotclear\Process\Admin\Page\Page;
use Dotclear\Process\Admin\Action\Action\PostAction;
use Dotclear\Process\Admin\Action\Action\CommentAction;
use Dotclear\Process\Admin\Inventory\Inventory\PostInventory;
use Dotclear\Process\Admin\Inventory\Inventory\CommentInventory;
use Dotclear\Process\Admin\Filter\Filter\PostFilter;
use Dotclear\Process\Admin\Filter\Filter\CommentFilter;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Search extends Page
{
    private $s_qtype_combo = [];
    private $s_args = [];

    protected $s_count   = null;
    protected $s_list    = null;
    protected $s_actions = null;

    protected $workspaces = ['interface'];

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        dotclear()->behavior()->add('adminSearchPageCombo', [$this,'typeCombo']);
        dotclear()->behavior()->add('adminSearchPageHead', [$this,'pageHead']);
        // posts search
        dotclear()->behavior()->add('adminSearchPageProcess', [$this,'processPosts']);
        dotclear()->behavior()->add('adminSearchPageDisplay', [$this,'displayPosts']);
        // comments search
        dotclear()->behavior()->add('adminSearchPageProcess', [$this,'processComments']);
        dotclear()->behavior()->add('adminSearchPageDisplay', [$this,'displayComments']);

        $qtype_combo = new ArrayObject();

        # --BEHAVIOR-- adminSearchPageCombo
        dotclear()->behavior()->call('adminSearchPageCombo', $qtype_combo);

        $this->s_qtype_combo = $qtype_combo->getArrayCopy();
        $q     = !empty($_REQUEST['q']) ? $_REQUEST['q'] : (!empty($_REQUEST['qx']) ? $_REQUEST['qx'] : null);
        $qtype = !empty($_REQUEST['qtype']) ? $_REQUEST['qtype'] : 'p';
        if (!empty($q) && !in_array($qtype, $this->s_qtype_combo)) {
            $qtype = 'p';
        }

        $page = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $nb = dotclear()->listoption()->getUserFilters('search', 'nb');
        if (!empty($_GET['nb']) && (int) $_GET['nb'] > 0) {
            $nb = (int) $_GET['nb'];
        }

        $this->s_args = ['q' => $q, 'qtype' => $qtype, 'page' => $page, 'nb' => $nb];

        # --BEHAVIOR-- adminSearchPageHead
        $starting_scripts = $q ? dotclear()->behavior()->call('adminSearchPageHead', $this->s_args) : '';

        if ($q) {

            # --BEHAVIOR-- adminSearchPageProcess
            dotclear()->behavior()->call('adminSearchPageProcess', $this->s_args);
        }

        # Page setup
        $this
            ->setPageTitle(__('Search'))
            ->setPageHelp('core_search')
            ->setPageHead($starting_scripts)
            ->setPageBreadcrumb([
                    Html::escapeHTML(dotclear()->blog()->name) => '',
                    __('Search')                        => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo
        '<form action="' . dotclear()->adminurl()->get('admin.search') . '" method="get" role="search">' .
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

        if ($this->s_args['q'] && !dotclear()->error()->flag()) {
            ob_start();

            # --BEHAVIOR-- adminSearchPageDisplay
            dotclear()->behavior()->call('adminSearchPageDisplay', $this->s_args);

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
        if ($args['qtype'] == 'p') {
            return dotclear()->resource()->load('_posts_list.js');
        } elseif ($args['qtype'] == 'c') {
            return dotclear()->resource()->load('_comments.js');
        }
    }

    public function processPosts(array $args)
    {
        if ($args['qtype'] != 'p') {
            return null;
        }

        $params = [
            'search'     => $args['q'],
            'limit'      => [(($args['page'] - 1) * $args['nb']), $args['nb']],
            'no_content' => true,
            'order'      => 'post_dt DESC'
        ];

        try {
            $this->s_count   = (int) dotclear()->blog()->posts()->getPosts($params, true)->f(0);
            $this->s_list    = new PostInventory(dotclear()->blog()->posts()->getPosts($params), (int) $this->s_count);
            $this->s_actions = new PostAction(dotclear()->adminurl()->get('admin.search'), $args);
            if ($this->s_actions->getPagePrepend()) {
                return;
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }
    }

    public function displayPosts(array $args)
    {
        if ($args['qtype'] != 'p' || $this->s_count === null) {
            return null;
        }

        if ($this->s_count > 0) {
            printf('<h3>' . __('one result', __('%d results'), $this->s_count) . '</h3>', $this->s_count);
        }

        $this->s_list->display($args['page'], $args['nb'],
            '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
            Form::combo('action', $this->s_actions->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            dotclear()->adminurl()->getHiddenFormFields('admin.search', [], true) .
            preg_replace('/%/','%%', $this->s_actions->getHiddenFields()) .
            '</div>' .
            '</form>'
        );
    }

    public function processComments(array $args)
    {
        if ($args['qtype'] != 'c') {
            return null;
        }

        $params = [
            'search'     => $args['q'],
            'limit'      => [(($args['page'] - 1) * $args['nb']), $args['nb']],
            'no_content' => true,
            'order'      => 'comment_dt DESC'
        ];

        try {
            $this->s_count   = (int) dotclear()->blog()->comments()->getComments($params, true)->f(0);
            $this->s_list    = new CommentInventory(dotclear()->blog()->comments()->getComments($params), (int) $this->s_count);
            $this->s_actions = new CommentAction(dotclear()->adminurl()->get('admin.search'), $args);
            if ($this->s_actions->getPagePrepend()) {
                return;
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }
    }

    public function displayComments(array $args)
    {
        if ($args['qtype'] != 'c' || $this->s_count === null) {
            return null;
        }

        if ($this->s_count > 0) {
            printf('<h3>' . __('one comment found', __('%d comments found'), $this->s_count) . '</h3>', $this->s_count);
        }

        $this->s_list->display($args['page'], $args['nb'],
            '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-comments">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
            Form::combo('action', $this->s_actions->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            dotclear()->adminurl()->getHiddenFormFields('admin.search', [], true) .
            preg_replace('/%/','%%', $this->s_actions->getHiddenFields()) .
            '</div>' .
            '</form>'
        );
    }
}
