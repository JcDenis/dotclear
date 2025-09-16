<?php

/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

/**
 * @class Td
 * @brief HTML Forms Td creation helpers
 *
 * @method      $this colspan(int $colspan)
 * @method      $this rowspan(int $rowspan)
 * @method      $this headers(string $headers)
 * @method      $this text(string $text)
 * @method      $this separator(string $separator)
 * @method      $this format(string $format)
 * @method      $this items(Iterable<int|string, Component> $items)
 *
 * @property    ?int $colspan
 * @property    ?int $rowspan
 * @property    ?string $headers
 * @property    ?string $text
 * @property    ?string $separator
 * @property    ?string $format
 * @property    null|Iterable<int|string, Component> $items
 */
class Td extends Component
{
    public const DEFAULT_ELEMENT = 'td';

    /**
     * Constructs a new instance.
     *
     * @param      string|list{0: string, 1?: string}|null      $id       The identifier
     * @param      string                                       $element  The element
     */
    public function __construct(string|array|null $id = null, ?string $element = null)
    {
        parent::__construct(self::class, $element ?? self::DEFAULT_ELEMENT);
        if ($id !== null) {
            $this->setIdentifier($id);
        }
    }

    /**
     * Renders the HTML component.
     *
     * @param   string  $format     sprintf() format applied for each items/fields ('%s' by default)
     */
    public function render(?string $format = null): string
    {
        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) .
            ($this->colspan !== null ? ' colspan=' . strval((int) $this->colspan) : '') .
            ($this->rowspan !== null ? ' rowspan=' . strval((int) $this->rowspan) : '') .
            ($this->headers !== null ? ' headers="' . $this->headers . '"' : '') .
            $this->renderCommonAttributes() . '>';

        if ($this->text !== null) {
            $buffer .= $this->text;
        }

        // Cope with items
        $buffer .= $this->renderItems($format);

        return $buffer . '</' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . '>';
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
