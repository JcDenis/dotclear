<?php
/**
 * @class Dotclear\Admin\Filter
 * @brief Generic class for admin list filters form
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @since 2.20
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;
use Dotclear\Admin\UserPref;
use Dotclear\Admin\Combos;
use Dotclear\Admin\Filter\DefaultFilter;

use Dotclear\Html\Html;
use Dotclear\Html\Form;
use Dotclear\Html\Form\Select;
use Dotclear\Html\Form\Label;
use Dotclear\Html\Form\Number;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Filter
{
    /** @var Core core instance */
    protected $core;

    /** @var string Filter form type (main id) */
    protected $type;

    /** @var array Filters objects */
    protected $filters = [];

    /** @var boolean Show filter indicator */
    protected $show = false;

    /** @var boolean Has user preferences */
    protected $has_user_pref = false;

    /**
     * Constructs a new instance.
     *
     * @param Core $core  core instance
     * @param string $type  The filter form main id
     */
    public function __construct(Core $core, string $type)
    {
        $this->core = &$core;
        $this->type = $type;

        $this->parseOptions();
    }

    /**
     * Get user defined filter options (sortby, order, nb)
     *
     * @param   string   $option     The option
     *
     * @return  mixed                User option
     */
    public function userOptions(?string $option = null)
    {
        return UserPref::getUserFilters($this->type, $option);
    }

    /**
     * Parse _GET user pref options (sortby, order, nb)
     */
    protected function parseOptions()
    {
        $options = UserPref::getUserFilters($this->type);
        if (!empty($options)) {
            $this->has_user_pref = true;
        }

        if (!empty($options[1])) {
            $this->filters['sortby'] = new DefaultFilter('sortby', $this->userOptions('sortby'));
            $this->filters['sortby']->options($options[1]);

            if (!empty($_GET['sortby'])
                && in_array($_GET['sortby'], $options[1], true)
                && $_GET['sortby'] != $this->userOptions('sortby')
            ) {
                $this->show(true);
                $this->filters['sortby']->value($_GET['sortby']);
            }
        }
        if (!empty($options[3])) {
            $this->filters['order'] = new DefaultFilter('order', $this->userOptions('order'));
            $this->filters['order']->options(Combos::getOrderCombo());

            if (!empty($_GET['order'])
                && in_array($_GET['order'], Combos::getOrderCombo(), true)
                && $_GET['order'] != $this->userOptions('order')
            ) {
                $this->show(true);
                $this->filters['order']->value($_GET['order']);
            }
        }
        if (!empty($options[4])) {
            $this->filters['nb'] = new DefaultFilter('nb', $this->userOptions('nb'));
            $this->filters['nb']->title($options[4][0]);

            if (!empty($_GET['nb'])
                && (integer) $_GET['nb'] > 0
                && (integer) $_GET['nb'] != $this->userOptions('nb')
            ) {
                $this->show(true);
                $this->filters['nb']->value((integer) $_GET['nb']);
            }
        }
    }

    /**
     * Get filters key/value pairs
     *
     * @param  boolean $escape  Escape widlcard %
     * @param  boolean $ui_only Limit to filters with ui
     *
     * @return array            The filters
     */
    public function values($escape = false, $ui_only = false)
    {
        $res = [];
        foreach ($this->filters as $id => $filter) {
            if ($ui_only) {
                if (in_array($id, ['sortby', 'order', 'nb']) || $filter->html != '') {
                    $res[$id] = $filter->value;
                }
            } else {
                $res[$id] = $filter->value;
            }
        }

        return $escape ? preg_replace('/%/', '%%', $res) : $res;
    }

    /**
     * Get a filter value
     *
     * @param  string $id The filter id
     * @param  string $id The filter value if not exists
     *
     * @return mixed      The filter value
     */
    public function value(string $id, $undefined = null)
    {
        return isset($this->filters[$id]) ? $this->filters[$id]->value : $undefined;
    }

    /**
     * Magic get filter value
     *
     * @param  string   $id     The filter id
     *
     * @return mixed            The filter value
     */
    public function __get($id)
    {
        return $this->value($id);
    }

    /**
     * Add filter(s)
     *
     * @param array|string|DefaultFilter|null   $filter     The filter(s) array or id or object
     * @param mixed                             $value      The filter value if $filter is id
     *
     * @return mixed                                        The filter value
     */
    public function add($filter = null, $value = null)
    {
        # empty filter (ex: do not show form if there are no categories on a blog)
        if (null === $filter) {
            return null;
        }

        # multiple filters
        if (is_array($filter)) {
            foreach ($filter as $f) {
                $this->add($f);
            }

            return null;
        }

        # simple filter
        if (is_string($filter)) {
            $filter = new DefaultFilter($filter, $value);
        }

        # not well formed filter or reserved id
        if (!($filter instanceof DefaultFilter) || $filter->id == '') {
            return null;
        }

        # parse _GET values and create html forms
        $filter->parse();

        # set key/value pair
        $this->filters[$filter->id] = $filter;

        # has contents
        if ($filter->html != '' && $filter->form != 'none') {
            # not default value = show filters form
            $this->show($filter->value !== '');
        }

        return $filter->value;
    }

    /**
     * Remove a filter
     *
     * @param  string $id   The filter id
     *
     * @return boolean      The success
     */
    public function remove(string $id)
    {
        if (array_key_exists($id, $this->filters)) {
            unset($this->filters[$id]);

            return true;
        }

        return false;
    }

    /**
     * Get list query params
     *
     * @return array    The query params
     */
    public function params()
    {
        $filters = $this->values();

        $params = [
            'from'    => '',
            'where'   => '',
            'sql'     => '',
            'columns' => []
        ];

        if (!empty($filters['sortby']) && !empty($filters['order'])) {
            $params['order'] = $filters['sortby'] . ' ' . $filters['order'];
        }

        foreach ($this->filters as $filter) {
            if ($filter->value !== '') {
                $filters[0] = $filter->value;
                foreach ($filter->params as $p) {
                    if (is_callable($p[1])) {
                        $p[1] = call_user_func($p[1], $filters);
                    }

                    if (in_array($p[0], ['from', 'where', 'sql'])) {
                        $params[$p[0]] .= $p[1];
                    } elseif ($p[0] == 'columns' && is_array($p[1])) {
                        $params['columns'] = array_merge($params['columns'], $p[1]);
                    } else {
                        $params[$p[0]] = $p[1];
                    }
                }
            }
        }

        return $params;
    }

    /**
     * Show foldable filters form
     *
     * @param  boolean  $set    Force to show filter form
     *
     * @return boolean          Show filter form
     */
    public function show($set = false): bool
    {
        if ($set === true) {
            $this->show = true;
        }

        return $this->show;
    }

    /**
     * Get js filters foldable form control
     *
     * @param string $reset_url     The filter reset url
     */
    public function js(string $reset_url = '')
    {
        $js   = [
            'show_filters'      => $this->show(),
            'filter_posts_list' => __('Show filters and display options'),
            'cancel_the_filter' => __('Cancel filters and display options'),
            'filter_reset_url'  => $reset_url ?? $this->core->adminurl->get($this->core->adminurl->called()),
        ];

        //return $var . Page::jsFilterControl($this->show());
        return
            Page::jsJson('filter_controls', $js) .
            Page::jsJson('filter_options', ['auto_filter' => $this->core->auth->user_prefs->interface->auto_filter]) .
            Page::jsLoad('js/filter-controls.js');

    }

    /**
     * Echo filter form
     *
     * @param  array|string     $adminurl   The registered adminurl
     * @param  string           $extra      The extra contents
     */
    public function display($adminurl, string $extra = '')
    {
        $tab = '';
        if (is_array($adminurl)) {
            $tab      = $adminurl[1];
            $adminurl = $adminurl[0];
        }

        echo
        '<form action="' . $this->core->adminurl->get($adminurl) . $tab . '" method="get" id="filters-form">' .
        '<h3 class="out-of-screen-if-js">' . __('Show filters and display options') . '</h3>' .

        '<div class="table">';

        $prime = true;
        $cols  = [];
        foreach ($this->filters as $filter) {
            if (in_array($filter->id, ['sortby', 'order', 'nb'])) {
                continue;
            }
            if ($filter->html != '') {
                $cols[$filter->prime ? 1 : 0][$filter->id] = sprintf('<p>%s</p>', $filter->html);
            }
        }
        sort($cols);
        foreach ($cols as $col) {
            echo sprintf(
                $prime ?
                    '<div class="cell"><h4>' . __('Filters') . '</h4>%s</div>' :
                    '<div class="cell filters-sibling-cell">%s</div>',
                implode('', $col)
            );
            $prime = false;
        }

        if (isset($this->filters['sortby']) || isset($this->filters['order']) || isset($this->filters['nb'])) {
            echo
            '<div class="cell filters-options">' .
            '<h4>' . __('Display options') . '</h4>';

            if (isset($this->filters['sortby'])) {
                $label = (new Form\Label(__('Order by:'), Form\Label::OUTSIDE_LABEL_BEFORE, 'sortby'))
                    ->class('ib');

                $select = (new Form\Select('sortby'))
                    ->default($this->filters['sortby']->value)
                    ->items($this->filters['sortby']->options);

                echo sprintf(
                    '<p>%s</p>',
                    $label->render($select->render())
                );
            }
            if (isset($this->filters['order'])) {
                $label = (new Form\Label(__('Sort:'), Form\Label::OUTSIDE_LABEL_BEFORE, 'order'))
                    ->class('ib');

                $select = (new Form\Select('order'))
                    ->default($this->filters['order']->value)
                    ->items($this->filters['order']->options);

                echo sprintf(
                    '<p>%s</p>',
                    $label->render($select->render())
                );
            }
            if (isset($this->filters['nb'])) {
                $label = (new Form\Label($this->filters['nb']->title, Form\Label::INSIDE_TEXT_AFTER, 'nb'))
                    ->class('classic');

                $number = (new Form\Number('nb'))
                    ->min(0)
                    ->max(999)
                    ->value($this->filters['nb']->value);

                echo sprintf(
                    '<p><span class="label ib">' . __('Show') . '</span> %s</p>',
                    $label->render($number->render())
                );
            }

            if ($this->has_user_pref) {
                echo
                Form::hidden('filters-options-id', $this->type) .
                '<p class="hidden-if-no-js"><a href="#" id="filter-options-save">' . __('Save current options') . '</a></p>';
            }
            echo
            '</div>';
        }

        echo
        '</div>' .
        '<p><input type="submit" value="' . __('Apply filters and display options') . '" />' .
        Form::hidden('handler', $adminurl) .

        $extra .

        '<br class="clear" /></p>' . //Opera sucks
        '</form>';
    }
}
