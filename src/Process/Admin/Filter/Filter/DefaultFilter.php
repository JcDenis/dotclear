<?php
/**
 * @class Dotclear\Process\Admin\Filter\Filter\DefaultFilter
 * @brief Admin filter
 *
 * Dotclear utility class that provides reuseable list filter
 * Should be used with Filter
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

namespace Dotclear\Process\Admin\Filter\Filter;

use Dotclear\Exception\AdminException;
use Dotclear\Html\Form\Select as FormSelect;
use Dotclear\Html\Form\Label as FormLabel;
use Dotclear\Html\Form\Input as FormInput;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class DefaultFilter
{
    /** @var    array   The filter properties */
    protected $properties = [
        'id'      => '',
        'value'   => null,
        'form'    => 'none',
        'prime'   => false,
        'title'   => '',
        'options' => [],
        'html'    => '',
        'params'  => []
    ];

    /**
     * Constructs a new filter.
     *
     * @param   string  $id     The filter id
     * @param   mixed   $value  The filter value
     */
    public function __construct(string $id, mixed $value = null)
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            throw new AdminException('not a valid id');
        }
        $this->properties['id']    = $id;
        $this->properties['value'] = $value;
    }

    /**
     * Magic isset filter properties
     *
     * @param   string  $property   The property
     *
     * @return  bool                Is set
     */
    public function __isset(string $property): bool
    {
        return isset($this->properties[$property]);
    }

    /**
     * Magic get
     *
     * @param   string  $property   The property
     *
     * @return  mixed               Property
     */
    public function __get(string $property): mixed
    {
        return $this->get($property);
    }

    /**
     * Get a filter property
     *
     * @param   string  $property   The property
     *
     * @return  mixed               The value
     */
    public function get(string $property): mixed
    {
        return $this->properties[$property] ?? null;
    }

    /**
     * Magic set
     *
     * @param   string          $property   The property
     * @param   mixed           $value      The value
     *
     * @return  DefaultFilter               The filter instance
     */
    public function __set(string $property, mixed $value)
    {
        return $this->set($property, $value);
    }

    /**
     * Set a property value
     *
     * @param   string          $property   The property
     * @param   mixed           $value      The value
     *
     * @return  DefaultFilter               The filter instance
     */
    public function set(string $property, mixed $value): DefaultFilter
    {
        if (isset($this->properties[$property]) && method_exists($this, $property)) {
            return call_user_func([$this, $property], $value);
        }

        return $this;
    }

    /**
     * Set filter form type
     *
     * @param   string          $type   The type
     *
     * @return  DefaultFilter           The filter instance
     */
    public function form(string $type): DefaultFilter
    {
        if (in_array($type, ['none', 'input', 'select', 'html'])) {
            $this->properties['form'] = $type;
        }

        return $this;
    }

    /**
     * Set filter form title
     *
     * @param  string           $title  The title
     *
     * @return  DefaultFilter           The filter instance
     */
    public function title(string $title): DefaultFilter
    {
        $this->properties['title'] = $title;

        return $this;
    }

    /**
     * Set filter form options
     *
     * If filter form is a select box, this is the select options
     *
     * @param   array           $options    The options
     * @param   bool            $set_form   Auto set form type
     *
     * @return  DefaultFilter               The filter instance
     */
    public function options(array $options, bool $set_form = true): DefaultFilter
    {
        $this->properties['options'] = $options;
        if ($set_form) {
            $this->form('select');
        }

        return $this;
    }

    /**
     * Set filter value
     *
     * @param   mixed           $value  The value
     *
     * @return  DefaultFilter           The filter instance
     */
    public function value(mixed $value): DefaultFilter
    {
        $this->properties['value'] = $value;

        return $this;
    }

    /**
     * Set filter column in form
     *
     * @param   bool            $prime  First column
     *
     * @return  DefaultFilter           The filter instance
     */
    public function prime(bool $prime): DefaultFilter
    {
        $this->properties['prime'] = $prime;

        return $this;
    }

    /**
     * Set filter html contents
     *
     * @param   string          $contents   The contents
     * @param   bool            $set_form   Auto set form type
     *
     * @return  DefaultFilter               The filter instance
     */
    public function html(string $contents, bool $set_form = true): DefaultFilter
    {
        $this->properties['html'] = $contents;
        if ($set_form) {
            $this->form('html');
        }

        return $this;
    }

    /**
     * Set filter param (list query param)
     *
     * @param   string|null     $name   The param name
     * @param   mixed           $value  The param value
     *
     * @return  DefaultFilter           The filter instance
     */
    public function param(?string $name = null, mixed $value = null): DefaultFilter
    {
        # filter id as param name
        if (null === $name) {
            $name = $this->properties['id'];
        }
        # filter value as param value
        if (null === $value) {
            $value = function ($f) { return $f[0]; };
        }
        $this->properties['params'][] = [$name, $value];

        return $this;
    }

    /**
     * Parse the filter properties
     *
     * Only input and select forms are parsed
     */
    public function parse(): void
    {
        # form select
        if ($this->form == 'select') {
            # _GET value
            if ($this->value === null) {
                $get = $_GET[$this->id] ?? '';
                if ($get === '' || !in_array($get, $this->options, true)) {
                    $get = '';
                }
                $this->value($get);
            }
            # HTML field
            $select = (new FormSelect($this->id))
                ->default($this->value)
                ->items($this->options);

            $label = (new FormLabel($this->title, 2, $this->id))
                ->class('ib');

            $this->html($label->render($select->render()), false);

        # form input
        } elseif ($this->form == 'input') {
            # _GET value
            if ($this->value === null) {
                $this->value(!empty($_GET[$this->id]) ? $_GET[$this->id] : '');
            }
            # HTML field
            $input = (new FormInput($this->id))
                ->size(20)
                ->maxlength(255)
                ->value($this->value);

            $label = (new FormLabel($this->title, 2, $this->id))
                ->class('ib');

            $this->html($label->render($input->render()), false);
        }
    }
}
