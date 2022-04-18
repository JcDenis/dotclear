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
 * HTML Forms legend creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Legend
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Legend extends Component
{
    private const DEFAULT_ELEMENT = 'legend';

    /**
     * Constructs a new instance.
     *
     * @param string      $text    The text
     * @param null|string $id      The identifier
     * @param null|string $element The element
     */
    public function __construct(string $text = '', ?string $id = null, ?string $element = null)
    {
        parent::__construct(__CLASS__, $element ?? self::DEFAULT_ELEMENT);
        $this->set('text', $text);
        if (null !== $id) {
            $this
                ->set('id', $id)
                ->set('name', $id)
            ;
        }
    }

    /**
     * Renders the HTML component.
     */
    public function render(): string
    {
        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . $this->renderCommonAttributes() . '>';
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
