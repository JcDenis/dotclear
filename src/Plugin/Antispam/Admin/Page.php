<?php
/**
 * @class Dotclear\Plugin\Antispam\Admin\Page
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Admin;


use Dotclear\Module\AbstractPage;

use Dotclear\Plugin\Antispam\Lib\Antispam;

use Dotclear\Utils\Dt;
use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    protected $workspaces = ['accessibility'];

    private $a_filters;
    private $a_gui = false;
    private $a_tab = null;

    protected function getPermissions(): string|null|false
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        Antispam::initFilters();
        $this->a_filters = Antispam::$filters->getFilters();

        $filter = null;

        try {
            # Show filter configuration GUI
            if (!empty($_GET['f'])) {
                if (!isset($this->a_filters[$_GET['f']])) {
                    throw new \Exception(__('Filter does not exist.'));
                }

                if (!$this->a_filters[$_GET['f']]->hasGUI()) {
                    throw new \Exception(__('Filter has no user interface.'));
                }

                $filter     = $this->a_filters[$_GET['f']];
                $this->a_gui = $filter->gui($filter->guiURL());
                $this->a_tab = $filter->guiTab();
            }

            # Remove all spam
            if (!empty($_POST['delete_all'])) {
                $ts = Dt::str('%Y-%m-%d %H:%M:%S', $_POST['ts'], dotclear()->blog()->settings->system->blog_timezone);

                Antispam::delAllSpam(dotclear(), $ts);

                dotclear()->notices->addSuccessNotice(__('Spam comments have been successfully deleted.'));
                dotclear()->adminurl->redirect('admin.plugin.Antispam');
            }

            # Update filters
            if (isset($_POST['filters_upd'])) {
                $filters_opt = [];
                $i           = 0;
                foreach ($this->a_filters as $fid => $f) {
                    $filters_opt[$fid] = [false, $i];
                    $i++;
                }

                # Enable active filters
                if (isset($_POST['filters_active']) && is_array($_POST['filters_active'])) {
                    foreach ($_POST['filters_active'] as $v) {
                        $filters_opt[$v][0] = true;
                    }
                }

                # Order filters
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

                # Set auto delete flag
                if (isset($_POST['filters_auto_del']) && is_array($_POST['filters_auto_del'])) {
                    foreach ($_POST['filters_auto_del'] as $v) {
                        $filters_opt[$v][2] = true;
                    }
                }

                Antispam::$filters->saveFilterOpts($filters_opt);

                dotclear()->notices->addSuccessNotice(__('Filters configuration has been successfully saved.'));
                dotclear()->adminurl->redirect('admin.plugin.Antispam');
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }

        # Page setup
        $this
            ->setPageTitle(($this->a_gui !== false ? sprintf(__('%s configuration'), $filter->name) . ' - ' : '') . __('Antispam'))
            ->setPageHead(static::jsPageTabs($this->a_tab))
        ;

        if (!dotclear()->auth()->user_prefs->accessibility->nodragdrop) {
            $this->setPageHead(
                static::jsLoad('js/jquery/jquery-ui.custom.js') .
                static::jsLoad('js/jquery/jquery.ui.touch-punch.js')
            );
        }
        $this->setPageHead(
            static::jsJson('antispam', ['confirm_spam_delete' => __('Are you sure you want to delete all spams?')]) .
            static::jsLoad('?mf=Plugin/Antispam/files/js/antispam.js') .
            static::cssLoad('?mf=Plugin/Antispam/files/style.css')
        );

        if ($this->a_gui !== false) {
            $this
                ->setPageBreadcrumb([
                    __('Plugins')                                         => '',
                    __('Antispam')                                        => dotclear()->adminurl->get('admin.plugin.Antispam'),
                    sprintf(__('%s filter configuration'), $filter->name) => ''
                ])
                ->setPageHelp($filter->help)
            ;
        } else {
            $this
                ->setPageBreadcrumb([
                    __('Plugins')  => '',
                    __('Antispam') => ''
                ])
                ->setPageHelp('antispam', 'antispam-filters')
            ;
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if ($this->a_gui !== false) {
            echo '<p><a href="' . dotclear()->adminurl->get('admin.plugin.Antispam') . '" class="back">' . __('Back to filters list') . '</a></p>' . $this->a_gui;

            return;
        }

        # Information
        $spam_count      = Antispam::countSpam();
        $published_count = Antispam::countPublishedComments();
        $moderationTTL   = dotclear()->blog()->settings->antispam->antispam_moderation_ttl;

        echo
        '<form action="' . dotclear()->adminurl->get('admin.plugin.Antispam') . '" method="post" class="fieldset">' .
        '<h3>' . __('Information') . '</h3>';

        echo
        '<ul class="spaminfo">' .
        '<li class="spamcount"><a href="' . dotclear()->adminurl->get('admin.comments', ['status' => '-2']) . '">' . __('Junk comments:') . '</a> ' .
        '<strong>' . $spam_count . '</strong></li>' .
        '<li class="hamcount"><a href="' . dotclear()->adminurl->get('admin.comments', ['status' => '1']) . '">' . __('Published comments:') . '</a> ' .
            $published_count . '</li>' .
            '</ul>';

        if ($spam_count > 0) {
            echo
            '<p>' . dotclear()->nonce()->form() .
            Form::hidden('ts', time()) .
            '<input name="delete_all" class="delete" type="submit" value="' . __('Delete all spams') . '" /></p>';
        }
        if ($moderationTTL != null && $moderationTTL >= 0) {
            echo '<p>' . sprintf(__('All spam comments older than %s day(s) will be automatically deleted.'), $moderationTTL) . ' ' .
            sprintf(__('You can modify this duration in the %s'), '<a href="' . dotclear()->adminurl->get('admin.blog.pref') .
                '#antispam_moderation_ttl"> ' . __('Blog settings') . '</a>') .
                '.</p>';
        }
        echo
        Form::hidden(['handler'], 'admin.plugin.Antispam') .
        '</form>';

        # Filters
        echo
            '<form action="' . dotclear()->adminurl->get('admin.plugin.Antispam') . '" method="post" id="filters-list-form">';

        if (!empty($_GET['upd'])) {
            dotclear()->notices->success(__('Filters configuration has been successfully saved.'));
        }

        echo
        '<div class="table-outer">' .
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

            echo
            '<tr class="line' . ($f->active ? '' : ' offline') . '" id="f_' . $fid . '">' .
            '<td class="handle">' . Form::number(['f_order[' . $fid . ']'], [
                'min'        => 1,
                'max'        => count($this->a_filters),
                'default'    => $i,
                'class'      => 'position',
                'extra_html' => 'title="' . __('position') . '"'
            ]) .
            '</td>' .
            '<td class="nowrap">' . Form::checkbox(['filters_active[]'], $fid,
                [
                    'checked'    => $f->active,
                    'extra_html' => 'title="' . __('Active') . '"'
                ]
            ) . '</td>' .
            '<td class="nowrap">' . Form::checkbox(['filters_auto_del[]'], $fid,
                [
                    'checked'    => $f->auto_delete,
                    'extra_html' => 'title="' . __('Auto Del.') . '"'
                ]
            ) . '</td>' .
            '<td class="nowrap" scope="row">' . $f->name . '</td>' .
            '<td class="maximal">' . $f->description . '</td>' .
                '<td class="status">' . $gui_link . '</td>' .
                '</tr>';
            $i++;
        }
        echo
        '</tbody></table></div>' .
        '<p>' .
        Form::hidden('filters_order', '') .
        Form::hidden(['handler'], 'admin.plugin.Antispam') .
        dotclear()->nonce()->form() .
        '<input type="submit" name="filters_upd" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        '</p>' .
        '</form>';

        # Syndication
        if (dotclear()->config()->admin_url != '') {
            $ham_feed = dotclear()->blog()->url . dotclear()->url()->getURLFor(
                'hamfeed',
                $code = Antispam::getUserCode()
            );
            $spam_feed = dotclear()->blog()->url . dotclear()->url()->getURLFor(
                'spamfeed',
                $code = Antispam::getUserCode()
            );

            echo
            '<h3>' . __('Syndication') . '</h3>' .
            '<ul class="spaminfo">' .
            '<li class="feed"><a href="' . $spam_feed . '">' . __('Junk comments RSS feed') . '</a></li>' .
            '<li class="feed"><a href="' . $ham_feed . '">' . __('Published comments RSS feed') . '</a></li>' .
                '</ul>';
        }
    }
}
