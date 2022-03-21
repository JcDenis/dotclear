<?php
/**
 * @class Dotclear\Module\AbstractConfig
 * @brief Dotclear Module abstract Config
 *
 * If exists, Module Config class must extends this class.
 * It provides a simple way to add an admin form to configure module.
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Helper\Network\Http;

abstract class AbstractConfig
{
    private $redirection = '';

    /**
     * Constructor
     *
     * @param   string  $redir  Page redirection on validation
     */
    public function __construct(string $redirection)
    {
        $this->redirection = $redirection;
    }

    /**
     * Redirect on success
     */
    protected function redirect(): void
    {
        Http::redirect($this->redirection);
    }

    /**
     * Get module configuration permissions
     *
     * Returns null for super admin,
     * or comma separated list of permissions.
     *
     * @return  string|null     The permissions to configure module
     */
    public function getPermissions(): ?string
    {
        return null;
    }

    /**
     * Save configuration
     *
     * Chek and save configuration form fields.
     *
     * @param   array   $post   Http _POST fieds
     */
    abstract public function setConfiguration(array $post): void;

    /**
     * Get configuration form
     *
     * This methods should echo Html form (only fields) content.
     */
    abstract public function getConfiguration(): void;

    //! todo: add contextual help
}
