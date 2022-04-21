<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\Optgroup

/**
 * HTML Forms optgroup creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Optgroup extends Component
{
    private const DEFAULT_ELEMENT = 'optgroup';

    /**
     * Constructs a new instance.
     *
     * @param string      $name    The optgroup name
     * @param null|string $element The element
     */
    public function __construct(string $name, ?string $element = null)
    {
        parent::__construct(__CLASS__, $element ?? self::DEFAULT_ELEMENT);
        $this
            ->set('text', $name)
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
            ($this->exists('text') ? ' label="' . $this->get('text') . '"' : '') .
            $this->renderCommonAttributes() . '>' . "\n";

        if ($this->exists('items') && is_array($this->get('items'))) {
            foreach ($this->get('items') as $item => $value) {
                if ($value instanceof Option || $value instanceof Optgroup) {
                    $buffer .= $value->render($default);
                } elseif (is_array($value)) {
                    $buffer .= (new Optgroup($item))->call('items', $value)->render($this->get('default') ?? $default ?? null);
                } else {
                    $buffer .= (new Option($item, $value))->render($this->get('default') ?? $default ?? null);
                }
            }
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
