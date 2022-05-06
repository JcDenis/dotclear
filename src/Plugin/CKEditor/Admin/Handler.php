<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\CKEditor\Admin;

// Dotclear\Plugin\CKEditor\Admin\Handler
use Dotclear\App;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin page for plugin CKEditor.
 *
 * @ingroup  Plugin CKEditor
 */
class Handler extends AbstractPage
{
    /**
     * @var array<string,mixed> $ckes
     *                          CKEditor settings
     */
    private $ckes;

    protected function getPermissions(): string|bool
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        $s          = App::core()->blog()->settings()->get('dcckeditor');
        $this->ckes = [
            'is_admin'                    => App::core()->user()->check('admin,contentadmin', App::core()->blog()->id) || App::core()->user()->isSuperAdmin(),
            'active'                      => $s->get('active'),
            'alignment_buttons'           => $s->get('alignment_buttons'),
            'list_buttons'                => $s->get('list_buttons'),
            'textcolor_button'            => $s->get('textcolor_button'),
            'background_textcolor_button' => $s->get('background_textcolor_button'),
            'custom_color_list'           => $s->get('custom_color_list'),
            'colors_per_row'              => $s->get('colors_per_row'),
            'cancollapse_button'          => $s->get('cancollapse_button'),
            'format_select'               => $s->get('format_select'),
            'format_tags'                 => $s->get('format_tags'),
            'table_button'                => $s->get('table_button'),
            'clipboard_buttons'           => $s->get('clipboard_buttons'),
            'action_buttons'              => $s->get('action_buttons'),
            'disable_native_spellchecker' => $s->get('disable_native_spellchecker'),
            'was_actived'                 => $s->get('active'),
        ];

        if (!empty($_POST['saveconfig'])) {
            try {
                $this->ckes['active'] = (empty($_POST['dcckeditor_active'])) ? false : true;
                App::core()->blog()->settings()->get('dcckeditor')->put('active', $this->ckes['active'], 'boolean');

                // change other settings only if they were in html page
                if ($this->ckes['was_actived']) {
                    $this->ckes['alignment_buttons'] = (empty($_POST['dcckeditor_alignment_buttons'])) ? false : true;
                    $s->put('alignment_buttons', $this->ckes['alignment_buttons'], 'boolean');

                    $this->ckes['list_buttons'] = (empty($_POST['dcckeditor_list_buttons'])) ? false : true;
                    $s->put('list_buttons', $this->ckes['list_buttons'], 'boolean');

                    $this->ckes['textcolor_button'] = (empty($_POST['dcckeditor_textcolor_button'])) ? false : true;
                    $s->put('textcolor_button', $this->ckes['textcolor_button'], 'boolean');

                    $this->ckes['background_textcolor_button'] = !empty($_POST['dcckeditor_background_textcolor_button']);
                    $s->put('background_textcolor_button', $this->ckes['background_textcolor_button'], 'boolean');

                    $this->ckes['custom_color_list'] = str_replace(['#', ' '], '', $_POST['dcckeditor_custom_color_list']);
                    $s->put('custom_color_list', $this->ckes['custom_color_list'], 'string');

                    $this->ckes['colors_per_row'] = abs((int) $_POST['dcckeditor_colors_per_row']);
                    $s->put('colors_per_row', $this->ckes['colors_per_row']);

                    $this->ckes['cancollapse_button'] = (empty($_POST['dcckeditor_cancollapse_button'])) ? false : true;
                    $s->put('cancollapse_button', $this->ckes['cancollapse_button'], 'boolean');

                    $this->ckes['format_select'] = (empty($_POST['dcckeditor_format_select'])) ? false : true;
                    $s->put('format_select', $this->ckes['format_select'], 'boolean');

                    // default tags : p;h1;h2;h3;h4;h5;h6;pre;address
                    $this->ckes['format_tags'] = 'p;h1;h2;h3;h4;h5;h6;pre;address';
                    $allowed_tags              = explode(';', $this->ckes['format_tags']);
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
                            $this->ckes['format_tags'] = $_POST['dcckeditor_format_tags'];
                        }
                    }
                    $s->put('format_tags', $this->ckes['format_tags'], 'string');

                    $this->ckes['table_button'] = (empty($_POST['dcckeditor_table_button'])) ? false : true;
                    $s->put('table_button', $this->ckes['table_button'], 'boolean');

                    $this->ckes['clipboard_buttons'] = (empty($_POST['dcckeditor_clipboard_buttons'])) ? false : true;
                    $s->put('clipboard_buttons', $this->ckes['clipboard_buttons'], 'boolean');

                    $this->ckes['action_buttons'] = (empty($_POST['dcckeditor_action_buttons'])) ? false : true;
                    $s->put('action_buttons', $this->ckes['action_buttons'], 'boolean');

                    $this->ckes['disable_native_spellchecker'] = (empty($_POST['dcckeditor_disable_native_spellchecker'])) ? false : true;
                    $s->put('disable_native_spellchecker', $this->ckes['disable_native_spellchecker'], 'boolean');
                }
                App::core()->blog()->triggerBlog(); // !

                App::core()->notice()->addSuccessNotice(__('The configuration has been updated.'));
                App::core()->adminurl()->redirect('admin.plugin.CKEditor');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle('CKEditor')
            ->setPageHelp('CKEditor')
            ->setPageBreadcrumb([__('Plugins') => '', __('CKEditor') => ''])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if ($this->ckes['is_admin']) {
            echo '
            <h3 class="hidden-if-js">' . __('Settings') . '</h3>
            <form action="' . App::core()->adminurl()->root() . '" enctype="multipart/form-data" method="post">
            <div class="fieldset">
            <h3>' . __('Plugin activation') . '</h3>
            <p><label class="classic" for="dcckeditor_active">' .
            Form::checkbox('dcckeditor_active', 1, $this->ckes['active']) .
            __('Enable CKEditor plugin') . '</label></p>
            </div>';

            if ($this->ckes['active']) {
                echo '
                <div class="fieldset">
                <h3>' . __('Options') . '</h3>
                <p>' .
                Form::checkbox('dcckeditor_alignment_buttons', 1, $this->ckes['alignment_buttons']) . '
                <label class="classic" for="dcckeditor_alignment_buttons">' . __('Add alignment buttons') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_list_buttons', 1, $this->ckes['list_buttons']) . '
                <label class="classic" for="dcckeditor_list_buttons">' . __('Add lists buttons') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_textcolor_button', 1, $this->ckes['textcolor_button']) . '
                <label class="classic" for="dcckeditor_textcolor_button">' . __('Add text color button') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_background_textcolor_button', 1, $this->ckes['background_textcolor_button']) . '
                <label class="classic" for="dcckeditor_background_textcolor_button">' . __('Add background text color button') . '</label>
                </p>
                <p class="area">
                <label for="dcckeditor_custom_color_list">' . __('Custom colors list:') . '</label>' .
                Form::textarea('dcckeditor_custom_color_list', 60, 5, ['default' => Html::escapeHTML($this->ckes['custom_color_list'])]) . '
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
                Form::number('dcckeditor_colors_per_row', ['min' => 4, 'max' => 16, 'default' => $this->ckes['colors_per_row']]) . '
                </p>
                <p class="clear form-note">' . __('Valid range: 4 to 16') . '</p>
                <p>' .
                Form::checkbox('dcckeditor_cancollapse_button', 1, $this->ckes['cancollapse_button']) . '
                <label class="classic" for="dcckeditor_cancollapse_button">' . __('Add collapse button') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_format_select', 1, $this->ckes['format_select']) . '
                <label class="classic" for="dcckeditor_format_select">' . __('Add format selection') . '</label>
                </p>
                <p>
                <label class="classic" for="dcckeditor_format_tags">' . __('Custom formats') . '</label>' .
                Form::field('dcckeditor_format_tags', 100, 255, $this->ckes['format_tags']) . '
                </p>
                <p class="clear form-note">' . __('Default formats are p;h1;h2;h3;h4;h5;h6;pre;address') . '</p>
                <p>' .
                Form::checkbox('dcckeditor_table_button', 1, $this->ckes['table_button']) . '
                <label class="classic" for="dcckeditor_table_button">' . __('Add table button') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_clipboard_buttons', 1, $this->ckes['clipboard_buttons']) . '
                <label class="classic" for="dcckeditor_clipboard_buttons">' . __('Add clipboard buttons') . '</label>
                </p>
                <p class="clear form-note">' . __('Copy, Paste, Paste Text, Paste from Word') . '</p>
                <p>' .
                Form::checkbox('dcckeditor_action_buttons', 1, $this->ckes['action_buttons']) . '
                <label class="classic" for="dcckeditor_action_buttons">' . __('Add undo/redo buttons') . '</label>
                </p>
                <p>' .
                Form::checkbox('dcckeditor_disable_native_spellchecker', 1, $this->ckes['disable_native_spellchecker']) . '
                <label class="classic" for="dcckeditor_disable_native_spellchecker">' .
                __('Disables the built-in spell checker if the browser provides one') . '
                </label>
                </p>
                </div>';
            }

            echo '
            <p><input name="p" type="hidden" value="CKEditor"/>' .
            App::core()->adminurl()->getHiddenFormFields('admin.plugin.CKEditor', [], true) . '
            <input name="saveconfig" type="submit" value="' . __('Save configuration') . '"/>
            <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />
            </p>
            </form>';
        }
    }
}
