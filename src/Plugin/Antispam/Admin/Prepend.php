<?php
/**
 * @class Dotclear\Plugin\Antispam\Admin\Prepend
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

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Plugin\Antispam\Lib\Antispam;

use Dotclear\Core\Settings;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        if (!defined('DC_ANTISPAM_CONF_SUPER')) {
            define('DC_ANTISPAM_CONF_SUPER', false);
        }

        # Menu and favs
        static::addStandardMenu('Plugins');
        static::addStandardFavorites('admin');

        # Settings
        dotclear()->blog->settings->addNamespace('antispam');

        # Url
        $class = 'Dotclear\\Plugin\\Antispam\\Lib\\AntispamUrl';
        dotclear()->url()->register('spamfeed', 'spamfeed', '^spamfeed/(.+)$', [$class, 'spamFeed']);
        dotclear()->url()->register('hamfeed', 'hamfeed', '^hamfeed/(.+)$', [$class, 'hamFeed']);

        # Rest service
        $class = 'Dotclear\\Plugin\\Antispam\\Lib\\AntispamRest';
        dotclear()->rest()->addFunction('getSpamsCount', [$class, 'restGetSpamsCount']);

        # Core behaviors
        $class = 'Dotclear\\Plugin\\Antispam\\Lib\\Antispam';
        dotclear()->behavior()->add('coreAfterCommentUpdate', [$class, 'trainFilters']);
        dotclear()->behavior()->add('adminAfterCommentDesc', [$class, 'statusMessage']);
        dotclear()->behavior()->add('adminDashboardHeaders', [$class, 'dashboardHeaders']);
        dotclear()->behavior()->add('adminCommentsActionsPage', [$class, 'commentsActionsPage']);
        dotclear()->behavior()->add('coreBlogGetComments', [$class, 'blogGetComments']);
        dotclear()->behavior()->add('adminCommentListHeader', [$class, 'commentListHeader']);
        dotclear()->behavior()->add('adminCommentListValue', [$class, 'commentListValue']);


        # Admin behaviors
        dotclear()->behavior()->add('adminDashboardFavsIcon', [__CLASS__, 'behaviorAdminDashboardFavsIcon']);

        if (!DC_ANTISPAM_CONF_SUPER || dotclear()->auth()->isSuperAdmin()) {
            dotclear()->behavior()->add('adminBlogPreferencesForm', [__CLASS__, 'behaviorAdminBlogPreferencesForm']);
            dotclear()->behavior()->add('adminBeforeBlogSettingsUpdate', [__CLASS__, 'behaviorAdminBeforeBlogSettingsUpdate']);
            dotclear()->behavior()->add('adminCommentsSpamForm', [__CLASS__, 'behaviorAdminCommentsSpamForm']);
            dotclear()->behavior()->add('adminPageHelpBlock', [__CLASS__, 'behaviorAdminPageHelpBlock']);
        }
    }

    public static function installModule(): ?bool
    {
        return Antispam::installModule();
    }

    public static function behaviorAdminDashboardFavsIcon(string $name, ArrayObject $icon): void
    {
        # Check if it is comments favs
        if ($name == 'comments') {
            # Hack comments title if there is at least one spam
            $str = Antispam::dashboardIconTitle();
            if ($str != '') {
                $icon[0] .= $str;
            }
        }
    }

    public static function behaviorAdminPageHelpBlock(ArrayObject $blocks): void
    {
        $found = false;
        foreach ($blocks as $block) {
            if ($block == 'core_comments') {
                $found = true;

                break;
            }
        }
        if (!$found) {
            return;
        }
        $blocks[] = 'antispam_comments';
    }

    public static function behaviorAdminCommentsSpamForm(): void
    {
        $ttl = dotclear()->blog->settings->antispam->antispam_moderation_ttl;
        if ($ttl != null && $ttl >= 0) {
            echo '<p>' . sprintf(__('All spam comments older than %s day(s) will be automatically deleted.'), $ttl) . ' ' .
            sprintf(__('You can modify this duration in the %s'), '<a href="' . dotclear()->adminurl->get('admin.blog.pref') .
                '#antispam_moderation_ttl"> ' . __('Blog settings') . '</a>') .
                '.</p>';
        }
    }

    public static function behaviorAdminBlogPreferencesForm(Settings $settings)
    {
        $ttl = $settings->antispam->antispam_moderation_ttl;
        echo
        '<div class="fieldset"><h4 id="antispam_params">Antispam</h4>' .
        '<p><label for="antispam_moderation_ttl" class="classic">' . __('Delete junk comments older than') . ' ' .
        Form::number('antispam_moderation_ttl', -1, 999, (string) $ttl) .
        ' ' . __('days') .
        '</label></p>' .
        '<p class="form-note">' . __('Set -1 to disabled this feature ; Leave empty to use default 7 days delay.') . '</p>' .
        '<p><a href="' . dotclear()->adminurl->get('admin.plugin.Antispam') . '">' . __('Set spam filters.') . '</a></p>' .
            '</div>';
    }

    public static function behaviorAdminBeforeBlogSettingsUpdate(Settings $settings)
    {
        $settings->antispam->put('antispam_moderation_ttl', (int) $_POST['antispam_moderation_ttl']);
    }

    /**
     * Gets the spams count.
     *
     * @param      array   $get    The cleaned $_GET
     *
     * @return     xmlTag  The spams count.
     */
    public static function restGetSpamsCount($get)
    {
        $count = Antispam::countSpam();
        if ($count > 0) {
            $str = sprintf(($count > 1) ? __('(including %d spam comments)') : __('(including %d spam comment)'), $count);
        } else {
            $str = '';
        }

        $rsp      = new xmlTag('count');
        $rsp->ret = $str;

        return $rsp;
    }
}
