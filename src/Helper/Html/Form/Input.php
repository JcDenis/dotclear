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
 * HTML Forms input field creation helpers.
 *
 * \Dotclear\Helper\Html\Form\Input
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Html Form
 */
class Input extends Component
{
    private const DEFAULT_ELEMENT = 'input';

    /**
     * Should include the associated label if exist.
     *
     * @var bool
     */
    private $renderLabel = true;

    /**
     * Constructs a new instance.
     *
     * @param string $id          The identifier
     * @param string $type        The input type
     * @param bool   $renderLabel Render label if present
     */
    public function __construct(string $id = null, string $type = 'text', bool $renderLabel = true)
    {
        parent::__construct(__CLASS__, self::DEFAULT_ELEMENT);
        $this->call('type', $type);
        $this->renderLabel = $renderLabel;
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
        if (!$this->checkMandatoryAttributes()) {
            return '';
        }

        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . $this->renderCommonAttributes() . '/>' . "\n";

        if ($this->renderLabel && $this->exists('label') && $this->exists('id')) {
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
