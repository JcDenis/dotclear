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
 * @class Thead
 * @brief HTML Forms Thead creation helpers
 *
 * @method      $this format(string $format)
 * @method      $this rows(Iterable<int|string, Component> $rows)
 * @method      $this items(Iterable<int|string, Component> $items)
 *
 * @property    string $format
 * @property    Iterable<int|string, Component> $rows
 * @property    Iterable<int|string, Component> $items
 */
class Thead extends Component
{
    private const DEFAULT_ELEMENT = 'thead';

    /**
     * Constructs a new instance.
     *
     * @param      string|array{0: string, 1?: string}|null     $id       The identifier
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
            $this->renderCommonAttributes() . '>';

        $format ??= ($this->format ?? '%s');

        // Cope with rows
        if ($this->rows !== null) {
            foreach ($this->rows as $row) {
                if ($row instanceof None) {
                    continue;
                }
                $buffer .= sprintf($format, $row->render());
            }
        }

        // Cope with items (as rows)
        if ($this->items !== null) {
            foreach ($this->items as $item) {
                if ($item instanceof None) {
                    continue;
                }
                $buffer .= sprintf($format, $item->render());
            }
        }

        return $buffer . '</' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . '>' . "\n";
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
