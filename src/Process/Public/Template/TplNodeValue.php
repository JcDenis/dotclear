<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

// Dotclear\Process\Public\Template\TplNodeValue

/**
 * Template value node.
 *
 * Value node, for all {{tpl:Tag}}.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Template
 */
class TplNodeValue extends TplNode
{
    /**
     * Constructor.
     *
     * @param string  $tag      Current tag
     * @param TplAttr $attr     Tag attributes
     * @param string  $str_attr String attributes
     */
    public function __construct(protected string $tag, protected TplAttr $attr, protected string $str_attr)
    {
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
        return $tpl->compileValueNode($this->tag, $this->attr, $this->str_attr);
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
