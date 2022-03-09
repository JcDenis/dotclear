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

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        $s = dotclear()->blog()->settings()->addNamespace('LegacyEditor');
        if (!$s->active) {

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
        $settings = dotclear()->blog()->settings();
        $settings->addNamespace('LegacyEditor');
        $settings->LegacyEditor->put('active', true, 'boolean', 'LegacyEditor plugin activated ?', false, true);

        return true;
    }
}
