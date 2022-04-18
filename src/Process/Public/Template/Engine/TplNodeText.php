<?php
/**
 * @note Dotclear\Process\Public\Template\Engine\TplNodeText
 * @brief Text node, for any non-tpl content
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

class TplNodeText extends TplNode
{
    public function __construct(protected string $content)
    {
        parent::__construct();
    }

    public function compile(Template $tpl): string
    {
        return $this->content;
    }

    public function getTag(): string
    {
        return 'TEXT';
    }
}
