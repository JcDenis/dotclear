<?php
/**
 * @class Dotclear\Admin\Page\Search
 * @brief Dotclear admin search list page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use ArrayObject;


use Dotclear\Admin\Page;
use Dotclear\Admin\Action\PostAction;
use Dotclear\Admin\Catalog\PostCatalog;
use Dotclear\Admin\Filter\PostFilter;
use Dotclear\Admin\Action\CommentAction;
use Dotclear\Admin\Catalog\CommentCatalog;
use Dotclear\Admin\Filter\CommentFilter;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Search extends Page
{
    private $qtype_combo = [];
    private $args = [];

    protected static $count   = null;
    protected static $list    = null;
    protected static $actions = null;

    protected $workspaces = ['interface'];

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        dotclear()->behavior()->add('adminSearchPageCombo', [__NAMESPACE__ . '\\Search','typeCombo']);
        dotclear()->behavior()->add('adminSearchPageHead', [__NAMESPACE__ . '\\Search','pageHead']);
        // posts search
        dotclear()->behavior()->add('adminSearchPageProcess', [__NAMESPACE__ . '\\Search','processPosts']);
        dotclear()->behavior()->add('adminSearchPageDisplay', [__NAMESPACE__ . '\\Search','displayPosts']);
        // comments search
        dotclear()->behavior()->add('adminSearchPageProcess', [__NAMESPACE__ . '\\Search','processComments']);
        dotclear()->behavior()->add('adminSearchPageDisplay', [__NAMESPACE__ . '\\Search','displayComments']);

        $qtype_combo = new ArrayObject();

        # --BEHAVIOR-- adminSearchPageCombo
        dotclear()->behavior()->call('adminSearchPageCombo', $qtype_combo);

        $this->qtype_combo = $qtype_combo->getArrayCopy();
        $q     = !empty($_REQUEST['q']) ? $_REQUEST['q'] : (!empty($_REQUEST['qx']) ? $_REQUEST['qx'] : null);
        $qtype = !empty($_REQUEST['qtype']) ? $_REQUEST['qtype'] : 'p';
        if (!empty($q) && !in_array($qtype, $this->qtype_combo)) {
            $qtype = 'p';
        }

        $page = !empty($_GET['page']) ? max(1, (integer) $_GET['page']) : 1;
        $nb = dotclear()->userpref->getUserFilters('search', 'nb');
        if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
            $nb = (integer) $_GET['nb'];
        }

        $this->args = ['q' => $q, 'qtype' => $qtype, 'page' => $page, 'nb' => $nb];

        # --BEHAVIOR-- adminSearchPageHead
        $starting_scripts = $q ? dotclear()->behavior()->call('adminSearchPageHead', $this->args) : '';

        if ($q) {

            # --BEHAVIOR-- adminSearchPageProcess
            dotclear()->behavior()->call('adminSearchPageProcess', $this->args);
        }

        # Page setup
        $this
            ->setPageTitle(__('Search'))
            ->setPageHelp('core_search')
            ->setPageHead($starting_scripts)
            ->setPageBreadcrumb([
                    Html::escapeHTML(dotclear()->blog->name) => '',
                    __('Search')                        => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo
        '<form action="' . dotclear()->adminurl->get('admin.search') . '" method="get" role="search">' .
        '<div class="fieldset"><h3>' . __('Search options') . '</h3>' .
        '<p><label for="q">' . __('Query:') . ' </label>' .
        Form::field('q', 30, 255, Html::escapeHTML($this->args['q'])) . '</p>' .
        '<p><label for="qtype">' . __('In:') . '</label> ' .
        Form::combo('qtype', $this->qtype_combo, $this->args['qtype']) . '</p>' .
        '<p><input type="submit" value="' . __('Search') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        Form::hidden('handler', 'admin.search') .
        '</p>' .
        '</div>' .
        '</form>';

        if ($this->args['q'] && !dotclear()->error()->flag()) {
            ob_start();

            # --BEHAVIOR-- adminSearchPageDisplay
            dotclear()->behavior()->call('adminSearchPageDisplay', $this->args);

            $res = ob_get_contents();
            ob_end_clean();
            echo $res ?: '<p>' . __('No results found') . '</p>';
        }
    }

    public static function typeCombo(ArrayObject $combo)
    {
        $combo[__('Search in entries')]  = 'p';
        $combo[__('Search in comments')] = 'c';
    }

    public static function pageHead(array $args)
    {
        if ($args['qtype'] == 'p') {
            return static::jsLoad('js/_posts_list.js');
        } elseif ($args['qtype'] == 'c') {
            return static::jsLoad('js/_comments.js');
        }
    }

    public static function processPosts(array $args)
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
            self::$count   = (int) dotclear()->blog->getPosts($params, true)->f(0);
            self::$list    = new PostCatalog(dotclear()->blog->getPosts($params), self::$count);
            self::$actions = new PostAction(dotclear()->adminurl->get('admin.search'), $args);
            if (self::$actions->getPagePrepend()) {
                return;
            }
        } catch (\Exception $e) {
            dotclear()->error($e->getMessage());
        }
    }

    public static function displayPosts(array $args)
    {
        if ($args['qtype'] != 'p' || self::$count === null) {
            return null;
        }

        if (self::$count > 0) {
            printf('<h3>' . __('one result', __('%d results'), self::$count) . '</h3>', self::$count);
        }

        self::$list->display($args['page'], $args['nb'],
            '<form action="' . dotclear()->adminurl->get('admin.search') . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
            Form::combo('action', self::$actions->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            dotclear()->formNonce() .
            preg_replace('/%/','%%', self::$actions->getHiddenFields()) .
            '</div>' .
            '</form>'
        );
    }

    public static function processComments(array $args)
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
            self::$count   = (int) dotclear()->blog->getComments($params, true)->f(0);
            self::$list    = new CommentCatalog(dotclear()->blog->getComments($params), self::$count);
            self::$actions = new CommentAction(dotclear()->adminurl->get('admin.search'), $args);
            if (self::$actions->getPagePrepend()) {
                return;
            }
        } catch (\Exception $e) {
            dotclear()->error($e->getMessage());
        }
    }

    public static function displayComments(array $args)
    {
        if ($args['qtype'] != 'c' || self::$count === null) {
            return null;
        }

        if (self::$count > 0) {
            printf('<h3>' . __('one comment found', __('%d comments found'), self::$count) . '</h3>', self::$count);
        }

        self::$list->display($args['page'], $args['nb'],
            '<form action="' . dotclear()->adminurl->get('admin.search') . '" method="post" id="form-comments">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
            Form::combo('action', self::$actions->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            dotclear()->formNonce() .
            preg_replace('/%/','%%', self::$actions->getHiddenFields()) .
            '</div>' .
            '</form>'
        );
    }
}
