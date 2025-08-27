<?php

/**
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Core;

use dcCore;
use Dotclear\Helper\RestServer;
use Throwable;

/**
 * @brief   Rest server handler.
 *
 * This class extends Dotclear\Helper\RestServer to handle dcCore instance in each rest method call (XML response only).
 * Instance of this class is provided by App::rest().
 *
 * Rest class uses RestServer (class that RestInterface interface) constants.
 *
 * @since   2.28, container services have been added to constructor
 * @since   2.36, constructor arguments has been replaced by Core instance
 */
class Rest extends RestServer
{
    /**
     * Constructs a new instance.
     *
     * @param   Core    $core   The core container
     */
    public function __construct(
        protected Core $core
    ) {
        parent::__construct();
    }

    /**
     * @todo    Remove old dcCore from RestServer::serve returned parent parameters
     */
    public function serve(string $encoding = 'UTF-8', int $format = parent::XML_RESPONSE, $param = null): bool
    {
        if (isset($_REQUEST['json'])) {
            // No need to use dcCore::app() with JSON response
            return parent::serve($encoding, parent::JSON_RESPONSE);
        }

        // Use dcCore::app() as supplemental parameter to ensure retro-compatibility
        return parent::serve($encoding, parent::XML_RESPONSE, dcCore::app());
    }

    public function enableRestServer(bool $serve = true): void
    {
        if ($this->core->config()->coreUpgrade() !== '') {
            try {
                if ($serve && file_exists($this->core->config()->coreUpgrade())) {
                    // Remove watchdog file
                    unlink($this->core->config()->coreUpgrade());
                } elseif (!$serve && !file_exists($this->core->config()->coreUpgrade())) {
                    // Create watchdog file
                    touch($this->core->config()->coreUpgrade());
                }
            } catch (Throwable) {
            }
        }
    }

    public function serveRestRequests(): bool
    {
        return !file_exists($this->core->config()->coreUpgrade()) && $this->core->config()->allowRestServices();
    }
}
