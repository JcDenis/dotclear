<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

// Dotclear\Core\RsExt\RsExtPostPublic
use Dotclear\App;
use Dotclear\Helper\Html\Html;

/**
 * Posts record public helpers.
 *
 * @ingroup  Core Public Post Record
 */
class RsExtPostPublic extends RsExtPost
{
    public function getContent(bool $absolute_urls = false): string
    {
        // Not very nice hack but it does the job :)
        if (true === App::core()->context()->get('short_feed_items')) {
            $c = parent::getContent($absolute_urls);
            $c = App::core()->context()->remove_html($c);
            $c = App::core()->context()->cut_string($c, 350);

            return '<p>' . $c . '... ' .
            '<a href="' . $this->getURL() . '"><em>' . __('Read') . '</em> ' .
            Html::escapeHTML($this->rs->field('post_title')) . '</a></p>';
        }

        return App::core()->blog()->settings('system')->getSetting('use_smilies') ?
            $this->smilies(parent::getContent($absolute_urls)) :
            parent::getContent($absolute_urls);
    }

    public function getExcerpt(bool $absolute_urls = false): string
    {
        return App::core()->blog()->settings('system')->getSetting('use_smilies') ?
            $this->smilies(parent::getExcerpt($absolute_urls)) :
            parent::getExcerpt($absolute_urls);
    }

    protected function smilies(string $c): string
    {
        App::core()->context()->getSmilies();

        return App::core()->context()->addSmilies($c);
    }
}
