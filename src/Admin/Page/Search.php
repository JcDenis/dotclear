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

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;
use Dotclear\Admin\Userpref;
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
    protected static $count   = null;
    protected static $list    = null;
    protected static $actions = null;

    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->check('usage,contentadmin');

        $this->core->behaviors->add('adminSearchPageCombo', [__NAMESPACE__ . '\\Search','typeCombo']);
        $this->core->behaviors->add('adminSearchPageHead', [__NAMESPACE__ . '\\Search','pageHead']);
        // posts search
        $this->core->behaviors->add('adminSearchPageProcess', [__NAMESPACE__ . '\\Search','processPosts']);
        $this->core->behaviors->add('adminSearchPageDisplay', [__NAMESPACE__ . '\\Search','displayPosts']);
        // comments search
        $this->core->behaviors->add('adminSearchPageProcess', [__NAMESPACE__ . '\\Search','processComments']);
        $this->core->behaviors->add('adminSearchPageDisplay', [__NAMESPACE__ . '\\Search','displayComments']);

        $qtype_combo = new \ArrayObject();

        # --BEHAVIOR-- adminSearchPageCombo
        $this->core->behaviors->call('adminSearchPageCombo', $qtype_combo);

        $qtype_combo = $qtype_combo->getArrayCopy();
        $q     = !empty($_REQUEST['q']) ? $_REQUEST['q'] : (!empty($_REQUEST['qx']) ? $_REQUEST['qx'] : null);
        $qtype = !empty($_REQUEST['qtype']) ? $_REQUEST['qtype'] : 'p';
        if (!empty($q) && !in_array($qtype, $qtype_combo)) {
            $qtype = 'p';
        }

        $this->core->auth->user_prefs->addWorkspace('interface');
        $page = !empty($_GET['page']) ? max(1, (integer) $_GET['page']) : 1;
        $nb = UserPref::getUserFilters('search', 'nb');
        if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
            $nb = (integer) $_GET['nb'];
        }

        $args = ['q' => $q, 'qtype' => $qtype, 'page' => $page, 'nb' => $nb];

        # --BEHAVIOR-- adminSearchPageHead
        $starting_scripts = $q ? $this->core->behaviors->call('adminSearchPageHead', $args) : '';

        if ($q) {

            # --BEHAVIOR-- adminSearchPageProcess
            $this->core->behaviors->call('adminSearchPageProcess', $args);
        }

        $this->open(__('Search'), $starting_scripts,
            $this->breadcrumb(
                [
                    html::escapeHTML($core->blog->name) => '',
                    __('Search')                        => ''
                ])
        );

        echo
        '<form action="' . $this->core->adminurl->get('admin.search') . '" method="get" role="search">' .
        '<div class="fieldset"><h3>' . __('Search options') . '</h3>' .
        '<p><label for="q">' . __('Query:') . ' </label>' .
        Form::field('q', 30, 255, Html::escapeHTML($q)) . '</p>' .
        '<p><label for="qtype">' . __('In:') . '</label> ' .
        Form::combo('qtype', $qtype_combo, $qtype) . '</p>' .
        '<p><input type="submit" value="' . __('Search') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        Form::hidden('handler', 'admin.search') .
        '</p>' .
        '</div>' .
        '</form>';

        if ($q && !$this->core->error->flag()) {
            ob_start();

            # --BEHAVIOR-- adminSearchPageDisplay
            $this->core->behaviors->call('adminSearchPageDisplay', $args);

            $res = ob_get_contents();
            ob_end_clean();
            echo $res ?: '<p>' . __('No results found') . '</p>';
        }

        $this->helpBlock('core_search');
        $this->close();
    }

    public static function typeCombo(Core $core, \ArrayObject $combo)
    {
        $combo[__('Search in entries')]  = 'p';
        $combo[__('Search in comments')] = 'c';
    }

    public static function pageHead(Core $core, array $args)
    {
        if ($args['qtype'] == 'p') {
            return static::jsLoad('js/_posts_list.js');
        } elseif ($args['qtype'] == 'c') {
            return static::jsLoad('js/_comments.js');
        }
    }

    public static function processPosts(Core $core, array $args)
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
            self::$count   = (int) $core->blog->getPosts($params, true)->f(0);
            self::$list    = new PostCatalog($core, $core->blog->getPosts($params), self::$count);
            self::$actions = new PostAction($core, $core->adminurl->get('admin.search'), $args);
            if (self::$actions->process()) {
                return;
            }
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }

    public static function displayPosts(Core $core, array $args)
    {
        if ($args['qtype'] != 'p' || self::$count === null) {
            return null;
        }

        if (self::$count > 0) {
            printf('<h3>' . __('one result', __('%d results'), self::$count) . '</h3>', self::$count);
        }

        self::$list->display($args['page'], $args['nb'],
            '<form action="' . $core->adminurl->get('admin.search') . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
            Form::combo('action', self::$actions->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            $core->formNonce() .
            preg_replace('/%/','%%', self::$actions->getHiddenFields()) .
            '</div>' .
            '</form>'
        );
    }

    public static function processComments(Core $core, array $args)
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
            self::$count   = (int) $core->blog->getComments($params, true)->f(0);
            self::$list    = new CommentCatalog($core, $core->blog->getComments($params), self::$count);
            self::$actions = new CommentAction($core, $core->adminurl->get('admin.search'), $args);
            if (self::$actions->process()) {
                return;
            }
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }

    public static function displayComments(Core $core, array $args)
    {
        if ($args['qtype'] != 'c' || self::$count === null) {
            return null;
        }

        if (self::$count > 0) {
            printf('<h3>' . __('one comment found', __('%d comments found'), self::$count) . '</h3>', self::$count);
        }

        self::$list->display($args['page'], $args['nb'],
            '<form action="' . $core->adminurl->get('admin.search') . '" method="post" id="form-comments">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' . __('Selected comments action:') . '</label> ' .
            Form::combo('action', self::$actions->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" /></p>' .
            $core->formNonce() .
            preg_replace('/%/','%%', self::$actions->getHiddenFields()) .
            '</div>' .
            '</form>'
        );
    }
}
