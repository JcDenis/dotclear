<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template\Engine;

// Dotclear\Process\Public\Template\Engine\TplNodeBlock

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
    protected $closed  = false;
    protected $content = '';

    public function __construct(protected string $tag, protected TplAttr $attr)
    {
        parent::__construct();
    }

    public function setClosing()
    {
        $this->closed = true;
    }

    public function isClosed()
    {
        return $this->closed;
    }

    public function compile(Template $tpl): string
    {
        if ($this->closed) {
            $content = parent::compile($tpl);

            return $tpl->compileBlockNode($this->tag, $this->attr, $content);
        }
        // if tag has not been closed, silently ignore its content...
        return '';
    }

    public function getTag()
    {
        return $this->tag;
    }
}
