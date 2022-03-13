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

use Dotclear\Helper\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class TagsTemplate
{
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

        # Kept for backward compatibility (for now)
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

    public function Tags($attr, $content)
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

    public function TagsHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->meta->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    public function TagsFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->meta->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    public function EntryTags($attr, $content)
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

    public function TagIf($attr, $content)
    {
        $if        = [];
        $operateur = isset($attr['operator']) ? dotclear()->template()->getOperator($attr['operator']) : '&&';

        if (isset($attr['has_entries'])) {
            $sign = (bool) $attr['has_entries'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->meta->count';
        }

        if (!empty($if)) {
            return '<?php if(' . implode(' ' . $operateur . ' ', $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    public function TagID($attr)
    {
        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->meta->meta_id') . '; ?>';
    }

    public function TagCount($attr)
    {
        return '<?php echo dotclear()->context()->meta->count; ?>';
    }

    public function TagPercent($attr)
    {
        return '<?php echo dotclear()->context()->meta->percent; ?>';
    }

    public function TagRoundPercent($attr)
    {
        return '<?php echo dotclear()->context()->meta->roundpercent; ?>';
    }

    public function TagURL($attr)
    {
        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("tag",' .
            'rawurlencode(dotclear()->context()->meta->meta_id))') . '; ?>';
    }

    public function TagCloudURL($attr)
    {
        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("tags")') . '; ?>';
    }

    public function TagFeedURL($attr)
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'rss2';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'rss2';
        }

        $f = dotclear()->template()->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("tag_feed",' .
            'rawurlencode(dotclear()->context()->meta->meta_id)."/' . $type . '")') . '; ?>';
    }
}
