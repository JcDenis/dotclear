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
use Dotclear\App;

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
        App::core()->template()->addBlock('Tags', [$this, 'Tags']);
        App::core()->template()->addBlock('TagsHeader', [$this, 'TagsHeader']);
        App::core()->template()->addBlock('TagsFooter', [$this, 'TagsFooter']);
        App::core()->template()->addBlock('EntryTags', [$this, 'EntryTags']);
        App::core()->template()->addBlock('TagIf', [$this, 'TagIf']);
        App::core()->template()->addValue('TagID', [$this, 'TagID']);
        App::core()->template()->addValue('TagCount', [$this, 'TagCount']);
        App::core()->template()->addValue('TagPercent', [$this, 'TagPercent']);
        App::core()->template()->addValue('TagRoundPercent', [$this, 'TagRoundPercent']);
        App::core()->template()->addValue('TagURL', [$this, 'TagURL']);
        App::core()->template()->addValue('TagCloudURL', [$this, 'TagCloudURL']);
        App::core()->template()->addValue('TagFeedURL', [$this, 'TagFeedURL']);

        // Kept for backward compatibility (for now)
        App::core()->template()->addBlock('MetaData', [$this, 'Tags']);
        App::core()->template()->addBlock('MetaDataHeader', [$this, 'TagsHeader']);
        App::core()->template()->addBlock('MetaDataFooter', [$this, 'TagsFooter']);
        App::core()->template()->addValue('MetaID', [$this, 'TagID']);
        App::core()->template()->addValue('MetaPercent', [$this, 'TagPercent']);
        App::core()->template()->addValue('MetaRoundPercent', [$this, 'TagRoundPercent']);
        App::core()->template()->addValue('MetaURL', [$this, 'TagURL']);
        App::core()->template()->addValue('MetaAllURL', [$this, 'TagCloudURL']);
        App::core()->template()->addBlock('EntryMetaData', [$this, 'EntryTags']);
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
            '$param = new Param();' .
            '$param->set("meta_type", "' . $type . '");' .
            '$param->set("limit", ' . $limit . ');' .
            ('meta_id_lower' != $sortby ? '$param->set("order", "' . $sortby . ' ' . ('asc' == $order ? 'ASC' : 'DESC') . '");' : '') .
            'App::core()->context()->set("meta", App::core()->meta()->computeMetaStats(App::core()->meta()->getMetadata(param: $param))); ' .
            "App::core()->context()->get('meta')->sort('" . $sortby . "','" . $order . "'); " .
            self::$toff;

        $res .= self::$ton . 'while (App::core()->context()->get("meta")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; ' .
            'App::core()->context()->set("meta", null);' . self::$toff;

        return $res;
    }

    public function TagsHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("meta")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    public function TagsFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("meta")->isEnd()) :' . self::$toff .
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
            "App::core()->context()->set('meta', App::core()->meta()->getMetaRecordset((string) App::core()->context()->get('posts')->field('post_meta'),'" . $type . "')); " .
            "App::core()->context()->get('meta')->sort('" . $sortby . "','" . $order . "'); " .
            self::$toff;

        $res .= self::$ton . 'while (App::core()->context()->get("meta")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; ' .
            'App::core()->context()->set("meta", null);' . self::$toff;

        return $res;
    }

    public function TagIf(ArrayObject $attr, string $content): string
    {
        $if        = [];
        $operateur = isset($attr['operator']) ? App::core()->template()->getOperator($attr['operator']) : '&&';

        if (isset($attr['has_entries'])) {
            $sign = (bool) $attr['has_entries'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->get("meta")->integer("count")';
        }

        if (!empty($if)) {
            return self::$ton . 'if(' . implode(' ' . $operateur . ' ', $if) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    public function TagID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->context()->get("meta")->field("meta_id")') . ';' . self::$toff;
    }

    public function TagCount(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("meta")->integer("count");' . self::$toff;
    }

    public function TagPercent(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("meta")->field("percent");' . self::$toff;
    }

    public function TagRoundPercent(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("meta")->field("roundpercent");' . self::$toff;
    }

    public function TagURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->blog()->getURLFor("tag",' .
            'rawurlencode(App::core()->context()->get("meta")->field("meta_id")))') . ';' . self::$toff;
    }

    public function TagCloudURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->blog()->getURLFor("tags")') . ';' . self::$toff;
    }

    public function TagFeedURL(ArrayObject $attr): string
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'rss2';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'rss2';
        }

        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->blog()->getURLFor("tag_feed",' .
            'rawurlencode(App::core()->context()->get("meta")->field("meta_id"))."/' . $type . '")') . ';' . self::$toff;
    }
}
