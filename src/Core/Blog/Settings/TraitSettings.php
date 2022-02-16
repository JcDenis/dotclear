<?php
/**
 * @class Dotclear\Core\Blog\Setting\TraitSettings
 * @brief Dotclear trait Log
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Settings;

use Dotclear\Core\Blog\Settings\Settings;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitSettings
{
    /** @var    Settings   Settings instance */
    private $settings;

    /**
     * Get instance
     *
     * @return  Settings   Settings instance
     */
    public function settings(?string $blog_id = null, bool $reload = false): Settings
    {
        if (null !== $blog_id || $reload) {
            $this->settings = new Settings($blog_id);
        }

        return $this->settings;
    }
}
