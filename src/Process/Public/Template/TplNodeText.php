<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

// Dotclear\Process\Public\Template\TplNodeText

/**
 * Template text node.
 *
 * Text node, for any non-tpl content.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Template
 */
class TplNodeText extends TplNode
{
    /**
     * Consturtor.
     *
     * @param string $content The text node content
     */
    public function __construct(protected string $content)
    {
    }

    /**
     * Return compiled node.
     *
     * This simply return node content
     *
     * @param Template $tpl Template engine instance
     *
     * @return string The compiled node
     */
    public function compile(Template $tpl): string
    {
        return $this->content;
    }

    /**
     * Current node tag.
     *
     * @return string The node tag
     */
    public function getTag(): string
    {
        return 'TEXT';
    }
}
