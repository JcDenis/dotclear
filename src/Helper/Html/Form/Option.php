<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

/**
 * HTML Forms option creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Option
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Option extends Component
{
    private const DEFAULT_ELEMENT = 'option';

    /**
     * Constructs a new instance.
     *
     * @param string      $name    The option name
     * @param int|string  $value   The option value
     * @param null|string $element The element
     */
    public function __construct(string $name, string|int $value, ?string $element = null)
    {
        parent::__construct(__CLASS__, $element ?? self::DEFAULT_ELEMENT);
        $this
            ->set('text', $name)
            ->set('value', $value)
        ;
    }

    /**
     * Renders the HTML component.
     *
     * @param null|string $default The default value
     */
    public function render(?string $default = null): string
    {
        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) .
            ($this->get('value') === $default ? ' selected' : '') .
            $this->renderCommonAttributes() . '>';

        if ($this->get('text')) {
            $buffer .= $this->get('text');
        }

        $buffer .= '</' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . '>' . "\n";

        return $buffer;
    }

    /**
     * Gets the default element.
     *
     * @return string the default element
     */
    public function getDefaultElement(): string
    {
        return self::DEFAULT_ELEMENT;
    }
}
