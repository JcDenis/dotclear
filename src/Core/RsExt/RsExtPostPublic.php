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
use Dotclear\Helper\Html\Html;

class RsExtPostPublic extends RsExtPost
{
    public function getContent(bool $absolute_urls = false): string
    {
        # Not very nice hack but it does the job :)
        if (dotclear()->context() && dotclear()->context()->short_feed_items === true) {
            $c    = parent::getContent($absolute_urls);
            $c    = dotclear()->conText::remove_html($c);
            $c    = dotclear()->conText::cut_string($c, 350);

            $c = '<p>' . $c . '... ' .
            '<a href="' . $this->getURL() . '"><em>' . __('Read') . '</em> ' .
            Html::escapeHTML($this->rs->post_title) . '</a></p>';

            return $c;
        }

        if (dotclear()->blog()->settings()->system->use_smilies) {
            return $this->smilies(parent::getContent($absolute_urls));
        }

        return parent::getContent($absolute_urls);
    }

    public function getExcerpt(bool $absolute_urls = false): string
    {
        return dotclear()->blog()->settings()->system->use_smilies ?
            $this->smilies(parent::getExcerpt($absolute_urls)) :
            parent::getExcerpt($absolute_urls);
    }

    protected function smilies(string $c): string
    {
        dotclear()->context()->getSmilies();

        return dotclear()->context()->addSmilies($c);
    }
}
