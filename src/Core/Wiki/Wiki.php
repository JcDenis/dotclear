<?php
/**
 * @note Dotclear\Core\Wiki\Wiki
 * @brief Dotclear trait error
 *
 * @ingroup  Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Wiki;

use Dotclear\Helper\Html\Html;

class Wiki
{
    /** @var Wiki2xhtml Wiki2xhtml instance */
    private $wiki2xhtml;

    /**
     * Get instance.
     *
     * @return Wiki2xhtml Wiki2xhtml instance
     */
    public function wiki2xhtml(): Wiki2xhtml
    {
        if (!($this->wiki2xhtml instanceof Wiki2xhtml)) {
            $this->wiki2xhtml = new Wiki2xhtml();
        }

        return $this->wiki2xhtml;
    }

    /**
     * Returns a transformed string with wiki2xhtml.
     *
     * @param string $str The string
     */
    public function wikiTransform(string $str): string
    {
        return $this->wiki2xhtml()->transform($str);
    }

    /**
     * Inits <var>wiki2xhtml</var> property for blog post.
     */
    public function initWikiPost(): void
    {
        $this->wiki2xhtml()->setOpts([
            'active_title'        => 1,
            'active_setext_title' => 0,
            'active_hr'           => 1,
            'active_lists'        => 1,
            'active_defl'         => 1,
            'active_quote'        => 1,
            'active_pre'          => 1,
            'active_empty'        => 1,
            'active_auto_urls'    => 0,
            'active_auto_br'      => 0,
            'active_antispam'     => 1,
            'active_urls'         => 1,
            'active_auto_img'     => 0,
            'active_img'          => 1,
            'active_anchor'       => 1,
            'active_em'           => 1,
            'active_strong'       => 1,
            'active_br'           => 1,
            'active_q'            => 1,
            'active_code'         => 1,
            'active_acronym'      => 1,
            'active_ins'          => 1,
            'active_del'          => 1,
            'active_footnotes'    => 1,
            'active_wikiwords'    => 0,
            'active_macros'       => 1,
            'active_mark'         => 1,
            'active_aside'        => 1,
            'active_sup'          => 1,
            'active_sub'          => 1,
            'active_i'            => 1,
            'active_span'         => 1,
            'parse_pre'           => 1,
            'active_fr_syntax'    => 0,
            'first_title_level'   => 3,
            'note_prefix'         => 'wiki-footnote',
            'note_str'            => '<div class="footnotes"><h4>Notes</h4>%s</div>',
            'img_style_center'    => 'display:table; margin:0 auto;',
        ]);

        $this->wiki2xhtml()->registerFunction('url:post', [$this, 'wikiPostLink']);

        // --BEHAVIOR-- coreInitWikiPost, Wiki2xhtml
        dotclear()->behavior()->call('coreInitWikiPost', $this->wiki2xhtml);
    }

    /**
     * Inits <var>wiki2xhtml</var> property for simple blog comment (basic syntax).
     */
    public function initWikiSimpleComment(): void
    {
        $this->wiki2xhtml()->setOpts([
            'active_title'        => 0,
            'active_setext_title' => 0,
            'active_hr'           => 0,
            'active_lists'        => 0,
            'active_defl'         => 0,
            'active_quote'        => 0,
            'active_pre'          => 0,
            'active_empty'        => 0,
            'active_auto_urls'    => 1,
            'active_auto_br'      => 1,
            'active_antispam'     => 1,
            'active_urls'         => 0,
            'active_auto_img'     => 0,
            'active_img'          => 0,
            'active_anchor'       => 0,
            'active_em'           => 0,
            'active_strong'       => 0,
            'active_br'           => 0,
            'active_q'            => 0,
            'active_code'         => 0,
            'active_acronym'      => 0,
            'active_ins'          => 0,
            'active_del'          => 0,
            'active_inline_html'  => 0,
            'active_footnotes'    => 0,
            'active_wikiwords'    => 0,
            'active_macros'       => 0,
            'active_mark'         => 0,
            'active_aside'        => 0,
            'active_sup'          => 0,
            'active_sub'          => 0,
            'active_i'            => 0,
            'active_span'         => 0,
            'parse_pre'           => 0,
            'active_fr_syntax'    => 0,
        ]);

        // --BEHAVIOR-- coreInitWikiSimpleComment, Dotclear\Helper\Html\Wiki2xhtml
        dotclear()->behavior()->call('coreInitWikiSimpleComment', $this->wiki2xhtml);
    }

    /**
     * Inits <var>wiki2xhtml</var> property for blog comment.
     */
    public function initWikiComment(): void
    {
        $this->wiki2xhtml()->setOpts([
            'active_title'        => 0,
            'active_setext_title' => 0,
            'active_hr'           => 0,
            'active_lists'        => 1,
            'active_defl'         => 0,
            'active_quote'        => 1,
            'active_pre'          => 1,
            'active_empty'        => 0,
            'active_auto_br'      => 1,
            'active_auto_urls'    => 1,
            'active_urls'         => 1,
            'active_auto_img'     => 0,
            'active_img'          => 0,
            'active_anchor'       => 0,
            'active_em'           => 1,
            'active_strong'       => 1,
            'active_br'           => 1,
            'active_q'            => 1,
            'active_code'         => 1,
            'active_acronym'      => 1,
            'active_ins'          => 1,
            'active_del'          => 1,
            'active_footnotes'    => 0,
            'active_inline_html'  => 0,
            'active_wikiwords'    => 0,
            'active_macros'       => 0,
            'active_mark'         => 1,
            'active_aside'        => 0,
            'active_sup'          => 1,
            'active_sub'          => 1,
            'active_i'            => 1,
            'active_span'         => 0,
            'parse_pre'           => 0,
            'active_fr_syntax'    => 0,
        ]);

        // --BEHAVIOR-- coreInitWikiComment, Dotclear\Helper\Html\Wiki2xhtml
        dotclear()->behavior()->call('coreInitWikiComment', $this->wiki2xhtml);
    }

    /**
     * Get info about a post:id wiki macro.
     *
     * @param string $url     The post url
     * @param string $content The content
     */
    public function wikiPostLink(string $url, string $content): array
    {
        if (!dotclear()->blog()) {
            return [];
        }

        $post_id = abs((int) substr($url, 5));
        if (!$post_id) {
            return [];
        }

        $rs = dotclear()->blog()->posts()->getPosts(['post_id' => $post_id]);
        if ($rs->isEmpty()) {
            return [];
        }

        $res = ['url' => $rs->getURL()];

        if ($url != $content) {
            $res['title'] = Html::escapeHTML($rs->f('post_title'));
        }

        if ('' == $content || $url == $content) {
            $res['content'] = Html::escapeHTML($rs->f('post_title'));
        }

        if ($rs->f('post_lang')) {
            $res['lang'] = $rs->f('post_lang');
        }

        return $res;
    }

    /** @alias Wiki2xhtml::setOpt */
    public function setOpt(string $option, $value): void
    {
        $this->wiki2xhtml()->setOpt($option, $value);
    }
}
