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
    // Basic tree structure : links to parent, children forrest

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

    // Returns compiled block
    public function compile(Template $tpl): string
    {
        $res = '';
        foreach ($this->children as $child) {
            $res .= $child->compile($tpl);
        }

        return $res;
    }

    // Add a children to current node
    public function addChild($child): void
    {
        $this->children[] = $child;
        $child->setParent($this);
    }

    // Defines parent for current node
    protected function setParent($parent): void
    {
        $this->parentNode = $parent;
    }

    // Retrieves current node parent.
    // If parent is root node, null is returned
    public function getParent()
    {
        return $this->parentNode;
    }

    // Current node tag
    public function getTag(): string
    {
        return 'ROOT';
    }
}
