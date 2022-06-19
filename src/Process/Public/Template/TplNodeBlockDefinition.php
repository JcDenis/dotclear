<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

// Dotclear\Process\Public\Template\TplNodeBlockDefinition

/**
 * Template block node definition.
 *
 * Block node, for all <tpl:Tag>...</tpl:Tag>.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Template
 */
class TplNodeBlockDefinition extends TplNodeBlock
{
    /**
     * @var array<string,array> $stack
     *                          The stack of blocks
     */
    protected static $stack = [];

    /**
     * @var null|string $current_block
     *                  The current block name (from process loop)
     */
    protected static $current_block;

    /**
     * @var string $name
     *             The block name (from constructor)
     */
    protected $name;

    /**
     * Render the parent block of currently being displayed block.
     *
     * @param Template $tpl the current template engine instance
     *
     * @return string the compiled parent block
     */
    public static function renderParent(Template $tpl): string
    {
        return self::getStackBlock(self::$current_block, $tpl);
    }

    /**
     * Reset blocks stack.
     */
    public static function reset(): void
    {
        self::$stack         = [];
        self::$current_block = null;
    }

    /**
     * Retrieves block defined in call stack.
     *
     * @param string   $name the block name
     * @param Template $tpl  the template engine instance
     *
     * @return string the block (empty string if unavailable)
     */
    public static function getStackBlock(string $name, Template $tpl)
    {
        $stack = &self::$stack[$name];
        $pos   = $stack['pos'];
        // First check if block position is correct
        if (isset($stack['blocks'][$pos])) {
            self::$current_block = $name;
            if (!is_string($stack['blocks'][$pos])) {
                // Not a string ==> need to compile the tree

                // Go deeper 1 level in stack, to enable calls to parent
                ++$stack['pos'];
                $ret = '';
                // Compile each and every children
                foreach ($stack['blocks'][$pos] as $child) {
                    $ret .= $child->compile($tpl);
                }
                --$stack['pos'];
                $stack['blocks'][$pos] = $ret;
            } else {
                // Already compiled, nice ! Simply return string
                $ret = $stack['blocks'][$pos];
            }

            return $ret;
        }
        // Not found => return empty
        return '';
    }

    /**
     * Block definition specific constructor.
     *
     * Keep block name in mind.
     *
     * @param string  $tag  Current tag (might be "Block")
     * @param TplAttr $attr Tag attributes (must contain "name" attribute)
     */
    public function __construct(protected string $tag, protected TplAttr $attr)
    {
        $this->name = $attr->get('name');
    }

    /**
     * Override tag closing processing.
     *
     * Here we enrich the block stack to keep block history.
     */
    public function setClosing(): void
    {
        if (!isset(self::$stack[$this->name])) {
            self::$stack[$this->name] = [
                'pos'    => 0, // pos is the pointer to the current block being rendered
                'blocks' => [], ];
        }
        parent::setClosing();
        self::$stack[$this->name]['blocks'][] = $this->children;
        $this->children                       = [];
    }

    /**
     * Return compiled node.
     *
     * Grab latest block content being defined.
     *
     * @param Template $tpl Template engine instance
     *
     * @return string The compiled node
     */
    public function compile(Template $tpl): string
    {
        return $tpl->compileBlockNode(
            $this->tag,
            $this->attr,
            self::getStackBlock($this->name, $tpl)
        );
    }
}
