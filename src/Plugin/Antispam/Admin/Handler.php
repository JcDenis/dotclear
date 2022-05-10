<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Admin;

// Dotclear\Plugin\Antispam\Admin\Handler
use Dotclear\App;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\Antispam\Common\Antispam;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin page for plugin Antispam.
 *
 * @ingroup  Plugin Antispam
 */
class Handler extends AbstractPage
{
    private $a_antispam;
    private $a_filters;
    private $a_gui = false;
    private $a_tab;

    protected function getPermissions(): string|bool
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        $this->a_antispam = new Antispam();
        $this->a_antispam->initFilters();
        $this->a_filters = $this->a_antispam->filters->getFilters();

        $filter = null;

        try {
            // Show filter configuration GUI
            if (!empty($_GET['f'])) {
                if (!isset($this->a_filters[$_GET['f']])) {
                    throw new Exception(__('Filter does not exist.'));
                }

                if (!$this->a_filters[$_GET['f']]->hasGUI()) {
                    throw new Exception(__('Filter has no user interface.'));
                }

                $filter      = $this->a_filters[$_GET['f']];
                $this->a_gui = $filter->gui($filter->guiURL());
                $this->a_tab = $filter->guiTab();
            }

            // Remove all spam
            if (!empty($_POST['delete_all'])) {
                $this->a_antispam->delAllSpam(Clock::database(date: (int) $_POST['ts']));

                App::core()->notice()->addSuccessNotice(__('Spam comments have been successfully deleted.'));
                App::core()->adminurl()->redirect('admin.plugin.Antispam');
            }

            // Update filters
            if (isset($_POST['filters_upd'])) {
                $filters_opt = [];
                $i           = 0;
                foreach ($this->a_filters as $fid => $f) {
                    $filters_opt[$fid] = [false, $i];
                    ++$i;
                }

                // Enable active filters
                if (isset($_POST['filters_active']) && is_array($_POST['filters_active'])) {
                    foreach ($_POST['filters_active'] as $v) {
                        $filters_opt[$v][0] = true;
                    }
                }

                // Order filters
                if (!empty($_POST['f_order']) && empty($_POST['filters_order'])) {
                    $order = $_POST['f_order'];
                    asort($order);
                    $order = array_keys($order);
                } elseif (!empty($_POST['filters_order'])) {
                    $order = explode(',', trim($_POST['filters_order'], ','));
                }

                if (isset($order)) {
                    foreach ($order as $i => $f) {
                        $filters_opt[$f][1] = $i;
                    }
                }

                // Set auto delete flag
                if (isset($_POST['filters_auto_del']) && is_array($_POST['filters_auto_del'])) {
                    foreach ($_POST['filters_auto_del'] as $v) {
                        $filters_opt[$v][2] = true;
                    }
                }

                $this->a_antispam->filters->saveFilterOpts($filters_opt);

                App::core()->notice()->addSuccessNotice(__('Filters configuration has been successfully saved.'));
                App::core()->adminurl()->redirect('admin.plugin.Antispam');
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        // Page setup
        $this
            ->setPageTitle((false !== $this->a_gui ? sprintf(__('%s configuration'), $filter->name) . ' - ' : '') . __('Antispam'))
            ->setPageHead(App::core()->resource()->pageTabs($this->a_tab))
        ;

        if (!App::core()->user()->preference()->get('accessibility')->get('nodragdrop')) {
            $this->setPageHead(
                App::core()->resource()->load('jquery/jquery-ui.custom.js') .
                App::core()->resource()->load('jquery/jquery.ui.touch-punch.js')
            );
        }
        $this->setPageHead(
            App::core()->resource()->json('antispam', ['confirm_spam_delete' => __('Are you sure you want to delete all spams?')]) .
            App::core()->resource()->load('antispam.js', 'Plugin', 'Antispam') .
            App::core()->resource()->load('style.css', 'Plugin', 'Antispam')
        );

        if (false !== $this->a_gui) {
            $this
                ->setPageBreadcrumb([
                    __('Plugins')  => '',
                    __('Antispam') => App::core()->adminurl()->get('admin.plugin.Antispam'),
                    sprintf(__('%s filter configuration'), $filter->name) => '',
                ])
                ->setPageHelp($filter->help)
            ;
        } else {
            $this
                ->setPageBreadcrumb([
                    __('Plugins')  => '',
                    __('Antispam') => '',
                ])
                ->setPageHelp('antispam', 'antispam-filters')
            ;
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if (false !== $this->a_gui) {
            echo '<p><a href="' . App::core()->adminurl()->get('admin.plugin.Antispam') . '" class="back">' . __('Back to filters list') . '</a></p>' . $this->a_gui;

            return;
        }

        // Information
        $spam_count      = $this->a_antispam->countSpam();
        $published_count = $this->a_antispam->countPublishedComments();
        $moderationTTL   = App::core()->blog()->settings()->get('antispam')->get('antispam_moderation_ttl');

        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" class="fieldset">' .
        '<h3>' . __('Information') . '</h3>';

        echo '<ul class="spaminfo">' .
        '<li class="spamcount"><a href="' . App::core()->adminurl()->get('admin.comments', ['status' => '-2']) . '">' . __('Junk comments:') . '</a> ' .
        '<strong>' . $spam_count . '</strong></li>' .
        '<li class="hamcount"><a href="' . App::core()->adminurl()->get('admin.comments', ['status' => '1']) . '">' . __('Published comments:') . '</a> ' .
            $published_count . '</li>' .
            '</ul>';

        if (0 < $spam_count) {
            echo '<p>' .
            Form::hidden('ts', Clock::ts()) .
            '<input name="delete_all" class="delete" type="submit" value="' . __('Delete all spams') . '" /></p>';
        }
        if (null != $moderationTTL && 0 <= $moderationTTL) {
            echo '<p>' . sprintf(__('All spam comments older than %s day(s) will be automatically deleted.'), $moderationTTL) . ' ' .
            sprintf(__('You can modify this duration in the %s'), '<a href="' . App::core()->adminurl()->get('admin.blog.pref') .
                '#antispam_moderation_ttl"> ' . __('Blog settings') . '</a>') .
                '.</p>';
        }
        echo App::core()->adminurl()->getHiddenFormFields('admin.plugin.Antispam', [], true) .
        '</form>';

        // Filters
        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="filters-list-form">';

        if (!empty($_GET['upd'])) {
            App::core()->notice()->success(__('Filters configuration has been successfully saved.'));
        }

        echo '<div class="table-outer">' .
        '<table class="dragable">' .
        '<caption class="as_h3">' . __('Available spam filters') . '</caption>' .
        '<thead><tr>' .
        '<th>' . __('Order') . '</th>' .
        '<th>' . __('Active') . '</th>' .
        '<th>' . __('Auto Del.') . '</th>' .
        '<th class="nowrap">' . __('Filter name') . '</th>' .
        '<th colspan="2">' . __('Description') . '</th>' .
            '</tr></thead>' .
            '<tbody id="filters-list" >';

        $i = 1;
        foreach ($this->a_filters as $fid => $f) {
            $gui_link = '&nbsp;';
            if ($f->hasGUI()) {
                $gui_link = '<a href="' . Html::escapeHTML($f->guiURL()) . '">' .
                '<img src="?df=images/edit-mini.png" alt="' . __('Filter configuration') . '" ' .
                'title="' . __('Filter configuration') . '" /></a>';
            }

            echo '<tr class="line' . ($f->active ? '' : ' offline') . '" id="f_' . $fid . '">' .
            '<td class="handle">' . Form::number(['f_order[' . $fid . ']'], [
                'min'        => 1,
                'max'        => count($this->a_filters),
                'default'    => $i,
                'class'      => 'position',
                'extra_html' => 'title="' . __('position') . '"',
            ]) .
            '</td>' .
            '<td class="nowrap">' . Form::checkbox(
                ['filters_active[]'],
                $fid,
                [
                    'checked'    => $f->active,
                    'extra_html' => 'title="' . __('Active') . '"',
                ]
            ) . '</td>' .
            '<td class="nowrap">' . Form::checkbox(
                ['filters_auto_del[]'],
                $fid,
                [
                    'checked'    => $f->auto_delete,
                    'extra_html' => 'title="' . __('Auto Del.') . '"',
                ]
            ) . '</td>' .
            '<td class="nowrap" scope="row">' . $f->name . '</td>' .
            '<td class="maximal">' . $f->description . '</td>' .
                '<td class="status">' . $gui_link . '</td>' .
                '</tr>';
            ++$i;
        }
        echo '</tbody></table></div>' .
        '<p>' .
        Form::hidden('filters_order', '') .
        App::core()->adminurl()->getHiddenFormFields('admin.plugin.Antispam', [], true) .
        '<input type="submit" name="filters_upd" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        '</p>' .
        '</form>';

        // Syndication
        if ('' != App::core()->config()->get('admin_url')) {
            $ham_feed = App::core()->blog()->getURLFor(
                'hamfeed',
                $code = $this->a_antispam->getUserCode()
            );
            $spam_feed = App::core()->blog()->getURLFor(
                'spamfeed',
                $code = $this->a_antispam->getUserCode()
            );

            echo '<h3>' . __('Syndication') . '</h3>' .
            '<ul class="spaminfo">' .
            '<li class="feed"><a href="' . $spam_feed . '">' . __('Junk comments RSS feed') . '</a></li>' .
            '<li class="feed"><a href="' . $ham_feed . '">' . __('Published comments RSS feed') . '</a></li>' .
                '</ul>';
        }
    }
}
