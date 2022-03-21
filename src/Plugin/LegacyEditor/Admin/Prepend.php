<?php
/**
 * @class Dotclear\Plugin\LegacyEditor\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginLegacyEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\LegacyEditor\Admin\LegacyEditorBehavior;
use Dotclear\Plugin\LegacyEditor\Admin\LegacyEditorRest;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        if (!dotclear()->blog()->settings()->LegacyEditor->active) {

            return;
        }

        dotclear()->wiki()->initWikiPost();
        dotclear()->formater()->addEditorFormater('LegacyEditor', 'xhtml', fn ($s) => $s);
        dotclear()->formater()->addEditorFormater('LegacyEditor', 'wiki', [dotclear()->wiki(), 'wikiTransform']);

        new LegacyEditorBehavior();
        new LegacyEditorRest();
    }

    public function installModule(): ?bool
    {
        dotclear()->blog()->settings()->LegacyEditor->put('active', true, 'boolean', 'LegacyEditor plugin activated ?', false, true);

        return true;
    }
}
