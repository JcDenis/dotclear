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
use Dotclear\Exception\InvalidValueType;
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
    private $filters = [];

    /**
     * @var bool $show
     *           Show filter indicator
     */
    private $show = false;

    /**
     * @var bool $has_user_pref
     *           Has user preferences
     */
    private $has_user_pref = false;

    /**
     * Constructs a new instance.
     *
     * @param string      $id      The filter form main id
     * @param FilterStack $filters The filter stack
     */
    public function __construct(private string $id, FilterStack $filters = null)
    {
        $this->parseUserOptions();
        $this->addFilters(filters: $filters);
    }

    /**
     * Parse _GET user pref options (sortby, order, nb).
     */
    private function parseUserOptions(): void
    {
        $user_options = App::core()->listoption()->sort()->getGroup($this->getId());
        if (!empty($user_options)) {
            $this->has_user_pref = true;
        }

        $value = $user_options->getSortBy();
        if (null !== $value) {
            if (!GPC::get()->empty('sortby')
                && in_array(GPC::get()->string('sortby'), $user_options->combo, true)
                && GPC::get()->string('sortby') != $value
            ) {
                $this->setUnfolded();
                $value = GPC::get()->string('sortby');
            }

            $this->filters['sortby'] = new Filter(
                id: 'sortby',
                value: $value,
                options: $user_options->combo
            );
        }

        $value = $user_options->getSortOrder();
        if (null !== $value) {
            if (!GPC::get()->empty('order')
                && in_array(GPC::get()->string('order'), App::core()->combo()->getOrderCombo(), true)
                && GPC::get()->string('order') != $value
            ) {
                $this->setUnfolded();
                $value = GPC::get()->string('order');
            }
            $this->filters['order'] = new Filter(
                id: 'order',
                value: $value,
                options: App::core()->combo()->getOrderCombo()
            );
        }
        $value = $user_options->getSortLimit();
        if (null !== $value) {
            if (0 < GPC::get()->int('nb')
                && GPC::get()->int('nb') !== $value
            ) {
                $this->setUnfolded();
                $value = GPC::get()->int('nb');
            }

            $this->filters['nb'] = new Filter(
                id: 'nb',
                value: $value,
                title: $user_options->keyword
            );
        }
    }

    /**
     * Add filters.
     *
     * @param null|FilterStack $filters The filter stack instance
     *
     * @throws InvalidValueType Throws Error on non production env only
     */
    private function addFilters(?FilterStack $filters): void
    {
        if (null === $filters) {
            return;
        }

        if (App::core()->behavior('adminFiltersAddFilters')->count()) {
            $cloned_filters = clone $filters;

            // --BEHAVIOR-- adminFiltersAddStack, string, FilterStack
            App::core()->behavior('adminFiltersAddFilters')->call($this->getId(), $cloned_filters);

            if ($cloned_filters instanceof FilterStack) {
                $filters = $cloned_filters;

            // @phpstan-ignore-next-line (Failed to understand behavior)
            } elseif (!App::core()->production()) {
                throw new InvalidValueType('Invalid value type returned by behavior adminFiltersAddStack');
            }
            unset($cloned_filters);
        }

        foreach ($filters->dumpFilters() as $filter) {
            $this->addFilter(filter: $filter);
        }
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Get filters key/value pairs.
     *
     * @return array The filters
     */
    public function getValues(): array
    {
        $res = [];
        foreach ($this->filters as $id => $filter) {
            $res[$id] = $filter->getProperty(key: 'value');
        }

        return $res;
    }

    /**
     * Get escaped filters key/value pairs.
     *
     * @return array The filters
     */
    public function getEscapeValues(): array
    {
        return preg_replace('/%/', '%%', $this->getValues());
    }

    /**
     * Get filters key/value pairs having user interface (form).
     *
     * @return array The filters
     */
    public function getFormValues(): array
    {
        $res = [];
        foreach ($this->filters as $id => $filter) {
            if (in_array($id, ['sortby', 'order', 'nb']) || '' != $filter->getProperty(key: 'contents')) {
                $res[$id] = $filter->getProperty(key: 'value');
            }
        }

        return $res;
    }

    /**
     * Get a filter value.
     *
     * @param string $id      The filter id
     * @param mixed  $default The value to return if not set
     *
     * @return mixed The filter value
     */
    public function getValue(string $id, mixed $default = null): mixed
    {
        return isset($this->filters[$id]) ? $this->filters[$id]->getProperty(key: 'value') : $default;
    }

    /**
     * Update a filter value.
     *
     * @param string $id    The filter ID
     * @param mixed  $value The filter value
     */
    public function updateValue(string $id, mixed $value): void
    {
        if (isset($this->filters[$id])) {
            $this->filters[$id]->setValue(value: $value);
        }
    }

    /**
     * Add a filter.
     *
     * @param null|Filter $filter The filter
     */
    public function addFilter(?Filter $filter): void
    {
        // empty filter (ex: do not show form if there are no categories on a blog)
        if (null === $filter) {
            return;
        }

        // parse _GET values and create html forms
        $filter->parsePropertries();

        // set key/value pair
        $this->filters[$filter->getProperty(key: 'id')] = $filter;

        // has contents and not default value = show filters form
        if ('' != $filter->getProperty(key: 'contents') && 'none' != $filter->getProperty(key: 'type') && '' !== $filter->getProperty(key: 'value')) {
            $this->setUnfolded();
        }
    }

    /**
     * Remove a filter.
     *
     * @param string $id The filter id
     *
     * @return bool The success
     */
    public function removeFilter(string $id): bool
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
    public function getParams(): Param
    {
        $filters = $this->getValues();

        $param = new Param();

        if (!empty($filters['sortby']) && !empty($filters['order'])) {
            $param->set('order', $filters['sortby'] . ' ' . $filters['order']);
        }

        foreach ($this->filters as $filter) {
            if ('' !== $filter->getProperty(key: 'value')) {
                $filters[0] = $filter->getProperty(key: 'value');
                foreach ($filter->getProperty(key: 'params') as $p) {
                    if (!$p[0]) {
                        continue;
                    }
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
     * Check if filters form is unfold.
     *
     * @return bool True if it is unfolded
     */
    public function isUnfolded(): bool
    {
        return $this->show;
    }

    /**
     * Set filters form as unfolded.
     */
    public function setUnfolded(): void
    {
        $this->show = true;
    }

    /**
     * Get JS filters foldable form control.
     *
     * @param string $url The filter reset URL
     *
     * @return string The HTML JS code
     */
    public function getFoldableJSCode(string $url = ''): string
    {
        $js = [
            'show_filters'      => $this->isUnfolded(),
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
    public function displayHTMLForm(array|string $adminurl, string $append = ''): void
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
            if (in_array($filter->getProperty(key: 'id'), ['sortby', 'order', 'nb'])) {
                continue;
            }
            if ('' != $filter->getProperty(key: 'contents')) {
                $cols[$filter->getProperty(key: 'prime') ? 1 : 0][$filter->getProperty(key: 'id')] = sprintf('<p>%s</p>', $filter->getProperty(key: 'contents'));
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
                $select->set('default', $this->filters['sortby']->getProperty(key: 'value'));
                $select->set('items', $this->filters['sortby']->getProperty(key: 'options'));

                echo sprintf(
                    '<p>%s</p>',
                    $label->render($select->render())
                );
            }
            if (isset($this->filters['order'])) {
                $label = new Form\Label(__('Sort:'), Form\Label::OUTSIDE_LABEL_BEFORE, 'order');
                $label->set('class', 'ib');

                $select = new Form\Select('order');
                $select->set('default', $this->filters['order']->getProperty(key: 'value'));
                $select->set('items', $this->filters['order']->getProperty(key: 'options'));

                echo sprintf(
                    '<p>%s</p>',
                    $label->render($select->render())
                );
            }
            if (isset($this->filters['nb'])) {
                $label = new Form\Label($this->filters['nb']->getProperty(key: 'title'), Form\Label::INSIDE_TEXT_AFTER, 'nb');
                $label->set('class', 'classic');

                $number = new Form\Number('nb');
                $number->set('min', 0);
                $number->set('max', 999);
                $number->set('value', $this->filters['nb']->getProperty(key: 'value'));

                echo sprintf(
                    '<p><span class="label ib">' . __('Show') . '</span> %s</p>',
                    $label->render($number->render())
                );
            }

            if ($this->has_user_pref) {
                echo Form::hidden('filters-options-id', $this->getId()) .
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
     * @param null|string $name  The form parameters
     */
    public function getInputFilter(string $id, string $title, ?string $name = null): Filter
    {
        return new Filter(
            id: $id,
            params: [
                [$name ?: $id, null],
            ],
            type: 'input',
            title: $title
        );
    }

    /**
     * Common default select field.
     *
     * @param string      $id      The form id
     * @param string      $title   The form title
     * @param array       $options The form options
     * @param null|string $name    The form parameters
     */
    public function getSelectFilter(string $id, string $title, array $options, ?string $name = null): ?Filter
    {
        if (empty($options)) {
            return null;
        }

        return new Filter(
            id: $id,
            params: [
                [$name ?: $id, null],
            ],
            title: $title,
            options: $options
        );
    }

    /**
     * Common page filter (no field).
     *
     * @param string $id The id
     */
    public function getPageFilter(string $id = 'page'): Filter
    {
        return new Filter(
            id: $id,
            value: !GPC::get()->empty($id) ? max(1, GPC::get()->int($id)) : 1,
            params: [
                ['limit', fn ($f) => [(($f[0] - 1) * $f['nb']), $f['nb']]],
            ]
        );
    }

    /**
     * Common search field.
     */
    public function getSearchFilter(): Filter
    {
        return new Filter(
            id: 'q',
            type: 'input',
            title: __('Search:'),
            prime: true,
            params: [
                ['q', fn ($f) => $f['q']],
            ]
        );
    }
    // @}
}
