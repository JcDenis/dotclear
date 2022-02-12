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
     * Call key value from: config('my_key'); or config()->my_key;
     *
     * @param   string  The key
     *
     * @return  mixed   The value or Configuration instance
     */
    public function config(string $key = '')
    {
        return empty($key) ? $this->config : $this->config->{$key};
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
