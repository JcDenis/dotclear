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
final class Filter
{
    /**
     * Constructs a new filter.
     *
     * @param string              $id       The filter id
     * @param mixed               $value    The filter value
     * @param string              $type     The filter type
     * @param string              $title    The filter title
     * @param array<string,mixed> $options  The filter select combo options
     * @param string              $contents The filters contents (commonly HTML)
     * @param array<int,array>    $params   The filter params (for db query)
     * @param bool                $prime    The filter is placed in first column
     */
    public function __construct(
        private string $id,
        private mixed $value = null,
        private string $type = 'default',
        private string $title = '',
        private array $options = [],
        private string $contents = '',
        private array $params = [],// [[null, null],],
        private bool $prime = false
    ) {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $this->id)) {
            throw new InvalidValueFormat('not a valid id');
        }
        if (!in_array($this->type, ['default', 'none', 'input', 'select', 'html'])) {
            throw new InvalidValueFormat('not a valid type');
        }

        if ('default' == $this->type) {
            if (!empty($this->options)) {
                $this->type = 'select';
            } elseif (!empty($this->contents)) {
                $this->type = 'html';
            }
        }

        foreach ($this->params as $i => $param) {
            // filter id as param name
            if (null === $param[0]) {
                $param[0] = $this->id;
            }
            // filter value as param value
            if (null === $param[1]) {
                $param[1] = fn ($f) => $f[0];
            }
            $this->params[$i] = [$param[0], $param[1]];
        }
    }

    /**
     * Update filter value.
     *
     * @param mixed $value The filter value
     */
    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    /**
     * Get a filter property.
     *
     * @param string $key The property
     *
     * @return mixed The value
     */
    public function getProperty(string $key): mixed
    {
        return $this->{$key} ?? null;
    }

    /**
     * Parse the filter properties.
     *
     * Only input and select forms are parsed
     */
    public function parsePropertries(): void
    {
        // form select
        if ('select' == $this->type) {
            // _GET value
            if (null === $this->value) {
                $get = GPC::get()->string($this->id);
                if ('' === $get || !in_array($get, $this->options, true)) {
                    $get = '';
                }
                $this->value = $get;
            }
            // HTML field
            $form = new FormSelect($this->id);
            $form->set('default', $this->value);
            $form->set('items', $this->options);

            $label = new FormLabel($this->title, 2, $this->id);
            $label->set('class', 'ib');

            $this->contents = $label->render($form->render());

        // form input
        } elseif ('input' == $this->type) {
            // _GET value
            if (null === $this->value) {
                $this->value = GPC::get()->string($this->id);
            }
            // HTML field
            $form = new FormInput($this->id);
            $form->set('size', 20);
            $form->set('maxlength', 255);
            $form->set('value', $this->value);

            $label = new FormLabel($this->title, 2, $this->id);
            $label->set('class', 'ib');

            $this->contents = $label->render($form->render());
        }
    }
}
