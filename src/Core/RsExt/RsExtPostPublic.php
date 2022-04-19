<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Helper\Html\Html;

/**
 * Posts record public helpers.
 *
 * \Dotclear\Core\RsExt\RsExtPostPublic
 *
 * @ingroup  Core Public Post Record
 */
class RsExtPostPublic extends RsExtPost
{
    public function getContent(bool $absolute_urls = false): string
    {
        // Not very nice hack but it does the job :)
        if (true === dotclear()->context()?->get('short_feed_items')) {
            $c = parent::getContent($absolute_urls);
            $c = dotclear()->context()->remove_html($c);
            $c = dotclear()->context()->cut_string($c, 350);

            return '<p>' . $c . '... ' .
            '<a href="' . $this->getURL() . '"><em>' . __('Read') . '</em> ' .
            Html::escapeHTML($this->rs->f('post_title')) . '</a></p>';
        }

        return dotclear()->blog()->settings()->get('system')->get('use_smilies') ?
            $this->smilies(parent::getContent($absolute_urls)) :
            parent::getContent($absolute_urls);
    }

    public function getExcerpt(bool $absolute_urls = false): string
    {
        return dotclear()->blog()->settings()->get('system')->get('use_smilies') ?
            $this->smilies(parent::getExcerpt($absolute_urls)) :
            parent::getExcerpt($absolute_urls);
    }

    protected function smilies(string $c): string
    {
        dotclear()->context()->getSmilies();

        return dotclear()->context()->addSmilies($c);
    }
}
