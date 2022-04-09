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

use ArrayObject;
use Dotclear\Helper\Html\Html;

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
        if (isset($attr['order']) && $attr['order'] == 'desc') {
            $order = 'desc';
        }

        $res = "<?php\n" .
            "dotclear()->context()->set('meta', dotclear()->meta()->computeMetaStats(dotclear()->meta()->getMetadata(['meta_type'=>'"
            . $type . "','limit'=>" . $limit .
            ($sortby != 'meta_id_lower' ? ",'order'=>'" . $sortby . ' ' . ($order == 'asc' ? 'ASC' : 'DESC') . "'" : '') .
            ']))); ' .
            "dotclear()->context()->get('meta')->sort('" . $sortby . "','" . $order . "'); " .
            '?>';

        $res .= '<?php while (dotclear()->context()->get("meta")->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'dotclear()->context()->set("meta", null); ?>';

        return $res;
    }

    public function TagsHeader(ArrayObject $attr, string $content): string
    {
        return
            '<?php if (dotclear()->context()->get("meta")->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    public function TagsFooter(ArrayObject $attr, string $content): string
    {
        return
            '<?php if (dotclear()->context()->get("meta")->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
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
        if (isset($attr['order']) && $attr['order'] == 'desc') {
            $order = 'desc';
        }

        $res = "<?php\n" .
            "dotclear()->context()->set('meta', dotclear()->meta()->getMetaRecordset((string) dotclear()->context()->get('posts')->f('post_meta'),'" . $type . "')); " .
            "dotclear()->context()->get('meta')->sort('" . $sortby . "','" . $order . "'); " .
            '?>';

        $res .= '<?php while (dotclear()->context()->get("meta")->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'dotclear()->context()->set("meta", null); ?>';

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
            return '<?php if(' . implode(' ' . $operateur . ' ', $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    public function TagID(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->context()->get("meta")->f("meta_id")') . '; ?>';
    }

    public function TagCount(ArrayObject $attr): string
    {
        return '<?php echo dotclear()->context()->get("meta")->fInt("count"); ?>';
    }

    public function TagPercent(ArrayObject $attr): string
    {
        return '<?php echo dotclear()->context()->get("meta")->f("percent"); ?>';
    }

    public function TagRoundPercent(ArrayObject $attr): string
    {
        return '<?php echo dotclear()->context()->get("meta")->f("roundpercent"); ?>';
    }

    public function TagURL(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->blog()->getURLFor("tag",' .
            'rawurlencode(dotclear()->context()->get("meta")->f("meta_id")))') . '; ?>';
    }

    public function TagCloudURL(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->blog()->getURLFor("tags")') . '; ?>';
    }

    public function TagFeedURL(ArrayObject $attr): string
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'rss2';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'rss2';
        }

        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->blog()->getURLFor("tag_feed",' .
            'rawurlencode(dotclear()->context()->get("meta")->f("meta_id"))."/' . $type . '")') . '; ?>';
    }
}
