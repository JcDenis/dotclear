<?php
/**
 * @note Dotclear\Module\TraitPrependPublic
 * @brief Dotclear Module public trait Prepend
 *
 * @ingroup  Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

trait TraitPrependPublic
{
    /**
     * Not used on Public process.
     *
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    public function checkModule(): bool
    {
        return true;
    }

    /**
     * Not used on Public process.
     *
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
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
            dotclear()->behavior()->add('publicBeforeDocument', function () {
                $tplset = dotclear()->themes()->getModule((string) dotclear()->blog()->settings()->get('system')->get('theme'))->templateset();
                dotclear()->template()->setPath(
                    dotclear()->template()->getPath(),
                    $this->define()->root() . '/templates/' . (!empty($tplset) && is_dir($this->define()->root() . '/templates/' . $tplset) ? $tplset : dotclear()->config()->get('template_default'))
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
        return dotclear()->blog()->settings()->get('system')->get('theme') == $this->define()->id();
    }
}
