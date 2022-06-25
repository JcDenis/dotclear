<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\CKEditor\Admin;

// Dotclear\Plugin\CKEditor\Admin\CKEditorPluginItem

/**
 * CKEditor plugin item.
 *
 * @ingroup  Plugin CKEditor
 */
class CKEditorPluginItem
{
    /**
     * Constructor.
     *
     * @param string $name   The plugin name
     * @param string $button The plugin button
     * @param string $url    The plugin URL
     */
    public function __construct(
        public readonly string $name,
        public readonly string $button,
        public readonly string $url,
    ) {
    }
}
