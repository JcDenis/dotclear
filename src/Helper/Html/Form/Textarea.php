<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

// Dotclear\Helper\Html\Form\Textarea

/**
 * HTML Forms textarea creation helpers.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Textarea extends Component
{
    private const DEFAULT_ELEMENT = 'textarea';

    /**
     * Constructs a new instance.
     *
     * @param string      $id    The identifier
     * @param null|string $value The value
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct(__CLASS__, self::DEFAULT_ELEMENT);
        if (null !== $id) {
            $this
                ->set('id', $id)
                ->set('name', $id)
            ;
        }
        if (null !== $value) {
            $this->set('value', $value);
        }
    }

    /**
     * Renders the HTML component (including the associated label if any).
     *
     * @param null|string $extra The extra
     */
    public function render(?string $extra = null): string
    {
        if (!$this->checkMandatoryAttributes()) {
            return '';
        }

        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . ($extra ?? '') . $this->renderCommonAttributes(false) .
            ($this->exists('cols') ? ' cols="' . strval((int) $this->get('cols')) . '"' : '') .
            ($this->exists('rows') ? ' rows="' . strval((int) $this->get('rows')) . '"' : '') .
            '>' .
            ($this->get('value')        ?? '') .
            '</' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . '>' . "\n";

        if ($this->exists('label') && $this->exists('id')) {
            $this->get('label')->set('for', $this->get('id'));
            $buffer = $this->get('label')->render($buffer);
        }

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
