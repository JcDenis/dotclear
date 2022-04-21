<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html;

// Dotclear\Helper\Html\FormSelectOption

/**
 * HTML Forms creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class FormSelectOption
{
    /**
     * @var string $option
     *             sprintf template for option
     */
    private $option = '<option value="%1$s"%3$s>%2$s</option>' . "\n";

    /**
     * Option constructor.
     *
     * @param string $name       Option name
     * @param mixed  $value      Option value
     * @param string $class_name Element class name
     * @param string $html       Extra HTML attributes
     */
    public function __construct(public string $name, public mixed $value, public string $class_name = '', public string $html = '')
    {
    }

    /**
     * Option renderer.
     *
     * Returns option HTML code
     *
     * @param mixed $default Value of selected option
     */
    public function render(mixed $default): string
    {
        $attr = $this->html ? ' ' . $this->html : '';
        $attr .= $this->class_name ? ' class="' . $this->class_name . '"' : '';

        if ($this->value == $default) {
            $attr .= ' selected="selected"';
        }

        return sprintf($this->option, $this->value, $this->name, $attr) . "\n";
    }
}
