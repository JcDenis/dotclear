<?php
/**
 * @class Dotclear\Module\TraitPrependPublic
 * @brief Dotclear Module public trait Prepend
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitPrependPublic
{
    protected static $template_path = '';

    # Module check is not used on Public process
    public static function checkModule(): bool
    {
        return true;
    }

    # Module check is not used on Public process
    public static function installModule(): ?bool
    {
        return null;
    }

    /**
     * Add template path
     *
     * Helper for modules to add their template set
     */
    public static function addTemplatePath(): void
    {
        static::$template_path = static::$define->root() . '/templates/';
        if (is_dir(static::$template_path)) {
            dotclear()->behavior()->add('publicBeforeDocument', function () {
                $tplset = dotclear()->themes->getModule((string) dotclear()->blog()->settings()->system->theme)->templateset();
                dotclear()->template()->setPath(
                    dotclear()->template()->getPath(),
                    static::$template_path . (!empty($tplset) && is_dir(static::$template_path . $tplset) ? $tplset : dotclear()->config()->template_default)
                );
            });
        }
    }
}
