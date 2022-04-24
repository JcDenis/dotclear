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
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

/**
 * Admin prepend for plugin LegacyEditor.
 *
 * @ingroup  Plugin LegacyEditor
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        if (!dotclear()->blog()->settings()->get('LegacyEditor')->get('active')) {
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
        dotclear()->blog()->settings()->get('LegacyEditor')->put('active', true, 'boolean', 'LegacyEditor plugin activated ?', false, true);

        return true;
    }
}
