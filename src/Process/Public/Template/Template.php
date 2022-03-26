<?php
/**
 * @class Dotclear\Process\Public\Template\Tempalte
 * @brief Dotclear public core prepend class
 *
 * @package Dotclear
 * @subpackage Public
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

use ArrayObject;

use Dotclear\Process\Public\Template\Engine\Template as BaseTemplate;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Dt;

class Template extends BaseTemplate
{
    private $current_tag;

    protected $unknown_value_handler = null;
    protected $unknown_block_handler = null;

    public function __construct($cache_dir, $self_name)
    {
        parent::__construct($cache_dir, $self_name);

        $this->remove_php = !dotclear()->blog()->settings()->system->tpl_allow_php;
        $this->use_cache  = dotclear()->blog()->settings()->system->tpl_use_cache;

        # Transitional tags
        $this->addValue('EntryTrackbackCount', [$this, 'EntryPingCount']);
        $this->addValue('EntryTrackbackData', [$this, 'EntryPingData']);
        $this->addValue('EntryTrackbackLink', [$this, 'EntryPingLink']);

        # l10n
        $this->addValue('lang', [$this, 'l10n']);

        # Loops test tags
        $this->addBlock('LoopPosition', [$this, 'LoopPosition']);
        $this->addValue('LoopIndex', [$this, 'LoopIndex']);

        # Archives
        $this->addBlock('Archives', [$this, 'Archives']);
        $this->addBlock('ArchivesHeader', [$this, 'ArchivesHeader']);
        $this->addBlock('ArchivesFooter', [$this, 'ArchivesFooter']);
        $this->addBlock('ArchivesYearHeader', [$this, 'ArchivesYearHeader']);
        $this->addBlock('ArchivesYearFooter', [$this, 'ArchivesYearFooter']);
        $this->addValue('ArchiveDate', [$this, 'ArchiveDate']);
        $this->addBlock('ArchiveNext', [$this, 'ArchiveNext']);
        $this->addBlock('ArchivePrevious', [$this, 'ArchivePrevious']);
        $this->addValue('ArchiveEntriesCount', [$this, 'ArchiveEntriesCount']);
        $this->addValue('ArchiveURL', [$this, 'ArchiveURL']);

        # Blog
        $this->addValue('BlogArchiveURL', [$this, 'BlogArchiveURL']);
        $this->addValue('BlogCopyrightNotice', [$this, 'BlogCopyrightNotice']);
        $this->addValue('BlogDescription', [$this, 'BlogDescription']);
        $this->addValue('BlogEditor', [$this, 'BlogEditor']);
        $this->addValue('BlogFeedID', [$this, 'BlogFeedID']);
        $this->addValue('BlogFeedURL', [$this, 'BlogFeedURL']);
        $this->addValue('BlogRSDURL', [$this, 'BlogRSDURL']);
        $this->addValue('BlogName', [$this, 'BlogName']);
        $this->addValue('BlogLanguage', [$this, 'BlogLanguage']);
        $this->addValue('BlogLanguageURL', [$this, 'BlogLanguageURL']);
        $this->addValue('BlogThemeURL', [$this, 'BlogThemeURL']);
        $this->addValue('BlogParentThemeURL', [$this, 'BlogParentThemeURL']);
        $this->addValue('BlogUpdateDate', [$this, 'BlogUpdateDate']);
        $this->addValue('BlogID', [$this, 'BlogID']);
        $this->addValue('BlogURL', [$this, 'BlogURL']);
        $this->addValue('BlogXMLRPCURL', [$this, 'BlogXMLRPCURL']);
        $this->addValue('BlogPublicURL', [$this, 'BlogPublicURL']);
        $this->addValue('BlogQmarkURL', [$this, 'BlogQmarkURL']);
        $this->addValue('BlogMetaRobots', [$this, 'BlogMetaRobots']);
        $this->addValue('BlogJsJQuery', [$this, 'BlogJsJQuery']);
        $this->addValue('BlogPostsURL', [$this, 'BlogPostsURL']);
        $this->addBlock('IfBlogStaticEntryURL', [$this, 'IfBlogStaticEntryURL']);
        $this->addValue('BlogStaticEntryURL', [$this, 'BlogStaticEntryURL']);
        $this->addValue('BlogNbEntriesFirstPage', [$this, 'BlogNbEntriesFirstPage']);
        $this->addValue('BlogNbEntriesPerPage', [$this, 'BlogNbEntriesPerPage']);

        # Categories
        $this->addBlock('Categories', [$this, 'Categories']);
        $this->addBlock('CategoriesHeader', [$this, 'CategoriesHeader']);
        $this->addBlock('CategoriesFooter', [$this, 'CategoriesFooter']);
        $this->addBlock('CategoryIf', [$this, 'CategoryIf']);
        $this->addBlock('CategoryFirstChildren', [$this, 'CategoryFirstChildren']);
        $this->addBlock('CategoryParents', [$this, 'CategoryParents']);
        $this->addValue('CategoryFeedURL', [$this, 'CategoryFeedURL']);
        $this->addValue('CategoryID', [$this, 'CategoryID']);
        $this->addValue('CategoryURL', [$this, 'CategoryURL']);
        $this->addValue('CategoryShortURL', [$this, 'CategoryShortURL']);
        $this->addValue('CategoryDescription', [$this, 'CategoryDescription']);
        $this->addValue('CategoryTitle', [$this, 'CategoryTitle']);
        $this->addValue('CategoryEntriesCount', [$this, 'CategoryEntriesCount']);

        # Comments
        $this->addBlock('Comments', [$this, 'Comments']);
        $this->addValue('CommentAuthor', [$this, 'CommentAuthor']);
        $this->addValue('CommentAuthorDomain', [$this, 'CommentAuthorDomain']);
        $this->addValue('CommentAuthorLink', [$this, 'CommentAuthorLink']);
        $this->addValue('CommentAuthorMailMD5', [$this, 'CommentAuthorMailMD5']);
        $this->addValue('CommentAuthorURL', [$this, 'CommentAuthorURL']);
        $this->addValue('CommentContent', [$this, 'CommentContent']);
        $this->addValue('CommentDate', [$this, 'CommentDate']);
        $this->addValue('CommentTime', [$this, 'CommentTime']);
        $this->addValue('CommentEmail', [$this, 'CommentEmail']);
        $this->addValue('CommentEntryTitle', [$this, 'CommentEntryTitle']);
        $this->addValue('CommentFeedID', [$this, 'CommentFeedID']);
        $this->addValue('CommentID', [$this, 'CommentID']);
        $this->addBlock('CommentIf', [$this, 'CommentIf']);
        $this->addValue('CommentIfFirst', [$this, 'CommentIfFirst']);
        $this->addValue('CommentIfMe', [$this, 'CommentIfMe']);
        $this->addValue('CommentIfOdd', [$this, 'CommentIfOdd']);
        $this->addValue('CommentIP', [$this, 'CommentIP']);
        $this->addValue('CommentOrderNumber', [$this, 'CommentOrderNumber']);
        $this->addBlock('CommentsFooter', [$this, 'CommentsFooter']);
        $this->addBlock('CommentsHeader', [$this, 'CommentsHeader']);
        $this->addValue('CommentPostURL', [$this, 'CommentPostURL']);
        $this->addBlock('IfCommentAuthorEmail', [$this, 'IfCommentAuthorEmail']);
        $this->addValue('CommentHelp', [$this, 'CommentHelp']);

        # Comment preview
        $this->addBlock('IfCommentPreview', [$this, 'IfCommentPreview']);
        $this->addBlock('IfCommentPreviewOptional', [$this, 'IfCommentPreviewOptional']);
        $this->addValue('CommentPreviewName', [$this, 'CommentPreviewName']);
        $this->addValue('CommentPreviewEmail', [$this, 'CommentPreviewEmail']);
        $this->addValue('CommentPreviewSite', [$this, 'CommentPreviewSite']);
        $this->addValue('CommentPreviewContent', [$this, 'CommentPreviewContent']);
        $this->addValue('CommentPreviewCheckRemember', [$this, 'CommentPreviewCheckRemember']);

        # Entries
        $this->addBlock('DateFooter', [$this, 'DateFooter']);
        $this->addBlock('DateHeader', [$this, 'DateHeader']);
        $this->addBlock('Entries', [$this, 'Entries']);
        $this->addBlock('EntriesFooter', [$this, 'EntriesFooter']);
        $this->addBlock('EntriesHeader', [$this, 'EntriesHeader']);
        $this->addValue('EntryAuthorCommonName', [$this, 'EntryAuthorCommonName']);
        $this->addValue('EntryAuthorDisplayName', [$this, 'EntryAuthorDisplayName']);
        $this->addValue('EntryAuthorEmail', [$this, 'EntryAuthorEmail']);
        $this->addValue('EntryAuthorEmailMD5', [$this, 'EntryAuthorEmailMD5']);
        $this->addValue('EntryAuthorID', [$this, 'EntryAuthorID']);
        $this->addValue('EntryAuthorLink', [$this, 'EntryAuthorLink']);
        $this->addValue('EntryAuthorURL', [$this, 'EntryAuthorURL']);
        $this->addValue('EntryBasename', [$this, 'EntryBasename']);
        $this->addValue('EntryCategory', [$this, 'EntryCategory']);
        $this->addValue('EntryCategoryDescription', [$this, 'EntryCategoryDescription']);
        $this->addBlock('EntryCategoriesBreadcrumb', [$this, 'EntryCategoriesBreadcrumb']);
        $this->addValue('EntryCategoryID', [$this, 'EntryCategoryID']);
        $this->addValue('EntryCategoryURL', [$this, 'EntryCategoryURL']);
        $this->addValue('EntryCategoryShortURL', [$this, 'EntryCategoryShortURL']);
        $this->addValue('EntryCommentCount', [$this, 'EntryCommentCount']);
        $this->addValue('EntryContent', [$this, 'EntryContent']);
        $this->addValue('EntryDate', [$this, 'EntryDate']);
        $this->addValue('EntryExcerpt', [$this, 'EntryExcerpt']);
        $this->addValue('EntryFeedID', [$this, 'EntryFeedID']);
        $this->addValue('EntryFirstImage', [$this, 'EntryFirstImage']);
        $this->addValue('EntryID', [$this, 'EntryID']);
        $this->addBlock('EntryIf', [$this, 'EntryIf']);
        $this->addBlock('EntryIfContentCut', [$this, 'EntryIfContentCut']);
        $this->addValue('EntryIfFirst', [$this, 'EntryIfFirst']);
        $this->addValue('EntryIfOdd', [$this, 'EntryIfOdd']);
        $this->addValue('EntryIfSelected', [$this, 'EntryIfSelected']);
        $this->addValue('EntryLang', [$this, 'EntryLang']);
        $this->addBlock('EntryNext', [$this, 'EntryNext']);
        $this->addValue('EntryPingCount', [$this, 'EntryPingCount']);
        $this->addValue('EntryPingData', [$this, 'EntryPingData']);
        $this->addValue('EntryPingLink', [$this, 'EntryPingLink']);
        $this->addBlock('EntryPrevious', [$this, 'EntryPrevious']);
        $this->addValue('EntryTitle', [$this, 'EntryTitle']);
        $this->addValue('EntryTime', [$this, 'EntryTime']);
        $this->addValue('EntryURL', [$this, 'EntryURL']);

        # Languages
        $this->addBlock('Languages', [$this, 'Languages']);
        $this->addBlock('LanguagesHeader', [$this, 'LanguagesHeader']);
        $this->addBlock('LanguagesFooter', [$this, 'LanguagesFooter']);
        $this->addValue('LanguageCode', [$this, 'LanguageCode']);
        $this->addBlock('LanguageIfCurrent', [$this, 'LanguageIfCurrent']);
        $this->addValue('LanguageURL', [$this, 'LanguageURL']);
        $this->addValue('FeedLanguage', [$this, 'FeedLanguage']);

        # Pagination
        $this->addBlock('Pagination', [$this, 'Pagination']);
        $this->addValue('PaginationCounter', [$this, 'PaginationCounter']);
        $this->addValue('PaginationCurrent', [$this, 'PaginationCurrent']);
        $this->addBlock('PaginationIf', [$this, 'PaginationIf']);
        $this->addValue('PaginationURL', [$this, 'PaginationURL']);

        # Trackbacks
        $this->addValue('PingBlogName', [$this, 'PingBlogName']);
        $this->addValue('PingContent', [$this, 'PingContent']);
        $this->addValue('PingDate', [$this, 'PingDate']);
        $this->addValue('PingEntryTitle', [$this, 'PingEntryTitle']);
        $this->addValue('PingFeedID', [$this, 'PingFeedID']);
        $this->addValue('PingID', [$this, 'PingID']);
        $this->addValue('PingIfFirst', [$this, 'PingIfFirst']);
        $this->addValue('PingIfOdd', [$this, 'PingIfOdd']);
        $this->addValue('PingIP', [$this, 'PingIP']);
        $this->addValue('PingNoFollow', [$this, 'PingNoFollow']);
        $this->addValue('PingOrderNumber', [$this, 'PingOrderNumber']);
        $this->addValue('PingPostURL', [$this, 'PingPostURL']);
        $this->addBlock('Pings', [$this, 'Pings']);
        $this->addBlock('PingsFooter', [$this, 'PingsFooter']);
        $this->addBlock('PingsHeader', [$this, 'PingsHeader']);
        $this->addValue('PingTime', [$this, 'PingTime']);
        $this->addValue('PingTitle', [$this, 'PingTitle']);
        $this->addValue('PingAuthorURL', [$this, 'PingAuthorURL']);

        # System
        $this->addValue('SysBehavior', [$this, 'SysBehavior']);
        $this->addBlock('SysIf', [$this, 'SysIf']);
        $this->addBlock('SysIfCommentPublished', [$this, 'SysIfCommentPublished']);
        $this->addBlock('SysIfCommentPending', [$this, 'SysIfCommentPending']);
        $this->addBlock('SysIfFormError', [$this, 'SysIfFormError']);
        $this->addValue('SysFeedSubtitle', [$this, 'SysFeedSubtitle']);
        $this->addValue('SysFormError', [$this, 'SysFormError']);
        $this->addValue('SysPoweredBy', [$this, 'SysPoweredBy']);
        $this->addValue('SysSearchString', [$this, 'SysSearchString']);
        $this->addValue('SysSelfURI', [$this, 'SysSelfURI']);

        # Generic
        $this->addValue('else', [$this, 'GenericElse']);
    }

    public function getData(string $________): string
    {
        # --BEHAVIOR-- tplBeforeData
        if (dotclear()->behavior()->has('tplBeforeData')) {
            self::$_r = dotclear()->behavior()->call('tplBeforeData');
            if (self::$_r) {
                return self::$_r;
            }
        }

        parent::getData($________);

        # --BEHAVIOR-- tplAfterData
        if (dotclear()->behavior()->has('tplAfterData')) {
            dotclear()->behavior()->call('tplAfterData', self::$_r);
        }

        return self::$_r;
    }
/*
    protected function compileFile(string $file)
    {
        $res = parent::compileFile($file);

        return empty($res) ? '' : '<?php use function \Dotclear\core; ?>' . $res;
    }
*/
    public function compileBlockNode($tag, $attr, $content)
    {
        $this->current_tag = $tag;
        $attr              = new ArrayObject($attr);
        # --BEHAVIOR-- templateBeforeBlock
        $res = dotclear()->behavior()->call('templateBeforeBlock', $this->current_tag, $attr);

        # --BEHAVIOR-- templateInsideBlock
        dotclear()->behavior()->call('templateInsideBlock', $this->current_tag, $attr, [& $content]);

        $res .= parent::compileBlockNode($this->current_tag, $attr, $content);

        # --BEHAVIOR-- templateAfterBlock
        $res .= dotclear()->behavior()->call('templateAfterBlock', $this->current_tag, $attr);

        return $res;
    }

    public function compileValueNode($tag, $attr, $str_attr)
    {
        $this->current_tag = $tag;

        $attr = new ArrayObject($attr);
        # --BEHAVIOR-- templateBeforeValue
        $res = dotclear()->behavior()->call('templateBeforeValue', $this->current_tag, $attr);

        $res .= parent::compileValueNode($this->current_tag, $attr, $str_attr);

        # --BEHAVIOR-- templateAfterValue
        $res .= dotclear()->behavior()->call('templateAfterValue', $this->current_tag, $attr);

        return $res;
    }

    public function getFilters($attr, $default = [])
    {
        if (!is_array($attr) && !($attr instanceof arrayObject)) {
            $attr = [];
        }

        $p = array_merge(
            [
                0             => null,
                'encode_xml'  => 0,
                'encode_html' => 0,
                'cut_string'  => 0,
                'lower_case'  => 0,
                'upper_case'  => 0,
                'encode_url'  => 0,
                'remove_html' => 0,
                'capitalize'  => 0,
                'strip_tags'  => 0,
            ],
            $default
        );

        foreach ($attr as $k => $v) {
            // attributes names must follow this rule
            $k = preg_filter('/[a-zA-Z0-9_]/', '$0', $k);
            if ($k) {
                // addslashes protect var_export, str_replace protect sprintf;
                $p[$k] = str_replace('%', '%%', addslashes((string) $v));
            }
        }

        return 'dotclear()->context()->global_filters(%s,' . var_export($p, true) . ",'" . addslashes($this->current_tag) . "')";
    }

    public function getOperator($op)
    {
        retutn match (strtolower($op)) {
            'or', '||' => '||',
            default => '&&',
        };
    }

    public function getSortByStr($attr, $table = null)
    {
        $res = [];

        $default_order = 'desc';

        $default_alias = [
            'post' => [
                'title'     => 'post_title',
                'selected'  => 'post_selected',
                'author'    => 'user_id',
                'date'      => 'post_dt',
                'id'        => 'post_id',
                'comment'   => 'nb_comment',
                'trackback' => 'nb_trackback',
            ],
            'comment' => [
                'author' => 'comment_author',
                'date'   => 'comment_dt',
                'id'     => 'comment_id',
            ],
        ];

        $alias = new ArrayObject();

        # --BEHAVIOR-- templateCustomSortByAlias
        dotclear()->behavior()->call('templateCustomSortByAlias', $alias);

        $alias = $alias->getArrayCopy();

        if (is_array($alias)) {
            foreach ($alias as $k => $v) {
                if (!is_array($v)) {
                    $alias[$k] = [];
                }
                if (!isset($default_alias[$k]) || !is_array($default_alias[$k])) {
                    $default_alias[$k] = [];
                }
                $default_alias[$k] = array_merge($default_alias[$k], $alias[$k]);
            }
        }

        if (!array_key_exists($table, $default_alias)) {
            return implode(', ', $res);
        }

        if (isset($attr['order']) && preg_match('/^(desc|asc)$/i', $attr['order'])) {
            $default_order = $attr['order'];
        }
        if (isset($attr['sortby'])) {
            $sorts = explode(',', $attr['sortby']);
            foreach ($sorts as $k => $sort) {
                $order = $default_order;
                if (preg_match('/([a-z]*)\s*\?(desc|asc)$/i', $sort, $matches)) {
                    $sort  = $matches[1];
                    $order = $matches[2];
                }
                if (array_key_exists($sort, $default_alias[$table])) {
                    array_push($res, $default_alias[$table][$sort] . ' ' . $order);
                }
            }
        }

        if (count($res) === 0) {
            array_push($res, $default_alias[$table]['date'] . ' ' . $default_order);
        }

        return implode(', ', $res);
    }

    public function getAge($attr)
    {
        if (isset($attr['age']) && preg_match('/^(\-[0-9]+|last).*$/i', $attr['age'])) {
            if (($ts = strtotime($attr['age'])) !== false) {
                return dt::str('%Y-%m-%d %H:%m:%S', $ts);
            }
        }

        return '';
    }

    public function displayCounter($variable, $values, $attr, $count_only_by_default = false)
    {
        if (isset($attr['count_only'])) {
            $count_only = ($attr['count_only'] == 1);
        } else {
            $count_only = $count_only_by_default;
        }
        if ($count_only) {
            return '<?php echo ' . $variable . '; ?>';
        }
        $v = $values;
        if (isset($attr['none'])) {
            $v['none'] = addslashes($attr['none']);
        }
        if (isset($attr['one'])) {
            $v['one'] = addslashes($attr['one']);
        }
        if (isset($attr['more'])) {
            $v['more'] = addslashes($attr['more']);
        }

        return
                '<?php if (' . $variable . " == 0) {\n" .
                "  printf(__('" . $v['none'] . "')," . $variable . ");\n" .
                '} elseif (' . $variable . " == 1) {\n" .
                "  printf(__('" . $v['one'] . "')," . $variable . ");\n" .
                "} else {\n" .
                "  printf(__('" . $v['more'] . "')," . $variable . ");\n" .
                '} ?>';
    }
    /* TEMPLATE FUNCTIONS
    ------------------------------------------------------- */

    public function l10n($attr, $str_attr)
    {
        # Normalize content
        $str_attr = preg_replace('/\s+/x', ' ', $str_attr);

        return "<?php echo __('" . str_replace("'", "\\'", $str_attr) . "'); ?>";
    }

    public function LoopPosition($attr, $content)
    {
        $start  = isset($attr['start']) ? (int) $attr['start'] : '0';
        $length = isset($attr['length']) ? (int) $attr['length'] : 'null';
        $even   = isset($attr['even']) ? (int) (bool) $attr['even'] : 'null';
        $modulo = isset($attr['modulo']) ? (int) $attr['modulo'] : 'null';

        if ($start > 0) {
            $start--;
        }

        return
            '<?php if (dotclear()->context()->loopPosition(' . $start . ',' . $length . ',' . $even . ',' . $modulo . ')) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    public function LoopIndex($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, '(!dotclear()->context()->cur_loop ? 0 : dotclear()->context()->cur_loop->index() + 1)') . '; ?>';
    }

    /* Archives ------------------------------------------- */
    /*dtd
    <!ELEMENT tpl:Archives - - -- Archives dates loop -->
    <!ATTLIST tpl:Archives
    type        (day|month|year)    #IMPLIED    -- Get days, months or years, default to month --
    category    CDATA            #IMPLIED  -- Get dates of given category --
    no_context (1|0)            #IMPLIED  -- Override context information
    order    (asc|desc)        #IMPLIED  -- Sort asc or desc --
    post_type    CDATA            #IMPLIED  -- Get dates of given type of entries, default to post --
    post_lang    CDATA        #IMPLIED  -- Filter on the given language
    >
     */
    public function Archives($attr, $content)
    {
        $p = "if (!isset(\$params)) \$params = [];\n";
        $p .= "\$params['type'] = 'month';\n";
        if (isset($attr['type'])) {
            $p .= "\$params['type'] = '" . addslashes($attr['type']) . "';\n";
        }

        if (isset($attr['category'])) {
            $p .= "\$params['cat_url'] = '" . addslashes($attr['category']) . "';\n";
        }

        if (isset($attr['post_type'])) {
            $p .= "\$params['post_type'] = '" . addslashes($attr['post_type']) . "';\n";
        }

        if (isset($attr['post_lang'])) {
            $p .= "\$params['post_lang'] = '" . addslashes($attr['post_lang']) . "';\n";
        }

        if (empty($attr['no_context']) && !isset($attr['category'])) {
            $p .= 'if (dotclear()->context()->exists("categories")) { ' .
                "\$params['cat_id'] = dotclear()->context()->categories->cat_id; " .
                "}\n";
        }

        $order = 'desc';
        if (isset($attr['order']) && preg_match('/^(desc|asc)$/i', $attr['order'])) {
            $p .= "\$params['order'] = '" . $attr['order'] . "';\n ";
        }

        $res = "<?php\n";
        $res .= $p;
        $res .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Archives', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= 'dotclear()->context()->archives = dotclear()->blog()->posts()->getDates($params); unset($params);' . "\n";
        $res .= "?>\n";

        $res .= '<?php while (dotclear()->context()->archives->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->archives = null; ?>';

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesHeader - - -- First archives result container -->
     */
    public function ArchivesHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->archives->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:ArchivesFooter - - -- Last archives result container -->
     */
    public function ArchivesFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->archives->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:ArchivesYearHeader - - -- First result of year in archives container -->
     */
    public function ArchivesYearHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->archives->yearHeader()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:ArchivesYearFooter - - -- Last result of year in archives container -->
     */
    public function ArchivesYearFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->archives->yearFooter()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:ArchiveDate - O -- Archive result date -->
    <!ATTLIST tpl:ArchiveDate
    format    CDATA    #IMPLIED  -- Date format (Default %B %Y) --
    >
     */
    public function ArchiveDate($attr)
    {
        $format = '%B %Y';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, "dotclear()->context()->archives->getDate('" . $format . "')") . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:ArchiveEntriesCount - O -- Current archive result number of entries -->
     */
    public function ArchiveEntriesCount($attr)
    {
        $f = $this->getFilters($attr);

        return $this->displayCounter(
            sprintf($f, 'dotclear()->context()->archives->nb_post'),
            [
                'none' => 'no archive',
                'one'  => 'one archive',
                'more' => '%d archives',
            ],
            $attr,
            true
        );
    }

    /*dtd
    <!ELEMENT tpl:ArchiveNext - - -- Next archive result container -->
    <!ATTLIST tpl:ArchiveNext
    type        (day|month|year)    #IMPLIED    -- Get days, months or years, default to month --
    post_type    CDATA            #IMPLIED  -- Get dates of given type of entries, default to post --
    post_lang    CDATA        #IMPLIED  -- Filter on the given language
    >
     */
    public function ArchiveNext($attr, $content)
    {
        $p = "if (!isset(\$params)) \$params = [];\n";
        $p .= "\$params['type'] = 'month';\n";
        if (isset($attr['type'])) {
            $p .= "\$params['type'] = '" . addslashes($attr['type']) . "';\n";
        }

        if (isset($attr['post_type'])) {
            $p .= "\$params['post_type'] = '" . addslashes($attr['post_type']) . "';\n";
        }

        if (isset($attr['post_lang'])) {
            $p .= "\$params['post_lang'] = '" . addslashes($attr['post_lang']) . "';\n";
        }

        $p .= "\$params['next'] = dotclear()->context()->archives->dt;";

        $res = "<?php\n";
        $res .= $p;
        $res .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'ArchiveNext', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= 'dotclear()->context()->archives = dotclear()->blog()->posts()->getDates($params); unset($params);' . "\n";
        $res .= "?>\n";

        $res .= '<?php while (dotclear()->context()->archives->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->archives = null; ?>';

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:ArchivePrevious - - -- Previous archive result container -->
    <!ATTLIST tpl:ArchivePrevious
    type        (day|month|year)    #IMPLIED    -- Get days, months or years, default to month --
    post_type    CDATA            #IMPLIED  -- Get dates of given type of entries, default to post --
    post_lang    CDATA        #IMPLIED  -- Filter on the given language
    >
     */
    public function ArchivePrevious($attr, $content)
    {
        $p = 'if (!isset($params)) $params = [];';
        $p .= "\$params['type'] = 'month';\n";
        if (isset($attr['type'])) {
            $p .= "\$params['type'] = '" . addslashes($attr['type']) . "';\n";
        }

        if (isset($attr['post_type'])) {
            $p .= "\$params['post_type'] = '" . addslashes($attr['post_type']) . "';\n";
        }

        if (isset($attr['post_lang'])) {
            $p .= "\$params['post_lang'] = '" . addslashes($attr['post_lang']) . "';\n";
        }

        $p .= "\$params['previous'] = dotclear()->context()->archives->dt;";

        $res = "<?php\n";
        $res .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'ArchivePrevious', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= $p;
        $res .= 'dotclear()->context()->archives = dotclear()->blog()->posts()->getDates($params); unset($params);' . "\n";
        $res .= "?>\n";

        $res .= '<?php while (dotclear()->context()->archives->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->archives = null; ?>';

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:ArchiveURL - O -- Current archive result URL -->
     */
    public function ArchiveURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->archives->url()') . '; ?>';
    }

    /* Blog ----------------------------------------------- */
    /*dtd
    <!ELEMENT tpl:BlogArchiveURL - O -- Blog Archives URL -->
     */
    public function BlogArchiveURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("archive")') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogCopyrightNotice - O -- Blog copyrght notices -->
     */
    public function BlogCopyrightNotice($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->settings()->system->copyright_notice') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogDescription - O -- Blog Description -->
     */
    public function BlogDescription($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->desc') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogEditor - O -- Blog Editor -->
     */
    public function BlogEditor($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->settings()->system->editor') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogFeedID - O -- Blog Feed ID -->
     */
    public function BlogFeedID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, '"urn:md5:".dotclear()->blog()->uid') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogFeedURL - O -- Blog Feed URL -->
    <!ATTLIST tpl:BlogFeedURL
    type    (rss2|atom)    #IMPLIED    -- feed type (default : rss2)
    >
     */
    public function BlogFeedURL($attr)
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'atom';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'atom';
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("feed","' . $type . '")') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogName - O -- Blog Name -->
     */
    public function BlogName($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->name') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogLanguage - O -- Blog Language -->
     */
    public function BlogLanguage($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->settings()->system->lang') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogLanguageURL - O -- Blog Localized URL -->
     */
    public function BlogLanguageURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php if (dotclear()->context()->exists("cur_lang")) echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("lang",' .
            'dotclear()->context()->cur_lang)') . ';
            else echo ' . sprintf($f, 'dotclear()->blog()->url') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogThemeURL - O -- Blog's current Theme URL -->
     */
    public function BlogThemeURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("resources") . "/"') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogParentThemeURL - O -- Blog's current Theme's parent URL -->
     */
    public function BlogParentThemeURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("resources") . "/"') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogPublicURL - O -- Blog Public directory URL -->
     */
    public function BlogPublicURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->public_url') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogUpdateDate - O -- Blog last update date -->
    <!ATTLIST tpl:BlogUpdateDate
    format    CDATA    #IMPLIED    -- date format (encoded in dc:str by default if iso8601 or rfc822 not specified)
    iso8601    CDATA    #IMPLIED    -- if set, tells that date format is ISO 8601
    rfc822    CDATA    #IMPLIED    -- if set, tells that date format is RFC 822
    >
     */
    public function BlogUpdateDate($attr)
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        } else {
            $format = '%Y-%m-%d %H:%M:%S';
        }

        $iso8601 = !empty($attr['iso8601']);
        $rfc822  = !empty($attr['rfc822']);

        $f = $this->getFilters($attr);

        if ($rfc822) {
            return '<?php echo ' . sprintf($f, "dotclear()->blog()->getUpdateDate('rfc822')") . '; ?>';
        } elseif ($iso8601) {
            return '<?php echo ' . sprintf($f, "dotclear()->blog()->getUpdateDate('iso8601')") . '; ?>';
        }

        return '<?php echo ' . sprintf($f, "dotclear()->blog()->getUpdateDate('". $format . "')") . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogID - 0 -- Blog ID -->
     */
    public function BlogID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->id') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogRSDURL - O -- Blog RSD URL -->
     */
    public function BlogRSDURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor(\'rsd\')') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogXMLRPCURL - O -- Blog XML-RPC URL -->
     */
    public function BlogXMLRPCURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor(\'xmlrpc\',dotclear()->blog()->id)') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogURL - O -- Blog URL -->
     */
    public function BlogURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->url') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogQmarkURL - O -- Blog URL, ending with a question mark -->
     */
    public function BlogQmarkURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getQmarkURL()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogMetaRobots - O -- Blog meta robots tag definition, overrides robots_policy setting -->
    <!ATTLIST tpl:BlogMetaRobots
    robots    CDATA    #IMPLIED    -- can be INDEX,FOLLOW,NOINDEX,NOFOLLOW,ARCHIVE,NOARCHIVE
    >
     */
    public function BlogMetaRobots($attr)
    {
        $robots = isset($attr['robots']) ? addslashes($attr['robots']) : '';

        return "<?php echo dotclear()->context()->robotsPolicy(dotclear()->blog()->settings()->system->robots_policy,'" . $robots . "'); ?>";
    }

    /*dtd
    <!ELEMENT gpl:BlogJsJQuery - 0 -- Blog Js jQuery version selected -->
     */
    public function BlogJsJQuery($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getJsJQuery()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogPostsURL - O -- Blog Posts URL -->
     */
    public function BlogPostsURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, ('dotclear()->blog()->settings()->system->static_home ? dotclear()->blog()->getURLFor("posts") : dotclear()->blog()->url')) . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:IfBlogStaticEntryURL - O -- Test if Blog has a static home entry URL -->
     */
    public function IfBlogStaticEntryURL($attr, $content)
    {
        return
            "<?php if (dotclear()->blog()->settings()->system->static_home_url != '') : ?>" .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogStaticEntryURL - O -- Set Blog static home entry URL -->
     */
    public function BlogStaticEntryURL($attr)
    {
        $f = $this->getFilters($attr);

        $p = "\$params['post_type'] = array_keys(dotclear()->posttype()->getPostTypes());\n";
        $p .= "\$params['post_url'] = " . sprintf($f, 'urldecode(dotclear()->blog()->settings()->system->static_home_url)') . ";\n";

        return "<?php\n" . $p . ' ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogNbEntriesFirstPage - O -- Number of entries for 1st page -->
     */
    public function BlogNbEntriesFirstPage($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->settings()->system->nb_post_for_home') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:BlogNbEntriesPerPage - O -- Number of entries per page -->
     */
    public function BlogNbEntriesPerPage($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->settings()->system->nb_post_per_page') . '; ?>';
    }

    /* Categories ----------------------------------------- */

    /*dtd
    <!ELEMENT tpl:Categories - - -- Categories loop -->
     */
    public function Categories($attr, $content)
    {
        $p = "if (!isset(\$params)) \$params = [];\n";

        if (isset($attr['url'])) {
            $p .= "\$params['cat_url'] = '" . addslashes($attr['url']) . "';\n";
        }

        if (!empty($attr['post_type'])) {
            $p .= "\$params['post_type'] = '" . addslashes($attr['post_type']) . "';\n";
        }

        if (!empty($attr['level'])) {
            $p .= "\$params['level'] = " . (int) $attr['level'] . ";\n";
        }

        if (isset($attr['with_empty']) && ((bool) $attr['with_empty'] == true)) {
            $p .= '$params[\'without_empty\'] = false;';
        }

        $res = "<?php\n";
        $res .= $p;
        $res .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Categories', 'method' => 'blog()->categories()->getCategories'],
            $attr,
            $content
        );
        $res .= 'dotclear()->context()->categories = dotclear()->blog()->categories()->getCategories($params);' . "\n";
        $res .= "?>\n";
        $res .= '<?php while (dotclear()->context()->categories->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->categories = null; unset($params); ?>';

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:CategoriesHeader - - -- First Categories result container -->
     */
    public function CategoriesHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->categories->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoriesFooter - - -- Last Categories result container -->
     */
    public function CategoriesFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->categories->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryIf - - -- tests on current entry -->
    <!ATTLIST tpl:CategoryIf
    url        CDATA    #IMPLIED    -- category has given url
    urls    CDATA    #IMPLIED    -- category has one of given urls
    has_entries    (0|1)    #IMPLIED    -- post is the first post from list (value : 1) or not (value : 0)
    has_description     (0|1)     #IMPLIED  -- category has description (value : 1) or not (value : 0)
    >
     */
    public function CategoryIf($attr, $content)
    {
        $if       = new ArrayObject();
        $operator = isset($attr['operator']) ? $this->getOperator($attr['operator']) : '&&';

        if (isset($attr['url'])) {
            $url  = addslashes(trim($attr['url']));
            $args = preg_split('/\s*[?]\s*/', $url, -1, PREG_SPLIT_NO_EMPTY);
            $url  = array_shift($args);
            $args = array_flip($args);
            if (substr($url, 0, 1) == '!') {
                $url = substr($url, 1);
                if (isset($args['sub'])) {
                    $if[] = '(!dotclear()->blog()->categories()->IsInCatSubtree(dotclear()->context()->categories->cat_url, "' . $url . '"))';
                } else {
                    $if[] = '(dotclear()->context()->categories->cat_url != "' . $url . '")';
                }
            } else {
                if (isset($args['sub'])) {
                    $if[] = '(dotclear()->blog()->categories()->IsInCatSubtree(dotclear()->context()->categories->cat_url, "' . $url . '"))';
                } else {
                    $if[] = '(dotclear()->context()->categories->cat_url == "' . $url . '")';
                }
            }
        }

        if (isset($attr['urls'])) {
            $urls = explode(',', addslashes(trim($attr['urls'])));
            if (is_array($urls)) {
                foreach ($urls as $url) {
                    $args = preg_split('/\s*[?]\s*/', trim($url), -1, PREG_SPLIT_NO_EMPTY);
                    $url  = array_shift($args);
                    $args = array_flip($args);
                    if (substr($url, 0, 1) == '!') {
                        $url = substr($url, 1);
                        if (isset($args['sub'])) {
                            $if[] = '(!dotclear()->blog()->categories()->IsInCatSubtree(dotclear()->context()->categories->cat_url, "' . $url . '"))';
                        } else {
                            $if[] = '(dotclear()->context()->categories->cat_url != "' . $url . '")';
                        }
                    } else {
                        if (isset($args['sub'])) {
                            $if[] = '(dotclear()->blog()->categories()->IsInCatSubtree(dotclear()->context()->categories->cat_url, "' . $url . '"))';
                        } else {
                            $if[] = '(dotclear()->context()->categories->cat_url == "' . $url . '")';
                        }
                    }
                }
            }
        }

        if (isset($attr['has_entries'])) {
            $sign = (bool) $attr['has_entries'] ? '>' : '==';
            $if[] = 'dotclear()->context()->categories->nb_post ' . $sign . ' 0';
        }

        if (isset($attr['has_description'])) {
            $sign = (bool) $attr['has_description'] ? '!=' : '==';
            $if[] = 'dotclear()->context()->categories->cat_desc ' . $sign . ' ""';
        }

        dotclear()->behavior()->call('tplIfConditions', 'CategoryIf', $attr, $content, $if);

        if (count($if) != 0) {
            return '<?php if(' . implode(' ' . $operator . ' ', (array) $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:CategoryFirstChildren - - -- Current category first children loop -->
     */
    public function CategoryFirstChildren($attr, $content)
    {
        return
            "<?php\n" .
            'dotclear()->context()->categories = dotclear()->blog()->categories()->getCategoryFirstChildren(dotclear()->context()->categories->cat_id);' . "\n" .
            'while (dotclear()->context()->categories->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->categories = null; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryParents - - -- Current category parents loop -->
     */
    public function CategoryParents($attr, $content)
    {
        return
            "<?php\n" .
            'dotclear()->context()->categories = dotclear()->blog()->categories()->getCategoryParents(dotclear()->context()->categories->cat_id);' . "\n" .
            'while (dotclear()->context()->categories->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->categories = null; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryFeedURL - O -- Category feed URL -->
    <!ATTLIST tpl:CategoryFeedURL
    type    (rss2|atom)    #IMPLIED    -- feed type (default : rss2)
    >
     */
    public function CategoryFeedURL($attr)
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'atom';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'atom';
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("feed","category/".' .
            'dotclear()->context()->categories->cat_url."/' . $type . '")') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryID - O -- Category ID -->
     */
    public function CategoryID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->categories->cat_id') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryURL - O -- Category URL (complete iabsolute URL, including blog URL) -->
     */
    public function CategoryURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("category",' .
            'dotclear()->context()->categories->cat_url)') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryShortURL - O -- Category short URL (relative URL, from /category/) -->
     */
    public function CategoryShortURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->categories->cat_url') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryDescription - O -- Category description -->
     */
    public function CategoryDescription($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->categories->cat_desc') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryTitle - O -- Category title -->
     */
    public function CategoryTitle($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->categories->cat_title') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CategoryEntriesCount - O -- Category number of entries -->
     */
    public function CategoryEntriesCount($attr)
    {
        $f = $this->getFilters($attr);

        return $this->displayCounter(
            sprintf($f, 'dotclear()->context()->categories->nb_post'),
            [
                'none' => 'No post',
                'one'  => 'One post',
                'more' => '%d posts',
            ],
            $attr,
            true
        );
    }

    /* Entries -------------------------------------------- */
    /*dtd
    <!ELEMENT tpl:Entries - - -- Blog Entries loop -->
    <!ATTLIST tpl:Entries
    lastn    CDATA    #IMPLIED    -- limit number of results to specified value
    author    CDATA    #IMPLIED    -- get entries for a given user id
    category    CDATA    #IMPLIED    -- get entries for specific categories only (multiple comma-separated categories can be specified. Use "!" as prefix to exclude a category)
    no_category    CDATA    #IMPLIED    -- get entries without category
    with_category    CDATA    #IMPLIED    -- get entries with category
    no_context (1|0)    #IMPLIED  -- Override context information
    sortby    (title|selected|author|date|id)    #IMPLIED    -- specify entries sort criteria (default : date) (multiple comma-separated sortby can be specified. Use "?asc" or "?desc" as suffix to provide an order for each sorby)
    order    (desc|asc)    #IMPLIED    -- specify entries order (default : desc)
    no_content    (0|1)    #IMPLIED    -- do not retrieve entries content
    selected    (0|1)    #IMPLIED    -- retrieve posts marked as selected only (value: 1) or not selected only (value: 0)
    url        CDATA    #IMPLIED    -- retrieve post by its url
    type        CDATA    #IMPLIED    -- retrieve post with given post_type (there can be many ones separated by comma)
    age        CDATA    #IMPLIED    -- retrieve posts by maximum age (ex: -2 days, last month, last week)
    ignore_pagination    (0|1)    #IMPLIED    -- ignore page number provided in URL (useful when using multiple tpl:Entries on the same page)
    >
     */
    public function Entries($attr, $content)
    {
        $lastn = -1;
        if (isset($attr['lastn'])) {
            $lastn = abs((int) $attr['lastn']) + 0;
        }

        $p = '$_page_number = dotclear()->context()->page_number(); if (!$_page_number) { $_page_number = 1; }' . "\n";

        if ($lastn != 0) {
            // Set limit (aka nb of entries needed)
            if ($lastn > 0) {
                // nb of entries per page specified in template -> regular pagination
                $p .= "\$params['limit'] = " . $lastn . ";\n";
                $p .= '$nb_entry_first_page = $nb_entry_per_page = ' . $lastn . ";\n";
            } else {
                // nb of entries per page not specified -> use ctx settings
                $p .= "\$nb_entry_first_page=dotclear()->context()->nb_entry_first_page; \$nb_entry_per_page = dotclear()->context()->nb_entry_per_page;\n";
                $p .= "if ((dotclear()->url()->type == 'default') || (dotclear()->url()->type == 'default-page')) {\n";
                $p .= "    \$params['limit'] = (\$_page_number == 1 ? \$nb_entry_first_page : \$nb_entry_per_page);\n";
                $p .= "} else {\n";
                $p .= "    \$params['limit'] = \$nb_entry_per_page;\n";
                $p .= "}\n";
            }
            // Set offset (aka index of first entry)
            if (!isset($attr['ignore_pagination']) || $attr['ignore_pagination'] == '0') {
                // standard pagination, set offset
                $p .= "if ((dotclear()->url()->type == 'default') || (dotclear()->url()->type == 'default-page')) {\n";
                $p .= "    \$params['limit'] = [(\$_page_number == 1 ? 0 : (\$_page_number - 2) * \$nb_entry_per_page + \$nb_entry_first_page),\$params['limit']];\n";
                $p .= "} else {\n";
                $p .= "    \$params['limit'] = [(\$_page_number - 1) * \$nb_entry_per_page,\$params['limit']];\n";
                $p .= "}\n";
            } else {
                // no pagination, get all posts from 0 to limit
                $p .= "\$params['limit'] = [0, \$params['limit']];\n";
            }
        }

        if (isset($attr['author'])) {
            $p .= "\$params['user_id'] = '" . addslashes($attr['author']) . "';\n";
        }

        if (isset($attr['category'])) {
            $p .= "\$params['cat_url'] = '" . addslashes($attr['category']) . "';\n";
            $p .= "dotclear()->context()->categoryPostParam(\$params);\n";
        }

        if (isset($attr['with_category']) && $attr['with_category']) {
            $p .= "@\$params['sql'] .= ' AND P.cat_id IS NOT NULL ';\n";
        }

        if (isset($attr['no_category']) && $attr['no_category']) {
            $p .= "@\$params['sql'] .= ' AND P.cat_id IS NULL ';\n";
            $p .= "unset(\$params['cat_url']);\n";
        }

        if (!empty($attr['type'])) {
            $p .= "\$params['post_type'] = preg_split('/\s*,\s*/','" . addslashes($attr['type']) . "',-1,PREG_SPLIT_NO_EMPTY);\n";
        }

        if (!empty($attr['url'])) {
            $p .= "\$params['post_url'] = '" . addslashes($attr['url']) . "';\n";
        }

        if (empty($attr['no_context'])) {
            if (!isset($attr['author'])) {
                $p .= 'if (dotclear()->context()->exists("users")) { ' .
                    "\$params['user_id'] = dotclear()->context()->users->user_id; " .
                    "}\n";
            }

            if (!isset($attr['category']) && (!isset($attr['no_category']) || !$attr['no_category'])) {
                $p .= 'if (dotclear()->context()->exists("categories")) { ' .
                    "\$params['cat_id'] = dotclear()->context()->categories->cat_id.(dotclear()->blog()->settings()->system->inc_subcats?' ?sub':'');" .
                    "}\n";
            }

            $p .= 'if (dotclear()->context()->exists("archives")) { ' .
                "\$params['post_year'] = dotclear()->context()->archives->year(); " .
                "\$params['post_month'] = dotclear()->context()->archives->month(); ";
            if (!isset($attr['lastn'])) {
                $p .= "unset(\$params['limit']); ";
            }
            $p .= "}\n";

            $p .= 'if (dotclear()->context()->exists("langs")) { ' .
                "\$params['post_lang'] = dotclear()->context()->langs->post_lang; " .
                "}\n";

            $p .= 'if (isset($_search)) { ' .
                "\$params['search'] = \$_search; " .
                "}\n";
        }

        $p .= "\$params['order'] = '" . $this->getSortByStr($attr, 'post') . "';\n";

        if (isset($attr['no_content']) && $attr['no_content']) {
            $p .= "\$params['no_content'] = true;\n";
        }

        if (isset($attr['selected'])) {
            $p .= "\$params['post_selected'] = " . (int) (bool) $attr['selected'] . ';';
        }

        if (isset($attr['age'])) {
            $age = $this->getAge($attr);
            $p .= !empty($age) ? "@\$params['sql'] .= ' AND P.post_dt > \'" . $age . "\'';\n" : '';
        }

        $res = "<?php\n";
        $res .= $p;
        $res .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Entries', 'method' => 'blog()->posts()->getPosts'],
            $attr,
            $content
        );
        $res .= 'dotclear()->context()->post_params = $params;' . "\n";
        $res .= 'dotclear()->context()->posts = dotclear()->blog()->posts()->getPosts($params); unset($params);' . "\n";
        $res .= "?>\n";
        $res .= '<?php while (dotclear()->context()->posts->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'dotclear()->context()->posts = null; dotclear()->context()->post_params = null; ?>';

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:DateHeader - O -- Displays date, if post is the first post of the given day -->
     */
    public function DateHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->posts->firstPostOfDay()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:DateFooter - O -- Displays date,  if post is the last post of the given day -->
     */
    public function DateFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->posts->lastPostOfDay()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryIf - - -- tests on current entry -->
    <!ATTLIST tpl:EntryIf
    type    CDATA    #IMPLIED    -- post has a given type (default: "post")
    category    CDATA    #IMPLIED    -- post has a given category
    categories    CDATA    #IMPLIED    -- post has a one of given categories
    first    (0|1)    #IMPLIED    -- post is the first post from list (value : 1) or not (value : 0)
    odd    (0|1)    #IMPLIED    -- post is in an odd position (value : 1) or not (value : 0)
    even    (0|1)    #IMPLIED    -- post is in an even position (value : 1) or not (value : 0)
    extended    (0|1)    #IMPLIED    -- post has an excerpt (value : 1) or not (value : 0)
    selected    (0|1)    #IMPLIED    -- post is selected (value : 1) or not (value : 0)
    has_category    (0|1)    #IMPLIED    -- post has a category (value : 1) or not (value : 0)
    has_attachment    (0|1)    #IMPLIED    -- post has attachments (value : 1) or not (value : 0) (see Attachment plugin for code)
    comments_active    (0|1)    #IMPLIED    -- comments are active for this post (value : 1) or not (value : 0)
    pings_active    (0|1)    #IMPLIED    -- trackbacks are active for this post (value : 1) or not (value : 0)
    show_comments    (0|1)    #IMPLIED    -- there are comments for this post (value : 1) or not (value : 0)
    show_pings    (0|1)    #IMPLIED    -- there are trackbacks for this post (value : 1) or not (value : 0)
    republished    (0|1)    #IMPLIED    -- post has been updated since publication (value : 1) or not (value : 0)
    operator    (and|or)    #IMPLIED    -- combination of conditions, if more than 1 specifiec (default: and)
    url        CDATA    #IMPLIED    -- post has given url
    author        CDATA    #IMPLIED    -- post has given user_id
    >
     */
    public function EntryIf($attr, $content)
    {
        $if          = new ArrayObject();
        $extended    = null;
        $hascategory = null;

        $operator = isset($attr['operator']) ? $this->getOperator($attr['operator']) : '&&';

        if (isset($attr['type'])) {
            $type = trim($attr['type']);
            $type = !empty($type) ? $type : 'post';
            $if[] = 'dotclear()->context()->posts->post_type == "' . addslashes($type) . '"';
        }

        if (isset($attr['url'])) {
            $url = trim($attr['url']);
            if (substr($url, 0, 1) == '!') {
                $url  = substr($url, 1);
                $if[] = 'dotclear()->context()->posts->post_url != "' . addslashes($url) . '"';
            } else {
                $if[] = 'dotclear()->context()->posts->post_url == "' . addslashes($url) . '"';
            }
        }

        if (isset($attr['category'])) {
            $category = addslashes(trim($attr['category']));
            $args     = preg_split('/\s*[?]\s*/', $category, -1, PREG_SPLIT_NO_EMPTY);
            $category = array_shift($args);
            $args     = array_flip($args);
            if (substr($category, 0, 1) == '!') {
                $category = substr($category, 1);
                if (isset($args['sub'])) {
                    $if[] = '(!dotclear()->context()->posts->underCat("' . $category . '"))';
                } else {
                    $if[] = '(dotclear()->context()->posts->cat_url != "' . $category . '")';
                }
            } else {
                if (isset($args['sub'])) {
                    $if[] = '(dotclear()->context()->posts->underCat("' . $category . '"))';
                } else {
                    $if[] = '(dotclear()->context()->posts->cat_url == "' . $category . '")';
                }
            }
        }

        if (isset($attr['categories'])) {
            $categories = explode(',', addslashes(trim($attr['categories'])));
            if (is_array($categories)) {
                foreach ($categories as $category) {
                    $args     = preg_split('/\s*[?]\s*/', trim($category), -1, PREG_SPLIT_NO_EMPTY);
                    $category = array_shift($args);
                    $args     = array_flip($args);
                    if (substr($category, 0, 1) == '!') {
                        $category = substr($category, 1);
                        if (isset($args['sub'])) {
                            $if[] = '(!dotclear()->context()->posts->underCat("' . $category . '"))';
                        } else {
                            $if[] = '(dotclear()->context()->posts->cat_url != "' . $category . '")';
                        }
                    } else {
                        if (isset($args['sub'])) {
                            $if[] = '(dotclear()->context()->posts->underCat("' . $category . '"))';
                        } else {
                            $if[] = '(dotclear()->context()->posts->cat_url == "' . $category . '")';
                        }
                    }
                }
            }
        }

        if (isset($attr['first'])) {
            $sign = (bool) $attr['first'] ? '=' : '!';
            $if[] = 'dotclear()->context()->posts->index() ' . $sign . '= 0';
        }

        if (isset($attr['odd'])) {
            $sign = (bool) $attr['odd'] ? '=' : '!';
            $if[] = '(dotclear()->context()->posts->index()+1)%2 ' . $sign . '= 1';
        }

        if (isset($attr['extended'])) {
            $sign = (bool) $attr['extended'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->posts->isExtended()';
        }

        if (isset($attr['selected'])) {
            $sign = (bool) $attr['selected'] ? '' : '!';
            $if[] = $sign . '(boolean)dotclear()->context()->posts->post_selected';
        }

        if (isset($attr['has_category'])) {
            $sign = (bool) $attr['has_category'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->posts->cat_id';
        }

        if (isset($attr['comments_active'])) {
            $sign = (bool) $attr['comments_active'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->posts->commentsActive()';
        }

        if (isset($attr['pings_active'])) {
            $sign = (bool) $attr['pings_active'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->posts->trackbacksActive()';
        }

        if (isset($attr['has_comment'])) {
            $sign = (bool) $attr['has_comment'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->posts->hasComments()';
        }

        if (isset($attr['has_ping'])) {
            $sign = (bool) $attr['has_ping'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->posts->hasTrackbacks()';
        }

        if (isset($attr['show_comments'])) {
            if ((bool) $attr['show_comments']) {
                $if[] = '(dotclear()->context()->posts->hasComments() || dotclear()->context()->posts->commentsActive())';
            } else {
                $if[] = '(!dotclear()->context()->posts->hasComments() && !dotclear()->context()->posts->commentsActive())';
            }
        }

        if (isset($attr['show_pings'])) {
            if ((bool) $attr['show_pings']) {
                $if[] = '(dotclear()->context()->posts->hasTrackbacks() || dotclear()->context()->posts->trackbacksActive())';
            } else {
                $if[] = '(!dotclear()->context()->posts->hasTrackbacks() && !dotclear()->context()->posts->trackbacksActive())';
            }
        }

        if (isset($attr['republished'])) {
            $sign = (bool) $attr['republished'] ? '' : '!';
            $if[] = $sign . '(boolean)dotclear()->context()->posts->isRepublished()';
        }

        if (isset($attr['author'])) {
            $author = trim($attr['author']);
            if (substr($author, 0, 1) == '!') {
                $author = substr($author, 1);
                $if[]   = 'dotclear()->context()->posts->user_id != "' . $author . '"';
            } else {
                $if[] = 'dotclear()->context()->posts->user_id == "' . $author . '"';
            }
        }

        dotclear()->behavior()->call('tplIfConditions', 'EntryIf', $attr, $content, $if);

        if (count($if) != 0) {
            return '<?php if(' . implode(' ' . $operator . ' ', (array) $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:EntryIfFirst - O -- displays value if entry is the first one -->
    <!ATTLIST tpl:EntryIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function EntryIfFirst($attr)
    {
        $ret = $attr['return'] ?? 'first';
        $ret = Html::escapeHTML($ret);

        return
        '<?php if (dotclear()->context()->posts->index() == 0) { ' .
        "echo '" . addslashes($ret) . "'; } ?>";
    }

    /*dtd
    <!ELEMENT tpl:EntryIfOdd - O -- displays value if entry is in an odd position -->
    <!ATTLIST tpl:EntryIfOdd
    return    CDATA    #IMPLIED    -- value to display in case of success (default: odd)
    even      CDATA    #IMPLIED    -- value to display in case of failure (default: <empty>)
    >
     */
    public function EntryIfOdd($attr)
    {
        $odd = $attr['return'] ?? 'odd';
        $odd = Html::escapeHTML($odd);

        $even = $attr['even'] ?? '';
        $even = Html::escapeHTML($even);

        return '<?php echo ((dotclear()->context()->posts->index()+1)%2 ? ' .
        '"' . addslashes($odd) . '" : ' .
        '"' . addslashes($even) . '"); ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryIfSelected - O -- displays value if entry is selected -->
    <!ATTLIST tpl:EntryIfSelected
    return    CDATA    #IMPLIED    -- value to display in case of success (default: selected)
    >
     */
    public function EntryIfSelected($attr)
    {
        $ret = $attr['return'] ?? 'selected';
        $ret = Html::escapeHTML($ret);

        return
        '<?php if (dotclear()->context()->posts->post_selected) { ' .
        "echo '" . addslashes($ret) . "'; } ?>";
    }

    /*dtd
    <!ELEMENT tpl:EntryContent -  -- Entry content -->
    <!ATTLIST tpl:EntryContent
    absolute_urls    CDATA    #IMPLIED -- transforms local URLs to absolute one
    full            (1|0)    #IMPLIED -- returns full content with excerpt
    >
     */
    public function EntryContent($attr)
    {
        $urls = '0';
        if (!empty($attr['absolute_urls'])) {
            $urls = '1';
        }

        $f = $this->getFilters($attr);

        if (!empty($attr['full'])) {
            return '<?php echo ' . sprintf(
                $f,
                'dotclear()->context()->posts->getExcerpt(' . $urls . ').' .
                '(strlen(dotclear()->context()->posts->getExcerpt(' . $urls . ')) ? " " : "").' .
                'dotclear()->context()->posts->getContent(' . $urls . ')'
            ) . '; ?>';
        }

        return '<?php echo ' . sprintf(
            $f,
            'dotclear()->context()->posts->getContent(' . $urls . ')'
        ) . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryIfContentCut - - -- Test if Entry content has been cut -->
    <!ATTLIST tpl:EntryIfContentCut
    absolute_urls    CDATA    #IMPLIED -- transforms local URLs to absolute one
    full            (1|0)    #IMPLIED -- test with full content and excerpt
    >
     */
    public function EntryIfContentCut($attr, $content)
    {
        if (empty($attr['cut_string'])) {
            return '';
        }

        $urls = '0';
        if (!empty($attr['absolute_urls'])) {
            $urls = '1';
        }

        $short              = $this->getFilters($attr);
        $cut                = $attr['cut_string'];
        $attr['cut_string'] = 0;
        $full               = $this->getFilters($attr);
        $attr['cut_string'] = $cut;

        if (!empty($attr['full'])) {
            return '<?php if (strlen(' . sprintf(
                $full,
                'dotclear()->context()->posts->getExcerpt(' . $urls . ').' .
                '(strlen(dotclear()->context()->posts->getExcerpt(' . $urls . ')) ? " " : "").' .
                'dotclear()->context()->posts->getContent(' . $urls . ')'
            ) . ') > ' .
            'strlen(' . sprintf(
                $short,
                'dotclear()->context()->posts->getExcerpt(' . $urls . ').' .
                '(strlen(dotclear()->context()->posts->getExcerpt(' . $urls . ')) ? " " : "").' .
                'dotclear()->context()->posts->getContent(' . $urls . ')'
            ) . ')) : ?>' .
                $content .
                '<?php endif; ?>';
        }

        return '<?php if (strlen(' . sprintf(
            $full,
            'dotclear()->context()->posts->getContent(' . $urls . ')'
        ) . ') > ' .
            'strlen(' . sprintf(
                $short,
                'dotclear()->context()->posts->getContent(' . $urls . ')'
            ) . ')) : ?>' .
                $content .
                '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryExcerpt - O -- Entry excerpt -->
    <!ATTLIST tpl:EntryExcerpt
    absolute_urls    CDATA    #IMPLIED -- transforms local URLs to absolute one
    >
     */
    public function EntryExcerpt($attr)
    {
        $urls = '0';
        if (!empty($attr['absolute_urls'])) {
            $urls = '1';
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->getExcerpt(' . $urls . ')') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorCommonName - O -- Entry author common name -->
     */
    public function EntryAuthorCommonName($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->getAuthorCN()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorDisplayName - O -- Entry author display name -->
     */
    public function EntryAuthorDisplayName($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->user_displayname') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorID - O -- Entry author ID -->
     */
    public function EntryAuthorID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->user_id') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorEmail - O -- Entry author email -->
    <!ATTLIST tpl:EntryAuthorEmail
    spam_protected    (0|1)    #IMPLIED    -- protect email from spam (default: 1)
    >
     */
    public function EntryAuthorEmail($attr)
    {
        $p = 'true';
        if (isset($attr['spam_protected']) && !$attr['spam_protected']) {
            $p = 'false';
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->getAuthorEmail(' . $p . ')') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorEmailMD5 - O -- Entry author email MD5 sum -->
    >
     */
    public function EntryAuthorEmailMD5($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'md5(dotclear()->context()->posts->getAuthorEmail(false))') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorLink - O -- Entry author link -->
     */
    public function EntryAuthorLink($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->getAuthorLink()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorURL - O -- Entry author URL -->
     */
    public function EntryAuthorURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->user_url') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryBasename - O -- Entry short URL (relative to /post) -->
     */
    public function EntryBasename($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->post_url') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryCategory - O -- Entry category (full name) -->
     */
    public function EntryCategory($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->cat_title') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryDescription - O -- Entry category description -->
     */
    public function EntryCategoryDescription($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->cat_desc') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoriesBreadcrumb - - -- Current entry parents loop (without last one) -->
     */
    public function EntryCategoriesBreadcrumb($attr, $content)
    {
        return
            "<?php\n" .
            'dotclear()->context()->categories = dotclear()->blog()->categories()->getCategoryParents(dotclear()->context()->posts->cat_id);' . "\n" .
            'while (dotclear()->context()->categories->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->categories = null; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryID - O -- Entry category ID -->
     */
    public function EntryCategoryID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->cat_id') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryURL - O -- Entry category URL -->
     */
    public function EntryCategoryURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->getCategoryURL()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryShortURL - O -- Entry category short URL (relative URL, from /category/) -->
     */
    public function EntryCategoryShortURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->cat_url') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryFeedID - O -- Entry feed ID -->
     */
    public function EntryFeedID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->getFeedID()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryFirstImage - O -- Extracts entry first image if exists -->
    <!ATTLIST tpl:EntryAuthorEmail
    size            (sq|t|s|m|o)    #IMPLIED    -- Image size to extract
    class        CDATA        #IMPLIED    -- Class to add on image tag
    with_category    (1|0)        #IMPLIED    -- Search in entry category description if present (default 0)
    no_tag    (1|0)    #IMPLIED    -- Return image URL without HTML tag (default 0)
    content_only    (1|0)        #IMPLIED    -- Search in content entry only, not in excerpt (default 0)
    cat_only    (1|0)        #IMPLIED    -- Search in category description only (default 0)
    >
     */
    public function EntryFirstImage($attr)
    {
        $size          = !empty($attr['size']) ? $attr['size'] : '';
        $class         = !empty($attr['class']) ? $attr['class'] : '';
        $with_category = !empty($attr['with_category']) ? 1 : 0;
        $no_tag        = !empty($attr['no_tag']) ? 1 : 0;
        $content_only  = !empty($attr['content_only']) ? 1 : 0;
        $cat_only      = !empty($attr['cat_only']) ? 1 : 0;

        return "<?php echo dotclear()->context()->EntryFirstImageHelper('" . addslashes($size) . "'," . $with_category . ",'" . addslashes($class) . "'," .
            $no_tag . ',' . $content_only . ',' . $cat_only . '); ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryID - O -- Entry ID -->
     */
    public function EntryID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->post_id') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryLang - O --  Entry language or blog lang if not defined -->
     */
    public function EntryLang($attr)
    {
        $f = $this->getFilters($attr);

        return
        '<?php if (dotclear()->context()->posts->post_lang) { ' .
        'echo ' . sprintf($f, 'dotclear()->context()->posts->post_lang') . '; ' .
        '} else {' .
        'echo ' . sprintf($f, 'dotclear()->blog()->settings()->system->lang') . '; ' .
            '} ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryNext - - -- Next entry block -->
    <!ATTLIST tpl:EntryNext
    restrict_to_category    (0|1)    #IMPLIED    -- find next post in the same category (default: 0)
    restrict_to_lang        (0|1)    #IMPLIED    -- find next post in the same language (default: 0)
    >
     */
    public function EntryNext($attr, $content)
    {
        $restrict_to_category = !empty($attr['restrict_to_category']) ? '1' : '0';
        $restrict_to_lang     = !empty($attr['restrict_to_lang']) ? '1' : '0';

        return
            '<?php $next_post = dotclear()->blog()->posts()->getNextPost(dotclear()->context()->posts,1,' . $restrict_to_category . ',' . $restrict_to_lang . '); ?>' . "\n" .
            '<?php if ($next_post !== null) : ?>' .

            '<?php dotclear()->context()->posts = $next_post; unset($next_post);' . "\n" .
            'while (dotclear()->context()->posts->fetch()) : ?>' .
            $content .
            '<?php endwhile; dotclear()->context()->posts = null; ?>' .
            "<?php endif; ?>\n";
    }

    /*dtd
    <!ELEMENT tpl:EntryPrevious - - -- Previous entry block -->
    <!ATTLIST tpl:EntryPrevious
    restrict_to_category    (0|1)    #IMPLIED    -- find previous post in the same category (default: 0)
    restrict_to_lang        (0|1)    #IMPLIED    -- find next post in the same language (default: 0)
    >
     */
    public function EntryPrevious($attr, $content)
    {
        $restrict_to_category = !empty($attr['restrict_to_category']) ? '1' : '0';
        $restrict_to_lang     = !empty($attr['restrict_to_lang']) ? '1' : '0';

        return
            '<?php $prev_post = dotclear()->blog()->posts()->getNextPost(dotclear()->context()->posts,-1,' . $restrict_to_category . ',' . $restrict_to_lang . '); ?>' . "\n" .
            '<?php if ($prev_post !== null) : ?>' .

            '<?php dotclear()->context()->posts = $prev_post; unset($prev_post);' . "\n" .
            'while (dotclear()->context()->posts->fetch()) : ?>' .
            $content .
            '<?php endwhile; dotclear()->context()->posts = null; ?>' .
            "<?php endif; ?>\n";
    }

    /*dtd
    <!ELEMENT tpl:EntryTitle - O -- Entry title -->
     */
    public function EntryTitle($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->post_title') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryURL - O -- Entry URL -->
     */
    public function EntryURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->posts->getURL()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryDate - O -- Entry date -->
    <!ATTLIST tpl:EntryDate
    format    CDATA    #IMPLIED    -- date format (encoded in dc:str by default if iso8601 or rfc822 not specified)
    iso8601    CDATA    #IMPLIED    -- if set, tells that date format is ISO 8601
    rfc822    CDATA    #IMPLIED    -- if set, tells that date format is RFC 822
    upddt    CDATA    #IMPLIED    -- if set, uses the post update time
    creadt    CDATA    #IMPLIED    -- if set, uses the post creation time
    >
     */
    public function EntryDate($attr)
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }

        $iso8601 = !empty($attr['iso8601']);
        $rfc822  = !empty($attr['rfc822']);
        $type    = (!empty($attr['creadt']) ? 'creadt' : '');
        $type    = (!empty($attr['upddt']) ? 'upddt' : $type);

        $f = $this->getFilters($attr);

        if ($rfc822) {
            return '<?php echo ' . sprintf($f, "dotclear()->context()->posts->getRFC822Date('" . $type . "')") . '; ?>';
        } elseif ($iso8601) {
            return '<?php echo ' . sprintf($f, "dotclear()->context()->posts->getISO8601Date('" . $type . "')") . '; ?>';
        }

        return '<?php echo ' . sprintf($f, "dotclear()->context()->posts->getDate('" . $format . "','" . $type . "')") . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryTime - O -- Entry date -->
    <!ATTLIST tpl:EntryTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the post update time
    creadt    CDATA    #IMPLIED    -- if set, uses the post creation time
    >
     */
    public function EntryTime($attr)
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }

        $type = (!empty($attr['creadt']) ? 'creadt' : '');
        $type = (!empty($attr['upddt']) ? 'upddt' : $type);

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, "dotclear()->context()->posts->getTime('" . $format . "','" . $type . "')") . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntriesHeader - - -- First entries result container -->
     */
    public function EntriesHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->posts->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntriesFooter - - -- Last entries result container -->
     */
    public function EntriesFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->posts->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryCommentCount - O -- Number of comments for entry -->
    <!ATTLIST tpl:EntryCommentCount
    none        CDATA    #IMPLIED    -- text to display for "no comments" (default: no comments)
    one        CDATA    #IMPLIED    -- text to display for "one comment" (default: one comment)
    more        CDATA    #IMPLIED    -- text to display for "more comments" (default: %s comments, %s is replaced by the number of comment)
    count_all    CDATA    #IMPLIED    -- count comments and trackbacks
    >
     */
    public function EntryCommentCount($attr)
    {
        if (empty($attr['count_all'])) {
            $operation = 'dotclear()->context()->posts->nb_comment';
        } else {
            $operation = '(dotclear()->context()->posts->nb_comment + dotclear()->context()->posts->nb_trackback)';
        }

        return $this->displayCounter(
            $operation,
            [
                'none' => 'no comments',
                'one'  => 'one comment',
                'more' => '%d comments',
            ],
            $attr,
            false
        );
    }

    /*dtd
    <!ELEMENT tpl:EntryPingCount - O -- Number of trackbacks for entry -->
    <!ATTLIST tpl:EntryPingCount
    none    CDATA    #IMPLIED    -- text to display for "no pings" (default: no pings)
    one    CDATA    #IMPLIED    -- text to display for "one ping" (default: one ping)
    more    CDATA    #IMPLIED    -- text to display for "more pings" (default: %s trackbacks, %s is replaced by the number of pings)
    >
     */
    public function EntryPingCount($attr)
    {
        return $this->displayCounter(
            'dotclear()->context()->posts->nb_trackback',
            [
                'none' => 'no trackbacks',
                'one'  => 'one trackback',
                'more' => '%d trackbacks',
            ],
            $attr,
            false
        );
    }

    /*dtd
    <!ELEMENT tpl:EntryPingData - O -- Display trackback RDF information -->
     */
    public function EntryPingData($attr)
    {
        $format = !empty($attr['format']) && $attr['format'] == 'xml' ? 'xml' : 'html';

        return "<?php if (dotclear()->context()->posts->trackbacksActive()) { echo dotclear()->context()->posts->getTrackbackData('" . $format . "'); } ?>\n";
    }

    /*dtd
    <!ELEMENT tpl:EntryPingLink - O -- Entry trackback link -->
     */
    public function EntryPingLink($attr)
    {
        return "<?php if (dotclear()->context()->posts->trackbacksActive()) { echo dotclear()->context()->posts->getTrackbackLink(); } ?>\n";
    }

    /* Languages -------------------------------------- */
    /*dtd
    <!ELEMENT tpl:Languages - - -- Languages loop -->
    <!ATTLIST tpl:Languages
    lang    CDATA    #IMPLIED    -- restrict loop on given lang
    order    (desc|asc)    #IMPLIED    -- languages ordering (default: desc)
    >
     */
    public function Languages($attr, $content)
    {
        $p = "if (!isset(\$params)) \$params = [];\n";

        if (isset($attr['lang'])) {
            $p = "\$params['lang'] = '" . addslashes($attr['lang']) . "';\n";
        }

        $order = 'desc';
        if (isset($attr['order']) && preg_match('/^(desc|asc)$/i', $attr['order'])) {
            $p .= "\$params['order'] = '" . $attr['order'] . "';\n ";
        }

        $res = "<?php\n";
        $res .= $p;
        $res .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Languages', 'method' => 'blog()->posts()->getLangs'],
            $attr,
            $content
        );
        $res .= 'dotclear()->context()->langs = dotclear()->blog()->posts()->getLangs($params); unset($params);' . "\n";
        $res .= "?>\n";

        $res .= '<?php if (dotclear()->context()->langs->count() > 1) : ' .
            'while (dotclear()->context()->langs->fetch()) : ?>' . $content .
            '<?php endwhile; dotclear()->context()->langs = null; endif; ?>';

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:LanguagesHeader - - -- First languages result container -->
     */
    public function LanguagesHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->langs->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:LanguagesFooter - - -- Last languages result container -->
     */
    public function LanguagesFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->langs->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:LanguageCode - O -- Language code -->
     */
    public function LanguageCode($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->langs->post_lang') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:LanguageIfCurrent - - -- tests if post language is current language -->
     */
    public function LanguageIfCurrent($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->cur_lang == dotclear()->context()->langs->post_lang) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:LanguageURL - O -- Language URL -->
     */
    public function LanguageURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->blog()->getURLFor("lang",' .
            'dotclear()->context()->langs->post_lang)') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:FeedLanguage - O -- Feed Language -->
     */
    public function FeedLanguage($attr)
    {
        $f = $this->getFilters($attr);

        return
        '<?php if (dotclear()->context()->exists("cur_lang")) ' . "\n" .
        '   { echo ' . sprintf($f, 'dotclear()->context()->cur_lang') . '; }' . "\n" .
        'elseif (dotclear()->context()->exists("posts") && dotclear()->context()->posts->exists("post_lang")) ' . "\n" .
        '   { echo ' . sprintf($f, 'dotclear()->context()->posts->post_lang') . '; }' . "\n" .
        'else ' . "\n" .
        '   { echo ' . sprintf($f, 'dotclear()->blog()->settings()->system->lang') . '; } ?>';
    }

    /* Pagination ------------------------------------- */
    /*dtd
    <!ELEMENT tpl:Pagination - - -- Pagination container -->
    <!ATTLIST tpl:Pagination
    no_context    (0|1)    #IMPLIED    -- override test on posts count vs number of posts per page
    >
     */
    public function Pagination($attr, $content)
    {
        $p = "<?php\n";
        $p .= '$params = dotclear()->context()->post_params;' . "\n";
        $p .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Pagination', 'method' => 'blog()->posts()->getPosts'],
            $attr,
            $content
        );
        $p .= 'dotclear()->context()->pagination = dotclear()->blog()->posts()->getPosts($params,true); unset($params);' . "\n";
        $p .= "?>\n";

        if (isset($attr['no_context']) && $attr['no_context']) {
            return $p . $content;
        }

        return
            $p .
            '<?php if (dotclear()->context()->pagination->f(0) > dotclear()->context()->posts->count()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PaginationCounter - O -- Number of pages -->
     */
    public function PaginationCounter($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->PaginationNbPages()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PaginationCurrent - O -- current page -->
     */
    public function PaginationCurrent($attr)
    {
        $offset = 0;
        if (isset($attr['offset'])) {
            $offset = (int) $attr['offset'];
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->PaginationPosition(' . $offset . ')') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PaginationIf - - -- pages tests -->
    <!ATTLIST tpl:PaginationIf
    start    (0|1)    #IMPLIED    -- test if we are at first page (value : 1) or not (value : 0)
    end    (0|1)    #IMPLIED    -- test if we are at last page (value : 1) or not (value : 0)
    >
     */
    public function PaginationIf($attr, $content)
    {
        $if = [];

        if (isset($attr['start'])) {
            $sign = (bool) $attr['start'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->PaginationStart()';
        }

        if (isset($attr['end'])) {
            $sign = (bool) $attr['end'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->PaginationEnd()';
        }

        dotclear()->behavior()->call('tplIfConditions', 'PaginationIf', $attr, $content, $if);

        if (count($if) != 0) {
            return '<?php if(' . implode(' && ', (array) $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:PaginationURL - O -- link to previoux/next page -->
    <!ATTLIST tpl:PaginationURL
    offset    CDATA    #IMPLIED    -- page offset (negative for previous pages), default: 0
    >
     */
    public function PaginationURL($attr)
    {
        $offset = 0;
        if (isset($attr['offset'])) {
            $offset = (int) $attr['offset'];
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->PaginationURL(' . $offset . ')') . '; ?>';
    }

    /* Comments --------------------------------------- */
    /*dtd
    <!ELEMENT tpl:Comments - - -- Comments container -->
    <!ATTLIST tpl:Comments
    with_pings    (0|1)    #IMPLIED    -- include trackbacks in request
    lastn    CDATA    #IMPLIED    -- restrict the number of entries
    no_context (1|0)        #IMPLIED  -- Override context information
    sortby    (title|selected|author|date|id)    #IMPLIED    -- specify comments sort criteria (default : date) (multiple comma-separated sortby can be specified. Use "?asc" or "?desc" as suffix to provide an order for each sorby)
    order    (desc|asc)    #IMPLIED    -- result ordering (default: asc)
    age        CDATA    #IMPLIED    -- retrieve comments by maximum age (ex: -2 days, last month, last week)
    >
     */
    public function Comments($attr, $content)
    {
        $p = '';
        if (empty($attr['with_pings'])) {
            $p .= "\$params['comment_trackback'] = false;\n";
        }

        $lastn = 0;
        if (isset($attr['lastn'])) {
            $lastn = abs((int) $attr['lastn']) + 0;
        }

        if ($lastn > 0) {
            $p .= "\$params['limit'] = " . $lastn . ";\n";
        } else {
            $p .= "if (dotclear()->context()->nb_comment_per_page !== null) { \$params['limit'] = dotclear()->context()->nb_comment_per_page; }\n";
        }

        if (empty($attr['no_context'])) {
            $p .= 'if (dotclear()->context()->posts !== null) { ' .
                "\$params['post_id'] = dotclear()->context()->posts->post_id; " .
                "dotclear()->blog()->withoutPassword(false);\n" .
                "}\n";
            $p .= 'if (dotclear()->context()->exists("categories")) { ' .
                "\$params['cat_id'] = dotclear()->context()->categories->cat_id; " .
                "}\n";

            $p .= 'if (dotclear()->context()->exists("langs")) { ' .
                "\$params['sql'] = \"AND P.post_lang = '\".dotclear()->con()->escape(dotclear()->context()->langs->post_lang).\"' \"; " .
                "}\n";
        }

        if (!isset($attr['order'])) {
            $attr['order'] = 'asc';
        }

        $p .= "\$params['order'] = '" . $this->getSortByStr($attr, 'comment') . "';\n";

        if (isset($attr['no_content']) && $attr['no_content']) {
            $p .= "\$params['no_content'] = true;\n";
        }

        if (isset($attr['age'])) {
            $age = $this->getAge($attr);
            $p .= !empty($age) ? "@\$params['sql'] .= ' AND P.post_dt > \'" . $age . "\'';\n" : '';
        }

        $res = "<?php\n";
        $res .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Comments', 'method' => 'blog()->comments()->getComments'],
            $attr,
            $content
        );
        $res .= $p;
        $res .= 'dotclear()->context()->comments = dotclear()->blog()->comments()->getComments($params); unset($params);' . "\n";
        $res .= "if (dotclear()->context()->posts !== null) { dotclear()->blog()->withoutPassword(true);}\n";

        if (!empty($attr['with_pings'])) {
            $res .= 'dotclear()->context()->pings = dotclear()->context()->comments;' . "\n";
        }

        $res .= "?>\n";

        $res .= '<?php while (dotclear()->context()->comments->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->comments = null; ?>';

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthor - O -- Comment author -->
     */
    public function CommentAuthor($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comments->comment_author') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorDomain - O -- Comment author website domain -->
     */
    public function CommentAuthorDomain($attr)
    {
        return '<?php echo preg_replace("#^http(?:s?)://(.+?)/.*$#msu",\'$1\',dotclear()->context()->comments->comment_site); ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorLink - O -- Comment author link -->
     */
    public function CommentAuthorLink($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comments->getAuthorLink()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorMailMD5 - O -- Comment author email MD5 sum -->
     */
    public function CommentAuthorMailMD5($attr)
    {
        return '<?php echo md5(dotclear()->context()->comments->comment_email) ; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorURL - O -- Comment author URL -->
     */
    public function CommentAuthorURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comments->getAuthorURL()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentContent - O --  Comment content -->
    <!ATTLIST tpl:CommentContent
    absolute_urls    (0|1)    #IMPLIED    -- convert URLS to absolute urls
    >
     */
    public function CommentContent($attr)
    {
        $urls = '0';
        if (!empty($attr['absolute_urls'])) {
            $urls = '1';
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comments->getContent(' . $urls . ')') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentDate - O -- Comment date -->
    <!ATTLIST tpl:CommentDate
    format    CDATA    #IMPLIED    -- date format (encoded in dc:str by default if iso8601 or rfc822 not specified)
    iso8601    CDATA    #IMPLIED    -- if set, tells that date format is ISO 8601
    rfc822    CDATA    #IMPLIED    -- if set, tells that date format is RFC 822
    upddt    CDATA    #IMPLIED    -- if set, uses the comment update time
    >
     */
    public function CommentDate($attr)
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }

        $iso8601 = !empty($attr['iso8601']);
        $rfc822  = !empty($attr['rfc822']);
        $type    = (!empty($attr['upddt']) ? 'upddt' : '');

        $f = $this->getFilters($attr);

        if ($rfc822) {
            return '<?php echo ' . sprintf($f, "dotclear()->context()->comments->getRFC822Date('" . $type . "')") . '; ?>';
        } elseif ($iso8601) {
            return '<?php echo ' . sprintf($f, "dotclear()->context()->comments->getISO8601Date('" . $type . "')") . '; ?>';
        }

        return '<?php echo ' . sprintf($f, "dotclear()->context()->comments->getDate('" . $format . "','" . $type . "')") . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentTime - O -- Comment date -->
    <!ATTLIST tpl:CommentTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the comment update time
    >
     */
    public function CommentTime($attr)
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }
        $type = (!empty($attr['upddt']) ? 'upddt' : '');

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, "dotclear()->context()->comments->getTime('" . $format . "','" . $type . "')") . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentEmail - O -- Comment author email -->
    <!ATTLIST tpl:CommentEmail
    spam_protected    (0|1)    #IMPLIED    -- protect email from spam (default: 1)
    >
     */
    public function CommentEmail($attr)
    {
        $p = 'true';
        if (isset($attr['spam_protected']) && !$attr['spam_protected']) {
            $p = 'false';
        }

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comments->getEmail(' . $p . ')') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentEntryTitle - O -- Title of the comment entry -->
     */
    public function CommentEntryTitle($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comments->post_title') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentFeedID - O -- Comment feed ID -->
     */
    public function CommentFeedID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comments->getFeedID()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentID - O -- Comment ID -->
     */
    public function CommentID($attr)
    {
        return '<?php echo dotclear()->context()->comments->comment_id; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentIf - - -- test container for comments -->
    <!ATTLIST tpl:CommentIf
    is_ping    (0|1)    #IMPLIED    -- test if comment is a trackback (value : 1) or not (value : 0)
    >
     */
    public function CommentIf($attr, $content)
    {
        $if      = [];
        $is_ping = null;

        if (isset($attr['is_ping'])) {
            $sign = (bool) $attr['is_ping'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->comments->comment_trackback';
        }

        dotclear()->behavior()->call('tplIfConditions', 'CommentIf', $attr, $content, $if);

        if (count($if) != 0) {
            return '<?php if(' . implode(' && ', (array) $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:CommentIfFirst - O -- displays value if comment is the first one -->
    <!ATTLIST tpl:CommentIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function CommentIfFirst($attr)
    {
        $ret = $attr['return'] ?? 'first';
        $ret = Html::escapeHTML($ret);

        return
        '<?php if (dotclear()->context()->comments->index() == 0) { ' .
        "echo '" . addslashes($ret) . "'; } ?>";
    }

    /*dtd
    <!ELEMENT tpl:CommentIfMe - O -- displays value if comment is the from the entry author -->
    <!ATTLIST tpl:CommentIfMe
    return    CDATA    #IMPLIED    -- value to display in case of success (default: me)
    >
     */
    public function CommentIfMe($attr)
    {
        $ret = $attr['return'] ?? 'me';
        $ret = Html::escapeHTML($ret);

        return
        '<?php if (dotclear()->context()->comments->isMe()) { ' .
        "echo '" . addslashes($ret) . "'; } ?>";
    }

    /*dtd
    <!ELEMENT tpl:CommentIfOdd - O -- displays value if comment is  at an odd position -->
    <!ATTLIST tpl:CommentIfOdd
    return    CDATA    #IMPLIED    -- value to display in case of success (default: odd)
    even      CDATA    #IMPLIED    -- value to display in case of failure (default: <empty>)
    >
     */
    public function CommentIfOdd($attr)
    {
        $odd = $attr['return'] ?? 'odd';
        $odd = Html::escapeHTML($odd);

        $even = $attr['even'] ?? '';
        $even = Html::escapeHTML($even);

        return '<?php echo ((dotclear()->context()->comments->index()+1)%2 ? ' .
        '"' . addslashes($odd) . '" : ' .
        '"' . addslashes($even) . '"); ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentIP - O -- Comment author IP -->
     */
    public function CommentIP($attr)
    {
        return '<?php echo dotclear()->context()->comments->comment_ip; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentOrderNumber - O -- Comment order in page -->
     */
    public function CommentOrderNumber($attr)
    {
        return '<?php echo dotclear()->context()->comments->index()+1; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentsFooter - - -- Last comments result container -->
     */
    public function CommentsFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->comments->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentsHeader - - -- First comments result container -->
     */
    public function CommentsHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->comments->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentPostURL - O -- Comment Entry URL -->
     */
    public function CommentPostURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comments->getPostURL()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:IfCommentAuthorEmail - - -- Container displayed if comment author email is set -->
     */
    public function IfCommentAuthorEmail($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->comments->comment_email) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentHelp - 0 -- Comment syntax mini help -->
     */
    public function CommentHelp($attr, $content)
    {
        return
            "<?php if (dotclear()->blog()->settings()->system->wiki_comments) {\n" .
            "  echo __('Comments can be formatted using a simple wiki syntax.');\n" .
            "} else {\n" .
            "  echo __('HTML code is displayed as text and web addresses are automatically converted.');\n" .
            '} ?>';
    }

    /* Comment preview -------------------------------- */
    /*dtd
    <!ELEMENT tpl:IfCommentPreviewOptional - - -- Container displayed if comment preview is optional or currently previewed -->
     */
    public function IfCommentPreviewOptional($attr, $content)
    {
        return
            '<?php if (dotclear()->blog()->settings()->system->comment_preview_optional || (dotclear()->context()->comment_preview !== null && dotclear()->context()->comment_preview["preview"])) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:IfCommentPreview - - -- Container displayed if comment is being previewed -->
     */
    public function IfCommentPreview($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->comment_preview !== null && dotclear()->context()->comment_preview["preview"]) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewName - O -- Author name for the previewed comment -->
     */
    public function CommentPreviewName($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comment_preview["name"]') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewEmail - O -- Author email for the previewed comment -->
     */
    public function CommentPreviewEmail($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comment_preview["mail"]') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewSite - O -- Author site for the previewed comment -->
     */
    public function CommentPreviewSite($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->comment_preview["site"]') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewContent - O -- Content of the previewed comment -->
    <!ATTLIST tpl:CommentPreviewContent
    raw    (0|1)    #IMPLIED    -- display comment in raw content
    >
     */
    public function CommentPreviewContent($attr)
    {
        $f = $this->getFilters($attr);

        if (!empty($attr['raw'])) {
            $co = 'dotclear()->context()->comment_preview["rawcontent"]';
        } else {
            $co = 'dotclear()->context()->comment_preview["content"]';
        }

        return '<?php echo ' . sprintf($f, $co) . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewCheckRemember - O -- checkbox attribute for "remember me" (same value as before preview) -->
     */
    public function CommentPreviewCheckRemember($attr)
    {
        return
            "<?php if (dotclear()->context()->comment_preview['remember']) { echo ' checked=\"checked\"'; } ?>";
    }

    /* Trackbacks ------------------------------------- */
    /*dtd
    <!ELEMENT tpl:PingBlogName - O -- Trackback blog name -->
     */
    public function PingBlogName($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->pings->comment_author') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingContent - O -- Trackback content -->
     */
    public function PingContent($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->pings->getTrackbackContent()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingDate - O -- Trackback date -->
    <!ATTLIST tpl:PingDate
    format    CDATA    #IMPLIED    -- date format (encoded in dc:str by default if iso8601 or rfc822 not specified)
    iso8601    CDATA    #IMPLIED    -- if set, tells that date format is ISO 8601
    rfc822    CDATA    #IMPLIED    -- if set, tells that date format is RFC 822
    upddt    CDATA    #IMPLIED    -- if set, uses the comment update time
    >
     */
    public function PingDate($attr, $type = '')
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }

        $iso8601 = !empty($attr['iso8601']);
        $rfc822  = !empty($attr['rfc822']);
        $type    = (!empty($attr['upddt']) ? 'upddt' : '');

        $f = $this->getFilters($attr);

        if ($rfc822) {
            return '<?php echo ' . sprintf($f, "dotclear()->context()->pings->getRFC822Date('" . $type . "')") . '; ?>';
        } elseif ($iso8601) {
            return '<?php echo ' . sprintf($f, "dotclear()->context()->pings->getISO8601Date('" . $type . "')") . '; ?>';
        }

        return '<?php echo ' . sprintf($f, "dotclear()->context()->pings->getDate('" . $format . "','" . $type . "')") . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingTime - O -- Trackback date -->
    <!ATTLIST tpl:PingTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the comment update time
    >
     */
    public function PingTime($attr)
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }
        $type = (!empty($attr['upddt']) ? 'upddt' : '');

        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, "dotclear()->context()->pings->getTime('" . $format . "','" . $type . "')") . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingEntryTitle - O -- Trackback entry title -->
     */
    public function PingEntryTitle($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->pings->post_title') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingFeedID - O -- Trackback feed ID -->
     */
    public function PingFeedID($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->pings->getFeedID()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingID - O -- Trackback ID -->
     */
    public function PingID($attr)
    {
        return '<?php echo dotclear()->context()->pings->comment_id; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingIfFirst - O -- displays value if trackback is the first one -->
    <!ATTLIST tpl:PingIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function PingIfFirst($attr)
    {
        $ret = $attr['return'] ?? 'first';
        $ret = Html::escapeHTML($ret);

        return
        '<?php if (dotclear()->context()->pings->index() == 0) { ' .
        "echo '" . addslashes($ret) . "'; } ?>";
    }

    /*dtd
    <!ELEMENT tpl:PingIfOdd - O -- displays value if trackback is  at an odd position -->
    <!ATTLIST tpl:PingIfOdd
    return    CDATA    #IMPLIED    -- value to display in case of success (default: odd)
    even      CDATA    #IMPLIED    -- value to display in case of failure (default: <empty>)
    >
     */
    public function PingIfOdd($attr)
    {
        $odd = $attr['return'] ?? 'odd';
        $odd = Html::escapeHTML($odd);

        $even = $attr['even'] ?? '';
        $even = Html::escapeHTML($even);

        return '<?php echo ((dotclear()->context()->pings->index()+1)%2 ? ' .
        '"' . addslashes($odd) . '" : ' .
        '"' . addslashes($even) . '"); ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingIP - O -- Trackback author IP -->
     */
    public function PingIP($attr)
    {
        return '<?php echo dotclear()->context()->pings->comment_ip; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingNoFollow - O -- displays 'rel="nofollow"' if set in blog -->
     */
    public function PingNoFollow($attr)
    {
        return
            '<?php if(dotclear()->blog()->settings()->system->comments_nofollow) { ' .
            'echo \' rel="nofollow"\';' .
            '} ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingOrderNumber - O -- Trackback order in page -->
     */
    public function PingOrderNumber($attr)
    {
        return '<?php echo dotclear()->context()->pings->index()+1; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingPostURL - O -- Trackback Entry URL -->
     */
    public function PingPostURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->pings->getPostURL()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:Pings - - -- Trackbacks container -->
    <!ATTLIST tpl:Pings
    with_pings    (0|1)    #IMPLIED    -- include trackbacks in request
    lastn    CDATA        #IMPLIED    -- restrict the number of entries
    no_context (1|0)        #IMPLIED  -- Override context information
    order    (desc|asc)    #IMPLIED    -- result ordering (default: asc)
    >
     */
    public function Pings($attr, $content)
    {
        $p = 'if (dotclear()->context()->posts !== null) { ' .
            "\$params['post_id'] = dotclear()->context()->posts->post_id; " .
            "dotclear()->blog()->withoutPassword(false);\n" .
            "}\n";

        $p .= "\$params['comment_trackback'] = true;\n";

        $lastn = 0;
        if (isset($attr['lastn'])) {
            $lastn = abs((int) $attr['lastn']) + 0;
        }

        if ($lastn > 0) {
            $p .= "\$params['limit'] = " . $lastn . ";\n";
        } else {
            $p .= "if (dotclear()->context()->nb_comment_per_page !== null) { \$params['limit'] = dotclear()->context()->nb_comment_per_page; }\n";
        }

        if (empty($attr['no_context'])) {
            $p .= 'if (dotclear()->context()->exists("categories")) { ' .
                "\$params['cat_id'] = dotclear()->context()->categories->cat_id; " .
                "}\n";

            $p .= 'if (dotclear()->context()->exists("langs")) { ' .
                "\$params['sql'] = \"AND P.post_lang = '\".dotclear()->con()->escape(dotclear()->context()->langs->post_lang).\"' \"; " .
                "}\n";
        }

        $order = 'asc';
        if (isset($attr['order']) && preg_match('/^(desc|asc)$/i', $attr['order'])) {
            $order = $attr['order'];
        }

        $p .= "\$params['order'] = 'comment_dt " . $order . "';\n";

        if (isset($attr['no_content']) && $attr['no_content']) {
            $p .= "\$params['no_content'] = true;\n";
        }

        $res = "<?php\n";
        $res .= $p;
        $res .= dotclear()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Pings', 'method' => 'blog()->comments()->getComments'],
            $attr,
            $content
        );
        $res .= 'dotclear()->context()->pings = dotclear()->blog()->comments()->getComments($params); unset($params);' . "\n";
        $res .= "if (dotclear()->context()->posts !== null) { dotclear()->blog()->withoutPassword(true);}\n";
        $res .= "?>\n";

        $res .= '<?php while (dotclear()->context()->pings->fetch()) : ?>' . $content . '<?php endwhile; dotclear()->context()->pings = null; ?>';

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:PingsFooter - - -- Last trackbacks result container -->
     */
    public function PingsFooter($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->pings->isEnd()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingsHeader - - -- First trackbacks result container -->
     */
    public function PingsHeader($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->pings->isStart()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingTitle - O -- Trackback title -->
     */
    public function PingTitle($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->pings->getTrackbackTitle()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:PingAuthorURL - O -- Trackback author URL -->
     */
    public function PingAuthorURL($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dotclear()->context()->pings->getAuthorURL()') . '; ?>';
    }

    # System
    /*dtd
    <!ELEMENT tpl:SysBehavior - O -- Call a given behavior -->
    <!ATTLIST tpl:SysBehavior
    behavior    CDATA    #IMPLIED    -- behavior to call
    >
     */
    public function SysBehavior($attr, $raw)
    {
        if (!isset($attr['behavior'])) {
            return;
        }

        $b = addslashes($attr['behavior']);

        return
            '<?php if (dotclear()->behavior()->has(\'' . $b . '\')) { ' .
            'dotclear()->behavior()->call(\'' . $b . '\',dotclear()->context());' .
            '} ?>';
    }

    /*dtd
    <!ELEMENT tpl:SysIf - - -- System settings tester container -->
    <!ATTLIST tpl:SysIf
    categories        (0|1)    #IMPLIED    -- test if categories are set in current context (value : 1) or not (value : 0)
    posts            (0|1)    #IMPLIED    -- test if posts are set in current context (value : 1) or not (value : 0)
    blog_lang            CDATA    #IMPLIED    -- tests if blog language is the one given in parameter
    current_tpl        CDATA    #IMPLIED    -- tests if current template is the one given in paramater
    current_mode        CDATA    #IMPLIED    -- tests if current URL mode is the one given in parameter
    has_tpl            CDATA     #IMPLIED  -- tests if a named template exists
    has_tag            CDATA     #IMPLIED  -- tests if a named template block or value exists
    blog_id            CDATA     #IMPLIED  -- tests if current blog ID is the one given in parameter
    comments_active    (0|1)    #IMPLIED    -- test if comments are enabled blog-wide
    pings_active        (0|1)    #IMPLIED    -- test if trackbacks are enabled blog-wide
    wiki_comments        (0|1)    #IMPLIED    -- test if wiki syntax is enabled for comments
    operator            (and|or)    #IMPLIED    -- combination of conditions, if more than 1 specifiec (default: and)
    >
     */
    public function SysIf($attr, $content)
    {
        $if      = new ArrayObject();
        $is_ping = null;

        $operator = isset($attr['operator']) ? $this->getOperator($attr['operator']) : '&&';

        if (isset($attr['categories'])) {
            $sign = (bool) $attr['categories'] ? '!' : '=';
            $if[] = 'dotclear()->context()->categories ' . $sign . '== null';
        }

        if (isset($attr['posts'])) {
            $sign = (bool) $attr['posts'] ? '!' : '=';
            $if[] = 'dotclear()->context()->posts ' . $sign . '== null';
        }

        if (isset($attr['blog_lang'])) {
            $sign = '=';
            if (substr($attr['blog_lang'], 0, 1) == '!') {
                $sign              = '!';
                $attr['blog_lang'] = substr($attr['blog_lang'], 1);
            }
            $if[] = 'dotclear()->blog()->settings()->system->lang ' . $sign . "= '" . addslashes($attr['blog_lang']) . "'";
        }

        if (isset($attr['current_tpl'])) {
            $sign = '=';
            if (substr($attr['current_tpl'], 0, 1) == '!') {
                $sign                = '!';
                $attr['current_tpl'] = substr($attr['current_tpl'], 1);
            }
            $if[] = 'dotclear()->context()->current_tpl ' . $sign . "= '" . addslashes($attr['current_tpl']) . "'";
        }

        if (isset($attr['current_mode'])) {
            $sign = '=';
            if (substr($attr['current_mode'], 0, 1) == '!') {
                $sign                 = '!';
                $attr['current_mode'] = substr($attr['current_mode'], 1);
            }
            $if[] = 'dotclear()->url()->type ' . $sign . "= '" . addslashes($attr['current_mode']) . "'";
        }

        if (isset($attr['has_tpl'])) {
            $sign = '';
            if (substr($attr['has_tpl'], 0, 1) == '!') {
                $sign            = '!';
                $attr['has_tpl'] = substr($attr['has_tpl'], 1);
            }
            $if[] = $sign . "dotclear()->template()->getFilePath('" . addslashes($attr['has_tpl']) . "') !== false";
        }

        if (isset($attr['has_tag'])) {
            $sign = 'true';
            if (substr($attr['has_tag'], 0, 1) == '!') {
                $sign            = 'false';
                $attr['has_tag'] = substr($attr['has_tag'], 1);
            }
            $if[] = "dotclear()->template()->tagExists('" . addslashes($attr['has_tag']) . "') === " . $sign;
        }

        if (isset($attr['blog_id'])) {
            $sign = '';
            if (substr($attr['blog_id'], 0, 1) == '!') {
                $sign            = '!';
                $attr['blog_id'] = substr($attr['blog_id'], 1);
            }
            $if[] = $sign . "(dotclear()->blog()->id == '" . addslashes($attr['blog_id']) . "')";
        }

        if (isset($attr['comments_active'])) {
            $sign = (bool) $attr['comments_active'] ? '' : '!';
            $if[] = $sign . 'dotclear()->blog()->settings()->system->allow_comments';
        }

        if (isset($attr['pings_active'])) {
            $sign = (bool) $attr['pings_active'] ? '' : '!';
            $if[] = $sign . 'dotclear()->blog()->settings()->system->allow_trackbacks';
        }

        if (isset($attr['wiki_comments'])) {
            $sign = (bool) $attr['wiki_comments'] ? '' : '!';
            $if[] = $sign . 'dotclear()->blog()->settings()->system->wiki_comments';
        }

        if (isset($attr['search_count']) && preg_match('/^((=|!|&gt;|&lt;)=|(&gt;|&lt;))\s*[0-9]+$/', trim($attr['search_count']))) {
            $if[] = '(isset($_search_count) && $_search_count ' . Html::decodeEntities($attr['search_count']) . ')';
        }

        if (isset($attr['jquery_needed'])) {
            $sign = (bool) $attr['jquery_needed'] ? '' : '!';
            $if[] = $sign . 'dotclear()->blog()->settings()->system->jquery_needed';
        }

        dotclear()->behavior()->call('tplIfConditions', 'SysIf', $attr, $content, $if);

        if (count($if) != 0) {
            return '<?php if(' . implode(' ' . $operator . ' ', (array) $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:SysIfCommentPublished - - -- Container displayed if comment has been published -->
     */
    public function SysIfCommentPublished($attr, $content)
    {
        return
            '<?php if (!empty($_GET[\'pub\'])) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:SysIfCommentPending - - -- Container displayed if comment is pending after submission -->
     */
    public function SysIfCommentPending($attr, $content)
    {
        return
            '<?php if (isset($_GET[\'pub\']) && $_GET[\'pub\'] == 0) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:SysFeedSubtitle - O -- Feed subtitle -->
     */
    public function SysFeedSubtitle($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php if (dotclear()->context()->feed_subtitle !== null) { echo ' . sprintf($f, 'dotclear()->context()->feed_subtitle') . ';} ?>';
    }

    /*dtd
    <!ELEMENT tpl:SysIfFormError - O -- Container displayed if an error has been detected after form submission -->
     */
    public function SysIfFormError($attr, $content)
    {
        return
            '<?php if (dotclear()->context()->form_error !== null) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:SysFormError - O -- Form error -->
     */
    public function SysFormError($attr)
    {
        return
            '<?php if (dotclear()->context()->form_error !== null) { echo dotclear()->context()->form_error; } ?>';
    }

    public function SysPoweredBy($attr)
    {
        return
            '<?php printf(__("Powered by %s"),"<a href=\"https://dotclear.org/\">Dotclear</a>"); ?>';
    }

    public function SysSearchString($attr)
    {
        $s = $attr['string'] ?? '%1$s';

        $f = $this->getFilters($attr);

        return '<?php if (isset($_search)) { echo sprintf(__(\'' . $s . '\'),' . sprintf($f, '$_search') . ',$_search_count);} ?>';
    }

    public function SysSelfURI($attr)
    {
        $f = $this->getFilters($attr);

        return '<?php echo ' . sprintf($f, '\Dotclear\Helper\Network\Http::getSelfURI()') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:else - O -- else: statement -->
     */
    public function GenericElse($attr)
    {
        return '<?php else: ?>';
    }
}
