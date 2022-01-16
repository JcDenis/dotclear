<?php
/**
 * @class Dotclear\Module\Store\DefineStore
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Store;

use Dotclear\Module\Define;
use Dotclear\Module\Theme\DefineTheme;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class DefineStore extends DefineTheme
{
    /**
     * Set module setion
     *
     * @param   string  The module section
     *
     * @return  Define  Self instance
     */
    protected function setSection(string $section): Define
    {
        $this->section = $section;

        return $this;
    }

    /**
     * Set module tags
     *
     * @param   array   The module tags
     *
     * @return  Define  Self instance
     */
    protected function setTags(array $tags): Define
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Set module score
     *
     * @param   int     The module score
     *
     * @return  Define  Self instance
     */
    protected function setScore(int $score): Define
    {
        $this->score = $score;

        return $this;
    }

    public function section(): string
    {
        return $this->properties['section'];
    }

    public function tags(): array
    {
        return $this->properties['tags'];
    }

    public function score(): int
    {
        return $this->properties['score'];
    }

    protected function checkProperties(): void
    {
        if (!isset($this->properties['section'])) {
            $this->properties['section'] = '';
        }
        if (!isset($this->properties['tags'])) {
            $this->properties['tags'] = [];
        }
        foreach($this->properties['tags'] as $k => $v) {
            if (!is_string($v)) {
                unset($this->properties['tags'][$k]);
            }
        }
        if (!isset($this->properties['score'])) {
            $this->properties['score'] = 0;
        }

        parent::checkProperties();
    }
}
