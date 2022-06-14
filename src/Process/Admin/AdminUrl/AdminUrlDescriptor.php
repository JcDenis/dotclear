<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\AdminUrl;

// Dotclear\Process\Admin\Adminurl\AdminUrlDescriptor

/**
 * Admin URL handler.
 *
 * @ingroup  Admin Url Handler
 */
final class AdminUrlDescriptor
{
    /**
     * Constructor.
     *
     * @param string $name   The URL handler name
     * @param string $class  The class name (with namespace)
     * @param array  $params The query string params (optional)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly array $params = [],
    ) {
        // We do not check if class exists hrere as it consumes memory
        // it will be checked only on call on Doclear\Process\Admin\Process::adminLoadPage
    }
}
