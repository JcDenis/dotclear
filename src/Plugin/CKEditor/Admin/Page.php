<?php
/**
 * @class Dotclear\Plugin\CKEditor\Admin\Page
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginCKEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\CKEditor\Admin;

use ArrayObject;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Module\AbstractPage;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    protected $namespaces = ['dcckeditor'];

    private $ckes;

    protected function getPermissions(): string|null|false
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        $s = dotclear()->blog()->settings()->dcckeditor;
        $this->ckes = new ArrayObject([]);
        $this->ckes->is_admin                    = dotclear()->user()->check('admin,contentadmin', dotclear()->blog()->id) || dotclear()->user()->isSuperAdmin();
        $this->ckes->active                      = $s->active;
        $this->ckes->alignment_buttons           = $s->alignment_buttons;
        $this->ckes->list_buttons                = $s->list_buttons;
        $this->ckes->textcolor_button            = $s->textcolor_button;
        $this->ckes->background_textcolor_button = $s->background_textcolor_button;
        $this->ckes->custom_color_list           = $s->custom_color_list;
        $this->ckes->colors_per_row              = $s->colors_per_row;
        $this->ckes->cancollapse_button          = $s->cancollapse_button;
        $this->ckes->format_select               = $s->format_select;
        $this->ckes->format_tags                 = $s->format_tags;
        $this->ckes->table_button                = $s->table_button;
        $this->ckes->clipboard_buttons           = $s->clipboard_buttons;
        $this->ckes->action_buttons              = $s->action_buttons;
        $this->ckes->disable_native_spellchecker = $s->disable_native_spellchecker;
        $this->ckes->was_actived                 = $s->active;

        if (!empty($_POST['saveconfig'])) {
            try {
                $this->ckes->active = (empty($_POST['dcckeditor_active'])) ? false : true;
                dotclear()->blog()->settings()->dcckeditor->put('active', $this->ckes->active, 'boolean');

                # change other settings only if they were in html page
                if ($this->ckes->was_actived) {
                    $this->ckes->alignement_buttons = (empty($_POST['dcckeditor_alignment_buttons'])) ? false : true;
                    $s->put('alignment_buttons', $this->ckes->alignement_buttons, 'boolean');

                    $this->ckes->list_buttons = (empty($_POST['dcckeditor_list_buttons'])) ? false : true;
                    $s->put('list_buttons', $this->ckes->list_buttons, 'boolean');

                    $this->ckes->textcolor_button = (empty($_POST['dcckeditor_textcolor_button'])) ? false : true;
                    $s->put('textcolor_button', $this->ckes->textcolor_button, 'boolean');

                    $this->ckes->background_textcolor_button = (empty($_POST['dcckeditor_background_textcolor_button'])) ? false : true;
                    $s->put('background_textcolor_button', $this->ckes->background_textcolor_button, 'boolean');

                    $this->ckes->custom_color_list = str_replace(['#', ' '], '', $_POST['dcckeditor_custom_color_list']);
                    $s->put('custom_color_list', $this->ckes->custom_color_list, 'string');

                    $this->ckes->colors_per_row = abs((int) $_POST['dcckeditor_colors_per_row']);
                    $s->put('colors_per_row', $this->ckes->colors_per_row);

                    $this->ckes->cancollapse_button = (empty($_POST['dcckeditor_cancollapse_button'])) ? false : true;
                    $s->put('cancollapse_button', $this->ckes->cancollapse_button, 'boolean');

                    $this->ckes->format_select = (empty($_POST['dcckeditor_format_select'])) ? false : true;
                    $s->put('format_select', $this->ckes->format_select, 'boolean');

                    # default tags : p;h1;h2;h3;h4;h5;h6;pre;address
                    $this->ckes->format_tags = 'p;h1;h2;h3;h4;h5;h6;pre;address';
                    $allowed_tags           = explode(';', $this->ckes->format_tags);
                    if (!empty($_POST['dcckeditor_format_tags'])) {
                        $tags     = explode(';', $_POST['dcckeditor_format_tags']);
                        $new_tags = true;
                        foreach ($tags as $tag) {
                            if (!in_array($tag, $allowed_tags)) {
                                $new_tags = false;

                                break;
                            }
                        }
                        if ($new_tags) {
                            $this->ckes->format_tags = $_POST['dcckeditor_format_tags'];
                        }
                    }
                    $s->put('format_tags', $this->ckes->format_tags, 'string');

                    $this->ckes->table_button = (empty($_POST['dcckeditor_table_button'])) ? false : true;
                    $s->put('table_button', $this->ckes->table_button, 'boolean');

                    $this->ckes->clipboard_buttons = (empty($_POST['dcckeditor_clipboard_buttons'])) ? false : true;
                    $s->put('clipboard_buttons', $this->ckes->clipboard_buttons, 'boolean');

                    $this->ckes->action_buttons = (empty($_POST['dcckeditor_action_buttons'])) ? false : true;
                    $s->put('action_buttons', $this->ckes->action_buttons, 'boolean');

                    $this->ckes->disable_native_spellchecker = (empty($_POST['dcckeditor_disable_native_spellchecker'])) ? false : true;
                    $s->put('disable_native_spellchecker', $this->ckes->disable_native_spellchecker, 'boolean');
                }

                dotclear()->notice()->addSuccessNotice(__('The configuration has been updated.'));
                dotclear()->adminurl()->redirect('admin.plugin.CKEditor');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle('CKEditor')
            ->setPageHelp('dcCKEditor')
            ->setPageBreadcrumb([__('Plugins') => '', __('dcCKEditor') => ''])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if ($this->ckes->is_admin) {
            echo '
            <h3 class="hidden-if-js">' . __('Settings') . '</h3>
            <form action="' . dotclear()->adminurl()->root() . '" enctype="multipart/form-data" method="post">
            <div class="fieldset">
            <h3>' . __('Plugin activation') . '</h3>
            <p><label class="classic" for="dcckeditor_active">' .
            Form::checkbox('dcckeditor_active', 1, $this->ckes->active) .
            __('Enable dcCKEditor plugin') . '</label></p>
            </div>';

            if ($this->ckes->active) {
                echo '
                <div class="fieldset">
                <h3>' . __('Options') . '</h3>
                <p>' .
                Form::checkbox('dcckeditor_alignment_buttons', 1, $this->ckes->alignment_buttons) . '
                <label class="classic" for="dcckeditor_alignment_buttons">' . __('Add alignment buttons') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_list_buttons', 1, $this->ckes->list_buttons) . '
                <label class="classic" for="dcckeditor_list_buttons">' . __('Add lists buttons') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_textcolor_button', 1, $this->ckes->textcolor_button) . '
                <label class="classic" for="dcckeditor_textcolor_button">' . __('Add text color button') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_background_textcolor_button', 1, $this->ckes->background_textcolor_button) . '
                <label class="classic" for="dcckeditor_background_textcolor_button">' . __('Add background text color button') . '</label>
                </p>
                <p class="area">
                <label for="dcckeditor_custom_color_list">' . __('Custom colors list:') . '</label>' .
                Form::textarea('dcckeditor_custom_color_list', 60, 5, ['default' => Html::escapeHTML($this->ckes->custom_color_list)]) . '
                </p>
                <p class="clear form-note">' .
                __('Add colors without # separated by a comma.') . '<br />' .
                __('Leave empty to use the default palette:') . '
                <blockquote><pre><code>1abc9c,2ecc71,3498db,9b59b6,4e5f70,f1c40f,16a085,27ae60,2980b9,8e44ad,2c3e50,f39c12,e67e22,e74c3c,ecf0f1,95a5a6,dddddd,ffffff,d35400,c0392b,bdc3c7,7f8c8d,999999,000000</code></pre></blockquote>' .
                __('Example of custom color list:') . '
                <blockquote><pre><code>000,800000,8b4513,2f4f4f,008080,000080,4b0082,696969,b22222,a52a2a,daa520,006400,40e0d0,0000cd,800080,808080,f00,ff8c00,ffd700,008000,0ff,00f,ee82ee,a9a9a9,ffa07a,ffa500,ffff00,00ff00,afeeee,add8e6,dda0dd,d3d3d3,fff0f5,faebd7,ffffe0,f0fff0,f0ffff,f0f8ff,e6e6fa,fff</code></pre></blockquote>
                </p>
                <p class="field">
                <label for="dcckeditor_colors_per_row">' . __('Colors per row in palette:') . ' </label>' .
                Form::number('dcckeditor_colors_per_row', ['min' => 0, 'max' => 16, 'default' => $this->ckes->colors_per_row]) . '
                </p>
                <p class="clear form-note">' . __('Leave empty to use default (6)') . '</p>
                <p>' .
                Form::checkbox('dcckeditor_cancollapse_button', 1, $this->ckes->cancollapse_button) . '
                <label class="classic" for="dcckeditor_cancollapse_button">' . __('Add collapse button') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_format_select', 1, $this->ckes->format_select) . '
                <label class="classic" for="dcckeditor_format_select">' . __('Add format selection') . '</label>
                </p>
                <p>
                <label class="classic" for="dcckeditor_format_tags">' . __('Custom formats') . '</label>' .
                Form::field('dcckeditor_format_tags', 100, 255, $this->ckes->format_tags) . '
                </p>
                <p class="clear form-note">' . __('Default formats are p;h1;h2;h3;h4;h5;h6;pre;address') . '</p>
                <p>' .
                Form::checkbox('dcckeditor_table_button', 1, $this->ckes->table_button) . '
                <label class="classic" for="dcckeditor_table_button">' . __('Add table button') .'</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_clipboard_buttons', 1, $this->ckes->clipboard_buttons) . '
                <label class="classic" for="dcckeditor_clipboard_buttons">' . __('Add clipboard buttons') . '</label>
                </p>
                <p class="clear form-note">' . __('Copy, Paste, Paste Text, Paste from Word') . '</p>
                <p>' .
                Form::checkbox('dcckeditor_action_buttons', 1, $this->ckes->action_buttons) . '
                <label class="classic" for="dcckeditor_action_buttons">' . __('Add undo/redo buttons') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_disable_native_spellchecker', 1, $this->ckes->disable_native_spellchecker) . '
                <label class="classic" for="dcckeditor_disable_native_spellchecker">' .
                __('Disables the built-in spell checker if the browser provides one') . '
                </label>
                </p>
                </div>';
            }

            echo '
            <p><input name="p" type="hidden" value="dcCKEditor"/>' .
            dotclear()->adminurl()->getHiddenFormFields('admin.plugin.CKEditor', [], true) . '
            <input name="saveconfig" type="submit" value="' . __('Save configuration') . '"/>
            <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />
            </p>
            </form>';
        }
    }
}
