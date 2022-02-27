<?php
/**
 * @class Dotclear\Plugin\Tags\Public\TagsTemplate
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Public;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class TagsTemplate
{
    public static function initTags()
    {
        dotclear()->template()->addBlock('Tags', [__CLASS__, 'Tags']);
        dotclear()->template()->addBlock('TagsHeader', [__CLASS__, 'TagsHeader']);
        dotclear()->template()->addBlock('TagsFooter', [__CLASS__, 'TagsFooter']);
        dotclear()->template()->addBlock('EntryTags', [__CLASS__, 'EntryTags']);
        dotclear()->template()->addBlock('TagIf', [__CLASS__, 'TagIf']);
        dotclear()->template()->addValue('TagID', [__CLASS__, 'TagID']);
        dotclear()->template()->addValue('TagCount', [__CLASS__, 'TagCount']);
        dotclear()->template()->addValue('TagPercent', [__CLASS__, 'TagPercent']);
        dotclear()->template()->addValue('TagRoundPercent', [__CLASS__, 'TagRoundPercent']);
        dotclear()->template()->addValue('TagURL', [__CLASS__, 'TagURL']);
        dotclear()->template()->addValue('TagCloudURL', [__CLASS__, 'TagCloudURL']);
        dotclear()->template()->addValue('TagFeedURL', [__CLASS__, 'TagFeedURL']);

        # Kept for backward compatibility (for now)
        dotclear()->template()->addBlock('MetaData', [__CLASS__, 'Tags']);
        dotclear()->template()->addBlock('MetaDataHeader', [__CLASS__, 'TagsHeader']);
        dotclear()->template()->addBlock('MetaDataFooter', [__CLASS__, 'TagsFooter']);
        dotclear()->template()->addValue('MetaID', [__CLASS__, 'TagID']);
        dotclear()->template()->addValue('MetaPercent', [__CLASS__, 'TagPercent']);
        dotclear()->template()->addValue('MetaRoundPercent', [__CLASS__, 'TagRoundPercent']);
        dotclear()->template()->addValue('MetaURL', [__CLASS__, 'TagURL']);
        dotclear()->template()->addValue('MetaAllURL', [__CLASS__, 'TagCloudURL']);
        dotclear()->template()->addBlock('EntryMetaData', [__CLASS__, 'EntryTags']);
    }

    public static function Tags($attr, $content)
    {
        $type = isset($attr['type']) ? addslashes($attr['type']) : 'tag';

        $limit = isset($attr['limit']) ? (int) $attr['limit'] : 'null';

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sortby = 'meta_id_lower';
        if (isset($attr['sortby']) && in_array($attr['sortby'], $combo)) {
            $sortby = strtolower($attr['sortby']);
        }

        $order = 'asc';
        if (isset($attr['order']) && $attr['order'] == 'desc') {
            $order = 'desc';
        }

        $res = "<?php\n" .
            "dotclear()->context()->meta = dotclear()->meta()->computeMetaStats(dotclear()->meta()->getMetadata(['meta_type'=>'"
            . $type . "','limit'=>" . $limit .
            ($sortby != 'meta_id_lower' ? ",'order'=>'" . $sortby . ' ' . ($order == 'asc' ? 'ASC' : 'DESC') . "'" : '') .
            '])); ' .
            "dotclear()->context()->meta->sort('" . $sortby . "','" . $order . "'); " .
            '?>';

        $res .= '<?php while (dotclear()->context()->meta->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'dotclear()->context()->meta = null; ?>';

        return $res;
    }

    public static function TagsHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->meta->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    public static function TagsFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->meta->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    public static function EntryTags($attr, $content)
    {
        $type = isset($attr['type']) ? addslashes($attr['type']) : 'tag';

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sortby = 'meta_id_lower';
        if (isset($attr['sortby']) && in_array($attr['sortby'], $combo)) {
            $sortby = strtolower($attr['sortby']);
        }

        $order = 'asc';
        if (isset($attr['order']) && $attr['order'] == 'desc') {
            $order = 'desc';
        }

        $res = "<?php\n" .
            "dotclear()->context()->meta = dotclear()->meta()->getMetaRecordset((string) dotclear()->context()->posts->post_meta,'" . $type . "'); " .
            "dotclear()->context()->meta->sort('" . $sortby . "','" . $order . "'); " .
            '?>';

        $res .= '<?php while (dotclear()->context()->meta->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'dotclear()->context()->meta = null; ?>';

        return $res;
    }

    public static function TagIf($attr, $content)
    {
        $if        = [];
        $operateur = isset($attr['operator']) ? dotclear()->template()::getOperator($attr['operator']) : '&&';

        if (isset($attr['has_entries'])) {
            $sign = (bool) $attr['has_entries'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->meta->count';
        }

        if (!empty($if)) {
            return '<?php if(' . implode(' ' . $operateur . ' ', $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    public static function TagID($attr)
    {
        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->meta->meta_id') . '; ?>';
    }

    public static function TagCount($attr)
    {
        return '<?php echo dotclear()->context()->meta->count; ?>';
    }

    public static function TagPercent($attr)
    {
        return '<?php echo dotclear()->context()->meta->percent; ?>';
    }

    public static function TagRoundPercent($attr)
    {
        return '<?php echo dotclear()->context()->meta->roundpercent; ?>';
    }

    public static function TagURL($attr)
    {
        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->url.dotclear()->url()->getURLFor("tag",' .
            'rawurlencode(dotclear()->context()->meta->meta_id))') . '; ?>';
    }

    public static function TagCloudURL($attr)
    {
        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->url.dotclear()->url()->getURLFor("tags")') . '; ?>';
    }

    public static function TagFeedURL($attr)
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'rss2';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'rss2';
        }

        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->url.dotclear()->url()->getURLFor("tag_feed",' .
            'rawurlencode(dotclear()->context()->meta->meta_id)."/' . $type . '")') . '; ?>';
    }
}
