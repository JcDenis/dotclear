<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

// Dotclear\Process\Public\Template\TplNode

/**
 * Template node.
 *
 * Generic list node, this one may only be instanciated once for root element.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Template
 */
class TplNode
{
    /**
     * @var TplNode $parentNode
     *              The parent node
     */
    protected $parentNode;

    /**
     * @var array<int,TplNode> $children
     *                         The node children
     */
    protected $children = [];

    /**
     * Return compiled block.
     *
     * @param Template $tpl Template engine instance
     *
     * @return string The compiled children nodes
     */
    public function compile(Template $tpl): string
    {
        $res = '';
        foreach ($this->children as $child) {
            $res .= $child->compile($tpl);
        }

        return $res;
    }

    /**
     * Add a children to current node.
     *
     * $child could be one of TplNode children class.
     *
     * @param mixed $child A child node
     */
    public function addChild($child): void
    {
        $this->children[] = $child;
        $child->setParent($this);
    }

    /**
     * Define parent for current node.
     *
     * $parent could be one of TplNode children class.
     *
     * @param mixed $parent The parent node
     */
    protected function setParent($parent): void
    {
        $this->parentNode = $parent;
    }

    /**
     * Retrieve current node parent.
     *
     * If parent is root node, null is returned
     * Returned parent could be one of TplNode children class.
     *
     * @return mixed The parent node
     */
    public function getParent()
    {
        return $this->parentNode;
    }

    /**
     * Current node tag.
     *
     * @return string The node tag
     */
    public function getTag(): string
    {
        return 'ROOT';
    }
}
