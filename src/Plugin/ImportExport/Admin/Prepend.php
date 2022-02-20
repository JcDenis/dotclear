<?php
/**
 * @brief importExport, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

$_menu['Plugins']->addItem(
    __('Import/Export'),
    $core->adminurl->get('admin.plugin.importExport'),
    [dcPage::getPF('importExport/icon.svg'), dcPage::getPF('importExport/icon-dark.svg')],
    preg_match('/' . preg_quote($core->adminurl->get('admin.plugin.importExport')) . '(&.*)?$/', $_SERVER['REQUEST_URI']),
    $core->auth->check('admin', $core->blog->id)
);

$core->addBehavior(
    'adminDashboardFavorites',
    function ($core, $favs) {
        $favs->register('importExport', [
            'title'       => __('Import/Export'),
            'url'         => $core->adminurl->get('admin.plugin.importExport'),
            'small-icon'  => [dcPage::getPF('importExport/icon.svg'), dcPage::getPF('importExport/icon-dark.svg')],
            'large-icon'  => [dcPage::getPF('importExport/icon.svg'), dcPage::getPF('importExport/icon-dark.svg')],
            'permissions' => 'admin',
        ]);
    }
);

$core->addBehavior(
    'dcMaintenanceInit',
    function ($maintenance) {
        $maintenance
            ->addTask('ieMaintenanceExportblog')
            ->addTask('ieMaintenanceExportfull')
        ;
    }
);
