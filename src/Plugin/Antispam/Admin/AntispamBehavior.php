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
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\XmlTag;
use Dotclear\Plugin\Antispam\Common\Antispam;

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
        App::core()->behavior()->add('adminDashboardFavsIcon', [$this, 'behaviorAdminDashboardFavsIcon']);

        if (false == DC_ANTISPAM_CONF_SUPER || App::core()->user()->isSuperAdmin()) {
            App::core()->behavior()->add('adminBlogPreferencesForm', [$this, 'behaviorAdminBlogPreferencesForm']);
            App::core()->behavior()->add('adminBeforeBlogSettingsUpdate', [$this, 'behaviorAdminBeforeBlogSettingsUpdate']);
            App::core()->behavior()->add('adminCommentsSpamForm', [$this, 'behaviorAdminCommentsSpamForm']);
            App::core()->behavior()->add('adminPageHelpBlock', [$this, 'behaviorAdminPageHelpBlock']);
        }
    }

    public function behaviorAdminDashboardFavsIcon(string $name, ArrayObject $icon): void
    {
        $str = '';
        // Check if it is comments favs
        if ('comments' == $name) {
            // Hack comments title if there is at least one spam
            if (0 < ($count = (new Antispam())->countSpam())) {
                $str = '</span></a> <a href="' . App::core()->adminurl()->get('admin.comments', ['status' => '-2']) . '"><span class="db-icon-title-spam">' .
                    sprintf((1 < $count) ? __('(including %d spam comments)') : __('(including %d spam comment)'), $count);
            }

            if ('' != $str) {
                $icon[0] .= $str;
            }
        }
    }

    public function behaviorAdminPageHelpBlock(ArrayObject $blocks): void
    {
        $found = false;
        foreach ($blocks as $block) {
            if ('core_comments' == $block) {
                $found = true;

                break;
            }
        }
        if (!$found) {
            return;
        }
        $blocks[] = 'antispam_comments';
    }

    public function behaviorAdminCommentsSpamForm(): void
    {
        $ttl = App::core()->blog()->settings()->get('antispam')->get('antispam_moderation_ttl');
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
        Form::number('antispam_moderation_ttl', -1, 999, (string) $settings->get('antispam')->get('antispam_moderation_ttl')) .
        ' ' . __('days') .
        '</label></p>' .
        '<p class="form-note">' . __('Set -1 to disabled this feature ; Leave empty to use default 7 days delay.') . '</p>' .
        '<p><a href="' . App::core()->adminurl()->get('admin.plugin.Antispam') . '">' . __('Set spam filters.') . '</a></p>' .
            '</div>';
    }

    public function behaviorAdminBeforeBlogSettingsUpdate(Settings $settings): void
    {
        $settings->get('antispam')->put('antispam_moderation_ttl', (int) $_POST['antispam_moderation_ttl']);
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
