<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Admin;

// Dotclear\Plugin\Antispam\Admin\AntispamBehavior
use Dotclear\App;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\XmlTag;
use Dotclear\Plugin\Antispam\Common\Antispam;
use Dotclear\Process\Admin\Favorite\DashboardIcon;
use Dotclear\Process\Admin\Help\HelpBlocks;

/**
 * Admin behaviors for plugin Antispam.
 *
 * @ingroup  Plugin Antispam Behavior
 */
class AntispamBehavior
{
    public function __construct()
    {
        // Rest service
        App::core()->rest()->addFunction('getSpamsCount', [$this, 'restGetSpamsCount']);

        // Admin behaviors
        App::core()->behavior('adminBeforeAddDashboardIcon')->add([$this, 'adminBeforeAddDashboardIcon']);

        // @phpstan-ignore-next-line (Failed to judge constant)
        if (false == DC_ANTISPAM_CONF_SUPER || App::core()->user()->isSuperAdmin()) {
            App::core()->behavior('adminBlogPreferencesForm')->add([$this, 'behaviorAdminBlogPreferencesForm']);
            App::core()->behavior('adminBeforeBlogSettingsUpdate')->add([$this, 'adminBeforeBlogSettingsUpdate']);
            App::core()->behavior('adminCommentsSpamForm')->add([$this, 'behaviorAdminCommentsSpamForm']);
            App::core()->behavior('adminBeforeGetPageHelpBlocks')->add([$this, 'behaviorAdminPageHelpBlock']);
        }
    }

    public function adminBeforeAddDashboardIcon(DashboardIcon $icon): void
    {
        $str = '';
        // Check if it is comments favs
        if ('comments' == $icon->id) {
            // Hack comments title if there is at least one spam
            if (0 < ($count = (new Antispam())->countSpam())) {
                $str = '</span></a> <a href="' . App::core()->adminurl()->get('admin.comments', ['status' => '-2']) . '"><span class="db-icon-title-spam">' .
                    sprintf((1 < $count) ? __('(including %d spam comments)') : __('(including %d spam comment)'), $count);
            }

            if ('' != $str) {
                $icon->appendTitle($str);
            }
        }
    }

    public function behaviorAdminPageHelpBlock(HelpBlocks $blocks): void
    {
        if ($blocks->hasResource('core_comments')) {
            $blocks->addResource('attachantispam_commentsments');
        }
    }

    public function behaviorAdminCommentsSpamForm(): void
    {
        $ttl = App::core()->blog()->settings()->getGroup('antispam')->getSetting('antispam_moderation_ttl');
        if (null != $ttl && 0 <= $ttl) {
            echo '<p>' . sprintf(__('All spam comments older than %s day(s) will be automatically deleted.'), $ttl) . ' ' .
            sprintf(__('You can modify this duration in the %s'), '<a href="' . App::core()->adminurl()->get('admin.blog.pref') .
                '#antispam_moderation_ttl"> ' . __('Blog settings') . '</a>') .
                '.</p>';
        }
    }

    public function behaviorAdminBlogPreferencesForm(Settings $settings): void
    {
        echo '<div class="fieldset"><h4 id="antispam_params">Antispam</h4>' .
        '<p><label for="antispam_moderation_ttl" class="classic">' . __('Delete junk comments older than') . ' ' .
        Form::number('antispam_moderation_ttl', -1, 999, (string) $settings->getGroup('antispam')->getSetting('antispam_moderation_ttl')) .
        ' ' . __('days') .
        '</label></p>' .
        '<p class="form-note">' . __('Set -1 to disabled this feature ; Leave empty to use default 7 days delay.') . '</p>' .
        '<p><a href="' . App::core()->adminurl()->get('admin.plugin.Antispam') . '">' . __('Set spam filters.') . '</a></p>' .
            '</div>';
    }

    public function adminBeforeBlogSettingsUpdate(Settings $settings): void
    {
        $settings->getGroup('antispam')->putSetting('antispam_moderation_ttl', GPC::post()->int('antispam_moderation_ttl'));
    }

    /**
     * Gets the spams count.
     *
     * @param array $get The cleaned $_GET
     *
     * @return XmlTag the spams count
     */
    public function restGetSpamsCount($get): XmlTag
    {
        $count = (new Antispam())->countSpam();
        $str   = 0     < $count ?
            sprintf((1 < $count) ? __('(including %d spam comments)') : __('(including %d spam comment)'), $count) :
            '';

        $rsp = new xmlTag('count');
        $rsp->insertAttr('ret', $str);

        return $rsp;
    }
}
