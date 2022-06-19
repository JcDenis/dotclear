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
use Dotclear\App;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Process\Public\Template\TplAttr;

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

    public function Tags(TplAttr $attr, string $content): string
    {
        $type   = $attr->has('type') ? addslashes($attr->get('type')) : 'tag';
        $limit  = $attr->has('limit') ? (int) $attr->get('limit') : 'null';
        $combo  = ['meta_id_lower', 'count', 'latest', 'oldest'];
        $sortby = ($attr->has('sortby') && in_array($attr->get('sortby'), $combo)) ? strtolower($attr->get('sortby')) : 'meta_id_lower';
        $order  = 'desc' == $attr->get('order') ? 'desc' : 'asc';

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

    public function TagsHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("meta")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    public function TagsFooter(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("meta")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    public function EntryTags(TplAttr $attr, string $content): string
    {
        $type   = $attr->has('type') ? addslashes($attr->get('type')) : 'tag';
        $combo  = ['meta_id_lower', 'count', 'latest', 'oldest'];
        $sortby = ($attr->has('sortby') && in_array($attr->get('sortby'), $combo)) ? strtolower($attr->get('sortby')) : 'meta_id_lower';
        $order  = ('desc' == $attr->get('order')) ? 'desc' : 'asc';

        $res = self::$ton . "\n" .
            "App::core()->context()->set('meta', App::core()->meta()->getMetaRecordset((string) App::core()->context()->get('posts')->field('post_meta'),'" . $type . "')); " .
            "App::core()->context()->get('meta')->sort('" . $sortby . "','" . $order . "'); " .
            self::$toff;

        $res .= self::$ton . 'while (App::core()->context()->get("meta")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; ' .
            'App::core()->context()->set("meta", null);' . self::$toff;

        return $res;
    }

    public function TagIf(TplAttr $attr, string $content): string
    {
        $if = new Strings();

        if ($attr->has('has_entries')) {
            $if->add(((bool) $attr->get('has_entries') ? '' : '!') . 'App::core()->context()->get("meta")->integer("count")');
        }

        if ($if->count()) {
            return self::$ton . 'if(' . implode(' ' . ($attr->has('operator') ? App::core()->template()->getOperator($attr->get('operator')) : '&&') . ' ', $if->dump()) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    public function TagID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->context()->get("meta")->field("meta_id")') . ';' . self::$toff;
    }

    public function TagCount(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("meta")->integer("count");' . self::$toff;
    }

    public function TagPercent(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("meta")->field("percent");' . self::$toff;
    }

    public function TagRoundPercent(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("meta")->field("roundpercent");' . self::$toff;
    }

    public function TagURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->blog()->getURLFor("tag",' .
            'rawurlencode(App::core()->context()->get("meta")->field("meta_id")))') . ';' . self::$toff;
    }

    public function TagCloudURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->blog()->getURLFor("tags")') . ';' . self::$toff;
    }

    public function TagFeedURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->blog()->getURLFor("tag_feed",' .
            'rawurlencode(App::core()->context()->get("meta")->field("meta_id"))."/' . (preg_match('#^(rss2|atom)$#', $attr->get('type')) ? $attr->get('type') : 'rss2') . '")') . ';' . self::$toff;
    }
}
