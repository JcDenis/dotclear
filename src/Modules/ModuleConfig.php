<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules;

// Dotclear\Modules\ModuleConfig
use Dotclear\Helper\Network\Http;

/**
 * Module abstract Config.
 *
 * If exists, Module Config class must extends this class.
 * It provides a simple way to add an admin form to configure module.
 *
 * @ingroup  Module Admin
 */
abstract class ModuleConfig
{
    /**
     * Constructor.
     *
     * @param string $redirection Page redirection on validation
     */
    public function __construct(private string $redirection = '')
    {
    }

    /**
     * Redirect on success.
     */
    protected function redirect(): void
    {
        Http::redirect($this->redirection);
    }

    /**
     * Get module configuration permissions.
     *
     * Returns comma separated list of permissions
     * or empty string for super admin
     *
     * @return string The permissions to configure module
     */
    public function getPermissions(): string
    {
        return '';
    }

    /**
     * Save configuration.
     *
     * Chek and save configuration form fields.
     *
     * @param array $post Http _POST fieds
     */
    abstract public function setConfiguration(array $post): void;

    /**
     * Get configuration form.
     *
     * This methods should echo Html form (only fields) content.
     */
    abstract public function getConfiguration(): void;

    // ! todo: add contextual help
}
