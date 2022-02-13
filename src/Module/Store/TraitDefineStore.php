<?php
/**
 * @class Dotclear\Module\Store\TraitDefineStore
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Store;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitDefineStore
{
    public function file(): string
    {
        return $this->properties['file'] ?: '';
    }

    public function section(): string
    {
        return $this->properties['section'] ?: '';
    }

    public function tags(): array
    {
        return $this->properties['tags'] ?: [];
    }

    public function score(): int
    {
        return $this->properties['score'] ?: 0;
    }

    protected function checkDefineStore(): void
    {
        $this->properties = array_merge(
            [
                'section' => '',
                'tags'    => [],
                'score'   => 0,
            ],
            $this->properties
        );

        foreach($this->properties['tags'] as $k => $v) {
            if (!is_string($v)) {
                unset($this->properties['tags'][$k]);
            }
        }
    }
}
