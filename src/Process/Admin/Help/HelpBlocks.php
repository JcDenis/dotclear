<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Help;

// Dotclear\Process\Admin\Help\HelpBlocks

/**
 * Help blocks helper.
 *
 * @ingroup  Admin Help Stack
 */
class HelpBlocks
{
    /**
     * @var array<int,string> $resources
     */
    private $resources = [];

    /**
     * @var array<int,string> $contents
     */
    private $contents = [];

    /**
     * Add an help block resource.
     *
     * @param string $id the resource file ID
     */
    public function addResource(string $id): void
    {
        $this->resources[] = $id;
    }

    /**
     * Get resources help IDs.
     *
     * @return array<int,string> The help IDs
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Check if a resources ID is set.
     *
     * @param string $id The resource help ID
     *
     * @return bool True if it exists
     */
    public function hasResource(string $id): bool
    {
        return in_array($id, $this->resources);
    }

    /**
     * Add an help block content.
     *
     * @param string $content The help block content
     */
    public function addContent(string $content): void
    {
        $this->contents[] = $content;
    }

    /**
     * Get contents help blocks.
     *
     * @return array<int,string> The contents help blocks
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * Check is there is help.
     *
     * @return bool True if there is help
     */
    public function isEmpty(): bool
    {
        return empty($this->resources) && empty($this->contents);
    }
}
