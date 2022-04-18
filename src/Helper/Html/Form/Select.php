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
 * HTML Forms select creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Select
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Select extends Component
{
    private const DEFAULT_ELEMENT = 'select';

    /**
     * Constructs a new instance.
     *
     * @param null|string $id      The identifier
     * @param null|string $element The element
     */
    public function __construct(?string $id = null, ?string $element = null)
    {
        parent::__construct(__CLASS__, $element ?? self::DEFAULT_ELEMENT);
        if (null !== $id) {
            $this
                ->set('id', $id)
                ->set('name', $id)
            ;
        }
    }

    /**
     * Renders the HTML component (including select options).
     *
     * @param null|string $default The default value
     */
    public function render(?string $default = null): string
    {
        if (!$this->checkMandatoryAttributes()) {
            return '';
        }

        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . $this->renderCommonAttributes() . '>' . "\n";

        if ($this->exists('items') && is_array($this->get('items'))) {
            foreach ($this->get('items') as $item => $value) {
                if ($value instanceof Option || $value instanceof Optgroup) {
                    $buffer .= $value->render($this->get('default') ?? $default ?? null);
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
