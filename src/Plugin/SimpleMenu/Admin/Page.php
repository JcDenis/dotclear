<?php
/**
 * @class Dotclear\Plugin\SimpleMenu\Admin\Page
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginSimpleMenu
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Admin;

use stdClass;
use ArrayObject;

use Dotclear\Exception\ModuleException;

use Dotclear\Module\AbstractPage;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    protected $workspaces = ['accessibility'];

    private $sm_items;
    private $sm_step              = 0;
    private $sm_menu              = [];
    private $sm_item_label        = '';
    private $sm_item_descr        = '';
    private $sm_item_url          = '';
    private $sm_item_type         = '';
    private $sm_item_type_label   = '';
    private $sm_item_select       = '';
    private $sm_item_select_label = '';

    private $sm_langs_combo      = [];
    private $sm_categories_combo = [];
    private $sm_months_combo     = [];
    private $sm_pages_combo      = [];
    private $sm_tags_combo       = [];

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        # Liste des catégories
        $categories_label = [];
        $rs               = dotclear()->blog->getCategories(['post_type' => 'post']);
        $this->sm_categories_combo = dotclear()->combos->getCategoriesCombo($rs, false, true);
        $rs->moveStart();
        while ($rs->fetch()) {
            $categories_label[$rs->cat_url] = Html::escapeHTML($rs->cat_title);
        }

        # Liste des langues utilisées
        $this->sm_langs_combo = dotclear()->combos->getLangscombo(
            dotclear()->blog->getLangs(['order' => 'asc'])
        );

        # Liste des mois d'archive
        $rs           = dotclear()->blog->getDates(['type' => 'month']);
        $this->sm_months_combo = array_merge(
            [__('All months') => '-'],
            dotclear()->combos->getDatesCombo($rs)
        );

        $first_year = $last_year = 0;
        while ($rs->fetch()) {
            if (($first_year == 0) || ($rs->year() < $first_year)) {
                $first_year = $rs->year();
            }

            if (($last_year == 0) || ($rs->year() > $last_year)) {
                $last_year = $rs->year();
            }
        }
        unset($rs);

        # Liste des pages -- Doit être pris en charge plus tard par le plugin ?
        try {
            $rs = dotclear()->blog->getPosts(['post_type' => 'page']);
            while ($rs->fetch()) {
                $this->sm_pages_combo[$rs->post_title] = $rs->getURL();
            }
            unset($rs);
        } catch (\Exception $e) {
        }

        # Liste des tags -- Doit être pris en charge plus tard par le plugin ?
        try {
            $rs                         = dotclear()->meta->getMetadata(['meta_type' => 'tag']);
            $this->sm_tags_combo[__('All tags')] = '-';
            while ($rs->fetch()) {
                $this->sm_tags_combo[$rs->meta_id] = $rs->meta_id;
            }
            unset($rs);
        } catch (\Exception $e) {
        }

        # Liste des types d'item de menu
        $this->sm_items         = new ArrayObject();
        $this->sm_items['home'] = new ArrayObject([__('Home'), false]);

        if (dotclear()->blog->settings->system->static_home) {
            $this->sm_items['posts'] = new ArrayObject([__('Posts'), false]);
        }

        if (count($this->sm_langs_combo) > 1) {
            $this->sm_items['lang'] = new ArrayObject([__('Language'), true]);
        }
        if (count($this->sm_categories_combo)) {
            $this->sm_items['category'] = new ArrayObject([__('Category'), true]);
        }
        if (count($this->sm_months_combo) > 1) {
            $this->sm_items['archive'] = new ArrayObject([__('Archive'), true]);
        }
        if (dotclear()->plugins->hasModule('pages')) {
            if (count($this->sm_pages_combo)) {
                $this->sm_items['pages'] = new ArrayObject([__('Page'), true]);
            }
        }
        if (dotclear()->plugins->hasModule('tags')) {
            if (count($this->sm_tags_combo) > 1) {
                $this->sm_items['tags'] = new ArrayObject([__('Tags'), true]);
            }
        }

        # --BEHAVIOR-- adminSimpleMenuAddType
        # Should add an item to $this->sm_items[<id>] as an [<label>,<optional step (true or false)>]
        dotclear()->behavior()->call('adminSimpleMenuAddType', $this->sm_items);

        $this->sm_items['special'] = new ArrayObject([__('User defined'), false]);

        # Lecture menu existant
        $menu = dotclear()->blog->settings->system->get('simpleMenu');
        if (is_array($menu)) {
            $this->sm_menu = $menu;
        }

        // Saving new configuration
        $item_targetBlank           = false;
        $step_label                 = '';
        if (!empty($_POST['saveconfig'])) {
            try {
                $menu_active = (empty($_POST['active'])) ? false : true;
                dotclear()->blog->settings->system->put('simpleMenu_active', $menu_active, 'boolean');
                dotclear()->blog->triggerBlog();

                // All done successfully, return to menu items list
                dotclear()->notices->addSuccessNotice(__('Configuration successfully updated.'));
                dotclear()->adminurl->redirect('admin.plugin.SimpleMenu');
            } catch (\Exception $e) {
                dotclear()->error($e->getMessage());
            }
        } else {
            # Récupération paramètres postés
            $this->sm_item_type   = $_POST['item_type']   ?? '';
            $this->sm_item_select = $_POST['item_select'] ?? '';
            $this->sm_item_label  = $_POST['item_label']  ?? '';
            $this->sm_item_descr  = $_POST['item_descr']  ?? '';
            $this->sm_item_url    = $_POST['item_url']    ?? '';
            $item_targetBlank     = isset($_POST['item_targetBlank']) ? (empty($_POST['item_targetBlank'])) ? false : true : false;
            # Traitement
            $this->sm_step = (!empty($_GET['add']) ? (integer) $_GET['add'] : 0);
            if (($this->sm_step > 4) || ($this->sm_step < 0)) {
                $this->sm_step = 0;
            }

            if ($this->sm_step) {

                # Récupération libellés des choix
                $this->sm_item_type_label = isset($this->sm_items[$this->sm_item_type]) ? $this->sm_items[$this->sm_item_type][0] : '';

                switch ($this->sm_step) {
                    case 1:
                        // First step, menu item type to be selected
                        $this->sm_item_type = $this->sm_item_select = '';

                        break;
                    case 2:
                        if ($this->sm_items[$this->sm_item_type][1]) {
                            // Second step (optional), menu item sub-type to be selected
                            $this->sm_item_select = '';

                            break;
                        }
                    case 3:
                        // Third step, menu item attributes to be changed or completed if necessary
                        $this->sm_item_select_label = '';
                        $this->sm_item_label        = __('Label');
                        $this->sm_item_descr        = __('Description');
                        $this->sm_item_url          = Html::stripHostURL(dotclear()->blog->url);
                        switch ($this->sm_item_type) {
                            case 'home':
                                $this->sm_item_label = __('Home');
                                $this->sm_item_descr = dotclear()->blog->settings->system->static_home ? __('Home page') : __('Recent posts');

                                break;
                            case 'posts':
                                $this->sm_item_label = __('Posts');
                                $this->sm_item_descr = __('Recent posts');
                                $this->sm_item_url .= dotclear()->url->getURLFor('posts');

                                break;
                            case 'lang':
                                $this->sm_item_select_label = array_search($this->sm_item_select, $this->sm_langs_combo);
                                $this->sm_item_label        = $this->sm_item_select_label;
                                $this->sm_item_descr        = sprintf(__('Switch to %s language'), $this->sm_item_select_label);
                                $this->sm_item_url .= dotclear()->url->getURLFor('lang', $this->sm_item_select);

                                break;
                            case 'category':
                                $this->sm_item_select_label = $categories_label[$this->sm_item_select];
                                $this->sm_item_label        = $this->sm_item_select_label;
                                $this->sm_item_descr        = __('Recent Posts from this category');
                                $this->sm_item_url .= dotclear()->url->getURLFor('category', $this->sm_item_select);

                                break;
                            case 'archive':
                                $this->sm_item_select_label = array_search($this->sm_item_select, $this->sm_months_combo);
                                if ($this->sm_item_select == '-') {
                                    $this->sm_item_label = __('Archives');
                                    $this->sm_item_descr = $first_year . ($first_year != $last_year ? ' - ' . $last_year : '');
                                    $this->sm_item_url .= dotclear()->url->getURLFor('archive');
                                } else {
                                    $this->sm_item_label = $this->sm_item_select_label;
                                    $this->sm_item_descr = sprintf(__('Posts from %s'), $this->sm_item_select_label);
                                    $this->sm_item_url .= dotclear()->url->getURLFor('archive', substr($this->sm_item_select, 0, 4) . '/' . substr($this->sm_item_select, -2));
                                }

                                break;
                            case 'pages':
                                $this->sm_item_select_label = array_search($this->sm_item_select, $this->sm_pages_combo);
                                $this->sm_item_label        = $this->sm_item_select_label;
                                $this->sm_item_descr        = '';
                                $this->sm_item_url          = Html::stripHostURL($this->sm_item_select);

                                break;
                            case 'tags':
                                $this->sm_item_select_label = array_search($this->sm_item_select, $this->sm_tags_combo);
                                if ($this->sm_item_select == '-') {
                                    $this->sm_item_label = __('All tags');
                                    $this->sm_item_descr = '';
                                    $this->sm_item_url .= dotclear()->url->getURLFor('tags');
                                } else {
                                    $this->sm_item_label = $this->sm_item_select_label;
                                    $this->sm_item_descr = sprintf(__('Recent posts for %s tag'), $this->sm_item_select_label);
                                    $this->sm_item_url .= dotclear()->url->getURLFor('tag', $this->sm_item_select);
                                }

                                break;
                            case 'special':
                                break;
                            default:
                                # --BEHAVIOR-- adminSimpleMenuBeforeEdit
                                # Should modify if necessary $this->sm_item_label, $this->sm_item_descr and $this->sm_item_url
                                # Should set if necessary $this->sm_item_select_label (displayed on further admin step only)
                                dotclear()->behavior()->call('adminSimpleMenuBeforeEdit', $this->sm_item_type, $this->sm_item_select,
                                    [& $this->sm_item_label, &$this->sm_item_descr, &$this->sm_item_url, &$this->sm_item_select_label]);

                                break;
                        }

                        break;
                    case 4:
                        // Fourth step, menu item to be added
                        try {
                            if (($this->sm_item_label != '') && ($this->sm_item_url != '')) {
                                // Add new item menu in menu array
                                $this->sm_menu[] = [
                                    'label'       => $this->sm_item_label,
                                    'descr'       => $this->sm_item_descr,
                                    'url'         => $this->sm_item_url,
                                    'targetBlank' => $item_targetBlank
                                ];

                                // Save menu in blog settings
                                dotclear()->blog->settings->system->put('simpleMenu', $this->sm_menu);
                                dotclear()->blog->triggerBlog();

                                // All done successfully, return to menu items list
                                dotclear()->notices->addSuccessNotice(__('Menu item has been successfully added.'));
                                dotclear()->adminurl->redirect('admin.plugin.SimpleMenu');
                            } else {
                                $this->sm_step              = 3;
                                $this->sm_item_select_label = $this->sm_item_label;
                                dotclear()->notices->addErrorNotice(__('Label and URL of menu item are mandatory.'));
                            }
                        } catch (\Exception $e) {
                            dotclear()->error($e->getMessage());
                        }

                        break;
                }
            } else {

                # Remove selected menu items
                if (!empty($_POST['removeaction'])) {
                    try {
                        if (!empty($_POST['items_selected'])) {
                            foreach ($_POST['items_selected'] as $k => $v) {
                                $this->sm_menu[$v]['label'] = '';
                            }
                            $newmenu = [];
                            foreach ($this->sm_menu as $k => $v) {
                                if ($v['label']) {
                                    $newmenu[] = [
                                        'label'       => $v['label'],
                                        'descr'       => $v['descr'],
                                        'url'         => $v['url'],
                                        'targetBlank' => $v['targetBlank']
                                    ];
                                }
                            }
                            $this->sm_menu = $newmenu;
                            // Save menu in blog settings
                            dotclear()->blog->settings->system->put('simpleMenu', $this->sm_menu);
                            dotclear()->blog->triggerBlog();

                            // All done successfully, return to menu items list
                            dotclear()->notices->addSuccessNotice(__('Menu items have been successfully removed.'));
                            dotclear()->adminurl->redirect('admin.plugin.SimpleMenu');
                        } else {
                            throw new ModuleException(__('No menu items selected.'));
                        }
                    } catch (\Exception $e) {
                        dotclear()->error($e->getMessage());
                    }
                }

                # Update menu items
                if (!empty($_POST['updateaction'])) {
                    try {
                        foreach ($_POST['items_label'] as $k => $v) {
                            if (!$v) {
                                throw new ModuleException(__('Label is mandatory.'));
                            }
                        }
                        foreach ($_POST['items_url'] as $k => $v) {
                            if (!$v) {
                                throw new ModuleException(__('URL is mandatory.'));
                            }
                        }
                        $newmenu = [];
                        for ($i = 0; $i < count($_POST['items_label']); $i++) {
                            $newmenu[] = [
                                'label'       => $_POST['items_label'][$i],
                                'descr'       => $_POST['items_descr'][$i],
                                'url'         => $_POST['items_url'][$i],
                                'targetBlank' => (empty($_POST['items_targetBlank' . $i])) ? false : true
                            ];
                        }
                        $this->sm_menu = $newmenu;

                        dotclear()->auth->user_prefs->addWorkspace('accessibility');
                        if (dotclear()->auth->user_prefs->accessibility->nodragdrop) {
                            # Order menu items
                            $order = [];
                            if (empty($_POST['im_order']) && !empty($_POST['order'])) {
                                $order = $_POST['order'];
                                asort($order);
                                $order = array_keys($order);
                            } elseif (!empty($_POST['im_order'])) {
                                $order = $_POST['im_order'];
                                if (substr($order, -1) == ',') {
                                    $order = substr($order, 0, strlen($order) - 1);
                                }
                                $order = explode(',', $order);
                            }
                            if (!empty($order)) {
                                $newmenu = [];
                                foreach ($order as $i => $k) {
                                    $newmenu[] = [
                                        'label' => $this->sm_menu[$k]['label'],
                                        'descr' => $this->sm_menu[$k]['descr'],
                                        'url'   => $this->sm_menu[$k]['url']];
                                }
                                $this->sm_menu = $newmenu;
                            }
                        }

                        // Save menu in blog settings
                        dotclear()->blog->settings->system->put('simpleMenu', $this->sm_menu);
                        dotclear()->blog->triggerBlog();

                        // All done successfully, return to menu items list
                        dotclear()->notices->addSuccessNotice(__('Menu items have been successfully updated.'));
                        dotclear()->adminurl->redirect('admin.plugin.SimpleMenu');
                    } catch (\Exception $e) {
                        dotclear()->error($e->getMessage());
                    }
                }
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Simple menu'))
            ->setPageHelp('simpleMenu')
            ->setPageHead(static::jsConfirmClose('settings', 'menuitemsappend', 'additem', 'menuitems'))
        ;
        if (!dotclear()->auth->user_prefs->accessibility->nodragdrop) {
            $this->setPageHead(
                static::jsLoad('js/jquery/jquery-ui.custom.js') .
                static::jsLoad('js/jquery/jquery.ui.touch-punch.js') .
                static::jsLoad('?mf=Plugin/SimpleMenu/files/js/simplemenu.js')
            );
        }

        if ($this->sm_step) {
            switch ($this->sm_step) {
                case 1:
                    $step_label = __('Step #1');

                    break;
                case 2:
                    if ($this->sm_items[$this->sm_item_type][1]) {
                        $step_label = __('Step #2');

                        break;
                    }
                case 3:
                    if ($this->sm_items[$this->sm_item_type][1]) {
                        $step_label = __('Step #3');
                    } else {
                        $step_label = __('Step #2');
                    }

                    break;
            }

            $this->setPageBreadcrumb(
                [
                    Html::escapeHTML(dotclear()->blog->name) => '',
                    __('Simple menu')                         => dotclear()->adminurl->get('admin.plugin.SimpleMenu'),
                    __('Add item')                            => '',
                    $step_label                               => ''
                ],
                ['hl_pos' => -2]
            );
        } else {
            $this->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog->name) => '',
                __('Simple menu')                         => ''
            ]);
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if ($this->sm_step) {
            // Formulaire d'ajout d'un item
            switch ($this->sm_step) {
                case 1:
                    $items_combo = [];
                    foreach ($this->sm_items as $k => $v) {
                        $items_combo[$v[0]] = $k;
                    }
                    // Selection du type d'item
                    echo '<form id="additem" action="' . dotclear()->adminurl->get('admin.plugin.SimpleMenu') . '&amp;add=2" method="post">';
                    echo '<fieldset><legend>' . __('Select type') . '</legend>';
                    echo '<p class="field"><label for="item_type" class="classic">' . __('Type of item menu:') . '</label>' . form::combo('item_type', $items_combo) . '</p>';
                    echo '<p>' . dotclear()->formNonce() . '<input type="submit" name="appendaction" value="' . __('Continue...') . '" />' . '</p>';
                    echo '</fieldset>';
                    echo '</form>';

                    break;
                case 2:
                    if ($this->sm_items[$this->sm_item_type][1]) {
                        // Choix à faire
                        echo '<form id="additem" action="' . dotclear()->adminurl->get('admin.plugin.SimpleMenu') . '&amp;add=3" method="post">';
                        echo '<fieldset><legend>' . $this->sm_item_type_label . '</legend>';
                        switch ($this->sm_item_type) {
                            case 'lang':
                                echo '<p class="field"><label for="item_select" class="classic">' . __('Select language:') . '</label>' .
                                form::combo('item_select', $this->sm_langs_combo);

                                break;
                            case 'category':
                                echo '<p class="field"><label for="item_select" class="classic">' . __('Select category:') . '</label>' .
                                form::combo('item_select', $this->sm_categories_combo);

                                break;
                            case 'archive':
                                echo '<p class="field"><label for="item_select" class="classic">' . __('Select month (if necessary):') . '</label>' .
                                form::combo('item_select', $this->sm_months_combo);

                                break;
                            case 'pages':
                                echo '<p class="field"><label for="item_select" class="classic">' . __('Select page:') . '</label>' .
                                form::combo('item_select', $this->sm_pages_combo);

                                break;
                            case 'tags':
                                echo '<p class="field"><label for="item_select" class="classic">' . __('Select tag (if necessary):') . '</label>' .
                                form::combo('item_select', $this->sm_tags_combo);

                                break;
                            default:
                                echo
                                # --BEHAVIOR-- adminSimpleMenuSelect
                                # Optional step once $this->sm_item_type known : should provide a field using 'item_select' as id
                                dotclear()->behavior()->call('adminSimpleMenuSelect', $this->sm_item_type, 'item_select');
                        }
                        echo form::hidden('item_type', $this->sm_item_type);
                        echo '<p>' . dotclear()->formNonce() . '<input type="submit" name="appendaction" value="' . __('Continue...') . '" /></p>';
                        echo '</fieldset>';
                        echo '</form>';

                        break;
                    }
                case 3:
                    // Libellé et description
                    echo '<form id="additem" action="' . dotclear()->adminurl->get('admin.plugin.SimpleMenu') . '&amp;add=4" method="post">';
                    echo '<fieldset><legend>' . $this->sm_item_type_label . ($this->sm_item_select_label != '' ? ' (' . $this->sm_item_select_label . ')' : '') . '</legend>';
                    echo '<p class="field"><label for="item_label" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' .
                    __('Label of item menu:') . '</label>' .
                    form::field('item_label', 20, 255, [
                        'default'    => $this->sm_item_label,
                        'extra_html' => 'required placeholder="' . __('Label') . '" lang="' . dotclear()->auth->getInfo('user_lang') . '" spellcheck="true"'
                    ]) .
                        '</p>';
                    echo '<p class="field"><label for="item_descr" class="classic">' .
                    __('Description of item menu:') . '</label>' . form::field('item_descr', 30, 255,
                        [
                            'default'    => $this->sm_item_descr,
                            'extra_html' => 'lang="' . dotclear()->auth->getInfo('user_lang') . '" spellcheck="true"'
                        ]) . '</p>';
                    echo '<p class="field"><label for="item_url" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' .
                    __('URL of item menu:') . '</label>' .
                    form::field('item_url', 40, 255, [
                        'default'    => $this->sm_item_url,
                        'extra_html' => 'required placeholder="' . __('URL') . '"'
                    ]) .
                        '</p>';
                    echo form::hidden('item_type', $this->sm_item_type) . form::hidden('item_select', $this->sm_item_select);
                    echo '<p class="field"><label for="item_descr" class="classic">' .
                    __('Open URL on a new tab') . ':</label>' . form::checkbox('item_targetBlank', 'blank') . '</p>';
                    echo '<p>' . dotclear()->formNonce() . '<input type="submit" name="appendaction" value="' . __('Add this item') . '" /></p>';
                    echo '</fieldset>';
                    echo '</form>';

                    break;
            }
        }

        // Formulaire d'activation
        if (!$this->sm_step) {
            echo '<form id="settings" action="' . dotclear()->adminurl->get('admin.plugin.SimpleMenu') . '" method="post">' .
            '<p>' . form::checkbox('active', 1, (boolean) dotclear()->blog->settings->system->simpleMenu_active) .
            '<label class="classic" for="active">' . __('Enable simple menu for this blog') . '</label>' . '</p>' .
            '<p>' . dotclear()->formNonce() . '<input type="submit" name="saveconfig" value="' . __('Save configuration') . '" />' .
            ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';
        }

        // Liste des items
        if (!$this->sm_step) {
            echo '<form id="menuitemsappend" action="' . dotclear()->adminurl->get('admin.plugin.SimpleMenu') . '&amp;add=1" method="post">';
            echo '<p class="top-add">' . dotclear()->formNonce() . '<input class="button add" type="submit" name="appendaction" value="' . __('Add an item') . '" /></p>';
            echo '</form>';
        }

        if (count($this->sm_menu)) {
            if (!$this->sm_step) {
                echo '<form id="menuitems" action="' . dotclear()->adminurl->get('admin.plugin.SimpleMenu') . '" method="post">';
            }
            // Entête table
            echo
            '<div class="table-outer">' .
            '<table class="dragable">' .
            '<caption>' . __('Menu items list') . '</caption>' .
                '<thead>' .
                '<tr>';
            if (!$this->sm_step) {
                echo '<th scope="col"></th>';
                echo '<th scope="col"></th>';
            }
            echo
            '<th scope="col">' . __('Label') . '</th>' .
            '<th scope="col">' . __('Description') . '</th>' .
            '<th scope="col">' . __('URL') . '</th>' .
            '<th scope="col">' . __('Open URL on a new tab') . '</th>' .
                '</tr>' .
                '</thead>' .
                '<tbody' . (!$this->sm_step ? ' id="menuitemslist"' : '') . '>';
            $count = 0;
            foreach ($this->sm_menu as $i => $m) {
                echo '<tr class="line" id="l_' . $i . '">';

                //because targetBlank can not exists. This value has been added after this plugin creation.
                if ((isset($m['targetBlank'])) && ($m['targetBlank'])) {
                    $targetBlank    = true;
                    $targetBlankStr = 'X';
                } else {
                    $targetBlank    = false;
                    $targetBlankStr = '';
                }

                if (!$this->sm_step) {
                    $count++;
                    echo '<td class="handle minimal">' .
                    form::number(['order[' . $i . ']'], [
                        'min'        => 1,
                        'max'        => count($this->sm_menu),
                        'default'    => $count,
                        'class'      => 'position',
                        'extra_html' => 'title="' . sprintf(__('position of %s'), Html::escapeHTML($m['label'])) . '"'
                    ]) .
                    form::hidden(['dynorder[]', 'dynorder-' . $i], $i) . '</td>';
                    echo '<td class="minimal">' . form::checkbox(['items_selected[]', 'ims-' . $i], $i) . '</td>';
                    echo '<td class="nowrap" scope="row">' . form::field(['items_label[]', 'iml-' . $i], null, 255,
                        [
                            'default'    => Html::escapeHTML($m['label']),
                            'extra_html' => 'lang="' . dotclear()->auth->getInfo('user_lang') . '" spellcheck="true"'
                        ]) . '</td>';
                    echo '<td class="nowrap">' . form::field(['items_descr[]', 'imd-' . $i], 30, 255,
                        [
                            'default'    => Html::escapeHTML($m['descr']),
                            'extra_html' => 'lang="' . dotclear()->auth->getInfo('user_lang') . '" spellcheck="true"'
                        ]) . '</td>';
                    echo '<td class="nowrap">' . form::field(['items_url[]', 'imu-' . $i], 30, 255, Html::escapeHTML($m['url'])) . '</td>';
                    echo '<td class="nowrap">' . form::checkbox('items_targetBlank' . $i, 'blank', $targetBlank) . '</td>';
                } else {
                    echo '<td class="nowrap" scope="row">' . Html::escapeHTML($m['label']) . '</td>';
                    echo '<td class="nowrap">' . Html::escapeHTML($m['descr']) . '</td>';
                    echo '<td class="nowrap">' . Html::escapeHTML($m['url']) . '</td>';
                    echo '<td class="nowrap">' . $targetBlankStr . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>' .
                '</table></div>';
            if (!$this->sm_step) {
                echo '<div class="two-cols">';
                echo '<p class="col">' . form::hidden('im_order', '') . dotclear()->formNonce();
                echo '<input type="submit" name="updateaction" value="' . __('Update menu') . '" />' . '</p>';
                echo '<p class="col right">' . '<input id="remove-action" type="submit" class="delete" name="removeaction" ' .
                'value="' . __('Delete selected menu items') . '" ' .
                'onclick="return window.confirm(\'' . Html::escapeJS(__('Are you sure you want to remove selected menu items?')) . '\');" />' .
                    '</p>';
                echo '</div>';
                echo '</form>';
            }
        } else {
            echo
            '<p>' . __('No menu items so far.') . '</p>';
        }
    }
}
