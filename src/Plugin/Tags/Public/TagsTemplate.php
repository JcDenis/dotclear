<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Public;

// Dotclear\Plugin\Tags\Public\TagsTemplate
use ArrayObject;

/**
 * XML-RPC methods of plugin Tags.
 *
 * @ingroup  Plugin Tags Xmlrpc
 */
class TagsTemplate
{
    // \cond
    // php tags break doxygen parser...
    private static $toff = ' ?>';
    private static $ton  = '<?php ';
    // \endcond

    public function __construct()
    {
        dotclear()->template()->addBlock('Tags', [$this, 'Tags']);
        dotclear()->template()->addBlock('TagsHeader', [$this, 'TagsHeader']);
        dotclear()->template()->addBlock('TagsFooter', [$this, 'TagsFooter']);
        dotclear()->template()->addBlock('EntryTags', [$this, 'EntryTags']);
        dotclear()->template()->addBlock('TagIf', [$this, 'TagIf']);
        dotclear()->template()->addValue('TagID', [$this, 'TagID']);
        dotclear()->template()->addValue('TagCount', [$this, 'TagCount']);
        dotclear()->template()->addValue('TagPercent', [$this, 'TagPercent']);
        dotclear()->template()->addValue('TagRoundPercent', [$this, 'TagRoundPercent']);
        dotclear()->template()->addValue('TagURL', [$this, 'TagURL']);
        dotclear()->template()->addValue('TagCloudURL', [$this, 'TagCloudURL']);
        dotclear()->template()->addValue('TagFeedURL', [$this, 'TagFeedURL']);

        // Kept for backward compatibility (for now)
        dotclear()->template()->addBlock('MetaData', [$this, 'Tags']);
        dotclear()->template()->addBlock('MetaDataHeader', [$this, 'TagsHeader']);
        dotclear()->template()->addBlock('MetaDataFooter', [$this, 'TagsFooter']);
        dotclear()->template()->addValue('MetaID', [$this, 'TagID']);
        dotclear()->template()->addValue('MetaPercent', [$this, 'TagPercent']);
        dotclear()->template()->addValue('MetaRoundPercent', [$this, 'TagRoundPercent']);
        dotclear()->template()->addValue('MetaURL', [$this, 'TagURL']);
        dotclear()->template()->addValue('MetaAllURL', [$this, 'TagCloudURL']);
        dotclear()->template()->addBlock('EntryMetaData', [$this, 'EntryTags']);
    }

    public function Tags(ArrayObject $attr, string $content): string
    {
        $type = isset($attr['type']) ? addslashes($attr['type']) : 'tag';

        $limit = isset($attr['limit']) ? (int) $attr['limit'] : 'null';

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sortby = 'meta_id_lower';
        if (isset($attr['sortby']) && in_array($attr['sortby'], $combo)) {
            $sortby = strtolower($attr['sortby']);
        }

        $order = 'asc';
        if (isset($attr['order']) && 'desc' == $attr['order']) {
            $order = 'desc';
        }

        $res = self::$ton . "\n" .
            "dotclear()->context()->set('meta', dotclear()->meta()->computeMetaStats(dotclear()->meta()->getMetadata(['meta_type'=>'"
            . $type . "','limit'=>" . $limit .
            ('meta_id_lower' != $sortby ? ",'order'=>'" . $sortby . ' ' . ('asc' == $order ? 'ASC' : 'DESC') . "'" : '') .
            ']))); ' .
            "dotclear()->context()->get('meta')->sort('" . $sortby . "','" . $order . "'); " .
            self::$toff;

        $res .= self::$ton . 'while (dotclear()->context()->get("meta")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; ' .
            'dotclear()->context()->set("meta", null);' . self::$toff;

        return $res;
    }

    public function TagsHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (dotclear()->context()->get("meta")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    public function TagsFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (dotclear()->context()->get("meta")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    public function EntryTags(ArrayObject $attr, string $content): string
    {
        $type = isset($attr['type']) ? addslashes($attr['type']) : 'tag';

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sortby = 'meta_id_lower';
        if (isset($attr['sortby']) && in_array($attr['sortby'], $combo)) {
            $sortby = strtolower($attr['sortby']);
        }

        $order = 'asc';
        if (isset($attr['order']) && 'desc' == $attr['order']) {
            $order = 'desc';
        }

        $res = self::$ton . "\n" .
            "dotclear()->context()->set('meta', dotclear()->meta()->getMetaRecordset((string) dotclear()->context()->get('posts')->f('post_meta'),'" . $type . "')); " .
            "dotclear()->context()->get('meta')->sort('" . $sortby . "','" . $order . "'); " .
            self::$toff;

        $res .= self::$ton . 'while (dotclear()->context()->get("meta")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; ' .
            'dotclear()->context()->set("meta", null);' . self::$toff;

        return $res;
    }

    public function TagIf(ArrayObject $attr, string $content): string
    {
        $if        = [];
        $operateur = isset($attr['operator']) ? dotclear()->template()->getOperator($attr['operator']) : '&&';

        if (isset($attr['has_entries'])) {
            $sign = (bool) $attr['has_entries'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->get("meta")->fInt("count")';
        }

        if (!empty($if)) {
            return self::$ton . 'if(' . implode(' ' . $operateur . ' ', $if) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    public function TagID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->context()->get("meta")->f("meta_id")') . ';' . self::$toff;
    }

    public function TagCount(ArrayObject $attr): string
    {
        return self::$ton . 'echo dotclear()->context()->get("meta")->fInt("count");' . self::$toff;
    }

    public function TagPercent(ArrayObject $attr): string
    {
        return self::$ton . 'echo dotclear()->context()->get("meta")->f("percent");' . self::$toff;
    }

    public function TagRoundPercent(ArrayObject $attr): string
    {
        return self::$ton . 'echo dotclear()->context()->get("meta")->f("roundpercent");' . self::$toff;
    }

    public function TagURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->blog()->getURLFor("tag",' .
            'rawurlencode(dotclear()->context()->get("meta")->f("meta_id")))') . ';' . self::$toff;
    }

    public function TagCloudURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->blog()->getURLFor("tags")') . ';' . self::$toff;
    }

    public function TagFeedURL(ArrayObject $attr): string
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'rss2';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'rss2';
        }

        return self::$ton . 'echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->blog()->getURLFor("tag_feed",' .
            'rawurlencode(dotclear()->context()->get("meta")->f("meta_id"))."/' . $type . '")') . ';' . self::$toff;
    }
}
