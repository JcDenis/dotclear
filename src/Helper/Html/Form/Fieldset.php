<?php
/**
 * @class Dotclear\Helper\Html\Form\Fieldset
 * @brief HTML Forms fieldset creation helpers
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
use Dotclear\Helper\Html\Form\Legend;

class Fieldset extends Component
{
    private const DEFAULT_ELEMENT = 'fieldset';

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
     * Attaches the legend to this fieldset.
     *
     * @param      Legend|null  $legend  The legend
     */
    public function attachLegend(?Legend $legend)
    {
        if ($legend) {
            $this->call('legend', $legend);
        } elseif ($this->exists('legend')) {
            $this->remove('legend');
        }
    }

    /**
     * Detaches the legend.
     */
    public function detachLegend()
    {
        if ($this->exists('legend')) {
            $this->remove('legend');
        }
    }

    /**
     * Renders the HTML component (including the associated legend if any).
     *
     * @return     string
     */
    public function render(): string
    {
        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . $this->renderCommonAttributes() . '>' . "\n";

        if ($this->exists('legend')) {
            $buffer .= $this->get('legend')->render();
        }

        if ($this->exists('fields')) {
            if (is_array($this->get('fields'))) {
                foreach ($this->get('fields') as $field) {
                    if ($this->exists('legend') && $field->getDefaultElement() === 'legend') {
                        // Do not put more than one legend in fieldset
                        continue;
                    }
                    $buffer .= $field->render();
                }
            }
        }

        $buffer .= "\n" . '</' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . '>' . "\n";

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
