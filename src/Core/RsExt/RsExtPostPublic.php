<?php
/**
 * @class Dotclear\Core\RsExt\RsExtPostPublic
 * @brief Dotclear post record public helpers.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Core\RsExt\RsExtPost;

use Dotclear\Html\Html;

class RsExtPostPublic extends RsExtPost
{
    public static function getContent($rs, $absolute_urls = false)
    {
        # Not very nice hack but it does the job :)
        if (isset(dcCore()->context) && dcCore()->context->short_feed_items === true) {
            $c    = parent::getContent($rs, $absolute_urls);
            $c    = dcCore()->context::remove_html($c);
            $c    = dcCore()->context::cut_string($c, 350);

            $c = '<p>' . $c . '... ' .
            '<a href="' . $rs->getURL() . '"><em>' . __('Read') . '</em> ' .
            html::escapeHTML($rs->post_title) . '</a></p>';

            return $c;
        }

        if (dcCore()->blog->settings->system->use_smilies) {
            return self::smilies($rs, parent::getContent($rs, $absolute_urls));
        }

        return parent::getContent($rs, $absolute_urls);
    }

    public static function getExcerpt($rs, $absolute_urls = false)
    {
        if (dcCore()->blog->settings->system->use_smilies) {
            return self::smilies($rs, parent::getExcerpt($rs, $absolute_urls));
        }

        return parent::getExcerpt($rs, $absolute_urls);
    }

    protected static function smilies($rs, $c)
    {
        if (!isset($GLOBALS['__smilies'])) {
            $GLOBALS['__smilies'] = dcCore()->context::getSmilies(dcCore()->blog);
        }

        return dcCore()->context::addSmilies($c);
    }
}
