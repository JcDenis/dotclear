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

use Dotclear\Module\AbstractDefine;
use Dotclear\Module\TraitDefine;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitDefineStore
{
    use TraitDefine;

    /**
     * Set module file URL
     *
     * @param   string  The module file URL
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setFile(string $file): AbstractDefine
    {
        $this->properties['file'] = $file;

        return $this;
    }

    /**
     * Set module setion
     *
     * @param   string  The module section
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setSection(string $section): AbstractDefine
    {
        $this->properties['section'] = $section;

        return $this;
    }

    /**
     * Set module tags
     *
     * @param   array   The module tags
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setTags(array $tags): AbstractDefine
    {
        $this->properties['tags'] = $tags;

        return $this;
    }

    /**
     * Set module score
     *
     * @param   int     The module score
     *
     * @return  AbstractDefine  Self instance
     */
    public function setScore(int $score): AbstractDefine
    {
        $this->properties['score'] = $score;

        return $this;
    }

    /**
     * Set module screenshot URL
     *
     * @param   string  The module screenshot URL
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setScreenshot(string $screenshot): AbstractDefine
    {
        $this->properties['screenshot'] = $screenshot;

        return $this;
    }

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

    public function screenshot(): string
    {
        return $this->properties['screenshot'] ?: '';
    }

    protected function checkDefineStore(): void
    {
        $this->properties = array_merge(
            [
                'section' => '',
                'tags'    => [],
                'score'   => 0,
                'screenshot'   => '',
            ],
            $this->properties
        );

        foreach($this->properties['tags'] as $k => $v) {
            if (!is_string($v)) {
                unset($this->properties['tags'][$k]);
            }
        }

        $this->checkDefine();
    }
}
