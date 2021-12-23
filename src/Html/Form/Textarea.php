<?php
/**
 * @class Dotclear\Html\Form\Textarea
 * @brief HTML Forms textarea creation helpers
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

class Textarea extends Component
{
    private const DEFAULT_ELEMENT = 'textarea';

    /**
     * Constructs a new instance.
     *
     * @param      string  $id     The identifier
     */
    public function __construct(?string $id = null, ?string $value = null)
    {
        parent::__construct(__CLASS__, self::DEFAULT_ELEMENT);
        if ($id !== null) {
            $this
                ->id($id)
                ->name($id);
        }
        if ($value !== null) {
            $this->value = $value;
        }
    }

    /**
     * Renders the HTML component (including the associated label if any).
     *
     * @param      null|string  $extra  The extra
     *
     * @return     string
     */
    public function render(?string $extra = null): string
    {
        if (!$this->checkMandatoryAttributes()) {
            return '';
        }

        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . ($extra ?? '') . $this->renderCommonAttributes(false) .
            (isset($this->cols) ? ' cols="' . strval((int) $this->cols) . '"' : '') .
            (isset($this->rows) ? ' rows="' . strval((int) $this->rows) . '"' : '') .
            '>' .
            ($this->value ?? '') .
            '</' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . '>' . "\n";

        if (isset($this->label) && isset($this->id)) {
            $this->label->for = $this->id;
            $buffer           = $this->label->render($buffer);
        }

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
