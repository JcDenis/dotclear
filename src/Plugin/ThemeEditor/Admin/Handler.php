<?php
/**
 * @class Dotclear\Plugin\ThemeEditor\Admin\Handler
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

use stdClass;
use ArrayObject;

use Dotclear\Exception\ModuleException;
use Dotclear\File\Path;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Module\AbstractPage;
use Dotclear\Plugin\ThemeEditor\Admin\ThemeEditor;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Handler extends AbstractPage
{
    protected $workspaces = ['interface'];

    private $te_theme;
    private $te_editor;
    private $te_file;

    protected function getPermissions(): string|null|false
    {
        # Super admin
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        if (!$this->isEditableTheme()) {
            $this
                ->setPageTitle(__('Edit theme files'))
                ->setPageHelp('themeEditor')
                ->setPageBreadcrumb([
                    Html::escapeHTML(dotclear()->blog()->name) => '',
                    __('Blog appearance')                  => dotclear()->adminurl()->get('admin.blog.theme'),
                    __('Edit theme files')                 => ''
                ])
            ;

            return true;
        }

        $file_default = $this->te_file = ['c' => null, 'w' => false, 'type' => null, 'f' => null, 'default_file' => false];

        # Get interface setting
        $user_ui_colorsyntax = dotclear()->user()->preference()->interface->colorsyntax;

        # Loading themes
        $this->te_theme = dotclear()->themes->getModule((string) dotclear()->blog()->settings()->system->theme);
        $this->te_editor = new ThemeEditor();

        try {
            try {
                if (!empty($_REQUEST['tpl'])) {
                    $this->te_file = $this->te_editor->getFileContent('tpl', $_REQUEST['tpl']);
                } elseif (!empty($_REQUEST['css'])) {
                    $this->te_file = $this->te_editor->getFileContent('css', $_REQUEST['css']);
                } elseif (!empty($_REQUEST['js'])) {
                    $this->te_file = $this->te_editor->getFileContent('js', $_REQUEST['js']);
                } elseif (!empty($_REQUEST['po'])) {
                    $this->te_file = $this->te_editor->getFileContent('po', $_REQUEST['po']);
                } elseif (!empty($_REQUEST['php'])) {
                    $this->te_file = $this->te_editor->getFileContent('php', $_REQUEST['php']);
                }
            } catch (\Exception $e) {
                $this->te_file = $file_default;

                throw $e;
            }

            # Write file
            if (!empty($_POST['write'])) {
                $this->te_file['c'] = $_POST['file_content'];
                $this->te_editor->writeFile($this->te_file['type'], $this->te_file['f'], $this->te_file['c']);
            }

            # Delete file
            if (!empty($_POST['delete'])) {
                $this->te_editor->deleteFile($this->te_file['type'], $this->te_file['f']);
                dotclear()->notice()->addSuccessNotice(__('The file has been reset.'));
                dotclear()->adminurl()->redirect('admin.plugin.ThemeEditor', [$this->te_file['type'] => $this->te_file['f']]);
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }

        # Page setup
        $this
            ->setPageTitle(__('Edit theme files'))
            ->setPageHelp('themeEditor')
            ->setPageHead(static::jsConfirmClose('settings', 'menuitemsappend', 'additem', 'menuitems'))
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Blog appearance')                  => dotclear()->adminurl()->get('admin.blog.theme'),
                __('Edit theme files')                 => ''
            ])
        ;

        if ($user_ui_colorsyntax) {
            $this->setPageHead(
                dotclear()->filer()->json('dotclear_colorsyntax', ['colorsyntax' => $user_ui_colorsyntax])
            );
        }
        $this->setPageHead(
            dotclear()->filer()->json('theme_editor_msg', [
                'saving_document'    => __('Saving document...'),
                'document_saved'     => __('Document saved'),
                'error_occurred'     => __('An error occurred:'),
                'confirm_reset_file' => __('Are you sure you want to reset this file?')
            ]) .
            dotclear()->filer()->load('script.js', 'Plugin', 'ThemeEditor') .
            static::jsConfirmClose('file-form')
        );
        if ($user_ui_colorsyntax) {
            $this->setPageHead(
                static::jsLoadCodeMirror(dotclear()->user()->preference()->interface->colorsyntax_theme)
            );
        }
        $this->setPageHead(
            dotclear()->filer()->load('style.css', 'Plugin', 'ThemeEditor')
        );

        return true;
    }

    protected function getPageContent(): void
    {
        if (!$this->isEditableTheme()) {
            echo '<div class="error"><p>' . __('This theme is not editable.') . '</p>';

            return;
        }

        echo
        '<p><strong>' . sprintf(__('Your current theme on this blog is "%s".'), Html::escapeHTML($this->te_theme->name())) . '</strong></p>';

        if (dotclear()->blog()->settings()->system->theme == 'default') {
            echo '<div class="error"><p>' .  __("You can't edit default theme.") . '</p></div>';

            return;
        }

        echo
        '<div id="file-box">' .
        '<div id="file-editor">';

        if ($this->te_file['c'] === null) {
            echo '<p>' . __('Please select a file to edit.') . '</p>';
        } else {
            echo
            '<form id="file-form" action="' . dotclear()->adminurl()->root() . '" method="post">' .
            '<div class="fieldset"><h3>' . __('File editor') . '</h3>' .
            '<p><label for="file_content">' . sprintf(__('Editing file %s'), '<strong>' . $this->te_file['f']) . '</strong></label></p>' .
            '<p>' . Form::textarea('file_content', 72, 25, [
                'default'  => Html::escapeHTML($this->te_file['c']),
                'class'    => 'maximal',
                'disabled' => !$this->te_file['w']
            ]) . '</p>';

            if ($this->te_file['w']) {
                echo
                '<p><input type="submit" name="write" value="' . __('Save') . ' (s)" accesskey="s" /> ' .
                ($this->te_editor->deletableFile($this->te_file['type'], $this->te_file['f']) ? '<input type="submit" name="delete" class="delete" value="' . __('Reset') . '" />' : '') .
                dotclear()->adminurl()->getHiddenFormFields('admin.plugin.ThemeEditor', [], true) .
                    ($this->te_file['type'] ? Form::hidden([$this->te_file['type']], $this->te_file['f']) : '') .
                    '</p>';
            } else {
                echo '<p>' . __('This file is not writable. Please check your theme files permissions.') . '</p>';
            }

            echo
                '</div></form>';

            if (dotclear()->user()->preference()->interface->colorsyntax) {
                $editorMode = (!empty($_REQUEST['css']) ? 'css' :
                    (!empty($_REQUEST['js']) ? 'javascript' :
                    (!empty($_REQUEST['po']) ? 'text/plain' :
                    (!empty($_REQUEST['php']) ? 'php' :
                    'text/html'))));
                dotclear()->filer()->json('theme_editor_mode', ['mode' => $editorMode]);
                echo dotclear()->filer()->load('mode.js', 'Plugin', 'themeEditor');
                echo static::jsRunCodeMirror('editor', 'file_content', 'dotclear', dotclear()->user()->preference()->interface->colorsyntax_theme);
            }
        }

        echo
        '</div>
        </div>

        <div id="file-chooser">' .
        '<h3>' . __('Templates files') . '</h3>' .
        $this->te_editor->filesList('tpl', '<a href="' . dotclear()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;tpl=%2$s" class="tpl-link">%1$s</a>') .

        '<h3>' . __('CSS files') . '</h3>' .
        $this->te_editor->filesList('css', '<a href="' . dotclear()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;css=%2$s" class="css-link">%1$s</a>') .

        '<h3>' . __('JavaScript files') . '</h3>' .
        $this->te_editor->filesList('js', '<a href="' . dotclear()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;js=%2$s" class="js-link">%1$s</a>') .

        '<h3>' . __('Locales files') . '</h3>' .
        $this->te_editor->filesList('po', '<a href="' . dotclear()->adminurl()->get('admin.plugin.ThemeEditor'). '&amp;po=%2$s" class="po-link">%1$s</a>') .

        '<h3>' . __('PHP files') . '</h3>' .
        $this->te_editor->filesList('php', '<a href="' . dotclear()->adminurl()->get('admin.plugin.ThemeEditor') . '&amp;php=%2$s" class="php-link">%1$s</a>') .
        '</div>';
    }

    private function isEditableTheme()
    {
        $theme = dotclear()->themes->getModule((string) dotclear()->blog()->settings()->system->theme);
        if ($theme && $theme->id() != 'default' && dotclear()->user()->isSuperAdmin()) {
            $path = dotclear()->themes->getModulesPath();

            return !dotclear()->production()
                || false === strpos(Path::real($theme->root()), Path::real((string) array_pop($path)))
                || !dotclear()->themes->isDistributedModule($theme->id());
        }

        return false;
    }
}
