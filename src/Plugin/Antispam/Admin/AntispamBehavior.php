<?php
/**
 * @class Dotclear\Plugin\Antispam\Admin\AntispamBehavior
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

use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\XmlTag;
use Dotclear\Plugin\Antispam\Common\Antispam;

class AntispamBehavior
{
    public function __construct()
    {
        # Rest service
        dotclear()->rest()->addFunction('getSpamsCount', [$this, 'restGetSpamsCount']);

        # Admin behaviors
        dotclear()->behavior()->add('adminDashboardFavsIcon', [$this, 'behaviorAdminDashboardFavsIcon']);

        if (!DC_ANTISPAM_CONF_SUPER || dotclear()->user()->isSuperAdmin()) {
            dotclear()->behavior()->add('adminBlogPreferencesForm', [$this, 'behaviorAdminBlogPreferencesForm']);
            dotclear()->behavior()->add('adminBeforeBlogSettingsUpdate', [$this, 'behaviorAdminBeforeBlogSettingsUpdate']);
            dotclear()->behavior()->add('adminCommentsSpamForm', [$this, 'behaviorAdminCommentsSpamForm']);
            dotclear()->behavior()->add('adminPageHelpBlock', [$this, 'behaviorAdminPageHelpBlock']);
        }
    }

    public function behaviorAdminDashboardFavsIcon(string $name, ArrayObject $icon): void
    {
        $str = '';
        # Check if it is comments favs
        if ($name == 'comments') {
            # Hack comments title if there is at least one spam
            if (($count = (new Antispam())->countSpam()) > 0) {
                $str = '</span></a> <a href="' . dotclear()->adminurl()->get('admin.comments', ['status' => '-2']) . '"><span class="db-icon-title-spam">' .
                    sprintf(($count > 1) ? __('(including %d spam comments)') : __('(including %d spam comment)'), $count);
            }

            if ($str != '') {
                $icon[0] .= $str;
            }
        }
    }

    public function behaviorAdminPageHelpBlock(ArrayObject $blocks): void
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

    public function behaviorAdminCommentsSpamForm(): void
    {
        $ttl = dotclear()->blog()->settings()->get('antispam')->get('antispam_moderation_ttl');
        if ($ttl != null && $ttl >= 0) {
            echo '<p>' . sprintf(__('All spam comments older than %s day(s) will be automatically deleted.'), $ttl) . ' ' .
            sprintf(__('You can modify this duration in the %s'), '<a href="' . dotclear()->adminurl()->get('admin.blog.pref') .
                '#antispam_moderation_ttl"> ' . __('Blog settings') . '</a>') .
                '.</p>';
        }
    }

    public function behaviorAdminBlogPreferencesForm(Settings $settings)
    {
        echo
        '<div class="fieldset"><h4 id="antispam_params">Antispam</h4>' .
        '<p><label for="antispam_moderation_ttl" class="classic">' . __('Delete junk comments older than') . ' ' .
        Form::number('antispam_moderation_ttl', -1, 999, (string) $settings->get('antispam')->get('antispam_moderation_ttl')) .
        ' ' . __('days') .
        '</label></p>' .
        '<p class="form-note">' . __('Set -1 to disabled this feature ; Leave empty to use default 7 days delay.') . '</p>' .
        '<p><a href="' . dotclear()->adminurl()->get('admin.plugin.Antispam') . '">' . __('Set spam filters.') . '</a></p>' .
            '</div>';
    }

    public function behaviorAdminBeforeBlogSettingsUpdate(Settings $settings)
    {
        $settings->get('antispam')->put('antispam_moderation_ttl', (int) $_POST['antispam_moderation_ttl']);
    }

    /**
     * Gets the spams count.
     *
     * @param      array   $get    The cleaned $_GET
     *
     * @return     XmlTag  The spams count.
     */
    public function restGetSpamsCount($get): XmlTag
    {
        $count = (new Antispam())->countSpam();
        if ($count > 0) {
            $str = sprintf(($count > 1) ? __('(including %d spam comments)') : __('(including %d spam comment)'), $count);
        } else {
            $str = '';
        }

        $rsp = new xmlTag('count');
        $rsp->insertAttr('ret', $str);

        return $rsp;
    }
}
