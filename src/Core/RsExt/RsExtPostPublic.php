<?php
/**
 * @class Dotclear\Core\RsExt\rsExtPostPublic
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

use Dotclear\Public\Context;

use Dotclear\Html\Html;

class RsExtPostPublic extends RsExtPost
{
    public static function getContent($rs, $absolute_urls = false)
    {
        # Not very nice hack but it does the job :)
        if (isset($GLOBALS['_ctx']) && $GLOBALS['_ctx']->short_feed_items === true) {
            $_ctx = &$GLOBALS['_ctx'];
            $c    = parent::getContent($rs, $absolute_urls);
            $c    = context::remove_html($c);
            $c    = context::cut_string($c, 350);

            $c = '<p>' . $c . '... ' .
            '<a href="' . $rs->getURL() . '"><em>' . __('Read') . '</em> ' .
            html::escapeHTML($rs->post_title) . '</a></p>';

            return $c;
        }

        if ($rs->core->blog->settings->system->use_smilies) {
            return self::smilies(parent::getContent($rs, $absolute_urls), $rs->core->blog);
        }

        return parent::getContent($rs, $absolute_urls);
    }

    public static function getExcerpt($rs, $absolute_urls = false)
    {
        if ($rs->core->blog->settings->system->use_smilies) {
            return self::smilies(parent::getExcerpt($rs, $absolute_urls), $rs->core->blog);
        }

        return parent::getExcerpt($rs, $absolute_urls);
    }

    protected static function smilies($c, $blog)
    {
        if (!isset($GLOBALS['__smilies'])) {
            $GLOBALS['__smilies'] = context::getSmilies($blog);
        }

        return context::addSmilies($c);
    }
}
