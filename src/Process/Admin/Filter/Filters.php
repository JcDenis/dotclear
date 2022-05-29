<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter;

// Dotclear\Process\Admin\Filter\Filters
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;

/**
 * Generic class for admin list filters form.
 *
 * @ingroup  Admin Filter
 */
class Filters
{
    /**
     * @var array<string,Filter> $filters
     *                           Filters objects
     */
    protected $filters = [];

    /**
     * @var bool $show
     *           Show filter indicator
     */
    protected $show = false;

    /**
     * @var bool $has_user_pref
     *           Has user preferences
     */
    protected $has_user_pref = false;

    /**
     * Constructs a new instance.
     *
     * @param string      $type    The filter form main id
     * @param FilterStack $filters The filter stack
     */
    public function __construct(protected readonly string $type, FilterStack $filters = null)
    {
        $this->parseOptions();

        if (null !== $filters) {
            // --BEHAVIOR-- adminFiltersAddStack, string, FilterStack
            App::core()->behavior()->call('adminFiltersAddStack', $this->type, $filters);

            foreach ($filters->dump() as $filter) {
                $this->add(filter: $filter);
            }
        }
    }

    /**
     * Parse _GET user pref options (sortby, order, nb).
     */
    protected function parseOptions(): void
    {
        $options = App::core()->listoption()->getUserFiltersType($this->type);
        if (!empty($options)) {
            $this->has_user_pref = true;
        }

        if (!empty($options[1])) {
            $this->filters['sortby'] = new Filter(id: 'sortby', value: App::core()->listoption()->getUserFiltersSortby($this->type));
            $this->filters['sortby']->options(options: $options[1]);

            if (!GPC::get()->empty('sortby')
                && in_array(GPC::get()->string('sortby'), $options[1], true)
                && App::core()->listoption()->getUserFiltersSortby($this->type) != GPC::get()->string('sortby')
            ) {
                $this->show(show: true);
                $this->filters['sortby']->value(value: GPC::get()->string('sortby'));
            }
        }
        if (!empty($options[3])) {
            $this->filters['order'] = new Filter(id: 'order', value: App::core()->listoption()->getUserFiltersOrder($this->type));
            $this->filters['order']->options(options: App::core()->combo()->getOrderCombo());

            if (!GPC::get()->empty('order')
                && in_array(GPC::get()->string('order'), App::core()->combo()->getOrderCombo(), true)
                && App::core()->listoption()->getUserFiltersOrder($this->type) != GPC::get()->string('order')
            ) {
                $this->show(show: true);
                $this->filters['order']->value(value: GPC::get()->string('order'));
            }
        }
        if (!empty($options[4])) {
            $this->filters['nb'] = new Filter(id: 'nb', value: App::core()->listoption()->getUserFiltersNb($this->type));
            $this->filters['nb']->title(title: $options[4][0]);

            if (0 < GPC::get()->int('nb')
                && GPC::get()->int('nb') != App::core()->listoption()->getUserFiltersNb($this->type)
            ) {
                $this->show(show: true);
                $this->filters['nb']->value(value: GPC::get()->int('nb'));
            }
        }
    }

    /**
     * Get filters key/value pairs.
     *
     * @param bool $escape Escape widlcard %
     * @param bool $ui     Limit to filters with ui
     *
     * @return array The filters
     */
    public function values(bool $escape = false, bool $ui = false): array
    {
        $res = [];
        foreach ($this->filters as $id => $filter) {
            if ($ui) {
                if (in_array($id, ['sortby', 'order', 'nb']) || '' != $filter->get(key: 'html')) {
                    $res[$id] = $filter->get(key: 'value');
                }
            } else {
                $res[$id] = $filter->get(key: 'value');
            }
        }

        return $escape ? preg_replace('/%/', '%%', $res) : $res;
    }

    /**
     * Get a filter value.
     *
     * @param string $id      The filter id
     * @param mixed  $default The value to return if not set
     *
     * @return mixed The filter value
     */
    public function value(string $id, mixed $default = null): mixed
    {
        return isset($this->filters[$id]) ? $this->filters[$id]->get(key: 'value') : $default;
    }

    /**
     * @see self::value()
     *
     * @param string $id The filter ID
     *
     * @return mixed The filter value
     */
    public function get(string $id): mixed
    {
        return $this->value(id: $id);
    }

    /**
     * Update a filter value.
     *
     * @param string $id    The filter ID
     * @param mixed  $value The filter value
     */
    public function set(string $id, mixed $value): void
    {
        if (isset($this->filters[$id])) {
            $this->filters[$id]->value(value: $value);
        }
    }

    /**
     * Add a filter.
     *
     * @param null|Filter $filter The filter
     */
    public function add(?Filter $filter): void
    {
        // empty filter (ex: do not show form if there are no categories on a blog)
        if (null === $filter) {
            return;
        }

        // parse _GET values and create html forms
        $filter->parse();

        // set key/value pair
        $this->filters[$filter->get(key: 'id')] = $filter;

        // has contents
        if ('' != $filter->get(key: 'html') && 'none' != $filter->get(key: 'form')) {
            // not default value = show filters form
            $this->show(show: '' !== $filter->get(key: 'value'));
        }
    }

    /**
     * Remove a filter.
     *
     * @param string $id The filter id
     *
     * @return bool The success
     */
    public function remove(string $id): bool
    {
        if (array_key_exists($id, $this->filters)) {
            unset($this->filters[$id]);

            return true;
        }

        return false;
    }

    /**
     * Get list query params.
     *
     * @return Param The query params
     */
    public function params(): Param
    {
        $filters = $this->values();

        $param = new Param();

        if (!empty($filters['sortby']) && !empty($filters['order'])) {
            $param->set('order', $filters['sortby'] . ' ' . $filters['order']);
        }

        foreach ($this->filters as $filter) {
            if ('' !== $filter->get(key: 'value')) {
                $filters[0] = $filter->get(key: 'value');
                foreach ($filter->get(key: 'params') as $p) {
                    if (is_callable($p[1])) {
                        $p[1] = call_user_func($p[1], $filters);
                    }

                    if (in_array($p[0], ['sql', 'join', 'from', 'where', 'columns'])) {
                        $param->push($p[0], $p[1]);
                    } else {
                        $param->set($p[0], $p[1]);
                    }
                }
            }
        }

        return $param;
    }

    /**
     * Show foldable filters form.
     *
     * @param bool $show Force to show filter form
     *
     * @return bool Show filter form
     */
    public function show(bool $show = false): bool
    {
        if (true === $show) {
            $this->show = true;
        }

        return $this->show;
    }

    /**
     * Get JS filters foldable form control.
     *
     * @param string $url The filter reset URL
     *
     * @return string The HTML JS code
     */
    public function js(string $url = ''): string
    {
        $js = [
            'show_filters'      => $this->show(),
            'filter_posts_list' => __('Show filters and display options'),
            'cancel_the_filter' => __('Cancel filters and display options'),
            'filter_reset_url'  => $url ?: App::core()->adminurl()->get(App::core()->adminurl()->called()),
        ];

        return
            App::core()->resource()->json('filter_controls', $js) .
            App::core()->resource()->json('filter_options', ['auto_filter' => App::core()->user()->preference()->get('interface')->get('auto_filter')]) .
            App::core()->resource()->load('filter-controls.js');
    }

    /**
     * Echo filter form.
     *
     * @param array|string $adminurl The registered adminurl
     * @param string       $append   The extra contents
     */
    public function display(array|string $adminurl, string $append = ''): void
    {
        $tab = '';
        if (is_array($adminurl)) {
            $tab      = $adminurl[1];
            $adminurl = $adminurl[0];
        }

        echo '<form action="' . App::core()->adminurl()->get($adminurl) . $tab . '" method="get" id="filters-form">' .
        '<h3 class="out-of-screen-if-js">' . __('Show filters and display options') . '</h3>' .

        '<div class="table">';

        $prime = true;
        $cols  = [];
        foreach ($this->filters as $filter) {
            if (in_array($filter->get(key: 'id'), ['sortby', 'order', 'nb'])) {
                continue;
            }
            if ('' != $filter->get(key: 'html')) {
                $cols[$filter->get(key: 'prime') ? 1 : 0][$filter->get(key: 'id')] = sprintf('<p>%s</p>', $filter->get(key: 'html'));
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
            echo '<div class="cell filters-options">' .
            '<h4>' . __('Display options') . '</h4>';

            if (isset($this->filters['sortby'])) {
                $label = new Form\Label(__('Order by:'), Form\Label::OUTSIDE_LABEL_BEFORE, 'sortby');
                $label->set('class', 'ib');

                $select = new Form\Select('sortby');
                $select->set('default', $this->filters['sortby']->get(key: 'value'));
                $select->set('items', $this->filters['sortby']->get(key: 'options'));

                echo sprintf(
                    '<p>%s</p>',
                    $label->render($select->render())
                );
            }
            if (isset($this->filters['order'])) {
                $label = new Form\Label(__('Sort:'), Form\Label::OUTSIDE_LABEL_BEFORE, 'order');
                $label->set('class', 'ib');

                $select = new Form\Select('order');
                $select->set('default', $this->filters['order']->get(key: 'value'));
                $select->set('items', $this->filters['order']->get(key: 'options'));

                echo sprintf(
                    '<p>%s</p>',
                    $label->render($select->render())
                );
            }
            if (isset($this->filters['nb'])) {
                $label = new Form\Label($this->filters['nb']->get(key: 'title'), Form\Label::INSIDE_TEXT_AFTER, 'nb');
                $label->set('class', 'classic');

                $number = new Form\Number('nb');
                $number->set('min', 0);
                $number->set('max', 999);
                $number->set('value', $this->filters['nb']->get(key: 'value'));

                echo sprintf(
                    '<p><span class="label ib">' . __('Show') . '</span> %s</p>',
                    $label->render($number->render())
                );
            }

            if ($this->has_user_pref) {
                echo Form::hidden('filters-options-id', $this->type) .
                '<p class="hidden-if-no-js"><a href="#" id="filter-options-save">' . __('Save current options') . '</a></p>';
            }
            echo '</div>';
        }

        echo '</div>' .
        '<p><input type="submit" value="' . __('Apply filters and display options') . '" />' .
        Form::hidden(['handler'], $adminurl) .

        $append .

        '<br class="clear" /></p>' . // Opera sucks
        '</form>';
    }

    // / @name Common filters methods
    // @{
    /**
     * Common default input field.
     *
     * @param string      $id    The form id
     * @param string      $title The form name
     * @param null|string $param The form parameters
     */
    public function getInputFilter(string $id, string $title, ?string $param = null): Filter
    {
        $filter = new Filter(id: $id);
        $filter->param(name: $param ?: $id);
        $filter->form(type: 'input');
        $filter->title(title: $title);

        return $filter;
    }

    /**
     * Common default select field.
     *
     * @param string      $id      The form id
     * @param string      $title   The form title
     * @param array       $options The form options
     * @param null|string $param   The form parameters
     */
    public function getSelectFilter(string $id, string $title, array $options, ?string $param = null): ?Filter
    {
        if (empty($options)) {
            return null;
        }

        $filter = new Filter(id: $id);
        $filter->param(name: $param ?: $id);
        $filter->title(title: $title);
        $filter->options(options: $options);

        return $filter;
    }

    /**
     * Common page filter (no field).
     *
     * @param string $id The id
     */
    public function getPageFilter(string $id = 'page'): Filter
    {
        $filter = new Filter(id: $id);
        $filter->value(value: !GPC::get()->empty($id) ? max(1, GPC::get()->int($id)) : 1);
        $filter->param(name: 'limit', value: fn ($f) => [(($f[0] - 1) * $f['nb']), $f['nb']]);

        return $filter;
    }

    /**
     * Common search field.
     */
    public function getSearchFilter(): Filter
    {
        $filter = new Filter(id: 'q');
        $filter->param(name: 'q', value: fn ($f) => $f['q']);
        $filter->form(type: 'input');
        $filter->title(title: __('Search:'));
        $filter->prime(prime: true);

        return $filter;
    }
    // @}
}
