<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

// Dotclear\Process\Public\Template\TplNodeBlock

/**
 * Template block node.
 *
 * Block node, for all <tpl:Tag>...</tpl:Tag>.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Template
 */
class TplNodeBlock extends TplNode
{
    /**
     * @var bool $closed
     *           If block is closed
     */
    protected $closed  = false;

    /**
     * Constructor.
     *
     * @param string  $tag  Current tag (might be "Block")
     * @param TplAttr $attr Tag attributes
     */
    public function __construct(protected string $tag, protected TplAttr $attr)
    {
    }

    /**
     * Set block as closed.
     */
    public function setClosing(): void
    {
        $this->closed = true;
    }

    /**
     * Check if block is closed.
     *
     * @return bool True if block is closed
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Return compiled node.
     *
     * @param Template $tpl Template engine instance
     *
     * @return string The compiled node
     */
    public function compile(Template $tpl): string
    {
        if ($this->closed) {
            $content = parent::compile($tpl);

            return $tpl->compileBlockNode($this->tag, $this->attr, $content);
        }
        // if tag has not been closed, silently ignore its content...
        return '';
    }

    /**
     * Current node tag.
     *
     * @return string The node tag
     */
    public function getTag(): string
    {
        return $this->tag;
    }
}
