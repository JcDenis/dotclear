<?php
/**
 * @class Dotclear\Utils\Configuration
 * @brief Dotclear trait Configuration
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Utils;

use Dotclear\Utils\Configuration;

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
     * @return  mixed   Configuration instance
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
