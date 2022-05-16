<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter;

// Dotclear\Process\Admin\Filter\Filter
use Dotclear\App;
use Dotclear\Process\Admin\Filter\Filter\DefaultFilter;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;

/**
 * Generic class for admin list filters form.
 *
 * @ingroup  Admin Filter
 */
class Filter extends Filters
{
    /**
     * @var array<string,DefaultFilter> $filters
     *                                  Filters objects
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
     * @param string $type The filter form main id
     */
    public function __construct(protected string $type)
    {
        $this->parseOptions();
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
            $this->filters['sortby'] = DefaultFilter::init('sortby', App::core()->listoption()->getUserFiltersSortby($this->type))
                ->options($options[1])
            ;

            if (!empty($_GET['sortby'])
                && in_array($_GET['sortby'], $options[1], true)
                && App::core()->listoption()->getUserFiltersSortby($this->type) != $_GET['sortby']
            ) {
                $this->show(true);
                $this->filters['sortby']->value($_GET['sortby']);
            }
        }
        if (!empty($options[3])) {
            $this->filters['order'] = DefaultFilter::init('order', App::core()->listoption()->getUserFiltersOrder($this->type))
                ->options(App::core()->combo()->getOrderCombo())
            ;

            if (!empty($_GET['order'])
                && in_array($_GET['order'], App::core()->combo()->getOrderCombo(), true)
                && App::core()->listoption()->getUserFiltersOrder($this->type) != $_GET['order']
            ) {
                $this->show(true);
                $this->filters['order']->value($_GET['order']);
            }
        }
        if (!empty($options[4])) {
            $this->filters['nb'] = DefaultFilter::init('nb', App::core()->listoption()->getUserFiltersNb($this->type))
                ->title($options[4][0])
            ;

            if (!empty($_GET['nb'])
                && (int) $_GET['nb'] > 0
                && (int) $_GET['nb'] != App::core()->listoption()->getUserFiltersNb($this->type)
            ) {
                $this->show(true);
                $this->filters['nb']->value((int) $_GET['nb']);
            }
        }
    }

    /**
     * Get filters key/value pairs.
     *
     * @param bool $escape  Escape widlcard %
     * @param bool $ui_only Limit to filters with ui
     *
     * @return array The filters
     */
    public function values(bool $escape = false, bool $ui_only = false): array
    {
        $res = [];
        foreach ($this->filters as $id => $filter) {
            if ($ui_only) {
                if (in_array($id, ['sortby', 'order', 'nb']) || '' != $filter->get('html')) {
                    $res[$id] = $filter->get('value');
                }
            } else {
                $res[$id] = $filter->get('value');
            }
        }

        return $escape ? preg_replace('/%/', '%%', $res) : $res;
    }

    /**
     * Get a filter value.
     *
     * @param string $id        The filter id
     * @param mixed  $undefined The value to return if not set
     *
     * @return mixed The filter value
     */
    public function value(string $id, mixed $undefined = null): mixed
    {
        return isset($this->filters[$id]) ? $this->filters[$id]->get('value') : $undefined;
    }

    /**
     * @see self::value()
     */
    public function get(string $id): mixed
    {
        return $this->value($id);
    }

    /**
     * Update a filter value.
     */
    public function set(string $id, mixed $value, mixed $undefined = null): mixed
    {
        return isset($this->filters[$id]) ? $this->filters[$id]->value($value) : $undefined;
    }

    /**
     * Add filters.
     *
     * @param FiltersStack $filters The stack of filters
     */
    public function addStack(FiltersStack $filters): void
    {
        foreach ($filters->dump() as $filter) {
            $this->add($filter);
        }
    }

    /**
     * Add a filter.
     *
     * @param null|DefaultFilter $filter The filter
     *
     * @return mixed The filter value
     */
    public function add(?DefaultFilter $filter): mixed
    {
        // empty filter (ex: do not show form if there are no categories on a blog)
        if (null === $filter) {
            return null;
        }

        // parse _GET values and create html forms
        $filter->parse();

        // set key/value pair
        $this->filters[$filter->get('id')] = $filter;

        // has contents
        if ('' != $filter->get('html') && 'none' != $filter->get('form')) {
            // not default value = show filters form
            $this->show('' !== $filter->get('value'));
        }

        return $filter->get('value');
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
     * @return array The query params
     */
    public function params(): array
    {
        $filters = $this->values();

        $params = [
            'from'    => '',
            'where'   => '',
            'sql'     => '',
            'columns' => [],
        ];

        if (!empty($filters['sortby']) && !empty($filters['order'])) {
            $params['order'] = $filters['sortby'] . ' ' . $filters['order'];
        }

        foreach ($this->filters as $filter) {
            if ('' !== $filter->get('value')) {
                $filters[0] = $filter->get('value');
                foreach ($filter->get('params') as $p) {
                    if (is_callable($p[1])) {
                        $p[1] = call_user_func($p[1], $filters);
                    }

                    if (in_array($p[0], ['from', 'where', 'sql'])) {
                        $params[$p[0]] .= $p[1];
                    } elseif ('columns' == $p[0] && is_array($p[1])) {
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
     * Show foldable filters form.
     *
     * @param bool $set Force to show filter form
     *
     * @return bool Show filter form
     */
    public function show(bool $set = false): bool
    {
        if (true === $set) {
            $this->show = true;
        }

        return $this->show;
    }

    /**
     * Get js filters foldable form control.
     *
     * @param string $reset_url The filter reset url
     *
     * @return string The HTML JS code
     */
    public function js(string $reset_url = ''): string
    {
        $js = [
            'show_filters'      => $this->show(),
            'filter_posts_list' => __('Show filters and display options'),
            'cancel_the_filter' => __('Cancel filters and display options'),
            'filter_reset_url'  => $reset_url ?: App::core()->adminurl()->get(App::core()->adminurl()->called()),
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
     * @param string       $extra    The extra contents
     */
    public function display(array|string $adminurl, string $extra = ''): void
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
            if (in_array($filter->get('id'), ['sortby', 'order', 'nb'])) {
                continue;
            }
            if ('' != $filter->get('html')) {
                $cols[$filter->get('prime') ? 1 : 0][$filter->get('id')] = sprintf('<p>%s</p>', $filter->get('html'));
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
                $label = Form\Label::init(__('Order by:'), Form\Label::OUTSIDE_LABEL_BEFORE, 'sortby')
                    ->set('class', 'ib')
                ;

                $select = Form\Select::init('sortby')
                    ->set('default', $this->filters['sortby']->get('value'))
                    ->set('items', $this->filters['sortby']->get('options'))
                ;

                echo sprintf(
                    '<p>%s</p>',
                    $label->render($select->render())
                );
            }
            if (isset($this->filters['order'])) {
                $label = Form\Label::init(__('Sort:'), Form\Label::OUTSIDE_LABEL_BEFORE, 'order')
                    ->set('class', 'ib')
                ;

                $select = Form\Select::init('order')
                    ->set('default', $this->filters['order']->get('value'))
                    ->set('items', $this->filters['order']->get('options'))
                ;

                echo sprintf(
                    '<p>%s</p>',
                    $label->render($select->render())
                );
            }
            if (isset($this->filters['nb'])) {
                $label = Form\Label::init($this->filters['nb']->get('title'), Form\Label::INSIDE_TEXT_AFTER, 'nb')
                    ->set('class', 'classic')
                ;

                $number = Form\Number::init('nb')
                    ->set('min', 0)
                    ->set('max', 999)
                    ->set('value', $this->filters['nb']->get('value'))
                ;

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

        $extra .

        '<br class="clear" /></p>' . // Opera sucks
        '</form>';
    }
}
