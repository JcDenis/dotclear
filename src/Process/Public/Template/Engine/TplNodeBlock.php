<?php
/**
 * @note Dotclear\Process\Public\Template\Engine\TplNodeBlock
 * @brief Block node, for all <tpl:Tag>...</tpl:Tag>
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

use ArrayObject;

class TplNodeBlock extends TplNode
{
    protected $closed  = false;
    protected $content = '';

    public function __construct(protected string $tag, protected array $attr)
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

            return $tpl->compileBlockNode($this->tag, new ArrayObject($this->attr), $content);
        }
        // if tag has not been closed, silently ignore its content...
        return '';
    }

    public function getTag()
    {
        return $this->tag;
    }
}
