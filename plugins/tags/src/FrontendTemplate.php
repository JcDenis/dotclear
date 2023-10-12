<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\tags;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Frontend\Tpl;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\widgets\WidgetsElement;

/**
 * @brief   The module frontend template.
 * @ingroup tags
 */
class FrontendTemplate
{
    /**
     * tpl:Tags [attributes] : Tags loop (tpl block).
     *
     * attributes:
     *
     *      - type           metadata type                          Type of metadata to list, default to "tag"
     *      - limit          int                                    Max number of metadata in list
     *      - order          (asc|desc)                             Sort asc or desc
     *      - sortby         (meta_id_lower|count|latest|oldest)    Sort on information
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The content
     *
     * @return  string
     */
    public static function Tags(ArrayObject $attr, string $content): string
    {
        $type = isset($attr['type']) ? addslashes($attr['type']) : 'tag';

        $limit = isset($attr['limit']) ? (int) $attr['limit'] : 'null';

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sortby = 'meta_id_lower';
        if (isset($attr['sortby']) && in_array($attr['sortby'], $combo)) {
            $sortby = strtolower($attr['sortby']);
        }

        $order = 'asc';
        if (isset($attr['order']) && $attr['order'] === 'desc') {
            $order = 'desc';
        }

        return "<?php\n" .
        "App::frontend()->context()->meta = App::meta()->computeMetaStats(App::meta()->getMetadata(['meta_type'=>'" . $type . "','limit'=>" . $limit . ($sortby !== 'meta_id_lower' ? ",'order'=>'" . $sortby . ' ' . ($order === 'asc' ? 'ASC' : 'DESC') . "'" : '') . '])); ' . "\n" .
        "App::frontend()->context()->meta->sort('" . $sortby . "','" . $order . "'); " . "\n" .
        'while (App::frontend()->context()->meta->fetch()) : ?>' . "\n" .
        $content .
        '<?php endwhile; ' . "\n" .
        'App::frontend()->context()->meta = null; ?>';
    }

    /**
     * tpl:TagsHeader : Tags header (tpl block).
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The content
     *
     * @return  string
     */
    public static function TagsHeader(ArrayObject $attr, string $content): string
    {
        return
        '<?php if (App::frontend()->context()->meta->isStart()) : ?>' .
        $content .
        '<?php endif; ?>';
    }

    /**
     * tpl:TagsFooter : Tags footer (tpl block).
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The content
     *
     * @return  string
     */
    public static function TagsFooter(ArrayObject $attr, string $content): string
    {
        return
        '<?php if (App::frontend()->context()->meta->isEnd()) : ?>' .
        $content .
        '<?php endif; ?>';
    }

    /**
     * tpl:EntryTags [attributes] : Entry tags loop (tpl block).
     *
     * attributes:
     *
     *      - type           metadata type                          Type of metadata to list, default to "tag"
     *      - order          (asc|desc)                             Sort asc or desc
     *      - sortby         (meta_id_lower|count|latest|oldest)    Sort on information
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The content
     *
     * @return  string
     */
    public static function EntryTags(ArrayObject $attr, string $content): string
    {
        $type = isset($attr['type']) ? addslashes($attr['type']) : 'tag';

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sortby = 'meta_id_lower';
        if (isset($attr['sortby']) && in_array($attr['sortby'], $combo)) {
            $sortby = strtolower($attr['sortby']);
        }

        $order = 'asc';
        if (isset($attr['order']) && $attr['order'] === 'desc') {
            $order = 'desc';
        }

        $res = "<?php\n" .
            "App::frontend()->context()->meta = App::meta()->getMetaRecordset(App::frontend()->context()->posts->post_meta,'" . $type . "'); " .
            "App::frontend()->context()->meta->sort('" . $sortby . "','" . $order . "'); " .
            '?>';

        $res .= '<?php while (App::frontend()->context()->meta->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'App::frontend()->context()->meta = null; ?>';

        return $res;
    }

    /**
     * tpl:TagIf [attributes] : Includes content depending on tag test (tpl block).
     *
     * attributes:
     *
     *      - has_entry       (0|1)                   Categories are set in current context (if 1) or not (if 0)
     *      - operator        (and|or)                Combination of conditions, if more than 1 specifiec (default: and)
     *
     * Notes:
     *
     *  1) Prefix with a ! to reverse test
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The content
     *
     * @return  string
     */
    public static function TagIf(ArrayObject $attr, string $content): string
    {
        $if        = [];
        $operateur = isset($attr['operator']) ? Tpl::getOperator($attr['operator']) : '&&';

        if (isset($attr['has_entries'])) {
            $sign = (bool) $attr['has_entries'] ? '' : '!';
            $if[] = $sign . 'App::frontend()->context()->meta->count';
        }

        if (!empty($if)) {
            return '<?php if(' . implode(' ' . $operateur . ' ', $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    /**
     * tpl:TagID [attributes] : Tag ID (tpl value).
     *
     * attributes:
     *
     *      - any filters                 See self::getFilters()
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     *
     * @return  string
     */
    public static function TagID(ArrayObject $attr): string
    {
        $f = App::frontend()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'App::frontend()->context()->meta->meta_id') . '; ?>';
    }

    /**
     * tpl:TagCount : Tag count (tpl value).
     *
     * @return  string
     */
    public static function TagCount(): string
    {
        return '<?php echo App::frontend()->context()->meta->count; ?>';
    }

    /**
     * tpl:TagPercent : Tag percentage usage (tpl value).
     *
     * @return  string
     */
    public static function TagPercent(): string
    {
        return '<?php echo App::frontend()->context()->meta->percent; ?>';
    }

    /**
     * tpl:TagRoundPercent : Tag rounded percentage usage (tpl value).
     *
     * @return  string
     */
    public static function TagRoundPercent(): string
    {
        return '<?php echo App::frontend()->context()->meta->roundpercent; ?>';
    }

    /**
     * tpl:TagURL [attributes] : Tag URL (tpl value).
     *
     * attributes:
     *
     *      - any filters                 See self::getFilters()
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     *
     * @return  string
     */
    public static function TagURL(ArrayObject $attr): string
    {
        $f = App::frontend()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'App::blog()->url().App::url()->getURLFor("tag",' .
            'rawurlencode(App::frontend()->context()->meta->meta_id))') . '; ?>';
    }

    /**
     * tpl:TagCloudURL [attributes] : All tags URL (tpl value).
     *
     * attributes:
     *
     *      - any filters                 See self::getFilters()
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     *
     * @return  string
     */
    public static function TagCloudURL(ArrayObject $attr): string
    {
        $f = App::frontend()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'App::blog()->url().App::url()->getURLFor("tags")') . '; ?>';
    }

    /**
     * tpl:TagFeedURL [attributes] : Tag feed URL (tpl value).
     *
     * attributes:
     *
     *      - type       (atom|rss2)      Feed type, default to 'rss2'
     *      - any filters                 See self::getFilters()
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     *
     * @return  string
     */
    public static function TagFeedURL(ArrayObject $attr): string
    {
        $type = !empty($attr['type']) ? (string) $attr['type'] : 'rss2';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'rss2';
        }

        $f = App::frontend()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'App::blog()->url().App::url()->getURLFor("tag_feed",' .
            'rawurlencode(App::frontend()->context()->meta->meta_id)."/' . $type . '")') . '; ?>';
    }

    /**
     * Widget public rendering helper.
     *
     * @param   WidgetsElement  $widget     The widget
     *
     * @return  string
     */
    public static function tagsWidget(WidgetsElement $widget): string
    {
        if ($widget->offline) {
            return '';
        }

        if (!$widget->checkHomeOnly(App::url()->type)) {
            return '';
        }

        $combo = ['meta_id_lower', 'count', 'latest', 'oldest'];

        $sort = $widget->sortby;
        if (!in_array($sort, $combo)) {
            $sort = 'meta_id_lower';
        }

        $order = $widget->orderby;
        if ($order != 'asc') {
            $order = 'desc';
        }

        $params = ['meta_type' => 'tag'];

        if ($sort != 'meta_id_lower') {
            // As optional limit may restrict result, we should set order (if not computed after)
            $params['order'] = $sort . ' ' . ($order == 'asc' ? 'ASC' : 'DESC');
        }

        if ($widget->limit !== '') {
            $params['limit'] = abs((int) $widget->limit);
        }

        $rs = App::meta()->computeMetaStats(
            App::meta()->getMetadata($params)
        );

        if ($rs->isEmpty()) {
            return '';
        }

        if ($sort == 'meta_id_lower') {
            // Sort resulting recordset on cleaned id
            $rs->sort($sort, $order);
        }

        $res = ($widget->title ? $widget->renderTitle(Html::escapeHTML($widget->title)) : '') .
            '<ul>';

        if (App::url()->type == 'post' && App::frontend()->context()->posts instanceof MetaRecord) {
            App::frontend()->context()->meta = App::meta()->getMetaRecordset(App::frontend()->context()->posts->post_meta, 'tag');
        }
        while ($rs->fetch()) {
            $class = '';
            if (App::url()->type == 'post' && App::frontend()->context()->posts instanceof MetaRecord) {
                while (App::frontend()->context()->meta->fetch()) {
                    if (App::frontend()->context()->meta->meta_id == $rs->meta_id) {
                        $class = ' class="tag-current"';

                        break;
                    }
                }
            }
            $res .= '<li' . $class . '><a href="' . App::blog()->url() . App::url()->getURLFor('tag', rawurlencode($rs->meta_id)) . '" ' .
            'class="tag' . $rs->roundpercent . '">' .
            $rs->meta_id . '</a> </li>';
        }

        $res .= '</ul>';

        if (App::url()->getURLFor('tags') && !is_null($widget->alltagslinktitle) && $widget->alltagslinktitle !== '') {
            $res .= '<p><strong><a href="' . App::blog()->url() . App::url()->getURLFor('tags') . '">' .
            Html::escapeHTML($widget->alltagslinktitle) . '</a></strong></p>';
        }

        return $widget->renderDiv((bool) $widget->content_only, 'tags ' . $widget->class, '', $res);
    }
}
