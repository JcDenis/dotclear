<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template;

// Dotclear\Process\Public\Template\Template
use ArrayObject;
use Dotclear\App;
use Dotclear\Process\Public\Template\Engine\Template as BaseTemplate;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Dt;

/**
 * Public template methods.
 *
 * @ingroup  Public Template
 */
class Template extends BaseTemplate
{
    // \cond
    // php tags break doxygen parser...
    private static $toff = ' ?>';
    private static $ton  = '<?php ';
    // \endcond

    /**
     * @var string $current_tag
     *             Current tag
     */
    private $current_tag;

    /**
     * Constructor.
     *
     * @param string $cache_dir Cache directory path
     * @param string $self_name Tempalte engine method name
     */
    public function __construct(string $cache_dir, string $self_name)
    {
        parent::__construct($cache_dir, $self_name);

        $this->remove_php = !App::core()->blog()->settings()->get('system')->get('tpl_allow_php');
        $this->use_cache  = App::core()->blog()->settings()->get('system')->get('tpl_use_cache');

        // l10n
        $this->addValue('lang', [$this, 'l10n']);

        // Loops test tags
        $this->addBlock('LoopPosition', [$this, 'LoopPosition']);
        $this->addValue('LoopIndex', [$this, 'LoopIndex']);

        // Archives
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

        // Blog
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

        // Categories
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

        // Comments
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

        // Comment preview
        $this->addBlock('IfCommentPreview', [$this, 'IfCommentPreview']);
        $this->addBlock('IfCommentPreviewOptional', [$this, 'IfCommentPreviewOptional']);
        $this->addValue('CommentPreviewName', [$this, 'CommentPreviewName']);
        $this->addValue('CommentPreviewEmail', [$this, 'CommentPreviewEmail']);
        $this->addValue('CommentPreviewSite', [$this, 'CommentPreviewSite']);
        $this->addValue('CommentPreviewContent', [$this, 'CommentPreviewContent']);
        $this->addValue('CommentPreviewCheckRemember', [$this, 'CommentPreviewCheckRemember']);

        // Entries
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

        // Languages
        $this->addBlock('Languages', [$this, 'Languages']);
        $this->addBlock('LanguagesHeader', [$this, 'LanguagesHeader']);
        $this->addBlock('LanguagesFooter', [$this, 'LanguagesFooter']);
        $this->addValue('LanguageCode', [$this, 'LanguageCode']);
        $this->addBlock('LanguageIfCurrent', [$this, 'LanguageIfCurrent']);
        $this->addValue('LanguageURL', [$this, 'LanguageURL']);
        $this->addValue('FeedLanguage', [$this, 'FeedLanguage']);

        // Pagination
        $this->addBlock('Pagination', [$this, 'Pagination']);
        $this->addValue('PaginationCounter', [$this, 'PaginationCounter']);
        $this->addValue('PaginationCurrent', [$this, 'PaginationCurrent']);
        $this->addBlock('PaginationIf', [$this, 'PaginationIf']);
        $this->addValue('PaginationURL', [$this, 'PaginationURL']);

        // Trackbacks
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

        // System
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

        // Generic
        $this->addValue('else', [$this, 'GenericElse']);
    }

    public function getData(string $________): string
    {
        // --BEHAVIOR-- tplBeforeData
        if (App::core()->behavior()->has('tplBeforeData')) {
            self::$_r = App::core()->behavior()->call('tplBeforeData');
            if (self::$_r) {
                return self::$_r;
            }
        }

        parent::getData($________);

        // --BEHAVIOR-- tplAfterData
        if (App::core()->behavior()->has('tplAfterData')) {
            App::core()->behavior()->call('tplAfterData', self::$_r);
        }

        return self::$_r;
    }

    protected function compileFile(string $file): string
    {
        return self::$ton . 'use Dotclear\App;' . self::$toff . "\n" . parent::compileFile($file);
    }

    public function compileBlockNode(string $tag, ArrayObject $attr, string $content): string
    {
        $this->current_tag = $tag;

        // --BEHAVIOR-- templateBeforeBlock
        $res = App::core()->behavior()->call('templateBeforeBlock', $this->current_tag, $attr);

        // --BEHAVIOR-- templateInsideBlock
        App::core()->behavior()->call('templateInsideBlock', $this->current_tag, $attr, [&$content]);

        $res .= parent::compileBlockNode($this->current_tag, $attr, $content);

        // --BEHAVIOR-- templateAfterBlock
        $res .= App::core()->behavior()->call('templateAfterBlock', $this->current_tag, $attr);

        return $res;
    }

    public function compileValueNode(string $tag, ArrayObject $attr, string $str_attr): string
    {
        $this->current_tag = $tag;

        $attr = new ArrayObject($attr);
        // --BEHAVIOR-- templateBeforeValue
        $res = App::core()->behavior()->call('templateBeforeValue', $this->current_tag, $attr);

        $res .= parent::compileValueNode($this->current_tag, $attr, $str_attr);

        // --BEHAVIOR-- templateAfterValue
        $res .= App::core()->behavior()->call('templateAfterValue', $this->current_tag, $attr);

        return $res;
    }

    public function getFilters(ArrayObject $attr, array $default = []): string
    {
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

        return 'App::core()->context()->global_filters(%s,' . var_export($p, true) . ",'" . addslashes($this->current_tag) . "')";
    }

    public function getOperator(string $op): string
    {
        return match (strtolower($op)) {
            'or', '||' => '||',
            default => '&&',
        };
    }

    public function getSortByStr(ArrayObject $attr, string $table = null): string
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

        // --BEHAVIOR-- templateCustomSortByAlias
        App::core()->behavior()->call('templateCustomSortByAlias', $alias);

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

        if (0 === count($res)) {
            array_push($res, $default_alias[$table]['date'] . ' ' . $default_order);
        }

        return implode(', ', $res);
    }

    public function getAge(ArrayObject $attr): string
    {
        if (isset($attr['age']) && preg_match('/^(\-[0-9]+|last).*$/i', $attr['age'])) {
            if (false !== ($ts = strtotime($attr['age']))) {
                return dt::str('%Y-%m-%d %H:%m:%S', $ts);
            }
        }

        return '';
    }

    public function displayCounter(string $variable, array $values, ArrayObject $attr, bool $count_only_by_default = false): string
    {
        $count_only = isset($attr['count_only']) ? (1 == $attr['count_only']) : $count_only_by_default;
        if ($count_only) {
            return self::$ton . 'echo ' . $variable . ';' . self::$toff;
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
                self::$ton . 'if (' . $variable . " == 0) {\n" .
                "  printf(__('" . $v['none'] . "')," . $variable . ");\n" .
                '} elseif (' . $variable . " == 1) {\n" .
                "  printf(__('" . $v['one'] . "')," . $variable . ");\n" .
                "} else {\n" .
                "  printf(__('" . $v['more'] . "')," . $variable . ");\n" .
                '}' . self::$toff;
    }
    /* TEMPLATE FUNCTIONS
    ------------------------------------------------------- */

    public function l10n(ArrayObject $attr, string $str_attr): string
    {
        // Normalize content
        $str_attr = preg_replace('/\s+/x', ' ', $str_attr);

        return self::$ton . "echo __('" . str_replace("'", "\\'", $str_attr) . "');" . self::$toff;
    }

    public function LoopPosition(ArrayObject $attr, string $content): string
    {
        $start  = isset($attr['start']) ? (int) $attr['start'] : '0';
        $length = isset($attr['length']) ? (int) $attr['length'] : 'null';
        $even   = isset($attr['even']) ? (int) (bool) $attr['even'] : 'null';
        $modulo = isset($attr['modulo']) ? (int) $attr['modulo'] : 'null';

        if (0 < $start) {
            --$start;
        }

        return
            self::$ton . 'if (App::core()->context()->loopPosition(' . $start . ',' . $length . ',' . $even . ',' . $modulo . ')) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    public function LoopIndex(ArrayObject $attr): string
    {
        $f = $this->getFilters($attr);

        return self::$ton . 'echo ' . sprintf($f, '(!App::core()->context()->get("cur_loop") ? 0 : App::core()->context()->get("cur_loop")->index() + 1)') . ';' . self::$toff;
    }

    // Archives -------------------------------------------
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
    public function Archives(ArrayObject $attr, string $content): string
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
            $p .= 'if (App::core()->context()->exists("categories")) { ' .
                "\$params['cat_id'] = App::core()->context()->get('categories')->fInt('cat_id'); " .
                "}\n";
        }

        $order = 'desc';
        if (isset($attr['order']) && preg_match('/^(desc|asc)$/i', $attr['order'])) {
            $p .= "\$params['order'] = '" . $attr['order'] . "';\n ";
        }

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Archives', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("archives", App::core()->blog()->posts()->getDates($params)); unset($params);' . "\n";
        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("archives")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("archives", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesHeader - - -- First archives result container -->
     */
    public function ArchivesHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("archives")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesFooter - - -- Last archives result container -->
     */
    public function ArchivesFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("archives")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesYearHeader - - -- First result of year in archives container -->
     */
    public function ArchivesYearHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("archives")->yearHeader()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesYearFooter - - -- Last result of year in archives container -->
     */
    public function ArchivesYearFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("archives")->yearFooter()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchiveDate - O -- Archive result date -->
    <!ATTLIST tpl:ArchiveDate
    format    CDATA    #IMPLIED  -- Date format (Default %B %Y) --
    >
     */
    public function ArchiveDate(ArrayObject $attr): string
    {
        $format = '%B %Y';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }

        $f = $this->getFilters($attr);

        return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('archives')->getDate('" . $format . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchiveEntriesCount - O -- Current archive result number of entries -->
     */
    public function ArchiveEntriesCount(ArrayObject $attr): string
    {
        $f = $this->getFilters($attr);

        return $this->displayCounter(
            sprintf($f, 'App::core()->context()->get("archives")->fInt("nb_post")'),
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
    public function ArchiveNext(ArrayObject $attr, string $content): string
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

        $p .= "\$params['next'] = App::core()->context()->get('archives')->f('dt');";

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'ArchiveNext', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("archives", App::core()->blog()->posts()->getDates($params)); unset($params);' . "\n";
        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("archives")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("archives", null);' . self::$toff;

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
    public function ArchivePrevious(ArrayObject $attr, string $content): string
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

        $p .= "\$params['previous'] = App::core()->context()->get('archives')->f('dt');";

        $res = self::$ton . "\n";
        $res .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'ArchivePrevious', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= $p;
        $res .= 'App::core()->context()->set("archives", App::core()->blog()->posts()->getDates($params)); unset($params);' . "\n";
        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("archives")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("archives", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:ArchiveURL - O -- Current archive result URL -->
     */
    public function ArchiveURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("archives")->call("url")') . ';' . self::$toff;
    }

    // Blog -----------------------------------------------
    /*dtd
    <!ELEMENT tpl:BlogArchiveURL - O -- Blog Archives URL -->
     */
    public function BlogArchiveURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("archive")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogCopyrightNotice - O -- Blog copyrght notices -->
     */
    public function BlogCopyrightNotice(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->get("system")->get("copyright_notice")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogDescription - O -- Blog Description -->
     */
    public function BlogDescription(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->desc') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogEditor - O -- Blog Editor -->
     */
    public function BlogEditor(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->get("system")->get("editor")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogFeedID - O -- Blog Feed ID -->
     */
    public function BlogFeedID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), '"urn:md5:".App::core()->blog()->uid') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogFeedURL - O -- Blog Feed URL -->
    <!ATTLIST tpl:BlogFeedURL
    type    (rss2|atom)    #IMPLIED    -- feed type (default : rss2)
    >
     */
    public function BlogFeedURL(ArrayObject $attr): string
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'atom';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'atom';
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("feed","' . $type . '")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogName - O -- Blog Name -->
     */
    public function BlogName(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->name') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogLanguage - O -- Blog Language -->
     */
    public function BlogLanguage(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->get("system")->get("lang")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogLanguageURL - O -- Blog Localized URL -->
     */
    public function BlogLanguageURL(ArrayObject $attr): string
    {
        $f = $this->getFilters($attr);

        return self::$ton . 'if (App::core()->context()->exists("cur_lang")) echo ' . sprintf($f, 'App::core()->blog()->getURLFor("lang",' .
            'App::core()->context()->cur_lang)') . ';
            else echo ' . sprintf($f, 'App::core()->blog()->url') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogThemeURL - O -- Blog's current Theme URL -->
     */
    public function BlogThemeURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("resources") . "/"') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogParentThemeURL - O -- Blog's current Theme's parent URL -->
     */
    public function BlogParentThemeURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("resources") . "/"') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogPublicURL - O -- Blog Public directory URL -->
     */
    public function BlogPublicURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->public_url') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogUpdateDate - O -- Blog last update date -->
    <!ATTLIST tpl:BlogUpdateDate
    format    CDATA    #IMPLIED    -- date format (encoded in dc:str by default if iso8601 or rfc822 not specified)
    iso8601    CDATA    #IMPLIED    -- if set, tells that date format is ISO 8601
    rfc822    CDATA    #IMPLIED    -- if set, tells that date format is RFC 822
    >
     */
    public function BlogUpdateDate(ArrayObject $attr): string
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
            return self::$ton . 'echo ' . sprintf($f, "App::core()->blog()->getUpdateDate('rfc822')") . ';' . self::$toff;
        }
        if ($iso8601) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->blog()->getUpdateDate('iso8601')") . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf($f, "App::core()->blog()->getUpdateDate('" . $format . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogID - 0 -- Blog ID -->
     */
    public function BlogID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->id') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogRSDURL - O -- Blog RSD URL -->
     */
    public function BlogRSDURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor(\'rsd\')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogXMLRPCURL - O -- Blog XML-RPC URL -->
     */
    public function BlogXMLRPCURL(ArrayObject $attr): string
    {
        $f = $this->getFilters($attr);

        return self::$ton . 'echo ' . sprintf($f, 'App::core()->blog()->getURLFor(\'xmlrpc\',App::core()->blog()->id)') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogURL - O -- Blog URL -->
     */
    public function BlogURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->url') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogQmarkURL - O -- Blog URL, ending with a question mark -->
     */
    public function BlogQmarkURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getQmarkURL()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogMetaRobots - O -- Blog meta robots tag definition, overrides robots_policy setting -->
    <!ATTLIST tpl:BlogMetaRobots
    robots    CDATA    #IMPLIED    -- can be INDEX,FOLLOW,NOINDEX,NOFOLLOW,ARCHIVE,NOARCHIVE
    >
     */
    public function BlogMetaRobots(ArrayObject $attr): string
    {
        $robots = isset($attr['robots']) ? addslashes($attr['robots']) : '';

        return self::$ton . "echo App::core()->context()->robotsPolicy(App::core()->blog()->settings()->get('system')->get('robots_policy'),'" . $robots . "');" . self::$toff;
    }

    /*dtd
    <!ELEMENT gpl:BlogJsJQuery - 0 -- Blog Js jQuery version selected -->
     */
    public function BlogJsJQuery(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getJsJQuery()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogPostsURL - O -- Blog Posts URL -->
     */
    public function BlogPostsURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), ('App::core()->blog()->settings()->get("system")->get("static_home") ? App::core()->blog()->getURLFor("posts") : App::core()->blog()->url')) . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:IfBlogStaticEntryURL - O -- Test if Blog has a static home entry URL -->
     */
    public function IfBlogStaticEntryURL(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . "if (App::core()->blog()->settings()->get('system')->get('static_home_url') != '') :" . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogStaticEntryURL - O -- Set Blog static home entry URL -->
     */
    public function BlogStaticEntryURL(ArrayObject $attr): string
    {
        $p = "\$params['post_type'] = array_keys(App::core()->posttype()->getPostTypes());\n";
        $p .= "\$params['post_url'] = " . sprintf($this->getFilters($attr), 'urldecode(App::core()->blog()->settings()->get("system")->get("static_home_url"))') . ";\n";

        return self::$ton . "\n" . $p . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogNbEntriesFirstPage - O -- Number of entries for 1st page -->
     */
    public function BlogNbEntriesFirstPage(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->get("system")->get("nb_post_for_home")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogNbEntriesPerPage - O -- Number of entries per page -->
     */
    public function BlogNbEntriesPerPage(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->get("system")->get("nb_post_per_page")') . ';' . self::$toff;
    }

    // Categories -----------------------------------------

    /*dtd
    <!ELEMENT tpl:Categories - - -- Categories loop -->
     */
    public function Categories(ArrayObject $attr, string $content): string
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

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Categories', 'method' => 'blog()->categories()->getCategories'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("categories", App::core()->blog()->categories()->getCategories($params));' . "\n";
        $res .= "?>\n";
        $res .= self::$ton . 'while (App::core()->context()->get("categories")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("categories", null); unset($params);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:CategoriesHeader - - -- First Categories result container -->
     */
    public function CategoriesHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("categories")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoriesFooter - - -- Last Categories result container -->
     */
    public function CategoriesFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("categories")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
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
    public function CategoryIf(ArrayObject $attr, string $content): string
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
                    $if[] = '(!App::core()->blog()->categories()->IsInCatSubtree(App::core()->context()->get("categories")->f("cat_url"), "' . $url . '"))';
                } else {
                    $if[] = '(App::core()->context()->get("categories")->f("cat_url") != "' . $url . '")';
                }
            } else {
                if (isset($args['sub'])) {
                    $if[] = '(App::core()->blog()->categories()->IsInCatSubtree(App::core()->context()->get("categories")->f("cat_url"), "' . $url . '"))';
                } else {
                    $if[] = '(App::core()->context()->get("categories")->f("cat_url") == "' . $url . '")';
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
                            $if[] = '(!App::core()->blog()->categories()->IsInCatSubtree(App::core()->context()->get("categories")->f("cat_url"), "' . $url . '"))';
                        } else {
                            $if[] = '(App::core()->context()->get("categories")->f("cat_url") != "' . $url . '")';
                        }
                    } else {
                        if (isset($args['sub'])) {
                            $if[] = '(App::core()->blog()->categories()->IsInCatSubtree(App::core()->context()->get("categories")->f("cat_url"), "' . $url . '"))';
                        } else {
                            $if[] = '(App::core()->context()->get("categories")->f("cat_url") == "' . $url . '")';
                        }
                    }
                }
            }
        }

        if (isset($attr['has_entries'])) {
            $sign = (bool) $attr['has_entries'] ? '>' : '==';
            $if[] = 'App::core()->context()->get("categories")->fInt("nb_post") ' . $sign . ' 0';
        }

        if (isset($attr['has_description'])) {
            $sign = (bool) $attr['has_description'] ? '!=' : '==';
            $if[] = 'App::core()->context()->get("categories")->f("cat_desc") ' . $sign . ' ""';
        }

        App::core()->behavior()->call('tplIfConditions', 'CategoryIf', $attr, $content, $if);

        if (count($if) != 0) {
            return self::$ton . 'if(' . implode(' ' . $operator . ' ', (array) $if) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:CategoryFirstChildren - - -- Current category first children loop -->
     */
    public function CategoryFirstChildren(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . "\n" .
            'App::core()->context()->set("categories", App::core()->blog()->categories()->getCategoryFirstChildren(App::core()->context()->get("categories")->fInt("cat_id")));' . "\n" .
            'while (App::core()->context()->get("categories")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("categories", null);' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryParents - - -- Current category parents loop -->
     */
    public function CategoryParents(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . "\n" .
            'App::core()->context()->set("categories", App::core()->blog()->categories()->getCategoryParents(App::core()->context()->get("categories")->fInt("cat_id")));' . "\n" .
            'while (App::core()->context()->get("categories")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("categories", null);' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryFeedURL - O -- Category feed URL -->
    <!ATTLIST tpl:CategoryFeedURL
    type    (rss2|atom)    #IMPLIED    -- feed type (default : rss2)
    >
     */
    public function CategoryFeedURL(ArrayObject $attr): string
    {
        $type = !empty($attr['type']) ? $attr['type'] : 'atom';

        if (!preg_match('#^(rss2|atom)$#', $type)) {
            $type = 'atom';
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("feed","category/".' .
            'App::core()->context()->get("categories")->f("cat_url")."/' . $type . '")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryID - O -- Category ID -->
     */
    public function CategoryID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->fInt("cat_id")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryURL - O -- Category URL (complete iabsolute URL, including blog URL) -->
     */
    public function CategoryURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("category",' .
            'App::core()->context()->get("categories")->f("cat_url"))') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryShortURL - O -- Category short URL (relative URL, from /category/) -->
     */
    public function CategoryShortURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->f("cat_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryDescription - O -- Category description -->
     */
    public function CategoryDescription(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->f("cat_desc")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryTitle - O -- Category title -->
     */
    public function CategoryTitle($attr)
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->f("cat_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryEntriesCount - O -- Category number of entries -->
     */
    public function CategoryEntriesCount(ArrayObject $attr): string
    {
        return $this->displayCounter(
            sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->fInt("nb_post")'),
            [
                'none' => 'No post',
                'one'  => 'One post',
                'more' => '%d posts',
            ],
            $attr,
            true
        );
    }

    // Entries --------------------------------------------
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
    public function Entries(ArrayObject $attr, string $content): string
    {
        $lastn = -1;
        if (isset($attr['lastn'])) {
            $lastn = abs((int) $attr['lastn']) + 0;
        }

        $p = '$_page_number = App::core()->context()->page_number(); if (!$_page_number) { $_page_number = 1; }' . "\n";

        if (0 != $lastn) {
            // Set limit (aka nb of entries needed)
            if (0 < $lastn) {
                // nb of entries per page specified in template -> regular pagination
                $p .= "\$params['limit'] = " . $lastn . ";\n";
                $p .= '$nb_entry_first_page = $nb_entry_per_page = ' . $lastn . ";\n";
            } else {
                // nb of entries per page not specified -> use ctx settings
                $p .= "\$nb_entry_first_page=App::core()->context()->get('nb_entry_first_page'); \$nb_entry_per_page = App::core()->context()->get('nb_entry_per_page');\n";
                $p .= "if ((App::core()->url()->type == 'default') || (App::core()->url()->type == 'default-page')) {\n";
                $p .= "    \$params['limit'] = (\$_page_number == 1 ? \$nb_entry_first_page : \$nb_entry_per_page);\n";
                $p .= "} else {\n";
                $p .= "    \$params['limit'] = \$nb_entry_per_page;\n";
                $p .= "}\n";
            }
            // Set offset (aka index of first entry)
            if (!isset($attr['ignore_pagination']) || '0' == $attr['ignore_pagination']) {
                // standard pagination, set offset
                $p .= "if ((App::core()->url()->type == 'default') || (App::core()->url()->type == 'default-page')) {\n";
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
            $p .= "App::core()->context()->categoryPostParam(\$params);\n";
        }

        if (isset($attr['with_category']) && $attr['with_category']) {
            $p .= "@\$params['sql'] .= ' AND P.cat_id IS NOT NULL ';\n";
        }

        if (isset($attr['no_category']) && $attr['no_category']) {
            $p .= "@\$params['sql'] .= ' AND P.cat_id IS NULL ';\n";
            $p .= "unset(\$params['cat_url']);\n";
        }

        if (!empty($attr['type'])) {
            $p .= "\$params['post_type'] = preg_split('/\\s*,\\s*/','" . addslashes($attr['type']) . "',-1,PREG_SPLIT_NO_EMPTY);\n";
        }

        if (!empty($attr['url'])) {
            $p .= "\$params['post_url'] = '" . addslashes($attr['url']) . "';\n";
        }

        if (empty($attr['no_context'])) {
            if (!isset($attr['author'])) {
                $p .= 'if (App::core()->context()->exists("users")) { ' .
                    "\$params['user_id'] = App::core()->context()->get('users')->f('user_id'); " .
                    "}\n";
            }

            if (!isset($attr['category']) && (!isset($attr['no_category']) || !$attr['no_category'])) {
                $p .= 'if (App::core()->context()->exists("categories")) { ' .
                    "\$params['cat_id'] = App::core()->context()->get('categories')->fInt('cat_id').(App::core()->blog()->settings()->get('system')->get('inc_subcats')?' ?sub':'');" .
                    "}\n";
            }

            $p .= 'if (App::core()->context()->exists("archives")) { ' .
                "\$params['post_year'] = App::core()->context()->get('archives')->year(); " .
                "\$params['post_month'] = App::core()->context()->get('archives')->month(); ";
            if (!isset($attr['lastn'])) {
                $p .= "unset(\$params['limit']); ";
            }
            $p .= "}\n";

            $p .= 'if (App::core()->context()->exists("langs")) { ' .
                "\$params['post_lang'] = App::core()->context()->get('langs')->f('post_lang'); " .
                "}\n";

            $p .= 'if (App::core()->url()->search_string) { ' .
                "\$params['search'] = App::core()->url()->search_string; " .
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
            $p .= !empty($age) ? "@\$params['sql'] .= ' AND P.post_dt > \\'" . $age . "\\'';\n" : '';
        }

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Entries', 'method' => 'blog()->posts()->getPosts'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("post_params", $params);' . "\n";
        $res .= 'App::core()->context()->set("posts", App::core()->blog()->posts()->getPosts($params)); unset($params);' . "\n";
        $res .= "?>\n";
        $res .= self::$ton . 'while (App::core()->context()->get("posts")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; ' .
            'App::core()->context()->set("posts", null); App::core()->context()->set("post_params", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:DateHeader - O -- Displays date, if post is the first post of the given day -->
     */
    public function DateHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("posts")->firstPostOfDay()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:DateFooter - O -- Displays date,  if post is the last post of the given day -->
     */
    public function DateFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("posts")->lastPostOfDay()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
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
    public function EntryIf(ArrayObject $attr, string $content): string
    {
        $if          = new ArrayObject();
        $extended    = null;
        $hascategory = null;

        $operator = isset($attr['operator']) ? $this->getOperator($attr['operator']) : '&&';

        if (isset($attr['type'])) {
            $type = trim($attr['type']);
            $type = !empty($type) ? $type : 'post';
            $if[] = 'App::core()->context()->get("posts")->f("post_type") == "' . addslashes($type) . '"';
        }

        if (isset($attr['url'])) {
            $url = trim($attr['url']);
            if (substr($url, 0, 1) == '!') {
                $url  = substr($url, 1);
                $if[] = 'App::core()->context()->get("posts")->f("post_url") != "' . addslashes($url) . '"';
            } else {
                $if[] = 'App::core()->context()->get("posts")->f("post_url") == "' . addslashes($url) . '"';
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
                    $if[] = '(!App::core()->context()->get("posts")->underCat("' . $category . '"))';
                } else {
                    $if[] = '(App::core()->context()->get("posts")->f("cat_url") != "' . $category . '")';
                }
            } else {
                if (isset($args['sub'])) {
                    $if[] = '(App::core()->context()->get("posts")->underCat("' . $category . '"))';
                } else {
                    $if[] = '(App::core()->context()->get("posts")->f("cat_url") == "' . $category . '")';
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
                            $if[] = '(!App::core()->context()->get("posts")->underCat("' . $category . '"))';
                        } else {
                            $if[] = '(App::core()->context()->get("posts")->f("cat_url") != "' . $category . '")';
                        }
                    } else {
                        if (isset($args['sub'])) {
                            $if[] = '(App::core()->context()->get("posts")->underCat("' . $category . '"))';
                        } else {
                            $if[] = '(App::core()->context()->get("posts")->f("cat_url") == "' . $category . '")';
                        }
                    }
                }
            }
        }

        if (isset($attr['first'])) {
            $sign = (bool) $attr['first'] ? '=' : '!';
            $if[] = 'App::core()->context()->get("posts")->index() ' . $sign . '= 0';
        }

        if (isset($attr['odd'])) {
            $sign = (bool) $attr['odd'] ? '=' : '!';
            $if[] = '(App::core()->context()->get("posts")->index()+1)%2 ' . $sign . '= 1';
        }

        if (isset($attr['extended'])) {
            $sign = (bool) $attr['extended'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->get("posts")->isExtended()';
        }

        if (isset($attr['selected'])) {
            $sign = (bool) $attr['selected'] ? '' : '!';
            $if[] = $sign . '(bool)App::core()->context()->get("posts")->fInt("post_selected")';
        }

        if (isset($attr['has_category'])) {
            $sign = (bool) $attr['has_category'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->get("posts")->fInt("cat_id")';
        }

        if (isset($attr['comments_active'])) {
            $sign = (bool) $attr['comments_active'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->get("posts")->commentsActive()';
        }

        if (isset($attr['pings_active'])) {
            $sign = (bool) $attr['pings_active'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->get("posts")->trackbacksActive()';
        }

        if (isset($attr['has_comment'])) {
            $sign = (bool) $attr['has_comment'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->get("posts")->hasComments()';
        }

        if (isset($attr['has_ping'])) {
            $sign = (bool) $attr['has_ping'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->get("posts")->hasTrackbacks()';
        }

        if (isset($attr['show_comments'])) {
            if ((bool) $attr['show_comments']) {
                $if[] = '(App::core()->context()->get("posts")->hasComments() || App::core()->context()->get("posts")->commentsActive())';
            } else {
                $if[] = '(!App::core()->context()->get("posts")->hasComments() && !App::core()->context()->get("posts")->commentsActive())';
            }
        }

        if (isset($attr['show_pings'])) {
            if ((bool) $attr['show_pings']) {
                $if[] = '(App::core()->context()->get("posts")->hasTrackbacks() || App::core()->context()->get("posts")->trackbacksActive())';
            } else {
                $if[] = '(!App::core()->context()->get("posts")->hasTrackbacks() && !App::core()->context()->get("posts")->trackbacksActive())';
            }
        }

        if (isset($attr['republished'])) {
            $sign = (bool) $attr['republished'] ? '' : '!';
            $if[] = $sign . '(bool)App::core()->context()->get("posts")->isRepublished()';
        }

        if (isset($attr['author'])) {
            $author = trim($attr['author']);
            if (substr($author, 0, 1) == '!') {
                $author = substr($author, 1);
                $if[]   = 'App::core()->context()->get("posts")->f("user_id") != "' . $author . '"';
            } else {
                $if[] = 'App::core()->context()->get("posts")->f("user_id") == "' . $author . '"';
            }
        }

        App::core()->behavior()->call('tplIfConditions', 'EntryIf', $attr, $content, $if);

        if (count($if) != 0) {
            return self::$ton . 'if(' . implode(' ' . $operator . ' ', (array) $if) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:EntryIfFirst - O -- displays value if entry is the first one -->
    <!ATTLIST tpl:EntryIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function EntryIfFirst(ArrayObject $attr): string
    {
        $ret = $attr['return'] ?? 'first';
        $ret = Html::escapeHTML($ret);

        return
        self::$ton . 'if (App::core()->context()->get("posts")->index() == 0) { ' .
        "echo '" . addslashes($ret) . "'; }" . self::$toff;
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

        return self::$ton . 'echo ((App::core()->context()->get("posts")->index()+1)%2 ? ' .
        '"' . addslashes($odd) . '" : ' .
        '"' . addslashes($even) . '");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryIfSelected - O -- displays value if entry is selected -->
    <!ATTLIST tpl:EntryIfSelected
    return    CDATA    #IMPLIED    -- value to display in case of success (default: selected)
    >
     */
    public function EntryIfSelected(ArrayObject $attr): string
    {
        $ret = $attr['return'] ?? 'selected';
        $ret = Html::escapeHTML($ret);

        return
        self::$ton . 'if (App::core()->context()->get("posts")->fInt("post_selected")) { ' .
        "echo '" . addslashes($ret) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryContent -  -- Entry content -->
    <!ATTLIST tpl:EntryContent
    absolute_urls    CDATA    #IMPLIED -- transforms local URLs to absolute one
    full            (1|0)    #IMPLIED -- returns full content with excerpt
    >
     */
    public function EntryContent(ArrayObject $attr): string
    {
        $urls = '0';
        if (!empty($attr['absolute_urls'])) {
            $urls = '1';
        }

        $f = $this->getFilters($attr);

        if (!empty($attr['full'])) {
            return self::$ton . 'echo ' . sprintf(
                $f,
                'App::core()->context()->get("posts")->getExcerpt(' . $urls . ').' .
                '(strlen(App::core()->context()->get("posts")->getExcerpt(' . $urls . ')) ? " " : "").' .
                'App::core()->context()->get("posts")->getContent(' . $urls . ')'
            ) . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf(
            $f,
            'App::core()->context()->get("posts")->getContent(' . $urls . ')'
        ) . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryIfContentCut - - -- Test if Entry content has been cut -->
    <!ATTLIST tpl:EntryIfContentCut
    absolute_urls    CDATA    #IMPLIED -- transforms local URLs to absolute one
    full            (1|0)    #IMPLIED -- test with full content and excerpt
    >
     */
    public function EntryIfContentCut(ArrayObject $attr, string $content): string
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
            return self::$ton . 'if (strlen(' . sprintf(
                $full,
                'App::core()->context()->get("posts")->getExcerpt(' . $urls . ').' .
                '(strlen(App::core()->context()->get("posts")->getExcerpt(' . $urls . ')) ? " " : "").' .
                'App::core()->context()->get("posts")->getContent(' . $urls . ')'
            ) . ') > ' .
            'strlen(' . sprintf(
                $short,
                'App::core()->context()->get("posts")->getExcerpt(' . $urls . ').' .
                '(strlen(App::core()->context()->get("posts")->getExcerpt(' . $urls . ')) ? " " : "").' .
                'App::core()->context()->get("posts")->getContent(' . $urls . ')'
            ) . ')) :' . self::$toff .
                $content .
                self::$ton . 'endif;' . self::$toff;
        }

        return self::$ton . 'if (strlen(' . sprintf(
            $full,
            'App::core()->context()->get("posts")->getContent(' . $urls . ')'
        ) . ') > ' .
            'strlen(' . sprintf(
                $short,
                'App::core()->context()->get("posts")->getContent(' . $urls . ')'
            ) . ')) :' . self::$toff .
                $content .
                self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryExcerpt - O -- Entry excerpt -->
    <!ATTLIST tpl:EntryExcerpt
    absolute_urls    CDATA    #IMPLIED -- transforms local URLs to absolute one
    >
     */
    public function EntryExcerpt(ArrayObject $attr): string
    {
        $urls = '0';
        if (!empty($attr['absolute_urls'])) {
            $urls = '1';
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getExcerpt(' . $urls . ')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorCommonName - O -- Entry author common name -->
     */
    public function EntryAuthorCommonName(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getAuthorCN()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorDisplayName - O -- Entry author display name -->
     */
    public function EntryAuthorDisplayName(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->f("user_displayname")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorID - O -- Entry author ID -->
     */
    public function EntryAuthorID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->f("user_id")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorEmail - O -- Entry author email -->
    <!ATTLIST tpl:EntryAuthorEmail
    spam_protected    (0|1)    #IMPLIED    -- protect email from spam (default: 1)
    >
     */
    public function EntryAuthorEmail(ArrayObject $attr): string
    {
        $p = 'true';
        if (isset($attr['spam_protected']) && !$attr['spam_protected']) {
            $p = 'false';
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getAuthorEmail(' . $p . ')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorEmailMD5 - O -- Entry author email MD5 sum -->
    >
     */
    public function EntryAuthorEmailMD5(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'md5(App::core()->context()->get("posts")->getAuthorEmail(false))') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorLink - O -- Entry author link -->
     */
    public function EntryAuthorLink(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getAuthorLink()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorURL - O -- Entry author URL -->
     */
    public function EntryAuthorURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->f("user_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryBasename - O -- Entry short URL (relative to /post) -->
     */
    public function EntryBasename(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->f("post_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategory - O -- Entry category (full name) -->
     */
    public function EntryCategory(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->f("cat_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryDescription - O -- Entry category description -->
     */
    public function EntryCategoryDescription(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->f("cat_desc")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoriesBreadcrumb - - -- Current entry parents loop (without last one) -->
     */
    public function EntryCategoriesBreadcrumb(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . "\n" .
            'App::core()->context()->set("categories", App::core()->blog()->categories()->getCategoryParents(App::core()->context()->get("posts")->fInt("cat_id")));' . "\n" .
            'while (App::core()->context()->get("categories")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("categories", null);' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryID - O -- Entry category ID -->
     */
    public function EntryCategoryID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->fint("cat_id")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryURL - O -- Entry category URL -->
     */
    public function EntryCategoryURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getCategoryURL()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryShortURL - O -- Entry category short URL (relative URL, from /category/) -->
     */
    public function EntryCategoryShortURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->f("cat_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryFeedID - O -- Entry feed ID -->
     */
    public function EntryFeedID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getFeedID()') . ';' . self::$toff;
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
    public function EntryFirstImage(ArrayObject $attr): string
    {
        $size          = !empty($attr['size']) ? $attr['size'] : '';
        $class         = !empty($attr['class']) ? $attr['class'] : '';
        $with_category = !empty($attr['with_category']) ? 1 : 0;
        $no_tag        = !empty($attr['no_tag']) ? 1 : 0;
        $content_only  = !empty($attr['content_only']) ? 1 : 0;
        $cat_only      = !empty($attr['cat_only']) ? 1 : 0;

        return self::$ton . "echo App::core()->context()->EntryFirstImageHelper('" . addslashes($size) . "'," . $with_category . ",'" . addslashes($class) . "'," .
            $no_tag . ',' . $content_only . ',' . $cat_only . ');' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryID - O -- Entry ID -->
     */
    public function EntryID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->fInt("post_id")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryLang - O --  Entry language or blog lang if not defined -->
     */
    public function EntryLang(ArrayObject $attr): string
    {
        $f = $this->getFilters($attr);

        return
        self::$ton . 'if (App::core()->context()->get("posts")->f("post_lang")) { ' .
        'echo ' . sprintf($f, 'App::core()->context()->get("posts")->f("post_lang")') . '; ' .
        '} else {' .
        'echo ' . sprintf($f, 'App::core()->blog()->settings()->get("system")->get("lang")') . '; ' .
            '}' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryNext - - -- Next entry block -->
    <!ATTLIST tpl:EntryNext
    restrict_to_category    (0|1)    #IMPLIED    -- find next post in the same category (default: 0)
    restrict_to_lang        (0|1)    #IMPLIED    -- find next post in the same language (default: 0)
    >
     */
    public function EntryNext(ArrayObject $attr, string $content): string
    {
        $restrict_to_category = !empty($attr['restrict_to_category']) ? '1' : '0';
        $restrict_to_lang     = !empty($attr['restrict_to_lang']) ? '1' : '0';

        return
            self::$ton . '$next_post = App::core()->blog()->posts()->getNextPost(App::core()->context()->get("posts"),1,' . $restrict_to_category . ',' . $restrict_to_lang . ');' . self::$toff . "\n" .
            self::$ton . 'if ($next_post !== null) :' . self::$toff .

            self::$ton . 'App::core()->context()->set("posts", $next_post); unset($next_post);' . "\n" .
            'while (App::core()->context()->get("posts")->fetch()) :' . self::$toff .
            $content .
            self::$ton . 'endwhile; App::core()->context()->set("posts", null);' . self::$toff .
            self::$ton . 'endif;' . self::$toff . "\n";
    }

    /*dtd
    <!ELEMENT tpl:EntryPrevious - - -- Previous entry block -->
    <!ATTLIST tpl:EntryPrevious
    restrict_to_category    (0|1)    #IMPLIED    -- find previous post in the same category (default: 0)
    restrict_to_lang        (0|1)    #IMPLIED    -- find next post in the same language (default: 0)
    >
     */
    public function EntryPrevious(ArrayObject $attr, string $content): string
    {
        $restrict_to_category = !empty($attr['restrict_to_category']) ? '1' : '0';
        $restrict_to_lang     = !empty($attr['restrict_to_lang']) ? '1' : '0';

        return
            self::$ton . '$prev_post = App::core()->blog()->posts()->getNextPost(App::core()->context()->get("posts"),-1,' . $restrict_to_category . ',' . $restrict_to_lang . ');' . self::$toff . "\n" .
            self::$ton . 'if ($prev_post !== null) :' . self::$toff .

            self::$ton . 'App::core()->context()->set("posts", $prev_post); unset($prev_post);' . "\n" .
            'while (App::core()->context()->get("posts")->fetch()) :' . self::$toff .
            $content .
            self::$ton . 'endwhile; App::core()->context()->set("posts", null);' . self::$toff .
            self::$ton . 'endif;' . self::$toff . "\n";
    }

    /*dtd
    <!ELEMENT tpl:EntryTitle - O -- Entry title -->
     */
    public function EntryTitle(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->f("post_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryURL - O -- Entry URL -->
     */
    public function EntryURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getURL()') . ';' . self::$toff;
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
    public function EntryDate(ArrayObject $attr): string
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
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('posts')->getRFC822Date('" . $type . "')") . ';' . self::$toff;
        }
        if ($iso8601) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('posts')->getISO8601Date('" . $type . "')") . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('posts')->getDate('" . $format . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryTime - O -- Entry date -->
    <!ATTLIST tpl:EntryTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the post update time
    creadt    CDATA    #IMPLIED    -- if set, uses the post creation time
    >
     */
    public function EntryTime(ArrayObject $attr): string
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }

        $type = (!empty($attr['creadt']) ? 'creadt' : '');
        $type = (!empty($attr['upddt']) ? 'upddt' : $type);

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), "App::core()->context()->get('posts')->getTime('" . $format . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntriesHeader - - -- First entries result container -->
     */
    public function EntriesHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("posts")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntriesFooter - - -- Last entries result container -->
     */
    public function EntriesFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("posts")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
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
    public function EntryCommentCount(ArrayObject $attr): string
    {
        if (empty($attr['count_all'])) {
            $operation = 'App::core()->context()->get("posts")->fInt("nb_comment")';
        } else {
            $operation = '(App::core()->context()->get("posts")->fInt("nb_comment") + App::core()->context()->get("posts")->fInt("nb_trackback"))';
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
    public function EntryPingCount(ArrayObject $attr): string
    {
        return $this->displayCounter(
            'App::core()->context()->get("posts")->fInt("nb_trackback")',
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
    public function EntryPingData(ArrayObject $attr): string
    {
        $format = !empty($attr['format']) && 'xml' == $attr['format'] ? 'xml' : 'html';

        return self::$ton . "if (App::core()->context()->get('posts')->trackbacksActive()) { echo App::core()->context()->get('posts')->getTrackbackData('" . $format . "'); }" . self::$toff . "\n";
    }

    /*dtd
    <!ELEMENT tpl:EntryPingLink - O -- Entry trackback link -->
     */
    public function EntryPingLink(ArrayObject $attr): string
    {
        return self::$ton . "if (App::core()->context()->get('posts')->trackbacksActive()) { echo App::core()->context()->get('posts')->getTrackbackLink(); }" . self::$toff . "\n";
    }

    // Languages --------------------------------------
    /*dtd
    <!ELEMENT tpl:Languages - - -- Languages loop -->
    <!ATTLIST tpl:Languages
    lang    CDATA    #IMPLIED    -- restrict loop on given lang
    order    (desc|asc)    #IMPLIED    -- languages ordering (default: desc)
    >
     */
    public function Languages(ArrayObject $attr, string $content): string
    {
        $p = "if (!isset(\$params)) \$params = [];\n";

        if (isset($attr['lang'])) {
            $p = "\$params['lang'] = '" . addslashes($attr['lang']) . "';\n";
        }

        $order = 'desc';
        if (isset($attr['order']) && preg_match('/^(desc|asc)$/i', $attr['order'])) {
            $p .= "\$params['order'] = '" . $attr['order'] . "';\n ";
        }

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Languages', 'method' => 'blog()->posts()->getLangs'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("langs", App::core()->blog()->posts()->getLangs($params)); unset($params);' . "\n";
        $res .= "?>\n";

        $res .= self::$ton . 'if (App::core()->context()->get("langs")->count() > 1) : ' .
            'while (App::core()->context()->get("langs")->fetch()) :' . self::$toff . $content .
            self::$ton . 'endwhile; App::core()->context()->set("langs", null); endif;' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:LanguagesHeader - - -- First languages result container -->
     */
    public function LanguagesHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("langs")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:LanguagesFooter - - -- Last languages result container -->
     */
    public function LanguagesFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("lang")"->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:LanguageCode - O -- Language code -->
     */
    public function LanguageCode(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("langs")->f("post_lang")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:LanguageIfCurrent - - -- tests if post language is current language -->
     */
    public function LanguageIfCurrent(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->cur_lang == App::core()->context()->get("langs")->f("post_lang")) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:LanguageURL - O -- Language URL -->
     */
    public function LanguageURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("lang",' .
            'App::core()->context()->get("angs")->f("post_lang"))') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:FeedLanguage - O -- Feed Language -->
     */
    public function FeedLanguage(ArrayObject $attr): string
    {
        $f = $this->getFilters($attr);

        return
        self::$ton . 'if (App::core()->context()->exists("cur_lang")) ' . "\n" .
        '   { echo ' . sprintf($f, 'App::core()->context()->get("cur_lang")') . '; }' . "\n" .
        'elseif (App::core()->context()->exists("posts") && App::core()->context()->get("posts")->exists("post_lang")) ' . "\n" .
        '   { echo ' . sprintf($f, 'App::core()->context()->get("posts")->get("post_lang")') . '; }' . "\n" .
        'else ' . "\n" .
        '   { echo ' . sprintf($f, 'App::core()->blog()->settings()->get("system")->get("lang")') . '; }' . self::$toff;
    }

    // Pagination -------------------------------------
    /*dtd
    <!ELEMENT tpl:Pagination - - -- Pagination container -->
    <!ATTLIST tpl:Pagination
    no_context    (0|1)    #IMPLIED    -- override test on posts count vs number of posts per page
    >
     */
    public function Pagination(ArrayObject $attr, string $content): string
    {
        $p = self::$ton . "\n";
        $p .= '$params = App::core()->context()->get("post_params");' . "\n";
        $p .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Pagination', 'method' => 'blog()->posts()->getPosts'],
            $attr,
            $content
        );
        $p .= 'App::core()->context()->set("pagination", App::core()->blog()->posts()->getPosts($params,true)); unset($params);' . "\n";
        $p .= "?>\n";

        if (isset($attr['no_context']) && $attr['no_context']) {
            return $p . $content;
        }

        return
            $p .
            self::$ton . 'if (App::core()->context()->get("pagination")->fInt() > App::core()->context()->get("posts")->count()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PaginationCounter - O -- Number of pages -->
     */
    public function PaginationCounter(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->PaginationNbPages()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PaginationCurrent - O -- current page -->
     */
    public function PaginationCurrent(ArrayObject $attr): string
    {
        $offset = 0;
        if (isset($attr['offset'])) {
            $offset = (int) $attr['offset'];
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->PaginationPosition(' . $offset . ')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PaginationIf - - -- pages tests -->
    <!ATTLIST tpl:PaginationIf
    start    (0|1)    #IMPLIED    -- test if we are at first page (value : 1) or not (value : 0)
    end    (0|1)    #IMPLIED    -- test if we are at last page (value : 1) or not (value : 0)
    >
     */
    public function PaginationIf(ArrayObject $attr, string $content): string
    {
        $if = new ArrayObject();

        if (isset($attr['start'])) {
            $sign = (bool) $attr['start'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->PaginationStart()';
        }

        if (isset($attr['end'])) {
            $sign = (bool) $attr['end'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->PaginationEnd()';
        }

        App::core()->behavior()->call('tplIfConditions', 'PaginationIf', $attr, $content, $if);

        if (count($if) != 0) {
            return self::$ton . 'if(' . implode(' && ', (array) $if) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:PaginationURL - O -- link to previoux/next page -->
    <!ATTLIST tpl:PaginationURL
    offset    CDATA    #IMPLIED    -- page offset (negative for previous pages), default: 0
    >
     */
    public function PaginationURL(ArrayObject $attr): string
    {
        $offset = 0;
        if (isset($attr['offset'])) {
            $offset = (int) $attr['offset'];
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->PaginationURL(' . $offset . ')') . ';' . self::$toff;
    }

    // Comments ---------------------------------------
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
    public function Comments(ArrayObject $attr, string $content): string
    {
        $p = '';
        if (empty($attr['with_pings'])) {
            $p .= "\$params['comment_trackback'] = false;\n";
        }

        $lastn = 0;
        if (isset($attr['lastn'])) {
            $lastn = abs((int) $attr['lastn']) + 0;
        }

        if (0 < $lastn) {
            $p .= "\$params['limit'] = " . $lastn . ";\n";
        } else {
            $p .= "if (App::core()->context()->get('nb_comment_per_page') !== null) { \$params['limit'] = (int) App::core()->context()->get('nb_comment_per_page'); }\n";
        }

        if (empty($attr['no_context'])) {
            $p .= 'if (App::core()->context()->get("posts") !== null) { ' .
                "\$params['post_id'] = App::core()->context()->get('posts')->fInt('post_id'); " .
                "App::core()->blog()->withoutPassword(false);\n" .
                "}\n";
            $p .= 'if (App::core()->context()->exists("categories")) { ' .
                "\$params['cat_id'] = App::core()->context()->get('categories')->fInt('cat_id'); " .
                "}\n";

            $p .= 'if (App::core()->context()->exists("langs")) { ' .
                "\$params['sql'] = \"AND P.post_lang = '\".App::core()->con()->escape(App::core()->context()->get('langs')->f('post_lang')).\"' \"; " .
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
            $p .= !empty($age) ? "@\$params['sql'] .= ' AND P.post_dt > \\'" . $age . "\\'';\n" : '';
        }

        $res = self::$ton . "\n";
        $res .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Comments', 'method' => 'blog()->comments()->getComments'],
            $attr,
            $content
        );
        $res .= $p;
        $res .= 'App::core()->context()->set("comments", App::core()->blog()->comments()->getComments($params)); unset($params);' . "\n";
        $res .= "if (App::core()->context()->get('posts') !== null) { App::core()->blog()->withoutPassword(true);}\n";

        if (!empty($attr['with_pings'])) {
            $res .= 'App::core()->context()->set("pings", App::core()->context()->get("comments"));' . "\n";
        }

        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("comments")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("comments", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthor - O -- Comment author -->
     */
    public function CommentAuthor(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->f("comment_author")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorDomain - O -- Comment author website domain -->
     */
    public function CommentAuthorDomain(ArrayObject $attr): string
    {
        return self::$ton . 'echo preg_replace("#^http(?:s?)://(.+?)/.*$#msu",\'$1\',App::core()->context()->get("comments")->f("comment_site"));' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorLink - O -- Comment author link -->
     */
    public function CommentAuthorLink(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getAuthorLink()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorMailMD5 - O -- Comment author email MD5 sum -->
     */
    public function CommentAuthorMailMD5(ArrayObject $attr): string
    {
        return self::$ton . 'echo md5(App::core()->context()->get("comments")->f("comment_email")) ;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorURL - O -- Comment author URL -->
     */
    public function CommentAuthorURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getAuthorURL()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentContent - O --  Comment content -->
    <!ATTLIST tpl:CommentContent
    absolute_urls    (0|1)    #IMPLIED    -- convert URLS to absolute urls
    >
     */
    public function CommentContent(ArrayObject $attr): string
    {
        $urls = '0';
        if (!empty($attr['absolute_urls'])) {
            $urls = '1';
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getContent(' . $urls . ')') . ';' . self::$toff;
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
    public function CommentDate(ArrayObject $attr): string
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
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('comments')->getRFC822Date('" . $type . "')") . ';' . self::$toff;
        }
        if ($iso8601) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('comments')->getISO8601Date('" . $type . "')") . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('comments')->getDate('" . $format . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentTime - O -- Comment date -->
    <!ATTLIST tpl:CommentTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the comment update time
    >
     */
    public function CommentTime(ArrayObject $attr): string
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }
        $type = (!empty($attr['upddt']) ? 'upddt' : '');

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), "App::core()->context()->get('comments')->getTime('" . $format . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentEmail - O -- Comment author email -->
    <!ATTLIST tpl:CommentEmail
    spam_protected    (0|1)    #IMPLIED    -- protect email from spam (default: 1)
    >
     */
    public function CommentEmail(ArrayObject $attr): string
    {
        $p = 'true';
        if (isset($attr['spam_protected']) && !$attr['spam_protected']) {
            $p = 'false';
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getEmail(' . $p . ')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentEntryTitle - O -- Title of the comment entry -->
     */
    public function CommentEntryTitle(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->get("post_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentFeedID - O -- Comment feed ID -->
     */
    public function CommentFeedID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getFeedID()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentID - O -- Comment ID -->
     */
    public function CommentID(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("comments")->fInt("comment_id");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentIf - - -- test container for comments -->
    <!ATTLIST tpl:CommentIf
    is_ping    (0|1)    #IMPLIED    -- test if comment is a trackback (value : 1) or not (value : 0)
    >
     */
    public function CommentIf(ArrayObject $attr, string $content): string
    {
        $if      = new ArrayObject();
        $is_ping = null;

        if (isset($attr['is_ping'])) {
            $sign = (bool) $attr['is_ping'] ? '' : '!';
            $if[] = $sign . 'App::core()->context()->get("comments")->fInt("comment_trackback")';
        }

        App::core()->behavior()->call('tplIfConditions', 'CommentIf', $attr, $content, $if);

        if (count($if) != 0) {
            return self::$ton . 'if(' . implode(' && ', (array) $if) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:CommentIfFirst - O -- displays value if comment is the first one -->
    <!ATTLIST tpl:CommentIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function CommentIfFirst(ArrayObject $attr): string
    {
        $ret = $attr['return'] ?? 'first';
        $ret = Html::escapeHTML($ret);

        return
        self::$ton . 'if (App::core()->context()->get("comments")->index() == 0) { ' .
        "echo '" . addslashes($ret) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentIfMe - O -- displays value if comment is the from the entry author -->
    <!ATTLIST tpl:CommentIfMe
    return    CDATA    #IMPLIED    -- value to display in case of success (default: me)
    >
     */
    public function CommentIfMe(ArrayObject $attr): string
    {
        $ret = $attr['return'] ?? 'me';
        $ret = Html::escapeHTML($ret);

        return
        self::$ton . 'if (App::core()->context()->get("comments")->isMe()) { ' .
        "echo '" . addslashes($ret) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentIfOdd - O -- displays value if comment is  at an odd position -->
    <!ATTLIST tpl:CommentIfOdd
    return    CDATA    #IMPLIED    -- value to display in case of success (default: odd)
    even      CDATA    #IMPLIED    -- value to display in case of failure (default: <empty>)
    >
     */
    public function CommentIfOdd(ArrayObject $attr): string
    {
        $odd = $attr['return'] ?? 'odd';
        $odd = Html::escapeHTML($odd);

        $even = $attr['even'] ?? '';
        $even = Html::escapeHTML($even);

        return self::$ton . 'echo ((App::core()->context()->get("comments")->index()+1)%2 ? ' .
        '"' . addslashes($odd) . '" : ' .
        '"' . addslashes($even) . '");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentIP - O -- Comment author IP -->
     */
    public function CommentIP(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("comments")->f("comment_ip");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentOrderNumber - O -- Comment order in page -->
     */
    public function CommentOrderNumber(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("comments")->index()+1;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentsFooter - - -- Last comments result container -->
     */
    public function CommentsFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("comments")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentsHeader - - -- First comments result container -->
     */
    public function CommentsHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("comments")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPostURL - O -- Comment Entry URL -->
     */
    public function CommentPostURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getPostURL()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:IfCommentAuthorEmail - - -- Container displayed if comment author email is set -->
     */
    public function IfCommentAuthorEmail(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("comments")->f("comment_email")) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentHelp - 0 -- Comment syntax mini help -->
     */
    public function CommentHelp(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . "if (App::core()->blog()->settings()->get('system')->get('wiki_comments')) {\n" .
            "  echo __('Comments can be formatted using a simple wiki syntax.');\n" .
            "} else {\n" .
            "  echo __('HTML code is displayed as text and web addresses are automatically converted.');\n" .
            '}' . self::$toff;
    }

    // Comment preview --------------------------------
    /*dtd
    <!ELEMENT tpl:IfCommentPreviewOptional - - -- Container displayed if comment preview is optional or currently previewed -->
     */
    public function IfCommentPreviewOptional(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->blog()->settings()->get("system")->get("comment_preview_optional") || (App::core()->context()->get("comment_preview") !== null && App::core()->context()->get("comment_preview")["preview"])) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:IfCommentPreview - - -- Container displayed if comment is being previewed -->
     */
    public function IfCommentPreview(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("comment_preview") !== null && App::core()->context()->get("comment_preview")["preview"]) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewName - O -- Author name for the previewed comment -->
     */
    public function CommentPreviewName(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comment_preview")["name"]') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewEmail - O -- Author email for the previewed comment -->
     */
    public function CommentPreviewEmail(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comment_preview")["mail"]') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewSite - O -- Author site for the previewed comment -->
     */
    public function CommentPreviewSite(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comment_preview")["site"]') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewContent - O -- Content of the previewed comment -->
    <!ATTLIST tpl:CommentPreviewContent
    raw    (0|1)    #IMPLIED    -- display comment in raw content
    >
     */
    public function CommentPreviewContent(ArrayObject $attr): string
    {
        if (!empty($attr['raw'])) {
            $co = 'App::core()->context()->get("comment_preview")["rawcontent"]';
        } else {
            $co = 'App::core()->context()->get("comment_preview")["content"]';
        }

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), $co) . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewCheckRemember - O -- checkbox attribute for "remember me" (same value as before preview) -->
     */
    public function CommentPreviewCheckRemember(ArrayObject $attr): string
    {
        return self::$ton . "if (App::core()->context()->get('comment_preview')['remember']) { echo ' checked=\"checked\"'; }" . self::$toff;
    }

    // Trackbacks -------------------------------------
    /*dtd
    <!ELEMENT tpl:PingBlogName - O -- Trackback blog name -->
     */
    public function PingBlogName(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->f("comment_author")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingContent - O -- Trackback content -->
     */
    public function PingContent(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->getTrackbackContent()') . ';' . self::$toff;
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
    public function PingDate(ArrayObject $attr, string $type = ''): string
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }

        $iso8601 = !empty($attr['iso8601']);
        $rfc822  = !empty($attr['rfc822']);
        $type    = !empty($attr['upddt']) ? 'upddt' : '';

        $f = $this->getFilters($attr);

        if ($rfc822) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('pings')->getRFC822Date('" . $type . "')") . ';' . self::$toff;
        }
        if ($iso8601) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('pings')->getISO8601Date('" . $type . "')") . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('pings')->getDate('" . $format . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingTime - O -- Trackback date -->
    <!ATTLIST tpl:PingTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the comment update time
    >
     */
    public function PingTime(ArrayObject $attr): string
    {
        $format = '';
        if (!empty($attr['format'])) {
            $format = addslashes($attr['format']);
        }
        $type = (!empty($attr['upddt']) ? 'upddt' : '');

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), "App::core()->context()->get('pings')->getTime('" . $format . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingEntryTitle - O -- Trackback entry title -->
     */
    public function PingEntryTitle(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->f("post_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingFeedID - O -- Trackback feed ID -->
     */
    public function PingFeedID(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->getFeedID()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingID - O -- Trackback ID -->
     */
    public function PingID(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("pings")->fInt("comment_id");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingIfFirst - O -- displays value if trackback is the first one -->
    <!ATTLIST tpl:PingIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function PingIfFirst(ArrayObject $attr): string
    {
        $ret = $attr['return'] ?? 'first';
        $ret = Html::escapeHTML($ret);

        return
        self::$ton . 'if (App::core()->context()->get("pings")->index() == 0) { ' .
        "echo '" . addslashes($ret) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingIfOdd - O -- displays value if trackback is  at an odd position -->
    <!ATTLIST tpl:PingIfOdd
    return    CDATA    #IMPLIED    -- value to display in case of success (default: odd)
    even      CDATA    #IMPLIED    -- value to display in case of failure (default: <empty>)
    >
     */
    public function PingIfOdd(ArrayObject $attr): string
    {
        $odd = $attr['return'] ?? 'odd';
        $odd = Html::escapeHTML($odd);

        $even = $attr['even'] ?? '';
        $even = Html::escapeHTML($even);

        return self::$ton . 'echo ((App::core()->context()->get("pings")->index()+1)%2 ? ' .
        '"' . addslashes($odd) . '" : ' .
        '"' . addslashes($even) . '");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingIP - O -- Trackback author IP -->
     */
    public function PingIP(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("pings")->f("comment_ip");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingNoFollow - O -- displays 'rel="nofollow"' if set in blog -->
     */
    public function PingNoFollow(ArrayObject $attr): string
    {
        return
            self::$ton . 'if(App::core()->blog()->settings()->get("system")->get("comments_nofollow")) { ' .
            'echo \' rel="nofollow"\';' .
            '}' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingOrderNumber - O -- Trackback order in page -->
     */
    public function PingOrderNumber(ArrayObject $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("pings")->index()+1;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingPostURL - O -- Trackback Entry URL -->
     */
    public function PingPostURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->getPostURL()') . ';' . self::$toff;
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
    public function Pings(ArrayObject $attr, string $content): string
    {
        $p = 'if (App::core()->context()->get("posts") !== null) { ' .
            "\$params['post_id'] = App::core()->context()->get('posts')->fInt('post_id'); " .
            "App::core()->blog()->withoutPassword(false);\n" .
            "}\n";

        $p .= "\$params['comment_trackback'] = true;\n";

        $lastn = 0;
        if (isset($attr['lastn'])) {
            $lastn = abs((int) $attr['lastn']) + 0;
        }

        if (0 < $lastn) {
            $p .= "\$params['limit'] = " . $lastn . ";\n";
        } else {
            $p .= "if (App::core()->context()->get('nb_comment_per_page') !== null) { \$params['limit'] = App::core()->context()->get('nb_comment_per_page'); }\n";
        }

        if (empty($attr['no_context'])) {
            $p .= 'if (App::core()->context()->exists("categories")) { ' .
                "\$params['cat_id'] = App::core()->context()->get('categories')->fInt('cat_id'); " .
                "}\n";

            $p .= 'if (App::core()->context()->exists("langs")) { ' .
                "\$params['sql'] = \"AND P.post_lang = '\".App::core()->con()->escape(App::core()->context()->get('langs')->f('post_lang')).\"' \"; " .
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

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior()->call(
            'templatePrepareParams',
            ['tag' => 'Pings', 'method' => 'blog()->comments()->getComments'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("pings", App::core()->blog()->comments()->getComments($params)); unset($params);' . "\n";
        $res .= "if (App::core()->context()->get('posts') !== null) { App::core()->blog()->withoutPassword(true);}\n";
        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("pings")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("pings", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:PingsFooter - - -- Last trackbacks result container -->
     */
    public function PingsFooter(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("pings")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingsHeader - - -- First trackbacks result container -->
     */
    public function PingsHeader(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("pings")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingTitle - O -- Trackback title -->
     */
    public function PingTitle(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->getTrackbackTitle()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingAuthorURL - O -- Trackback author URL -->
     */
    public function PingAuthorURL(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->getAuthorURL()') . ';' . self::$toff;
    }

    // System
    /*dtd
    <!ELEMENT tpl:SysBehavior - O -- Call a given behavior -->
    <!ATTLIST tpl:SysBehavior
    behavior    CDATA    #IMPLIED    -- behavior to call
    >
     */
    public function SysBehavior(ArrayObject $attr, string $raw): string
    {
        if (!isset($attr['behavior'])) {
            return '';
        }

        $b = addslashes($attr['behavior']);

        return
            self::$ton . 'if (App::core()->behavior()->has(\'' . $b . '\')) { ' .
            'App::core()->behavior()->call(\'' . $b . '\',App::core()->context());' .
            '}' . self::$toff;
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
    public function SysIf(ArrayObject $attr, string $content): string
    {
        $if      = new ArrayObject();
        $is_ping = null;

        $operator = isset($attr['operator']) ? $this->getOperator($attr['operator']) : '&&';

        if (isset($attr['categories'])) {
            $sign = (bool) $attr['categories'] ? '!' : '=';
            $if[] = 'App::core()->context()->get("categories") ' . $sign . '== null';
        }

        if (isset($attr['posts'])) {
            $sign = (bool) $attr['posts'] ? '!' : '=';
            $if[] = 'App::core()->context()->get("posts") ' . $sign . '== null';
        }

        if (isset($attr['blog_lang'])) {
            $sign = '=';
            if (substr($attr['blog_lang'], 0, 1) == '!') {
                $sign              = '!';
                $attr['blog_lang'] = substr($attr['blog_lang'], 1);
            }
            $if[] = 'App::core()->blog()->settings()->get("system")->get("lang") ' . $sign . "= '" . addslashes($attr['blog_lang']) . "'";
        }

        if (isset($attr['current_tpl'])) {
            $sign = '=';
            if (substr($attr['current_tpl'], 0, 1) == '!') {
                $sign                = '!';
                $attr['current_tpl'] = substr($attr['current_tpl'], 1);
            }
            $if[] = 'App::core()->context()->get("current_tpl") ' . $sign . "= '" . addslashes($attr['current_tpl']) . "'";
        }

        if (isset($attr['current_mode'])) {
            $sign = '=';
            if (substr($attr['current_mode'], 0, 1) == '!') {
                $sign                 = '!';
                $attr['current_mode'] = substr($attr['current_mode'], 1);
            }
            $if[] = 'App::core()->url()->type ' . $sign . "= '" . addslashes($attr['current_mode']) . "'";
        }

        if (isset($attr['has_tpl'])) {
            $sign = '';
            if (substr($attr['has_tpl'], 0, 1) == '!') {
                $sign            = '!';
                $attr['has_tpl'] = substr($attr['has_tpl'], 1);
            }
            $if[] = $sign . "App::core()->template()->getFilePath('" . addslashes($attr['has_tpl']) . "') !== false";
        }

        if (isset($attr['has_tag'])) {
            $sign = 'true';
            if (substr($attr['has_tag'], 0, 1) == '!') {
                $sign            = 'false';
                $attr['has_tag'] = substr($attr['has_tag'], 1);
            }
            $if[] = "App::core()->template()->tagExists('" . addslashes($attr['has_tag']) . "') === " . $sign;
        }

        if (isset($attr['blog_id'])) {
            $sign = '';
            if (substr($attr['blog_id'], 0, 1) == '!') {
                $sign            = '!';
                $attr['blog_id'] = substr($attr['blog_id'], 1);
            }
            $if[] = $sign . "(App::core()->blog()->id == '" . addslashes($attr['blog_id']) . "')";
        }

        if (isset($attr['comments_active'])) {
            $sign = (bool) $attr['comments_active'] ? '' : '!';
            $if[] = $sign . 'App::core()->blog()->settings()->get("system")->get("allow_comments")';
        }

        if (isset($attr['pings_active'])) {
            $sign = (bool) $attr['pings_active'] ? '' : '!';
            $if[] = $sign . 'App::core()->blog()->settings()->get("system")->get("allow_trackbacks")';
        }

        if (isset($attr['wiki_comments'])) {
            $sign = (bool) $attr['wiki_comments'] ? '' : '!';
            $if[] = $sign . 'App::core()->blog()->settings()->get("system")->get("wiki_comments")';
        }

        if (isset($attr['search_count']) && preg_match('/^((=|!|&gt;|&lt;)=|(&gt;|&lt;))\s*[0-9]+$/', trim($attr['search_count']))) {
            $if[] = '(App::core()->url()->search_string && App::core()->url()->search_count ' . Html::decodeEntities($attr['search_count']) . ')';
        }

        if (isset($attr['jquery_needed'])) {
            $sign = (bool) $attr['jquery_needed'] ? '' : '!';
            $if[] = $sign . 'App::core()->blog()->settings()->get("system")->get("jquery_needed")';
        }

        App::core()->behavior()->call('tplIfConditions', 'SysIf', $attr, $content, $if);

        if (count($if) != 0) {
            return self::$ton . 'if(' . implode(' ' . $operator . ' ', (array) $if) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:SysIfCommentPublished - - -- Container displayed if comment has been published -->
     */
    public function SysIfCommentPublished(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (!empty($_GET[\'pub\'])) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:SysIfCommentPending - - -- Container displayed if comment is pending after submission -->
     */
    public function SysIfCommentPending(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (isset($_GET[\'pub\']) && $_GET[\'pub\'] == 0) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:SysFeedSubtitle - O -- Feed subtitle -->
     */
    public function SysFeedSubtitle(ArrayObject $attr): string
    {
        return self::$ton . 'if (App::core()->context()->get("feed_subtitle") !== null) { echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("feed_subtitle")') . ';}' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:SysIfFormError - O -- Container displayed if an error has been detected after form submission -->
     */
    public function SysIfFormError(ArrayObject $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("form_error") !== null) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:SysFormError - O -- Form error -->
     */
    public function SysFormError(ArrayObject $attr): string
    {
        return self::$ton . 'if (App::core()->context()->get("form_error") !== null) { echo App::core()->context()->get("form_error"); }' . self::$toff;
    }

    public function SysPoweredBy(ArrayObject $attr): string
    {
        return self::$ton . 'printf(__("Powered by %s"),"<a href=\"https://dotclear.org/\">Dotclear</a>");' . self::$toff;
    }

    public function SysSearchString(ArrayObject $attr): string
    {
        $s = $attr['string'] ?? '%1$s';

        return self::$ton . 'if (App::core()->url()->search_string) { echo sprintf(__(\'' . $s . '\'),' . sprintf($this->getFilters($attr), 'App::core()->url()->search_string') . ',App::core()->url()->search_count);}' . self::$toff;
    }

    public function SysSelfURI(ArrayObject $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), '\Dotclear\Helper\Network\Http::getSelfURI()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:else - O -- else: statement -->
     */
    public function GenericElse(ArrayObject $attr): string
    {
        return self::$ton . 'else:' . self::$toff;
    }
}
