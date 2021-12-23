<?php
/**
 * @class Dotclear\Admin\Filter\DefaultFilter
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

namespace Dotclear\Admin\Filter;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Html\Form\Select as FormSelect;
use Dotclear\Html\Form\Label as FormLabel;
use Dotclear\Html\Form\Input as FormInput;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class DefaultFilter
{
    /** @var array The filter properties */
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
     * @param string    $id     The filter id
     * @param mixed     $value  The filter value
     */
    public function __construct(string $id, $value = null)
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
     * @param  string  $property    The property
     *
     * @return boolean              Is set
     */
    public function __isset(string $property)
    {
        return isset($this->properties[$property]);
    }

    /**
     * Magic get
     *
     * @param  string $property     The property
     *
     * @return mixed  Property
     */
    public function __get($property)
    {
        return $this->get($property);
    }

    /**
     * Get a filter property
     *
     * @param  string $property     The property
     *
     * @return mixed                The value
     */
    public function get(string $property)
    {
        return $this->properties[$property] ?? null;
    }

    /**
     * Magic set
     *
     * @param string $property  The property
     * @param mixed  $value     The value
     *
     * @return DefaultFilter    The filter instance
     */
    public function __set($property, $value)
    {
        return $this->set($property, $value);
    }

    /**
     * Set a property value
     *
     * @param string $property  The property
     * @param mixed  $value     The value
     *
     * @return DefaultFilter    The filter instance
     */
    public function set($property, $value)
    {
        if (isset($this->properties[$property]) && method_exists($this, $property)) {
            return call_user_func([$this, $property], $value);
        }

        return $this;
    }

    /**
     * Set filter form type
     *
     * @param string $type      The type
     *
     * @return DefaultFilter    The filter instance
     */
    public function form(string $type)
    {
        if (in_array($type, ['none', 'input', 'select', 'html'])) {
            $this->properties['form'] = $type;
        }

        return $this;
    }

    /**
     * Set filter form title
     *
     * @param string $title     The title
     *
     * @return DefaultFilter    The filter instance
     */
    public function title(string $title)
    {
        $this->properties['title'] = $title;

        return $this;
    }

    /**
     * Set filter form options
     *
     * If filter form is a select box, this is the select options
     *
     * @param array     $options    The options
     * @param boolean   $set_form   Auto set form type
     *
     * @return DefaultFilter        The filter instance
     */
    public function options(array $options, $set_form = true)
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
     * @param mixed $value      The value
     *
     * @return DefaultFilter    The filter instance
     */
    public function value($value)
    {
        $this->properties['value'] = $value;

        return $this;
    }

    /**
     * Set filter column in form
     *
     * @param boolean $prime    First column
     *
     * @return DefaultFilter    The filter instance
     */
    public function prime(bool $prime)
    {
        $this->properties['prime'] = $prime;

        return $this;
    }

    /**
     * Set filter html contents
     *
     * @param string    $contents   The contents
     * @param boolean   $set_form   Auto set form type
     *
     * @return DefaultFilter        The filter instance
     */
    public function html(string $contents, $set_form = true)
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
     * @param  string|null           $name  The param name
     * @param  mixed                 $value The param value
     *
     * @return DefaultFilter         The filter instance
     */
    public function param($name = null, $value = null)
    {
        # filter id as param name
        if ($name === null) {
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
    public function parse()
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
