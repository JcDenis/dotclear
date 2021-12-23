<?php
/**
 * @class Dotclear\Html\Form\Legend
 * @brief HTML Forms legend creation helpers
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

namespace Dotclear\Html\Form;

use Dotclear\Html\Form\Component;

class Legend extends Component
{
    private const DEFAULT_ELEMENT = 'legend';

    /**
     * Constructs a new instance.
     *
     * @param      string       $text     The text
     * @param      null|string  $id       The identifier
     * @param      null|string  $element  The element
     */
    public function __construct(string $text = '', ?string $id = null, ?string $element = null)
    {
        parent::__construct(__CLASS__, $element ?? self::DEFAULT_ELEMENT);
        $this->text($text);
        if ($id !== null) {
            $this
                ->id($id)
                ->name($id);
        }
    }

    /**
     * Renders the HTML component.
     *
     * @return     string
     */
    public function render(): string
    {
        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . $this->renderCommonAttributes() . '>';
        if ($this->text) {
            $buffer .= $this->text;
        }
        $buffer .= '</' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . '>' . "\n";

        return $buffer;
    }

    /**
     * Gets the default element.
     *
     * @return     string  The default element.
     */
    public function getDefaultElement(): string
    {
        return self::DEFAULT_ELEMENT;
    }
}
