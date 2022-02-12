<?php
/**
 * @class Dotclear\Plugin\ThemeEditor\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginThemeEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ThemeEditor\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Plugin\SimpleMenu\Lib\SimpleMenuWidgets;

use Dotclear\Module\AbstractDefine;
use Dotclear\Database\Cursor;
use Dotclear\Admin\Page;
use Dotclear\Html\Form;
use Dotclear\File\Path;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        dotclear()->behaviors->add('adminCurrentThemeDetails', [__CLASS__, 'behaviorAdminCurrentThemeDetails']);
        dotclear()->behaviors->add('adminBeforeUserOptionsUpdate', [__CLASS__, 'behaviorAdminBeforeUserOptionsUpdate']);
        dotclear()->behaviors->add('adminPreferencesForm', [__CLASS__, 'behaviorAdminPreferencesForm']);
    }

    public static function behaviorAdminCurrentThemeDetails(AbstractDefine $theme): string
    {
        if ($theme->id() != 'default' && dotclear()->auth->isSuperAdmin()) {
            // Check if it's not an officially distributed theme
            $path = dotclear()->themes->getModulesPath();
            if (dotclear()->config()->run_level >= DOTCLEAR_RUN_DEVELOPMENT
                || false === strpos(Path::real($theme->root()), Path::real((string) array_pop($path)))
                || !dotclear()->themes->isDistributedModule($theme->id())
            ) {
                return '<p><a href="' . dotclear()->adminurl->get('admin.plugin.ThemeEditor') . '" class="button">' . __('Edit theme files') . '</a></p>';
            }
        }

        return '';
    }

    public static function behaviorAdminBeforeUserOptionsUpdate(Cursor $cur, string $userID): void
    {
        // Get and store user's prefs for plugin options
        dotclear()->auth->user_prefs->addWorkspace('interface');

        try {
            dotclear()->auth->user_prefs->interface->put('colorsyntax', !empty($_POST['colorsyntax']), 'boolean');
            dotclear()->auth->user_prefs->interface->put('colorsyntax_theme',
                (!empty($_POST['colorsyntax_theme']) ? $_POST['colorsyntax_theme'] : ''));
        } catch (\Exception $e) {
            dotclear()->error($e->getMessage());
        }
    }

    public static function behaviorAdminPreferencesForm(): void
    {
        // Add fieldset for plugin options
        dotclear()->auth->user_prefs->addWorkspace('interface');

        $themes_list  = Page::getCodeMirrorThemes();
        $themes_combo = [__('Default') => ''];
        foreach ($themes_list as $theme) {
            $themes_combo[$theme] = $theme;
        }

        echo
        '<div class="fieldset two-cols clearfix">' .
        '<h5 id="themeEditor_prefs">' . __('Syntax highlighting') . '</h5>';
        echo
        '<div class="col">' .
        '<p><label for="colorsyntax" class="classic">' .
        Form::checkbox('colorsyntax', 1, (int) dotclear()->auth->user_prefs->interface->colorsyntax) . '</label>' .
        __('Syntax highlighting in theme editor') .
            '</p>';
        if (count($themes_combo) > 1) {
            echo
            '<p><label for="colorsyntax_theme" class="classic">' . __('Theme:') . '</label> ' .
            Form::combo('colorsyntax_theme', $themes_combo,
                [
                    'default' => (string) dotclear()->auth->user_prefs->interface->colorsyntax_theme
                ]) .
                '</p>';
        } else {
            echo Form::hidden('colorsyntax_theme', '');
        }
        echo '</div>';
        echo '<div class="col">';
        echo Page::jsLoadCodeMirror('', false, ['javascript']);
        foreach ($themes_list as $theme) {
            echo Page::cssLoad('js/codemirror/theme/' . $theme . '.css');
        }
        echo '
<textarea id="codemirror" name="codemirror" readonly="true">
function findSequence(goal) {
  function find(start, history) {
    if (start == goal)
      return history;
    else if (start > goal)
      return null;
    else
      return find(start + 5, "(" + history + " + 5)") ||
             find(start * 3, "(" + history + " * 3)");
  }
  return find(1, "1");
}</textarea>';
        echo
        Page::jsJson('theme_editor_current', ['theme' => dotclear()->auth->user_prefs->interface->colorsyntax_theme != '' ? dotclear()->auth->user_prefs->interface->colorsyntax_theme : 'default']) .
        Page::jsLoad('?mf=Plugin/ThemeEditor/files/js/theme.js');
        echo '</div>';
        echo '</div>';
    }
}
