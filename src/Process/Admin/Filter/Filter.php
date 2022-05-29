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
use Dotclear\Exception\InvalidValueFormat;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form\Select as FormSelect;
use Dotclear\Helper\Html\Form\Label as FormLabel;
use Dotclear\Helper\Html\Form\Input as FormInput;

/**
 * Admin filter.
 *
 * Dotclear utility class that provides reuseable list filter
 * Should be used with Filters
 *
 * @ingroup  Admin Filter
 *
 * @since 2.20
 */
class Filter
{
    /**
     * @var array<string,mixed> $properties
     *                          The filter properties
     */
    protected $properties = [
        'id'      => '',
        'value'   => null,
        'form'    => 'none',
        'prime'   => false,
        'title'   => '',
        'options' => [],
        'html'    => '',
        'params'  => [],
    ];

    /**
     * Constructs a new filter.
     *
     * @param string $id    The filter id
     * @param mixed  $value The filter value
     */
    public function __construct(string $id, mixed $value = null)
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            throw new InvalidValueFormat('not a valid id');
        }
        $this->properties['id']    = $id;
        $this->properties['value'] = $value;
    }

    /**
     * Check if filter property exists.
     *
     * @param string $key The property
     *
     * @return bool Is set
     */
    public function exists(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    /**
     * Get a filter property.
     *
     * @param string $key The property
     *
     * @return mixed The value
     */
    public function get(string $key): mixed
    {
        return $this->properties[$key] ?? null;
    }

    /**
     * Set a property value.
     *
     * @param string $key   The property
     * @param mixed  $value The value
     */
    public function set(string $key, mixed $value): void
    {
        if (isset($this->properties[$key]) && method_exists($this, $key)) {
            call_user_func([$this, $key], $value);
        }
    }

    /**
     * Set filter form type.
     *
     * @param string $type The type
     */
    public function form(string $type): void
    {
        if (in_array($type, ['none', 'input', 'select', 'html'])) {
            $this->properties['form'] = $type;
        }
    }

    /**
     * Set filter form title.
     *
     * @param string $title The title
     */
    public function title(string $title): void
    {
        $this->properties['title'] = $title;
    }

    /**
     * Set filter form options.
     *
     * If filter form is a select box, this is the select options
     *
     * @param array $options The options
     * @param bool  $typed   Auto set form type
     */
    public function options(array $options, bool $typed = true): void
    {
        $this->properties['options'] = $options;
        if ($typed) {
            $this->form(type: 'select');
        }
    }

    /**
     * Set filter value.
     *
     * @param mixed $value The value
     */
    public function value(mixed $value): void
    {
        $this->properties['value'] = $value;
    }

    /**
     * Set filter column in form.
     *
     * @param bool $prime First column
     */
    public function prime(bool $prime): void
    {
        $this->properties['prime'] = $prime;
    }

    /**
     * Set filter html contents.
     *
     * @param string $contents The contents
     * @param bool   $typed    Auto set form type
     */
    public function html(string $contents, bool $typed = true): void
    {
        $this->properties['html'] = $contents;
        if ($typed) {
            $this->form(type: 'html');
        }
    }

    /**
     * Set filter param (list query param).
     *
     * @param null|string $name  The param name
     * @param mixed       $value The param value
     */
    public function param(?string $name = null, mixed $value = null): void
    {
        // filter id as param name
        if (null === $name) {
            $name = $this->properties['id'];
        }
        // filter value as param value
        if (null === $value) {
            $value = fn ($f) => $f[0];
        }
        $this->properties['params'][] = [$name, $value];
    }

    /**
     * Parse the filter properties.
     *
     * Only input and select forms are parsed
     */
    public function parse(): void
    {
        // form select
        if ('select' == $this->get(key: 'form')) {
            // _GET value
            if (null === $this->get(key: 'value')) {
                $get = GPC::get()->string($this->get(key: 'id'));
                if ('' === $get || !in_array($get, $this->get(key: 'options'), true)) {
                    $get = '';
                }
                $this->value(value: $get);
            }
            // HTML field
            $form = new FormSelect($this->get(key: 'id'));
            $form->set('default', $this->get(key: 'value'));
            $form->set('items', $this->get(key: 'options'));

            $label = new FormLabel($this->get(key: 'title'), 2, $this->get(key: 'id'));
            $label->set('class', 'ib');

            $this->html(contents: $label->render($form->render()), typed: false);

        // form input
        } elseif ('input' == $this->get(key: 'form')) {
            // _GET value
            if (null === $this->get(key: 'value')) {
                $this->value(value: GPC::get()->string($this->get(key: 'id')));
            }
            // HTML field
            $form = new FormInput($this->get(key: 'id'));
            $form->set('size', 20);
            $form->set('maxlength', 255);
            $form->set('value', $this->get(key: 'value'));

            $label = new FormLabel($this->get(key: 'title'), 2, $this->get(key: 'id'));
            $label->set('class', 'ib');

            $this->html($label->render($form->render()), false);
        }
    }
}
