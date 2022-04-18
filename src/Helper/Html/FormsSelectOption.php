<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html;

/**
 * HTML Forms creation helpers.
 *
 * \Dotclear\Helper\Html\FormSelectOption
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class FormsSelectOption
{
    public $name;       // /< string Option name
    public $value;      // /< mixed  Option value
    public $class_name; // /< string Element class name
    public $extra;      // /< string Extra HTML attributes

    /**
     * sprintf template for option.
     *
     * @var string
     */
    private $option = '<option value="%1$s"%3$s>%2$s</option>' . "\n";

    /**
     * Option constructor.
     *
     * @param array $params Parameters
     *                      $params = [
     *                      'name'          => string option name (required).
     *                      'value'         => string option value (required).
     *                      'class_name'    => string class name.
     *                      'extra'         => string extra HTML attributes.
     *                      ]
     */
    public function __construct(array $params)
    {
        $this->name       = $params['name'];
        $this->value      = $params['value'];
        $this->class_name = $params['class'] ?? null;
        $this->extra      = $params['extra'] ?? null;
    }

    /**
     * Option renderer.
     *
     * Returns option HTML code
     *
     * @param string $default Value of selected option
     */
    public function render(?string $default): string
    {
        $attr = $this->class_name ? ' class="' . $this->class_name . '"' : '';
        $attr .= $this->extra ? ' ' . $this->extra : '';

        if ($this->value == $default) {
            $attr .= ' selected';
        }

        return sprintf($this->option, $this->value, $this->name, $attr);
    }
}
