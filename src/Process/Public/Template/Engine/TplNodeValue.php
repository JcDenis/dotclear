<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template\Engine;

// Dotclear\Process\Public\Template\Engine\TplNodeValue
use ArrayObject;

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
    protected $content = '';

    public function __construct(protected string $tag, protected array $attr, protected string $str_attr)
    {
        parent::__construct();
    }

    public function compile(Template $tpl): string
    {
        return $tpl->compileValueNode($this->tag, new ArrayObject($this->attr), $this->str_attr);
    }

    public function getTag(): string
    {
        return $this->tag;
    }
}
