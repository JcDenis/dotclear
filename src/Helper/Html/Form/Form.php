<?php
/**
 * @class Dotclear\Helper\Html\Form\Form
 * @brief HTML Forms form creation helpers
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

use Dotclear\Helper\Html\Form\Component;

class Form extends Component
{
    private const DEFAULT_ELEMENT = 'form';

    /**
     * Constructs a new instance.
     *
     * @param      null|string  $id       The identifier
     * @param      null|string  $element  The element
     */
    public function __construct(?string $id = null, ?string $element = null)
    {
        parent::__construct(__CLASS__, $element ?? self::DEFAULT_ELEMENT);
        if ($id !== null) {
            $this
                ->set('id', $id)
                ->set('name', $id);
        }
    }

    /**
     * Renders the HTML component.
     *
     * @return     string
     */
    public function render(?string $fieldFormat = null): string
    {
        if (!$this->checkMandatoryAttributes()) {
            return '';
        }

        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) .
            ($this->exists('action') ? ' action="' . $this->get('action') . '"' : '') .
            ($this->exists('method') ? ' method="' . $this->get('method') . '"' : '') .
            $this->renderCommonAttributes() . '>' . "\n";

        if ($this->exists('fields')) {
            if (is_array($this->get('fields'))) {
                foreach ($this->get('fields') as $field) {
                    $buffer .= sprintf(($fieldFormat ?: '%s'), $field->render());
                }
            }
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
