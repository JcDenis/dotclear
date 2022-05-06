<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

// Dotclear\Plugin\LegacyEditor\Admin\Prepend
use Dotclear\App;
use Dotclear\Modules\ModulePrepend;

/**
 * Admin prepend for plugin LegacyEditor.
 *
 * @ingroup  Plugin LegacyEditor
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        if (!App::core()->blog()->settings()->get('LegacyEditor')->get('active')) {
            return;
        }

        App::core()->wiki()->initWikiPost();
        App::core()->formater()->addEditorFormater('LegacyEditor', 'xhtml', fn ($s) => $s);
        App::core()->formater()->addEditorFormater('LegacyEditor', 'wiki', [App::core()->wiki(), 'wikiTransform']);

        new LegacyEditorBehavior();
        new LegacyEditorRest();
    }

    public function installModule(): ?bool
    {
        App::core()->blog()->settings()->get('LegacyEditor')->put('active', true, 'boolean', 'LegacyEditor plugin activated ?', false, true);

        return true;
    }
}
