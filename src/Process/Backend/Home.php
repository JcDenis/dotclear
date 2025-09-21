<?php

/**
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Process\Backend;

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Date;
use Dotclear\Helper\Html\Form\Details;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Li;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Single;
use Dotclear\Helper\Html\Form\Span;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Summary;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Textarea;
use Dotclear\Helper\Html\Form\Ul;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Interface\Core\UserWorkspaceInterface;
use Exception;

/**
 * @since 2.27 Before as admin/index.php
 */
class Home
{
    use TraitProcess;

    public static function init(): bool
    {
        if (!App::task()->checkContext('BACKEND')) {
            throw new Exception('Application is not in administrative context.', 500);
        }

        if (!empty($_GET['default_blog'])) {
            try {
                App::users()->setUserDefaultBlog((string) App::auth()->userID(), App::blog()->id());
                App::backend()->url()->redirect('admin.home');
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        App::backend()->page()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]));

        $disabled = App::plugins()->disableDepModules();
        if ($disabled !== []) {
            App::backend()->notices()->addWarningNotice(
                (new Div())
                    ->items([
                        (new Note())
                            ->text(__('The following plugins have been disabled:')),
                        (new Ul())
                            ->items(
                                array_map(fn (string $item) => (new Li())->text($item), $disabled)
                            ),
                    ])
                ->render(),
                ['divtag' => true, 'with_ts' => false]
            );

            App::backend()->url()->redirect('admin.home');
            dotclear_exit();
        }

        // Check dashboard module global prefs
        if (!App::auth()->prefs()->dashboard->prefExists('doclinks', true)) {
            App::auth()->prefs()->dashboard->put('doclinks', true, 'boolean', '', false, true);
        }
        if (!App::auth()->prefs()->dashboard->prefExists('donate', true)) {
            App::auth()->prefs()->dashboard->put('donate', true, 'boolean', '', false, true);
        }
        if (!App::auth()->prefs()->dashboard->prefExists('dcnews', true)) {
            App::auth()->prefs()->dashboard->put('dcnews', true, 'boolean', '', false, true);
        }
        if (!App::auth()->prefs()->dashboard->prefExists('quickentry', true)) {
            App::auth()->prefs()->dashboard->put('quickentry', false, 'boolean', '', false, true);
        }
        if (!App::auth()->prefs()->dashboard->prefExists('nodcupdate', true)) {
            App::auth()->prefs()->dashboard->put('nodcupdate', false, 'boolean', '', false, true);
        }

        // Handle folded/unfolded sections in admin from user preferences
        if (!App::auth()->prefs()->toggles->prefExists('unfolded_sections')) {
            App::auth()->prefs()->toggles->put('unfolded_sections', '', 'string', 'Folded sections in admin', false, true);
        }

        return self::status(true);
    }

    /**
     * @deprecated  use of logout=1 in URL since 2.27, use App::backend()->url()->redirect('admin.logout'); instead
     */
    public static function process(): bool
    {
        if (!empty($_GET['logout'])) {
            // Enable REST service if disabled, for next requests
            if (!App::rest()->serveRestRequests()) {
                App::rest()->enableRestServer(true);
            }
            // Kill admin session
            App::backend()->killAdminSession();
            // Logout
            App::backend()->url()->redirect('admin.auth');
            dotclear_exit();
        }

        if (!empty($_POST['donation-save'])) {
            // Save last donation date
            try {
                App::auth()->prefs()->dashboard->put('donation_date', $_POST['donation-date'], UserWorkspaceInterface::WS_STRING, 'last donation date');

                App::backend()->notices()->addSuccessNotice(__('Your last donation date has been saved.'));
                App::backend()->url()->redirect('admin.home');
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        // Plugin install
        App::backend()->plugins_install = App::plugins()->installModules();

        return true;
    }

    public static function render(): void
    {
        // Dashboard icons

        /**
         * List of dashboard icons (user favorites)
         *
         * items structure:
         * [0] = title
         * [1] = url
         * [2] = icons (usually array (light/dark))
         * [3] = additional informations (usually set by 3rd party plugins)
         *
         * @var        ArrayObject<string, ArrayObject<int, mixed>>
         */
        $__dashboard_icons = new ArrayObject();
        App::backend()->favorites()->appendDashboardIcons($__dashboard_icons);

        // Dashboard items
        $__dashboard_items = new ArrayObject([new ArrayObject(), new ArrayObject()]);
        $dashboardItem     = 0;

        // Documentation links
        if (App::auth()->prefs()->dashboard->doclinks && App::backend()->resources()->entries('doc') !== []) {
            $__dashboard_items[$dashboardItem]->append(static::docLinks(App::backend()->resources()->entries('doc'))); // @phpstan-ignore-line
        }

        // Call for donations
        if (App::auth()->prefs()->dashboard->donate) {
            $__dashboard_items[$dashboardItem]->append(static::donationBlock()); // @phpstan-ignore-line
        }

        # --BEHAVIOR-- adminDashboardItemsV2 -- ArrayObject
        App::behavior()->callBehavior('adminDashboardItemsV2', $__dashboard_items);

        // Dashboard content
        $__dashboard_contents = new ArrayObject([new ArrayObject(), new ArrayObject()]);
        # --BEHAVIOR-- adminDashboardContentsV2 -- ArrayObject
        App::behavior()->callBehavior('adminDashboardContentsV2', $__dashboard_contents);

        // Editor stuff
        $quickentry          = '';
        $admin_post_behavior = '';
        if (App::auth()->prefs()->dashboard->quickentry) {
            if (App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_USAGE,
                App::auth()::PERMISSION_CONTENT_ADMIN,
            ]), App::blog()->id())) {
                $post_format = App::auth()->getOption('post_format');
                $post_editor = App::auth()->getOption('editor');
                if ($post_editor && !empty($post_editor[$post_format])) {
                    # --BEHAVIOR-- adminPostEditor -- string, string, array<int,string>, string
                    $admin_post_behavior = App::behavior()->callBehavior('adminPostEditor', $post_editor[$post_format], 'quickentry', ['#post_content'], $post_format);
                }
            }
            $quickentry = App::backend()->page()->jsJson('dotclear_quickentry', [
                'post_published' => App::status()->post()::PUBLISHED,
                'post_pending'   => App::status()->post()::PENDING,
            ]);
        }

        // Dashboard drag'n'drop switch for its elements
        $dragndrop      = '';
        $dragndrop_head = '';
        if (!App::auth()->prefs()->accessibility->nodragdrop) {
            $dragndrop_msg = [
                'dragndrop_off' => __('Dashboard area\'s drag and drop is disabled'),
                'dragndrop_on'  => __('Dashboard area\'s drag and drop is enabled'),
            ];
            $dragndrop_head = App::backend()->page()->jsJson('dotclear_dragndrop', $dragndrop_msg);
            $dragndrop_icon = '<svg aria-hidden="true" focusable="false" class="dragndrop-svg"><use xlink:href="images/dragndrop.svg#mask"></use></svg>' .
                (new Span($dragndrop_msg['dragndrop_off']))
                    ->id('dragndrop-label')
                    ->class('sr-only')
                ->render();
            $dragndrop = (new Checkbox('dragndrop'))
                ->class('sr-only')
                ->title($dragndrop_msg['dragndrop_off'])
                ->label((new Label($dragndrop_icon, Label::OL_FT)))
            ->render();
        }

        App::backend()->page()->open(
            __('Dashboard'),
            App::backend()->page()->jsLoad('js/jquery/jquery-ui.custom.js') .
            App::backend()->page()->jsLoad('js/jquery/jquery.ui.touch-punch.js') .
            $quickentry .
            App::backend()->page()->jsLoad('js/_index.js') .
            $dragndrop_head .
            $admin_post_behavior .
            App::backend()->page()->jsAdsBlockCheck() .

            # --BEHAVIOR-- adminDashboardHeaders --
            App::behavior()->callBehavior('adminDashboardHeaders'),
            App::backend()->page()->breadcrumb(
                [
                    __('Dashboard') . ' : ' . '<span class="blog-title">' . Html::escapeHTML(App::blog()->name()) . '</span>' => '',
                ],
                ['home_link' => false]
            )
        );

        if (App::auth()->getInfo('user_default_blog') != App::blog()->id() && App::auth()->getBlogCount() > 1) {
            echo (new Para())
                ->items([
                    (new Link())
                        ->class('button')
                        ->href(App::backend()->url()->get('admin.home', ['default_blog' => 1]))
                        ->text(__('Make this blog my default blog')),
                ])
            ->render();
        }

        if (App::blog()->status() === App::status()->blog()::OFFLINE) {
            App::backend()->notices()->message(__('This blog is offline'), false);
        } elseif (App::blog()->status() === App::status()->blog()::REMOVED) {
            App::backend()->notices()->message(__('This blog is removed'), false);
        }

        if (App::config()->adminUrl() === '') {
            App::backend()->notices()->message(
                sprintf(
                    __('%s is not defined, you should edit your configuration file.'),
                    'DC_ADMIN_URL'
                ) .
                ' ' .
                sprintf(
                    __('See <a href="%s">documentation</a> for more information.'),
                    'https://dotclear.org/documentation/2.0/admin/config'
                ),
                false
            );
        }

        if (App::config()->adminMailfrom() === 'dotclear@local') {
            App::backend()->notices()->message(
                sprintf(
                    __('%s is not defined, you should edit your configuration file.'),
                    'DC_ADMIN_MAILFROM'
                ) .
                ' ' .
                sprintf(
                    __('See <a href="%s">documentation</a> for more information.'),
                    'https://dotclear.org/documentation/2.0/admin/config'
                ),
                false
            );
        }

        $err = [];

        // Check cache directory
        if (App::auth()->isSuperAdmin()) {
            if (!is_dir(App::config()->cacheRoot()) || !is_writable(App::config()->cacheRoot())) {
                $err[] = __('The cache directory does not exist or is not writable. You must create this directory with sufficient rights and affect this location to "DC_TPL_CACHE" in inc/config.php file.');
            }
        } elseif (!is_dir(App::config()->cacheRoot()) || !is_writable(App::config()->cacheRoot())) {
            $err[] = __('The cache directory does not exist or is not writable. You should contact your administrator.');
        }

        // Check public directory
        if (App::auth()->isSuperAdmin()) {
            if (!is_dir(App::blog()->publicPath()) || !is_writable(App::blog()->publicPath())) {
                $err[] = __('There is no writable directory /public/ at the location set in about:config "public_path". You must create this directory with sufficient rights (or change this setting).');
            }
        } elseif (!is_dir(App::blog()->publicPath()) || !is_writable(App::blog()->publicPath())) {
            $err[] = __('There is no writable root directory for the media manager. You should contact your administrator.');
        }

        // Error list
        if ($err !== []) {
            App::backend()->notices()->error(
                (new Div())
                    ->items([
                        (new Note())
                            ->text(__('Error:')),
                        (new Ul())
                            ->items(
                                array_map(fn (string $item) => (new Li())->text($item), $err)
                            ),
                    ])
                ->render(),
                false,
                true
            );
            unset($err);
        }

        // Plugins install messages
        if (!empty(App::backend()->plugins_install['success'])) {
            $success = [];
            foreach (App::backend()->plugins_install['success'] as $k => $v) {
                $info      = implode(' - ', App::backend()->modulesList()->getSettingsUrls($k, true));
                $success[] = $k . ($info !== '' ? ' → ' . $info : '');
            }

            App::backend()->notices()->success(
                (new Div())
                    ->items([
                        (new Note())
                            ->text(__('Following plugins have been installed:')),
                        (new Ul())
                            ->items(
                                array_map(fn (string $item) => (new Li())->text($item), $success)
                            ),
                    ])
                ->render(),
                false,
                true
            );
            unset($success);
        }
        if (!empty(App::backend()->plugins_install['failure'])) {
            $failure = [];
            foreach (App::backend()->plugins_install['failure'] as $k => $v) {
                $failure[] = $k . ' (' . $v . ')';
            }

            App::backend()->notices()->error(
                (new Div())
                    ->items([
                        (new Note())
                            ->text(__('Following plugins have not been installed:')),
                        (new Ul())
                            ->items(
                                array_map(fn ($item) => (new Li())->text($item), $failure)
                            ),
                    ])
                ->render(),
                false,
                true
            );
            unset($failure);
        }

        // Errors modules notifications
        if (App::auth()->isSuperAdmin()) {
            $list = App::plugins()->getErrors();
            if ($list !== []) {
                App::backend()->notices()->error(
                    (new Div())
                        ->items([
                            (new Note())
                                ->text(__('Errors have occured with following plugins:')),
                            (new Ul())
                                ->items(
                                    array_map(fn (string $item) => (new Li())->text($item), $list)
                                ),
                        ])
                    ->render(),
                    false,
                    true
                );
            }
        }

        // Get current main orders
        $main_order = App::auth()->prefs()->dashboard->main_order;
        $main_order = ($main_order != '' ? explode(',', (string) $main_order) : []);

        // Get current boxes orders
        $boxes_order = App::auth()->prefs()->dashboard->boxes_order;
        $boxes_order = ($boxes_order != '' ? explode(',', (string) $boxes_order) : []);

        // Get current boxes items orders
        $boxes_items_order = App::auth()->prefs()->dashboard->boxes_items_order;
        $boxes_items_order = ($boxes_items_order != '' ? explode(',', (string) $boxes_items_order) : []);

        // Get current boxes contents orders
        $boxes_contents_order = App::auth()->prefs()->dashboard->boxes_contents_order;
        $boxes_contents_order = ($boxes_contents_order != '' ? explode(',', (string) $boxes_contents_order) : []);

        $composeItems = function ($list, $blocks, $flat = false): string {
            $ret   = [];
            $items = [];

            if ($flat) {
                $items = $blocks;
            } else {
                foreach ($blocks as $i) {
                    foreach ($i as $v) {
                        $items[] = $v;
                    }
                }
            }

            // First loop to find ordered indexes
            $order = [];
            $index = 0;
            foreach ($items as $v) {
                if (preg_match('/<div.*?id="([^"].*?)".*?>/ms', (string) $v, $match)) {
                    $id       = $match[1];
                    $position = array_search($id, $list, true);
                    if ($position !== false) {
                        $order[$position] = $index;
                    }
                }
                $index++;
            }

            // Second loop to combine ordered items
            $index = 0;
            foreach ($items as $v) {
                $position = array_search($index, $order, true);
                if ($position !== false) {
                    $ret[$position] = $v;
                }
                $index++;
            }
            ksort($ret);    // Reorder items on their position (key)

            // Third loop to combine unordered items
            $index = 0;
            foreach ($items as $v) {
                $position = array_search($index, $order, true);
                if ($position === false) {
                    $ret[] = $v;
                }
                $index++;
            }

            return implode('', $ret);
        };

        // Compose dashboard items (doc, …)
        $dashboardItems = $composeItems($boxes_items_order, $__dashboard_items);

        // Compose dashboard contents (plugin's modules)
        $dashboardContents = $composeItems($boxes_contents_order, $__dashboard_contents);

        // Compose dashboard boxes (items, contents)
        $__dashboard_boxes = [];
        if ($dashboardItems !== '') {
            $__dashboard_boxes[] = (new Div('db-items'))
                ->class('db-items')
                ->items([
                    (new Text(null, $dashboardItems)),
                ])
            ->render();
        }
        if ($dashboardContents !== '') {
            $__dashboard_boxes[] = (new Div('db-contents'))
                ->class('db-contents')
                ->items([
                    (new Text(null, $dashboardContents)),
                ])
            ->render();
        }
        $dashboardBoxes = $composeItems($boxes_order, $__dashboard_boxes, true);

        // Compose main area (icons, quick entry, boxes)
        $__dashboard_main = [];

        if (!App::auth()->prefs()->dashboard->nofavicons) {
            // Dashboard icons
            $dashboardIcons = (new Div('icons'))
                ->items(
                    array_map(
                        fn (string $id, ArrayObject $info) => (new Para())
                            /*
                             * $info item structure:
                             * [0] = title
                             * [1] = url
                             * [2] = icons (usually array (light/dark))
                             * [3] = additional informations (usually set by 3rd party plugins on adminDashboardFavsIconV2 behaviour)
                             */
                            ->items([
                                (new Link('icon-process-' . $id . '-fav'))
                                    ->href($info[1])
                                    ->items([
                                        (new Text(null, App::backend()->helper()->adminIcon($info[2]))),
                                        (new Single('br')),
                                        (new Span($info[0]))
                                            ->class('db-icon-title'),
                                        isset($info[3]) ?
                                        (new Text(null, $info[3])) :
                                        (new None()),
                                    ]),
                            ]),
                        array_keys($__dashboard_icons->getArrayCopy()),
                        array_values($__dashboard_icons->getArrayCopy())
                    )
                )
            ->render();
            $__dashboard_main[] = $dashboardIcons;
        }

        if (App::auth()->prefs()->dashboard->quickentry && App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            // Quick entry
            $__dashboard_main[] = static::quickEntry();
        }

        if ($dashboardBoxes !== '') {
            $__dashboard_main[] = '<div id="dashboard-boxes">' . $dashboardBoxes . '</div>';
        }

        $dashboardMain = $composeItems($main_order, $__dashboard_main, true);

        # --BEHAVIOR-- adminDashboardItemsV2 -- ArrayObject
        App::behavior()->callBehavior('adminDashboardMessage');

        echo $dragndrop . '<div id="dashboard-main">' . $dashboardMain . '</div>';

        App::backend()->page()->helpBlock('core_dashboard');
        App::backend()->page()->close();
    }

    // Helpers

    /**
     * Get rendered quick entry form module
     */
    protected static function quickEntry(): string
    {
        // Get categories
        $categories_combo = App::backend()->combos()->getCategoriesCombo(
            App::blog()->getCategories([])
        );

        return
        (new Div('quick'))
            ->items([
                (new Text('h3', __('Quick post') . sprintf(' &rsaquo; %s', App::formater()->getFormaterName(App::auth()->getOption('post_format'))))),
                (new Form('quick-entry'))
                    ->method('post')
                    ->action(App::backend()->url()->get('admin.post'))
                    ->class('fieldset')
                    ->fields([
                        (new Text('h4', __('New post'))),
                        (new Note())
                            ->class('form-note')
                            ->text(sprintf(__('Fields preceded by %s are mandatory.'), (new Span('*'))->class('required')->render())),
                        (new Para())
                            ->class('col')
                            ->items([
                                (new Input('post_title'))
                                    ->size(20)
                                    ->maxlength(255)
                                    ->class('maximal')
                                    ->placeholder(__('Title'))
                                    ->required(true)
                                    ->label(
                                        (new Label(
                                            (new Span('*'))->render() . __('Title:'),
                                            Label::IL_TF
                                        ))
                                        ->class('required')
                                    ),
                            ]),
                        (new Para())
                            ->class('area')
                            ->items([
                                (new Label((new Span('*'))->render() . __('Content:'), Label::OL_TF))
                                    ->for('post_content')
                                    ->class('required'),
                                (new Textarea('post_content'))
                                    ->cols(50)
                                    ->rows(10)
                                    ->placeholder(__('Content'))
                                    ->required(true),
                            ]),
                        (new Para())
                            ->items([
                                (new Select('cat_id'))
                                    ->items($categories_combo)
                                    ->label(new Label(__('Category:'), Label::IL_TF)),
                            ]),
                        App::auth()->check(App::auth()->makePermissions([App::auth()::PERMISSION_CATEGORIES]), App::blog()->id()) ?
                            (new Details('new_cat'))
                                ->summary(new Summary(__('Add a new category')))
                                ->items([
                                    (new Para())
                                        ->class('q-cat')
                                        ->items([
                                            (new Input('new_cat_title'))
                                                ->size(30)
                                                ->maxlength(255)
                                                ->label(new Label(__('Title:'), Label::IL_TF)),
                                        ]),
                                    (new Para())
                                        ->class('q-cat')
                                        ->items([
                                            (new Select('new_cat_parent'))
                                                ->items($categories_combo)
                                                ->label(new Label(__('Parent:'), Label::IL_TF)),
                                        ]),
                                    (new Note())
                                        ->class(['form-note', 'info', 'clear'])
                                        ->text(__('This category will be created when you will save your post.')),
                                ]) :
                            (new None()),
                        (new Para())
                            ->class('form-buttons')
                            ->items([
                                (new Submit('save', __('Save'))),
                                App::auth()->check(App::auth()->makePermissions([App::auth()::PERMISSION_PUBLISH]), App::blog()->id()) ?
                                    (new Hidden('save-publish', __('Save and publish'))) :
                                    (new None()),
                                App::nonce()->formNonce(),
                                (new Hidden('post_status', (string) App::status()->post()::PENDING)),
                                (new Hidden('post_format', (string) App::auth()->getOption('post_format'))),
                                (new Hidden('post_excerpt', '')),
                                (new Hidden('post_lang', (string) App::auth()->getInfo('user_lang'))),
                                (new Hidden('post_notes', '')),
                            ]),
                    ]),
            ])
        ->render();
    }

    /**
     * Get rendered donation module
     */
    protected static function donationBlock(): string
    {
        return (new Div('donate'))
            ->class(['box', 'small', 'dc-box'])
            ->items([
                (new Text('h3', __('Donate to Dotclear'))),
                (new Note())
                    ->text(__('Dotclear is not a commercial project — using Dotclear is <strong>free</strong> and <strong>always</strong> will be. If you wish to, you may contribute to Dotclear to help us cover project-related expenses.')),
                (new Note())
                    ->text(__('The collected funds will be spent as follows:')),
                (new Ul())
                    ->items([
                        (new Li())
                            ->text(__('Paying for the website hosting and translations')),
                        (new Li())
                            ->text(__('Paying for the domain names')),
                        (new Li())
                            ->text(__('Supporting related projects such as Dotaddict.org')),
                        (new Li())
                            ->text(__('Cover the costs of events set up by Dotclear')),
                    ]),
                (new Note())
                    ->text(sprintf(
                        __('See <a href="%s">this page</a> for more information and donation'),
                        'https://dotclear.org/donate'
                    )),
                (new Form('donation-form'))
                    ->method('post')
                    ->action(App::backend()->url()->get('admin.home'))
                    ->items([
                        (new Para())
                            ->class('form-buttons')
                            ->items([
                                (new Date('donation-date', App::auth()->prefs()->dashboard->donation_date))
                                    ->label((new Label(__('For the record, here is the date of my last donation to Dotclear:'), Label::IL_TF))->class('classic')),
                                (new Submit('donation-save', __('Save'))),
                                App::nonce()->formNonce(),
                            ]),
                    ]),
            ])
        ->render();
    }

    /**
     * Get rendered documentation links module
     *
     * @param      array<string, string>       $links  The links
     */
    protected static function docLinks(array $links): string
    {
        return (new Div('doc-and-support'))
            ->class(['box', 'small', 'dc-box'])
            ->items([
                (new Text('h3', __('Documentation and support'))),
                (new Ul())
                    ->items(
                        array_map(
                            fn ($title, $href) => (new Li())
                                ->items([
                                    (new Link())
                                        ->href($href)
                                        ->title($title)
                                        ->text($title),
                                ]),
                            array_keys($links),
                            array_values($links)
                        )
                    ),
            ])
        ->render();
    }
}
