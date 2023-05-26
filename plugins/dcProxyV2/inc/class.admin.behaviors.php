<?php
/**
 * @brief dcProxyV2, a plugin for Dotclear 2
 *
 * Admin behaviours
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
class dcProxyV2AdminBehaviors
{
    // Count : 55

    public static function adminBlogFilter($filters)
    {
        return dcCore::app()->behavior->call('adminBlogFilter', dcCore::app(), $filters);
    }
    public static function adminBlogListHeader($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminBlogListHeader', dcCore::app(), $rs, $cols);
    }
    public static function adminBlogListValue($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminBlogListValue', dcCore::app(), $rs, $cols);
    }
    public static function adminBlogPreferencesForm($blog_settings)
    {
        return dcCore::app()->behavior->call('adminBlogPreferencesForm', dcCore::app(), $blog_settings);
    }
    public static function adminBlogsActionsPage($that)
    {
        return dcCore::app()->behavior->call('adminBlogsActionsPage', dcCore::app(), $that);
    }
    public static function adminColumnsLists($cols)
    {
        return dcCore::app()->behavior->call('adminColumnsLists', dcCore::app(), $cols);
    }
    public static function adminCommentFilter($filters)
    {
        return dcCore::app()->behavior->call('adminCommentFilter', dcCore::app(), $filters);
    }
    public static function adminCommentListHeader($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminCommentListHeader', dcCore::app(), $rs, $cols);
    }
    public static function adminCommentListValue($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminCommentListValue', dcCore::app(), $rs, $cols);
    }
    public static function adminCommentsActions($getRS, $getAction, $getRedirection)
    {
        return dcCore::app()->behavior->call('adminCommentsActions', dcCore::app(), $getRS, $getAction, $getRedirection);
    }
    public static function adminCommentsActionsPage($that)
    {
        return dcCore::app()->behavior->call('adminCommentsActionsPage', dcCore::app(), $that);
    }
    public static function adminCommentsSpamForm()
    {
        return dcCore::app()->behavior->call('adminCommentsSpamForm', dcCore::app());
    }
    public static function adminCurrentThemeDetails($id, $define)
    {
        return dcCore::app()->behavior->call('adminCurrentThemeDetails', dcCore::app(), $id, $define->dump());
    }
    public static function adminDashboardContents($__dashboard_contents)
    {
        return dcCore::app()->behavior->call('adminDashboardContents', dcCore::app(), $__dashboard_contents);
    }
    public static function adminDashboardFavorites($favorites)
    {
        return dcCore::app()->behavior->call('adminDashboardFavorites', dcCore::app(), $favorites);
    }
    public static function adminDashboardFavs($f)
    {
        return dcCore::app()->behavior->call('adminDashboardFavs', dcCore::app(), $f);
    }
    public static function adminDashboardFavsIcon($k, $icons)
    {
        return dcCore::app()->behavior->call('adminDashboardFavsIcon', dcCore::app(), $k, $icons);
    }
    public static function adminDashboardItems($__dashboard_items)
    {
        return dcCore::app()->behavior->call('adminDashboardItems', dcCore::app(), $__dashboard_items);
    }
    public static function adminDashboardOptionsForm()
    {
        return dcCore::app()->behavior->call('adminDashboardOptionsForm', dcCore::app());
    }
    public static function adminFiltersLists($sorts)
    {
        return dcCore::app()->behavior->call('adminFiltersLists', dcCore::app(), $sorts);
    }
    public static function adminMediaFilter($filters)
    {
        return dcCore::app()->behavior->call('adminMediaFilter', dcCore::app(), $filters);
    }
    public static function adminModulesListGetActions($list, $define)
    {
        return dcCore::app()->behavior->call('adminModulesListGetActions', $list, $define->getId(), $define->dump());
    }
    public static function adminPageFooter($text)
    {
        return dcCore::app()->behavior->call('adminPageFooter', dcCore::app(), $text);
    }
    public static function adminPagesActionsPage($that)
    {
        return dcCore::app()->behavior->call('adminPagesActionsPage', dcCore::app(), $that);
    }
    public static function adminPagesListHeader($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminPagesListHeader', dcCore::app(), $rs, $cols);
    }
    public static function adminPagesListValue($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminPagesListValue', dcCore::app(), $rs, $cols);
    }
    public static function adminPostFilter($filters)
    {
        return dcCore::app()->behavior->call('adminPostFilter', dcCore::app(), $filters);
    }
    public static function adminPostListHeader($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminPostListHeader', dcCore::app(), $rs, $cols);
    }
    public static function adminPostListValue($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminPostListValue', dcCore::app(), $rs, $cols);
    }
    public static function adminPostMiniListHeader($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminPostMiniListHeader', dcCore::app(), $rs, $cols);
    }
    public static function adminPostMiniListValue($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminPostMiniListValue', dcCore::app(), $rs, $cols);
    }
    public static function adminPostsActions($getRS, $getAction, $getRedirection)
    {
        return dcCore::app()->behavior->call('adminPostsActions', dcCore::app(), $getRS, $getAction, $getRedirection);
    }
    public static function adminPostsActionsPage($that)
    {
        return dcCore::app()->behavior->call('adminPostsActionsPage', dcCore::app(), $that);
    }
    public static function adminPreferencesForm()
    {
        return dcCore::app()->behavior->call('adminPreferencesForm', dcCore::app());
    }
    public static function adminRteFlags($rte)
    {
        return dcCore::app()->behavior->call('adminRteFlags', dcCore::app(), $rte);
    }
    public static function adminSearchPageCombo($table)
    {
        return dcCore::app()->behavior->call('adminSearchPageCombo', dcCore::app(), $table);
    }
    public static function adminSearchPageDisplay($args)
    {
        return dcCore::app()->behavior->call('adminSearchPageDisplay', dcCore::app(), $args);
    }
    public static function adminSearchPageHead($args)
    {
        return dcCore::app()->behavior->call('adminSearchPageHead', dcCore::app(), $args);
    }
    public static function adminSearchPageProcess($args)
    {
        return dcCore::app()->behavior->call('adminSearchPageProcess', dcCore::app(), $args);
    }
    public static function adminUsersActions($users, $blogs, $action, $redir)
    {
        return dcCore::app()->behavior->call('adminUsersActions', dcCore::app(), $users, $blogs, $action, $redir);
    }
    public static function adminUsersActionsContent($action, $hidden_fields)
    {
        return dcCore::app()->behavior->call('adminUsersActionsContent', dcCore::app(), $action, $hidden_fields);
    }
    public static function adminUserFilter($filters)
    {
        return dcCore::app()->behavior->call('adminUserFilter', dcCore::app(), $filters);
    }
    public static function adminUserListHeader($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminUserListHeader', dcCore::app(), $rs, $cols);
    }
    public static function adminUserListValue($rs, $cols)
    {
        return dcCore::app()->behavior->call('adminUserListValue', dcCore::app(), $rs, $cols);
    }

    public static function exportFull($exp)
    {
        return dcCore::app()->behavior->call('exportFull', dcCore::app(), $exp);
    }
    public static function exportSingle($exp, $blog_id)
    {
        return dcCore::app()->behavior->call('exportSingle', dcCore::app(), $exp, $blog_id);
    }

    public static function importExportModules($modules)
    {
        return dcCore::app()->behavior->call('importExportModules', $modules, dcCore::app());
    }
    public static function importFull($line, $that)
    {
        return dcCore::app()->behavior->call('importFull', $line, $that, dcCore::app());
    }
    public static function importInit($that)
    {
        return dcCore::app()->behavior->call('importInit', $that, dcCore::app());
    }
    public static function importPrepareDC12($line, $that)
    {
        return dcCore::app()->behavior->call('importPrepareDC12', $line, $that, dcCore::app());
    }
    public static function importSingle($line, $that)
    {
        return dcCore::app()->behavior->call('importSingle', $line, $that, dcCore::app());
    }

    public static function pluginsToolsHeaders($config = false)
    {
        return dcCore::app()->behavior->call('pluginsToolsHeaders', dcCore::app(), $config);
    }
    public static function pluginsToolsTabs()
    {
        return dcCore::app()->behavior->call('pluginsToolsTabs', dcCore::app());
    }
    public static function pluginBeforeDelete($define)
    {
        return dcCore::app()->behavior->call('pluginBeforeDelete', $define->dump());
    }
    public static function pluginAfterDelete($define)
    {
        return dcCore::app()->behavior->call('pluginAfterDelete', $define->dump());
    }
    public static function pluginBeforeAdd($define)
    {
        return dcCore::app()->behavior->call('pluginBeforeAdd', $define->dump());
    }
    public static function pluginAfterAdd($define)
    {
        return dcCore::app()->behavior->call('pluginAfterAdd', $define->dump());
    }
    public static function pluginBeforeDeactivate($define)
    {
        return dcCore::app()->behavior->call('pluginBeforeDeactivate', $define->dump());
    }
    public static function pluginAfterDeactivate($define)
    {
        return dcCore::app()->behavior->call('pluginAfterDeactivate', $define->dump());
    }
    public static function pluginBeforeUpdate($define)
    {
        return dcCore::app()->behavior->call('pluginBeforeUpdate', $define->dump());
    }
    public static function pluginAfterUpdate($define)
    {
        return dcCore::app()->behavior->call('pluginAfterUpdate', $define->dump());
    }

    public static function restCheckStoreUpdate($store, $mod, $url)
    {
        return dcCore::app()->behavior->call('restCheckStoreUpdate', dcCore::app(), $store, $mod, $url);
    }

    public static function themesToolsHeaders($config = false)
    {
        return dcCore::app()->behavior->call('themesToolsHeaders', dcCore::app(), $config);
    }
    public static function themesToolsTabs()
    {
        return dcCore::app()->behavior->call('themesToolsTabs', dcCore::app());
    }
    public static function themeBeforeDeactivate($define)
    {
        return dcCore::app()->behavior->call('themeBeforeDeactivate', $define->dump());
    }
    public static function themeAfterDeactivate($define)
    {
        return dcCore::app()->behavior->call('themeAfterDeactivate', $define->dump());
    }
    public static function themeBeforeDelete($define)
    {
        return dcCore::app()->behavior->call('themeBeforeDelete', $define->dump());
    }
    public static function themeAfterDelete($define)
    {
        return dcCore::app()->behavior->call('themeAfterDelete', $define->dump());
    }
    public static function themeBeforeAdd($define)
    {
        return dcCore::app()->behavior->call('themeBeforeAdd', $define->dump());
    }
    public static function themeAfterAdd($define)
    {
        return dcCore::app()->behavior->call('themeAfterAdd', $define->dump());
    }
    public static function themeBeforeUpdate($define)
    {
        return dcCore::app()->behavior->call('themeBeforeUpdate', $define->dump());
    }
    public static function themeAfterUpdate($define)
    {
        return dcCore::app()->behavior->call('themeAfterUpdate', $define->dump());
    }
}
