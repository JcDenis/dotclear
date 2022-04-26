<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

// Dotclear\Module\TraitPrependPublic
use Dotclear\App;

/**
 * Module public trait Prepend.
 *
 * @ingroup  Module
 */
trait TraitPrependPublic
{
    /** This method is not used on Public process. */
    public function checkModule(): bool
    {
        return true;
    }

    /** This method is not used on Public process. */
    public function installModule(): ?bool
    {
        return null;
    }

    /**
     * Add template path.
     *
     * Helper for modules to add their template set
     */
    public function addTemplatePath(): void
    {
        if (is_dir($this->define()->root() . '/templates/')) {
            App::core()->behavior()->add('publicBeforeDocument', function () {
                $tplset = App::core()->themes()->getModule((string) App::core()->blog()->settings()->get('system')->get('theme'))->templateset();
                App::core()->template()->setPath(
                    App::core()->template()->getPath(),
                    $this->define()->root() . '/templates/' . (!empty($tplset) && is_dir($this->define()->root() . '/templates/' . $tplset) ? $tplset : App::core()->config()->get('template_default'))
                );
            });
        }
    }

    /**
     * Helper to check if current blog theme is this module.
     *
     * @return bool True if blog theme is this modle
     */
    protected function isTheme()
    {
        return App::core()->blog()->settings()->get('system')->get('theme') == $this->define()->id();
    }
}
