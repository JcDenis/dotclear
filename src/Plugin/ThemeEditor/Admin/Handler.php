<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ThemeEditor\Admin;

// Dotclear\Plugin\ThemeEditor\Admin\Handler
use Dotclear\App;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * ThemeEditor Admin page.
 *
 * @ingroup  Plugin ThemeEditor
 */
class Handler extends AbstractPage
{
    private $te_theme;
    private $te_editor;
    private $te_file;

    protected function getPermissions(): string|bool
    {
        return '';
    }

    protected function getPagePrepend(): ?bool
    {
        if (!$this->isEditableTheme()) {
            $this
                ->setPageTitle(__('Edit theme files'))
                ->setPageHelp('themeEditor')
                ->setPageBreadcrumb([
                    Html::escapeHTML(App::core()->blog()->name) => '',
                    __('Blog appearance')                       => App::core()->adminurl()->get('admin.theme'),
                    __('Edit theme files')                      => '',
                ])
            ;

            return true;
        }

        $file_default = $this->te_file = ['c' => null, 'w' => false, 'type' => null, 'f' => null, 'default_file' => false];

        // Get interface setting
        $user_ui_colorsyntax = App::core()->user()->preferences('interface')->getPreference('colorsyntax');

        // Loading themes
        $this->te_theme  = App::core()->themes()->getModule((string) App::core()->blog()->settings('system')->getSetting('theme'));
        $this->te_editor = new ThemeEditor();

        try {
            try {
                if (!GPC::request()->empty('tpl')) {
                    $this->te_file = $this->te_editor->getFileContent('tpl', GPC::request()->string('tpl'));
                } elseif (!GPC::request()->empty('css')) {
                    $this->te_file = $this->te_editor->getFileContent('css', GPC::request()->string('css'));
                } elseif (!GPC::request()->empty('js')) {
                    $this->te_file = $this->te_editor->getFileContent('js', GPC::request()->string('js'));
                } elseif (!GPC::request()->empty('po')) {
                    $this->te_file = $this->te_editor->getFileContent('po', GPC::request()->string('po'));
                } elseif (!GPC::request()->empty('php')) {
                    $this->te_file = $this->te_editor->getFileContent('php', GPC::request()->string('php'));
                }
            } catch (Exception $e) {
                $this->te_file = $file_default;

                throw $e;
            }

            // Write file
            if (!GPC::post()->empty('write')) {
                $this->te_file['c'] = GPC::post()->string('file_content');
                $this->te_editor->writeFile($this->te_file['type'], $this->te_file['f'], $this->te_file['c']);
            }

            // Delete file
            if (!GPC::post()->empty('delete')) {
                $this->te_editor->deleteFile($this->te_file['type'], $this->te_file['f']);
                App::core()->notice()->addSuccessNotice(__('The file has been reset.'));
                App::core()->adminurl()->redirect('admin.plugin.ThemeEditor', [$this->te_file['type'] => $this->te_file['f']]);
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        // Page setup
        $this
            ->setPageTitle(__('Edit theme files'))
            ->setPageHelp('themeEditor')
            ->setPageHead(App::core()->resource()->confirmClose('settings', 'menuitemsappend', 'additem', 'menuitems'))
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Blog appearance')                       => App::core()->adminurl()->get('admin.theme'),
                __('Edit theme files')                      => '',
            ])
        ;

        if ($user_ui_colorsyntax) {
            $this->setPageHead(
                App::core()->resource()->json('dotclear_colorsyntax', ['colorsyntax' => $user_ui_colorsyntax])
            );
        }
        $this->setPageHead(
            App::core()->resource()->json('theme_editor_msg', [
                'saving_document'    => __('Saving document...'),
                'document_saved'     => __('Document saved'),
                'error_occurred'     => __('An error occurred:'),
                'confirm_reset_file' => __('Are you sure you want to reset this file?'),
            ]) .
            App::core()->resource()->load('script.js', 'Plugin', 'ThemeEditor') .
            App::core()->resource()->confirmClose('file-form')
        );
        if ($user_ui_colorsyntax) {
            $this->setPageHead(
                App::core()->resource()->loadCodeMirror(App::core()->user()->preferences('interface')->getPreference('colorsyntax_theme'))
            );
        }
        $this->setPageHead(
            App::core()->resource()->load('style.css', 'Plugin', 'ThemeEditor')
        );

        return true;
    }

    protected function getPageContent(): void
    {
        if (!$this->isEditableTheme()) {
            echo '<div class="error"><p>' . __('This theme is not editable.') . '</p>';

            return;
        }

        echo '<p><strong>' . sprintf(__('Your current theme on this blog is "%s".'), Html::escapeHTML($this->te_theme->name())) . '</strong></p>';

        if ('default' == App::core()->blog()->settings('system')->getSetting('theme')) {
            echo '<div class="error"><p>' . __("You can't edit default theme.") . '</p></div>';

            return;
        }

        echo '<div id="file-box">' .
        '<div id="file-editor">';

        if (null === $this->te_file['c']) {
            echo '<p>' . __('Please select a file to edit.') . '</p>';
        } else {
            echo '<form id="file-form" action="' . App::core()->adminurl()->root() . '" method="post">' .
            '<div class="fieldset"><h3>' . __('File editor') . '</h3>' .
            '<p><label for="file_content">' . sprintf(__('Editing file %s'), '<strong>' . $this->te_file['f']) . '</strong></label></p>' .
            '<p>' . Form::textarea('file_content', 72, 25, [
                'default'  => Html::escapeHTML($this->te_file['c']),
                'class'    => 'maximal',
                'disabled' => !$this->te_file['w'],
            ]) . '</p>';

            if ($this->te_file['w']) {
                echo '<p><input type="submit" name="write" value="' . __('Save') . ' (s)" accesskey="s" /> ' .
                ($this->te_editor->deletableFile($this->te_file['type'], $this->te_file['f']) ? '<input type="submit" name="delete" class="delete" value="' . __('Reset') . '" />' : '') .
                App::core()->adminurl()->getHiddenFormFields('admin.plugin.ThemeEditor', [], true) .
                    ($this->te_file['type'] ? Form::hidden([$this->te_file['type']], $this->te_file['f']) : '') .
                    '</p>';
            } else {
                echo '<p>' . __('This file is not writable. Please check your theme files permissions.') . '</p>';
            }

            echo '</div></form>';

            if (App::core()->user()->preferences('interface')->getPreference('colorsyntax')) {
                $editorMode =
                    (!GPC::request()->empty('css') ? 'css' :
                    (!GPC::request()->empty('js') ? 'javascript' :
                    (!GPC::request()->empty('po') ? 'text/plain' :
                    (!GPC::request()->empty('php') ? 'php' :
                    'text/html'))))
                    ;
                App::core()->resource()->json('theme_editor_mode', ['mode' => $editorMode]);
                echo App::core()->resource()->load('mode.js', 'Plugin', 'themeEditor');
                echo App::core()->resource()->runCodeMirror('editor', 'file_content', 'dotclear', App::core()->user()->preferences('interface')->getPreference('colorsyntax_theme'));
            }
        }

        echo '</div>
        </div>

        <div id="file-chooser">' .
        '<h3>' . __('Templates files') . '</h3>' .
        $this->te_editor->filesList('tpl', '<a href="' . App::core()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;tpl=%2$s" class="tpl-link">%1$s</a>') .

        '<h3>' . __('CSS files') . '</h3>' .
        $this->te_editor->filesList('css', '<a href="' . App::core()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;css=%2$s" class="css-link">%1$s</a>') .

        '<h3>' . __('JavaScript files') . '</h3>' .
        $this->te_editor->filesList('js', '<a href="' . App::core()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;js=%2$s" class="js-link">%1$s</a>') .

        '<h3>' . __('Locales files') . '</h3>' .
        $this->te_editor->filesList('po', '<a href="' . App::core()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;po=%2$s" class="po-link">%1$s</a>') .

        '<h3>' . __('PHP files') . '</h3>' .
        $this->te_editor->filesList('php', '<a href="' . App::core()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;php=%2$s" class="php-link">%1$s</a>') .
        '</div>';
    }

    private function isEditableTheme(): bool
    {
        $theme = App::core()->themes()->getModule((string) App::core()->blog()->settings('system')->getSetting('theme'));
        if ($theme && 'default' != $theme->id() && App::core()->user()->isSuperAdmin()) {
            $path = App::core()->themes()->getPaths();

            return !App::core()->isProductionMode()
                || !str_contains(Path::real($theme->root(), false), Path::real((string) array_pop($path), false))
                || !App::core()->themes()->isDistributedModule($theme->id());
        }

        return false;
    }
}
