<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

// Dotclear\Process\Public\Template\TplNodeValueParent

/**
 * Template value node parent.
 *
 * Value node, for all {{tpl:Tag}}.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Template
 */
class TplNodeValueParent extends TplNodeValue
{
    /**
     * Return compiled node.
     *
     * Simply ask currently being displayed to display itself.
     *
     * @param Template $tpl Template engine instance
     *
     * @return string The compiled node
     */
    public function compile(Template $tpl): string
    {
        return TplNodeBlockDefinition::renderParent($tpl);
    }
}
