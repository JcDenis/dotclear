<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Admin;

// Dotclear\Plugin\SimpleMenu\Admin\Handler
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin page for plugin SimpleMenu.
 *
 * @ingroup  Plugin SimpleMenu
 */
class Handler extends AbstractPage
{
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

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        // Liste des catégories
        $param = new Param();
        $param->set('post_type', 'post');

        $categories_label          = [];
        $rs                        = App::core()->blog()->categories()->getCategories(param: $param);
        $this->sm_categories_combo = App::core()->combo()->getCategoriesCombo($rs, false, true);
        $rs->moveStart();
        while ($rs->fetch()) {
            $categories_label[$rs->f('cat_url')] = Html::escapeHTML($rs->f('cat_title'));
        }

        // Liste des langues utilisées
        $param = new Param();
        $param->set('order', 'asc');

        $this->sm_langs_combo = App::core()->combo()->getLangscombo(
            App::core()->blog()->posts()->getLangs(param: $param)
        );

        // Liste des mois d'archive
        $param = new Param();
        $param->set('type', 'month');

        $rs                    = App::core()->blog()->posts()->getDates(param: $param);
        $this->sm_months_combo = array_merge(
            [__('All months') => '-'],
            App::core()->combo()->getDatesCombo($rs)
        );

        $first_year = $last_year = 0;
        while ($rs->fetch()) {
            if (0 == $first_year || $rs->year() < $first_year) {
                $first_year = $rs->year();
            }

            if (0 == $last_year || $rs->year() > $last_year) {
                $last_year = $rs->year();
            }
        }
        unset($rs);

        // Liste des pages -- Doit être pris en charge plus tard par le plugin ?
        try {
            $param = new Param();
            $param->set('post_type', 'page');

            $rs = App::core()->blog()->posts()->getPosts(param: $param);
            while ($rs->fetch()) {
                $this->sm_pages_combo[$rs->f('post_title')] = $rs->getURL();
            }
            unset($rs);
        } catch (\Exception) {
        }

        // Liste des tags -- Doit être pris en charge plus tard par le plugin ?
        try {
            $param = new Param();
            $param->set('meta_type', 'tag');

            $rs                                  = App::core()->meta()->getMetadata(param: $param);
            $this->sm_tags_combo[__('All tags')] = '-';
            while ($rs->fetch()) {
                $this->sm_tags_combo[$rs->f('meta_id')] = $rs->f('meta_id');
            }
            unset($rs);
        } catch (\Exception) {
        }

        // Liste des types d'item de menu
        $this->sm_items         = new ArrayObject();
        $this->sm_items['home'] = new ArrayObject([__('Home'), false]);

        if (App::core()->blog()->settings()->getGroup('system')->getSetting('static_home')) {
            $this->sm_items['posts'] = new ArrayObject([__('Posts'), false]);
        }

        if (1 < count($this->sm_langs_combo)) {
            $this->sm_items['lang'] = new ArrayObject([__('Language'), true]);
        }
        if (count($this->sm_categories_combo)) {
            $this->sm_items['category'] = new ArrayObject([__('Category'), true]);
        }
        if (1 < count($this->sm_months_combo)) {
            $this->sm_items['archive'] = new ArrayObject([__('Archive'), true]);
        }
        if (App::core()->plugins()->hasModule('pages')) {
            if (count($this->sm_pages_combo)) {
                $this->sm_items['pages'] = new ArrayObject([__('Page'), true]);
            }
        }
        if (App::core()->plugins()->hasModule('tags')) {
            if (1 < count($this->sm_tags_combo)) {
                $this->sm_items['tags'] = new ArrayObject([__('Tags'), true]);
            }
        }

        // --BEHAVIOR-- adminSimpleMenuAddType
        // Should add an item to $this->sm_items[<id>] as an [<label>,<optional step (true or false)>]
        App::core()->behavior()->call('adminSimpleMenuAddType', $this->sm_items);

        $this->sm_items['special'] = new ArrayObject([__('User defined'), false]);

        // Lecture menu existant
        $menu = App::core()->blog()->settings()->getGroup('system')->getSetting('simpleMenu');
        if (is_array($menu)) {
            $this->sm_menu = $menu;
        }

        // Saving new configuration
        $item_targetBlank = false;
        $step_label       = '';
        if (!GPC::post()->empty('saveconfig')) {
            try {
                $menu_active = !GPC::post()->empty('active');
                App::core()->blog()->settings()->getGroup('system')->putSetting('simpleMenu_active', $menu_active, 'boolean');
                App::core()->blog()->triggerBlog();

                // All done successfully, return to menu items list
                App::core()->notice()->addSuccessNotice(__('Configuration successfully updated.'));
                App::core()->adminurl()->redirect('admin.plugin.SimpleMenu');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        } else {
            // Récupération paramètres postés
            $this->sm_item_type   = GPC::post()->string('item_type');
            $this->sm_item_select = GPC::post()->string('item_select');
            $this->sm_item_label  = GPC::post()->string('item_label');
            $this->sm_item_descr  = GPC::post()->string('item_descr');
            $this->sm_item_url    = GPC::post()->string('item_url');
            $item_targetBlank     = !GPC::post()->empty('item_targetBlank');
            // Traitement
            $this->sm_step = GPC::request()->int('add');
            if (4 < $this->sm_step || 0 > $this->sm_step) {
                $this->sm_step = 0;
            }

            if ($this->sm_step) {
                // Récupération libellés des choix
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
                        // no break
                    case 3:
                        // Third step, menu item attributes to be changed or completed if necessary
                        $this->sm_item_select_label = '';
                        $this->sm_item_label        = __('Label');
                        $this->sm_item_descr        = __('Description');
                        $this->sm_item_url          = Html::stripHostURL(App::core()->blog()->url);

                        switch ($this->sm_item_type) {
                            case 'home':
                                $this->sm_item_label = __('Home');
                                $this->sm_item_descr = App::core()->blog()->settings()->getGroup('system')->getSetting('static_home') ? __('Home page') : __('Recent posts');

                                break;

                            case 'posts':
                                $this->sm_item_label = __('Posts');
                                $this->sm_item_descr = __('Recent posts');
                                $this->sm_item_url .= App::core()->url()->getURLFor('posts');

                                break;

                            case 'lang':
                                $this->sm_item_select_label = array_search($this->sm_item_select, $this->sm_langs_combo);
                                $this->sm_item_label        = $this->sm_item_select_label;
                                $this->sm_item_descr        = sprintf(__('Switch to %s language'), $this->sm_item_select_label);
                                $this->sm_item_url .= App::core()->url()->getURLFor('lang', $this->sm_item_select);

                                break;

                            case 'category':
                                $this->sm_item_select_label = $categories_label[$this->sm_item_select];
                                $this->sm_item_label        = $this->sm_item_select_label;
                                $this->sm_item_descr        = __('Recent Posts from this category');
                                $this->sm_item_url .= App::core()->url()->getURLFor('category', $this->sm_item_select);

                                break;

                            case 'archive':
                                $this->sm_item_select_label = array_search($this->sm_item_select, $this->sm_months_combo);
                                if ('-' == $this->sm_item_select) {
                                    $this->sm_item_label = __('Archives');
                                    $this->sm_item_descr = $first_year . ($first_year != $last_year ? ' - ' . $last_year : '');
                                    $this->sm_item_url .= App::core()->url()->getURLFor('archive');
                                } else {
                                    $this->sm_item_label = $this->sm_item_select_label;
                                    $this->sm_item_descr = sprintf(__('Posts from %s'), $this->sm_item_select_label);
                                    $this->sm_item_url .= App::core()->url()->getURLFor('archive', substr($this->sm_item_select, 0, 4) . '/' . substr($this->sm_item_select, -2));
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
                                if ('-' == $this->sm_item_select) {
                                    $this->sm_item_label = __('All tags');
                                    $this->sm_item_descr = '';
                                    $this->sm_item_url .= App::core()->url()->getURLFor('tags');
                                } else {
                                    $this->sm_item_label = $this->sm_item_select_label;
                                    $this->sm_item_descr = sprintf(__('Recent posts for %s tag'), $this->sm_item_select_label);
                                    $this->sm_item_url .= App::core()->url()->getURLFor('tag', $this->sm_item_select);
                                }

                                break;

                            case 'special':
                                break;

                            default:
                                // --BEHAVIOR-- adminSimpleMenuBeforeEdit
                                // Should modify if necessary $this->sm_item_label, $this->sm_item_descr and $this->sm_item_url
                                // Should set if necessary $this->sm_item_select_label (displayed on further admin step only)
                                App::core()->behavior()->call(
                                    'adminSimpleMenuBeforeEdit',
                                    $this->sm_item_type,
                                    $this->sm_item_select,
                                    [&$this->sm_item_label, &$this->sm_item_descr, &$this->sm_item_url, &$this->sm_item_select_label]
                                );

                                break;
                        }

                        break;

                    case 4:
                        // Fourth step, menu item to be added
                        try {
                            if ('' != $this->sm_item_label && '' != $this->sm_item_url) {
                                // Add new item menu in menu array
                                $this->sm_menu[] = [
                                    'label'       => $this->sm_item_label,
                                    'descr'       => $this->sm_item_descr,
                                    'url'         => $this->sm_item_url,
                                    'targetBlank' => $item_targetBlank,
                                ];

                                // Save menu in blog settings
                                App::core()->blog()->settings()->getGroup('system')->putSetting('simpleMenu', $this->sm_menu);
                                App::core()->blog()->triggerBlog();

                                // All done successfully, return to menu items list
                                App::core()->notice()->addSuccessNotice(__('Menu item has been successfully added.'));
                                App::core()->adminurl()->redirect('admin.plugin.SimpleMenu');
                            } else {
                                $this->sm_step              = 3;
                                $this->sm_item_select_label = $this->sm_item_label;
                                App::core()->notice()->addErrorNotice(__('Label and URL of menu item are mandatory.'));
                            }
                        } catch (Exception $e) {
                            App::core()->error()->add($e->getMessage());
                        }

                        break;
                }
            } else {
                // Remove selected menu items
                if (!GPC::post()->empty('removeaction')) {
                    try {
                        if (!GPC::post()->empty('items_selected')) {
                            foreach (GPC::post()->array('items_selected') as $k => $v) {
                                $this->sm_menu[$v]['label'] = '';
                            }
                            $newmenu = [];
                            foreach ($this->sm_menu as $k => $v) {
                                if ($v['label']) {
                                    $newmenu[] = [
                                        'label'       => $v['label'],
                                        'descr'       => $v['descr'],
                                        'url'         => $v['url'],
                                        'targetBlank' => $v['targetBlank'],
                                    ];
                                }
                            }
                            $this->sm_menu = $newmenu;
                            // Save menu in blog settings
                            App::core()->blog()->settings()->getGroup('system')->putSetting('simpleMenu', $this->sm_menu);
                            App::core()->blog()->triggerBlog();

                            // All done successfully, return to menu items list
                            App::core()->notice()->addSuccessNotice(__('Menu items have been successfully removed.'));
                            App::core()->adminurl()->redirect('admin.plugin.SimpleMenu');
                        } else {
                            throw new ModuleException(__('No menu items selected.'));
                        }
                    } catch (Exception $e) {
                        App::core()->error()->add($e->getMessage());
                    }
                }

                // Update menu items
                if (!GPC::post()->empty('updateaction')) {
                    try {
                        foreach (GPC::post()->array('items_label') as $k => $v) {
                            if (!$v) {
                                throw new ModuleException(__('Label is mandatory.'));
                            }
                        }
                        foreach (GPC::post()->array('items_url') as $k => $v) {
                            if (!$v) {
                                throw new ModuleException(__('URL is mandatory.'));
                            }
                        }
                        $newmenu = [];
                        for ($i = 0; count(GPC::post()->array('items_label')) > $i; ++$i) {
                            $newmenu[] = [
                                'label'       => GPC::post()->array('items_label')[$i],
                                'descr'       => GPC::post()->array('items_label')[$i],
                                'url'         => GPC::post()->array('items_label')[$i],
                                'targetBlank' => !GPC::post()->empty('items_targetBlank' . $i),
                            ];
                        }
                        $this->sm_menu = $newmenu;

                        if (App::core()->user()->preference()->get('accessibility')->get('nodragdrop')) {
                            // Order menu items
                            $order = [];
                            if (GPC::post()->empty('im_order') && !GPC::post()->empty('order')) {
                                $order = GPC::post()->array('order');
                                asort($order);
                                $order = array_keys($order);
                            } elseif (!GPC::post()->empty('im_order')) {
                                $order = GPC::post()->string('im_order');
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
                                        'url'   => $this->sm_menu[$k]['url'], ];
                                }
                                $this->sm_menu = $newmenu;
                            }
                        }

                        // Save menu in blog settings
                        App::core()->blog()->settings()->getGroup('system')->putSetting('simpleMenu', $this->sm_menu);
                        App::core()->blog()->triggerBlog();

                        // All done successfully, return to menu items list
                        App::core()->notice()->addSuccessNotice(__('Menu items have been successfully updated.'));
                        App::core()->adminurl()->redirect('admin.plugin.SimpleMenu');
                    } catch (Exception $e) {
                        App::core()->error()->add($e->getMessage());
                    }
                }
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Simple menu'))
            ->setPageHelp('simpleMenu')
            ->setPageHead(App::core()->resource()->confirmClose('settings', 'menuitemsappend', 'additem', 'menuitems'))
        ;
        if (!App::core()->user()->preference()->get('accessibility')->get('nodragdrop')) {
            $this->setPageHead(
                App::core()->resource()->load('jquery/jquery-ui.custom.js') .
                App::core()->resource()->load('jquery/jquery.ui.touch-punch.js') .
                App::core()->resource()->load('simplemenu.js', 'Plugin', 'SimpleMenu')
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
                    // no break
                case 3:
                    $step_label = $this->sm_items[$this->sm_item_type][1] ? __('Step #3') : __('Step #2');

                    break;
            }

            $this->setPageBreadcrumb(
                [
                    Html::escapeHTML(App::core()->blog()->name) => '',
                    __('Simple menu')                           => App::core()->adminurl()->get('admin.plugin.SimpleMenu'),
                    __('Add item')                              => '',
                    $step_label                                 => '',
                ],
                ['hl_pos' => -2]
            );
        } else {
            $this->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Simple menu')                           => '',
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
                    echo '<form id="additem" action="' . App::core()->adminurl()->root() . '" method="post">';
                    echo '<fieldset><legend>' . __('Select type') . '</legend>';
                    echo '<p class="field"><label for="item_type" class="classic">' . __('Type of item menu:') . '</label>' . form::combo('item_type', $items_combo) . '</p>';
                    echo '<p>' . App::core()->adminurl()->getHiddenFormFields('admin.plugin.SimpleMenu', ['add' => 2], true);
                    echo '<input type="submit" name="appendaction" value="' . __('Continue...') . '" />' . '</p>';
                    echo '</fieldset>';
                    echo '</form>';

                    break;

                case 2:
                    if ($this->sm_items[$this->sm_item_type][1]) {
                        // Choix à faire
                        echo '<form id="additem" action="' . App::core()->adminurl()->root() . '" method="post">';
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
                                echo // --BEHAVIOR-- adminSimpleMenuSelect
                                // Optional step once $this->sm_item_type known : should provide a field using 'item_select' as id
                                App::core()->behavior()->call('adminSimpleMenuSelect', $this->sm_item_type, 'item_select');
                        }
                        echo '<p>' . App::core()->adminurl()->getHiddenFormFields('admin.plugin.SimpleMenu', ['item_type' => $this->sm_item_type, 'add' => 3], true);
                        echo '<input type="submit" name="appendaction" value="' . __('Continue...') . '" /></p>';
                        echo '</fieldset>';
                        echo '</form>';

                        break;
                    }
                    // no break
                case 3:
                    // Libellé et description
                    echo '<form id="additem" action="' . App::core()->adminurl()->root() . '" method="post">';
                    echo '<fieldset><legend>' . $this->sm_item_type_label . ('' != $this->sm_item_select_label ? ' (' . $this->sm_item_select_label . ')' : '') . '</legend>';
                    echo '<p class="field"><label for="item_label" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' .
                    __('Label of item menu:') . '</label>' .
                    form::field('item_label', 20, 255, [
                        'default'    => $this->sm_item_label,
                        'extra_html' => 'required placeholder="' . __('Label') . '" lang="' . App::core()->user()->getInfo('user_lang') . '" spellcheck="true"',
                    ]) .
                        '</p>';
                    echo '<p class="field"><label for="item_descr" class="classic">' .
                    __('Description of item menu:') . '</label>' . form::field(
                        'item_descr',
                        30,
                        255,
                        [
                            'default'    => $this->sm_item_descr,
                            'extra_html' => 'lang="' . App::core()->user()->getInfo('user_lang') . '" spellcheck="true"',
                        ]
                    ) . '</p>';
                    echo '<p class="field"><label for="item_url" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' .
                    __('URL of item menu:') . '</label>' .
                    form::field('item_url', 40, 255, [
                        'default'    => $this->sm_item_url,
                        'extra_html' => 'required placeholder="' . __('URL') . '"',
                    ]) .
                        '</p>';
                    echo '<p class="field"><label for="item_descr" class="classic">' .
                    __('Open URL on a new tab') . ':</label>' . form::checkbox('item_targetBlank', 'blank') . '</p>';
                    echo '<p>' . App::core()->adminurl()->getHiddenFormFields(
                        'admin.plugin.SimpleMenu',
                        ['item_type' => $this->sm_item_type, 'item_select' => $this->sm_item_select, 'add' => 4],
                        true
                    );
                    echo '<input type="submit" name="appendaction" value="' . __('Add this item') . '" /></p>';
                    echo '</fieldset>';
                    echo '</form>';

                    break;
            }
        }

        // Formulaire d'activation
        if (!$this->sm_step) {
            echo '<form id="settings" action="' . App::core()->adminurl()->root() . '" method="post">' .
            '<p>' . form::checkbox('active', 1, (bool) App::core()->blog()->settings()->getGroup('system')->getSetting('simpleMenu_active')) .
            '<label class="classic" for="active">' . __('Enable simple menu for this blog') . '</label>' . '</p>' .
            '<p>' . App::core()->adminurl()->getHiddenFormFields('admin.plugin.SimpleMenu', [], true) .
            '<input type="submit" name="saveconfig" value="' . __('Save configuration') . '" />' .
            ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';
        }

        // Liste des items
        if (!$this->sm_step) {
            echo '<form id="menuitemsappend" action="' . App::core()->adminurl()->root() . '" method="post">';
            echo '<p class="top-add">' . App::core()->adminurl()->getHiddenFormFields('admin.plugin.SimpleMenu', ['add' => 1], true);
            echo '<input class="button add" type="submit" name="appendaction" value="' . __('Add an item') . '" /></p>';
            echo '</form>';
        }

        if (count($this->sm_menu)) {
            if (!$this->sm_step) {
                echo '<form id="menuitems" action="' . App::core()->adminurl()->root() . '" method="post">';
            }
            // Entête table
            echo '<div class="table-outer">' .
            '<table class="dragable">' .
            '<caption>' . __('Menu items list') . '</caption>' .
                '<thead>' .
                '<tr>';
            if (!$this->sm_step) {
                echo '<th scope="col"></th>';
                echo '<th scope="col"></th>';
            }
            echo '<th scope="col">' . __('Label') . '</th>' .
            '<th scope="col">' . __('Description') . '</th>' .
            '<th scope="col">' . __('URL') . '</th>' .
            '<th scope="col">' . __('Open URL on a new tab') . '</th>' .
                '</tr>' .
                '</thead>' .
                '<tbody' . (!$this->sm_step ? ' id="menuitemslist"' : '') . '>';
            $count = 0;
            foreach ($this->sm_menu as $i => $m) {
                echo '<tr class="line" id="l_' . $i . '">';

                // because targetBlank can not exists. This value has been added after this plugin creation.
                if ((isset($m['targetBlank'])) && ($m['targetBlank'])) {
                    $targetBlank    = true;
                    $targetBlankStr = 'X';
                } else {
                    $targetBlank    = false;
                    $targetBlankStr = '';
                }

                if (!$this->sm_step) {
                    ++$count;
                    echo '<td class="handle minimal">' .
                    form::number(['order[' . $i . ']'], [
                        'min'        => 1,
                        'max'        => count($this->sm_menu),
                        'default'    => $count,
                        'class'      => 'position',
                        'extra_html' => 'title="' . sprintf(__('position of %s'), Html::escapeHTML($m['label'])) . '"',
                    ]) .
                    form::hidden(['dynorder[]', 'dynorder-' . $i], $i) . '</td>';
                    echo '<td class="minimal">' . form::checkbox(['items_selected[]', 'ims-' . $i], $i) . '</td>';
                    echo '<td class="nowrap" scope="row">' . form::field(
                        ['items_label[]', 'iml-' . $i],
                        null,
                        255,
                        [
                            'default'    => Html::escapeHTML($m['label']),
                            'extra_html' => 'lang="' . App::core()->user()->getInfo('user_lang') . '" spellcheck="true"',
                        ]
                    ) . '</td>';
                    echo '<td class="nowrap">' . form::field(
                        ['items_descr[]', 'imd-' . $i],
                        30,
                        255,
                        [
                            'default'    => Html::escapeHTML($m['descr']),
                            'extra_html' => 'lang="' . App::core()->user()->getInfo('user_lang') . '" spellcheck="true"',
                        ]
                    ) . '</td>';
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
                echo '<p class="col">' . form::hidden('im_order', '') . App::core()->adminurl()->getHiddenFormFields('admin.plugin.SimpleMenu', [], true);
                echo '<input type="submit" name="updateaction" value="' . __('Update menu') . '" />' . '</p>';
                echo '<p class="col right">' . '<input id="remove-action" type="submit" class="delete" name="removeaction" ' .
                'value="' . __('Delete selected menu items') . '" ' .
                'onclick="return window.confirm(\'' . Html::escapeJS(__('Are you sure you want to remove selected menu items?')) . '\');" />' .
                    '</p>';
                echo '</div>';
                echo '</form>';
            }
        } else {
            echo '<p>' . __('No menu items so far.') . '</p>';
        }
    }
}
