<?php
/**
 * @note Dotclear\Plugin\ThemeEditor\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginThemeEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ThemeEditor\Admin;

use Dotclear\Database\Cursor;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\File\Path;
use Dotclear\Module\AbstractDefine;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Exception;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        dotclear()->behavior()->add('adminCurrentThemeDetails', [$this, 'behaviorAdminCurrentThemeDetails']);
        dotclear()->behavior()->add('adminBeforeUserOptionsUpdate', [$this, 'behaviorAdminBeforeUserOptionsUpdate']);
        dotclear()->behavior()->add('adminPreferencesForm', [$this, 'behaviorAdminPreferencesForm']);
    }

    public function behaviorAdminCurrentThemeDetails(AbstractDefine $theme): string
    {
        if ('default' != $theme->id() && dotclear()->user()->isSuperAdmin()) {
            // Check if it's not an officially distributed theme
            $path = dotclear()->themes()->getModulesPath();
            if (!dotclear()->production()
                || !str_contains(Path::real($theme->root()), Path::real((string) array_pop($path)))
                || !dotclear()->themes()->isDistributedModule($theme->id())
            ) {
                return '<p><a href="' . dotclear()->adminurl()->get('admin.plugin.ThemeEditor') . '" class="button">' . __('Edit theme files') . '</a></p>';
            }
        }

        return '';
    }

    public function behaviorAdminBeforeUserOptionsUpdate(Cursor $cur, string $userID): void
    {
        // Get and store user's prefs for plugin options
        try {
            dotclear()->user()->preference()->get('interface')->put('colorsyntax', !empty($_POST['colorsyntax']), 'boolean');
            dotclear()->user()->preference()->get('interface')->put(
                'colorsyntax_theme',
                (!empty($_POST['colorsyntax_theme']) ? $_POST['colorsyntax_theme'] : '')
            );
        } catch (Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }
    }

    public function behaviorAdminPreferencesForm(): void
    {
        // Add fieldset for plugin options
        $current_theme = (string) dotclear()->user()->preference()->get('interface')->get('colorsyntax_theme') ?: 'default';
        $themes_list   = dotclear()->resource()->getCodeMirrorThemes();
        $themes_combo  = [__('Default') => ''];
        foreach ($themes_list as $theme) {
            $themes_combo[$theme] = $theme;
        }

        echo '<div class="fieldset two-cols clearfix">' .
        '<h5 id="themeEditor_prefs">' . __('Syntax highlighting') . '</h5>';
        echo '<div class="col">' .
        '<p><label for="colorsyntax" class="classic">' .
        Form::checkbox('colorsyntax', 1, (int) dotclear()->user()->preference()->get('interface')->get('colorsyntax')) . '</label>' .
        __('Syntax highlighting in theme editor') .
            '</p>';
        if (1 < count($themes_combo)) {
            echo '<p><label for="colorsyntax_theme" class="classic">' . __('Theme:') . '</label> ' .
            Form::combo(
                'colorsyntax_theme',
                $themes_combo,
                [
                    'default' => $current_theme,
                ]
            ) .
                '</p>';
        } else {
            echo Form::hidden('colorsyntax_theme', '');
        }
        echo '</div>';
        echo '<div class="col">';
        echo dotclear()->resource()->loadCodeMirror('', false, ['javascript']);
        if ('default' !== $current_theme) {
            echo dotclear()->resource()->js('codemirror/theme/' . $current_theme . '.css');
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
        echo dotclear()->resource()->json('theme_editor_current', ['theme' => $current_theme]) .
        dotclear()->resource()->load('theme.js', 'Plugin', 'ThemeEditor');
        echo '</div>';
        echo '</div>';
    }
}
