<?php
/**
 * @note Dotclear\Process\Public\Template\Engine\TplNodeValueParent
 * @brief Value node, for all {{tpl:Tag}}
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Template
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template\Engine;

class TplNodeValueParent extends TplNodeValue
{
    public function compile(Template $tpl): string
    {
        // Simply ask currently being displayed to display itself!
        return TplNodeBlockDefinition::renderParent($tpl);
    }
}
