<?php
/**
 * @class Dotclear\Core\Instance\Configuration
 * @brief Dotclear trait Configuration
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Configuration;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitConfiguration
{
    /** @var    Configuration   Configuration instance */
    private $config;

    /** @var    bool            Instance is initalized */
    public $has_config = false;

    /**
     * Get Configuration instance or value
     *
     * @return  Configuration|null  Configuration instance or null
     */
    public function config(): ?Configuration
    {
        return $this->config;
    }

    /**
     * Initialize configuration
     *
     * @param   array           $default    The default configration
     * @param   string|array    $config     The configration to parse
     */
    public function initConfiguration(array $default, string|array $config = []): void
    {
        if (!($this->config instanceof Configuration)) {
            $this->config = new Configuration($default, $config);
            $this->has_config = true;
        }
    }
}
