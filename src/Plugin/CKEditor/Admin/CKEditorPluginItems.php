<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\CKEditor\Admin;

// Dotclear\Plugin\CKEditor\Admin\CKEditorPluginItems

/**
 * CKEditor plugin items.
 *
 * @ingroup  Plugin CKEditor
 */
class CKEditorPluginItems
{
    /**
     * @var array<int,CKEditorPluginItem> $stack
     *                                    The plugins items stack
     */
    private $stack;

    /**
     * Add a plugin.
     *
     * @param CKEditorPluginItem $item The plugin item
     */
    public function addItem(CKEditorPluginItem $item): void
    {
        $this->stack[] = $item;
    }

    /**
     * Get plugins.
     *
     * @return array<int,CKEditorPluginItem> The plugins items
     */
    public function dumpItems(): array
    {
        return $this->stack;
    }
}
