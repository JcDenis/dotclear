<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ThemeEditor\Admin;

// Dotclear\Plugin\ThemeEditor\Admin\Prepend
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\File\Path;
use Dotclear\Modules\ModuleDefine;
use Dotclear\Modules\ModulePrepend;
use Exception;

/**
 * Admin prepend for plugin ThemeEditor.
 *
 * @ingroup  Plugin ThemeEditor
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        App::core()->behavior('adminAfterDetailCurrentTheme')->add([$this, 'behaviorAdminCurrentThemeDetails']);
        App::core()->behavior('adminBeforeUserOptionsUpdate')->add([$this, 'behaviorAdminBeforeUserOptionsUpdate']);
        App::core()->behavior('adminPreferencesForm')->add([$this, 'behaviorAdminPreferencesForm']);
    }

    public function behaviorAdminCurrentThemeDetails(ModuleDefine $module): string
    {
        if ('default' != $module->id() && App::core()->user()->isSuperAdmin()) {
            // Check if it's not an officially distributed theme
            $path = App::core()->themes()->getPaths();
            if (!App::core()->isProductionMode()
                || !str_contains(Path::real($module->root(), false), Path::real((string) array_pop($path), false))
                || !App::core()->themes()->isDistributedModule($module->id())
            ) {
                return '<p><a href="' . App::core()->adminurl()->get('admin.plugin.ThemeEditor') . '" class="button">' . __('Edit theme files') . '</a></p>';
            }
        }

        return '';
    }

    public function behaviorAdminBeforeUserOptionsUpdate(Cursor $cur, string $userID): void
    {
        // Get and store user's prefs for plugin options
        try {
            App::core()->user()->preference()->get('interface')->put('colorsyntax', !GPC::post()->empty('colorsyntax'), 'boolean');
            App::core()->user()->preference()->get('interface')->put('colorsyntax_theme', GPC::post()->string('colorsyntax_theme'));
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }
    }

    public function behaviorAdminPreferencesForm($user): void
    {
        // Add fieldset for plugin options
        $current_theme = (string) App::core()->user()->preference()->get('interface')->get('colorsyntax_theme') ?: 'default';
        $themes_list   = App::core()->resource()->getCodeMirrorThemes();
        $themes_combo  = [__('Default') => ''];
        foreach ($themes_list as $theme) {
            $themes_combo[$theme] = $theme;
        }

        echo '<div class="fieldset two-cols clearfix">' .
        '<h5 id="themeEditor_prefs">' . __('Syntax highlighting') . '</h5>';
        echo '<div class="col">' .
        '<p><label for="colorsyntax" class="classic">' .
        Form::checkbox('colorsyntax', 1, (int) App::core()->user()->preference()->get('interface')->get('colorsyntax')) . '</label>' .
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
        echo App::core()->resource()->loadCodeMirror('', false, ['javascript']);
        if ('default' !== $current_theme) {
            echo App::core()->resource()->js('codemirror/theme/' . $current_theme . '.css');
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
        echo App::core()->resource()->json('theme_editor_current', ['theme' => $current_theme]) .
        App::core()->resource()->load('theme.js', 'Plugin', 'ThemeEditor');
        echo '</div>';
        echo '</div>';
    }
}
