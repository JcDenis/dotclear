<?php
/**
 * @class Dotclear\Helper\Html\Form\Component
 * @brief HTML Forms creation helpers
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage html.form
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

use Dotclear\Helper\Html\Form\Label;

abstract class Component
{
    /** @var    array   Custom component properties (see __get() and __set()) */
    private $_data = [];

    public function __construct(private string|null $_type = null, private string|null $_element = null)
    {
        if (!$this->_type) {
            $this->_type = __CLASS__;
        }
    }

    /**
     * Call statically new instance
     *
     * @return object New formXxx instance
     */
    public static function init()
    {
        $c = static::class;

        return new $c(...func_get_args());
    }

    /**
     * Magic getter method
     *
     * @param      string  $property  The property
     *
     * @return     mixed   property value if property exists or null
     */
    public function get(string $property)
    {
        if (array_key_exists($property, $this->_data)) {
            return $this->_data[$property];
        }

        return null;
    }
/*
    public function __get(string $property)
    {
        return $this->get($property);
    }
*/
    /**
     * Magic setter method
     *
     * @param      string  $property  The property
     * @param      mixed   $value     The value
     *
     * @return     self
     */
    public function set(string $property, $value)
    {
        $this->_data[$property] = $value;

        return $this;
    }
/*
    public function __set(string $property, $value)
    {
        return $this->set($property, $value);
    }
*/
    /**
     * Check if a property exists
     *
     * @param      string  $property  The property
     *
     * @return     bool
     */
    public function exists(string $property): bool
    {
        return isset($this->_data[$property]);
    }
/*
    public function __isset(string $property): bool
    {
        return $this->exists($property);
    }
*/
    /**
     * Remove a property
     *
     * @param      string  $property  The property
     */
    public function remove(string $property)
    {
        unset($this->_data[$property]);
    }
/*
    public function __unset(string $property)
    {
        $this->remove($property);
    }
*/
    /**
     * Call a component method
     *
     * If the method exists, call it and return it's return value
     * If not, if there is no argument ($argument empty array), assume that it's a get
     * If not, assume that's is a set (value = $argument[0])
     *
     * @param      string  $method     The property
     * @param      mixed   ...$arguments  The arguments
     *
     * @return     mixed   method called, property value (or null), self
     */
    public function call(string $method, mixed ...$arguments)
    {
        // Cope with known methods
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $arguments);
        }

        // Unknown method
        if (!count($arguments)) {
            // No argument, assume its a get
            if (array_key_exists($method, $this->_data)) {
                return $this->_data[$method];
            }

            return null;
        }
        // Argument here, assume its a set
        $this->_data[$method] = $arguments[0];

        return $this;
    }
/*
    public function __call(string $method, $arguments)
    {
        return call_user_func_array([$this, 'call'], $arguments);
    }
*/
    /**
     * Magic invoke method
     *
     * Return rendering of component
     *
     * @return     string
     */
    public function __invoke(): string
    {
        return $this->render();
    }

    /**
     * Gets the type of component
     *
     * @return     string  The type.
     */
    public function getType(): string
    {
        return $this->_type;
    }

    /**
     * Sets the type of component
     *
     * @param      string  $type   The type
     *
     * @return     self
     */
    public function setType(string $type)
    {
        $this->_type = $type;

        return $this;
    }

    /**
     * Gets the HTML element
     *
     * @return     null|string  The element.
     */
    public function getElement(): ?string
    {
        return $this->_element;
    }

    /**
     * Sets the HTML element
     *
     * @param      string  $element  The element
     *
     * @return     self
     */
    public function setElement(string $element)
    {
        $this->_element = $element;

        return $this;
    }

    /**
     * Attaches the label.
     *
     * @param      Label|null  $label     The label
     * @param      int|null        $position  The position
     *
     * @return     self
     */
    public function attachLabel(?Label $label = null, ?int $position = null)
    {
        if ($label) {
            $this->call('label', $label);
            $label->call('for', $this->get('id'));
            if ($position !== null) {
                $label->setPosition($position);
            }
        } elseif ($this->exists('label')) {
            $this->remove('label');
        }

        return $this;
    }

    /**
     * Detaches the label from this component
     *
     * @return     self
     */
    public function detachLabel()
    {
        if ($this->exists('label')) {
            $this->remove('label');
        }

        return $this;
    }

    /**
     * Check mandatory attributes in properties, at least name or id must be present
     *
     * @return     bool
     */
    public function checkMandatoryAttributes(): bool
    {
        // Check for mandatory info
        return $this->exists('name') || $this->exists('id');
    }

    /**
     * Render common attributes
     *
     *      $this->
     *
     *          type            => string type (may be used for input component).
     *
     *          name            => string name (required if id is not provided).
     *          id              => string id (required if name is not provided).
     *
     *          value           => string value.
     *          default         => string default value (will be used if value is not provided).
     *          checked         => boolean checked.
     *
     *          accesskey       => string accesskey (character(s) space separated).
     *          autocomplete    => string autocomplete type.
     *          autofocus       => boolean autofocus.
     *          class           => string (or array of string) class(es).
     *          contenteditable => boolean content editable.
     *          dir             => string direction.
     *          disabled        => boolean disabled.
     *          form            => string form id.
     *          lang            => string lang.
     *          list            => string list id.
     *          max             => int max value.
     *          maxlength       => int max length.
     *          min             => int min value.
     *          readonly        => boolean readonly.
     *          required        => boolean required.
     *          pattern         => string pattern.
     *          placeholder     => string placeholder.
     *          size            => int size.
     *          spellcheck      => boolean spellcheck.
     *          tabindex        => int tabindex.
     *          title           => string title.
     *
     *          data            => array data.
     *              [
     *                  key   => string data id (rendered as data-<id>).
     *                  value => string data value.
     *              ]
     *
     *          extra           => string (or array of string) extra HTML attributes.
     *
     * @param      bool    $includeValue    Includes $this->value if exist (default = true)
     *                                      should be set to false to textarea and may be some others
     *
     * @return     string
     */
    public function renderCommonAttributes(bool $includeValue = true): string
    {
        $render = '' .

            // Type (used for input component)
            ($this->exists('type') ?
                ' type="' . $this->get('type') . '"' : '') .

            // Identifier
            // - use $this->name for name attribute else $this->id if exists
            // - use $this->id for id attribute else $this->name if exists
            ($this->exists('name') ?
                ' name="' . $this->get('name') . '"' :
                (null !== $this->get('id') ? ' name="' . $this->get('id') . '"' : '')) .
            ($this->exists('id') ?
                ' id="' . $this->get('id') . '"' :
                ($this->exists('name') ? ' id="' . $this->get('name') . '"' : '')) .

            // Value
            // - $this->default will be used as value if exists and $this->value does not
            ($includeValue && array_key_exists('value', $this->_data) ?
                ' value="' . $this->get('value') . '"' : '') .
            ($includeValue && !array_key_exists('value', $this->_data) && array_key_exists('default', $this->_data) ?
                ' value="' . $this->get('default') . '"' : '') .
            ($this->exists('checked') && $this->get('checked') ?
                ' checked' : '') .

            // Common attributes
            ($this->exists('accesskey') ?
                ' accesskey="' . $this->get('accesskey') . '"' : '') .
            ($this->exists('autocomplete') ?
                ' autocomplete="' . $this->get('autocomplete') . '"' : '') .
            ($this->exists('autofocus') && $this->get('autofocus') ?
                ' autofocus' : '') .
            ($this->exists('class') ?
                ' class="' . (is_array($this->get('class')) ? implode(' ', $this->get('class')) : $this->get('class')) . '"' : '') .
            ($this->exists('contenteditable') && $this->get('contenteditable') ?
                ' contenteditable' : '') .
            ($this->exists('dir') ?
                ' dir="' . $this->get('dir') . '"' : '') .
            ($this->exists('disabled') && $this->get('disabled') ?
                ' disabled' : '') .
            ($this->exists('form') ?
                ' form="' . $this->get('form') . '"' : '') .
            ($this->exists('lang') ?
                ' lang="' . $this->get('lang') . '"' : '') .
            ($this->exists('list') ?
                ' list="' . $this->get('list') . '"' : '') .
            ($this->exists('max') ?
                ' max="' . strval((int) $this->get('max')) . '"' : '') .
            ($this->exists('maxlength') ?
                ' maxlength="' . strval((int) $this->get('maxlength')) . '"' : '') .
            ($this->exists('min') ?
                ' min="' . strval((int) $this->get('min')) . '"' : '') .
            ($this->exists('pattern') ?
                ' pattern="' . $this->get('pattern') . '"' : '') .
            ($this->exists('placeholder') ?
                ' placeholder="' . $this->get('placeholder') . '"' : '') .
            ($this->exists('readonly') && $this->get('readonly') ?
                ' readonly' : '') .
            ($this->exists('required') && $this->get('required') ?
                ' required' : '') .
            ($this->exists('size') ?
                ' size="' . strval((int) $this->get('size')) . '"' : '') .
            ($this->exists('spellcheck') ?
                ' spellcheck="' . ($this->get('spellcheck') ? 'true' : 'false') . '"' : '') .
            ($this->exists('tabindex') ?
                ' tabindex="' . strval((int) $this->get('tabindex')) . '"' : '') .
            (isset($this->title) ?
                ' title="' . $this->title . '"' : '') .

        '';

        if (isset($this->data) && is_array($this->data)) {
            // Data attributes
            foreach ($this->data as $key => $value) {
                $render .= ' data-' . $key . '="' . $value . '"';
            }
        }

        if ($this->exists('extra')) {
            // Extra HTML
            $render .= ' ' . (is_array($this->get('extra')) ? implode(' ', $this->get('extra')) : $this->get('extra'));
        }

        return $render;
    }

    // Abstract methods

    /**
     * Renders the object.
     *
     * Must be provided by classes which extends this class
     */
    abstract protected function render(): string;

    /**
     * Gets the default element.
     *
     * @return     string  The default HTML element.
     */
    abstract protected function getDefaultElement(): string;
}
