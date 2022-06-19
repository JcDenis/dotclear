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
use Dotclear\Exception\TemplateException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\Strings;

/**
 * Public template methods.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Public Template
 */
final class Template
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

    public $use_cache = true;

    private $blocks = [];
    private $values = [];

    private $remove_php = true;

    private $unknown_value_handler;
    private $unknown_block_handler;

    private $tpl_path = [];
    private $cache_dir;
    private $parent_file;

    private $compile_stack = [];
    private $parent_stack  = [];

    // Inclusion variables
    private static $superglobals = ['GLOBALS', '_SERVER', '_GET', '_POST', '_COOKIE', '_FILES', '_ENV', '_REQUEST', '_SESSION'];
    private static $_k;
    private static $_n;
    private static $_r;

    /**
     * Constructor.
     *
     * @param string $cache_dir Cache directory path
     * @param string $self_name Tempalte engine method name
     */
    public function __construct(string $cache_dir, private string $self_name)
    {
        $this->setCacheDir($cache_dir);

        $this->remove_php = !App::core()->blog()->settings()->getGroup('system')->getSetting('tpl_allow_php');
        $this->use_cache  = App::core()->blog()->settings()->getGroup('system')->getSetting('tpl_use_cache');

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
        $this->addValue('include', [$this, 'includeFile']);
        $this->addBlock('Block', [$this, 'blockSection']);
        $this->addValue('else', [$this, 'GenericElse']);
    }

    public function setPath(): void
    {
        $path = [];

        foreach (func_get_args() as $v) {
            if (is_array($v)) {
                $path = array_merge($path, array_values($v));
            } else {
                $path[] = $v;
            }
        }

        foreach ($path as $k => $v) {
            if (false === ($v = Path::real($v))) {
                unset($path[$k]);
            }
        }

        $this->tpl_path = array_unique($path);
    }

    public function getPath(): array
    {
        return $this->tpl_path;
    }

    public function setCacheDir(string $dir): void
    {
        $dir = Path::real($dir);

        if (!$dir || !is_dir($dir)) {
            throw new TemplateException($dir . ' is not a valid directory.');
        }

        if (!is_writable($dir)) {
            throw new TemplateException($dir . ' is not writable.');
        }

        $this->cache_dir = $dir . '/';
    }

    public function addBlock(string $name, callable $callback): void
    {
        $this->blocks[$name] = $callback;
    }

    public function addValue(string $name, callable $callback): void
    {
        $this->values[$name] = $callback;
    }

    public function blockExists(string $name): bool
    {
        return isset($this->blocks[$name]);
    }

    public function valueExists(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function tagExists(string $name): bool
    {
        return $this->blockExists($name) || $this->valueExists($name);
    }

    public function getValueCallback(string $name): false|callable
    {
        return $this->valueExists($name) ? $this->values[$name] : false;
    }

    public function getBlockCallback(string $name): false|callable
    {
        return $this->blockExists($name) ? $this->blocks[$name] : false;
    }

    public function getBlocksList(): array
    {
        return array_keys($this->blocks);
    }

    public function getValuesList(): array
    {
        return array_keys($this->values);
    }

    public function getFile(string $file): string
    {
        $tpl_file = $this->getFilePath($file);

        if (!$tpl_file) {
            throw new TemplateException('No template found for ' . $file);
        }

        $file_md5  = md5($tpl_file);
        $dest_file = sprintf(
            '%s/%s/%s/%s/%s.php',
            $this->cache_dir,
            'cbtpl',
            substr($file_md5, 0, 2),
            substr($file_md5, 2, 2),
            $file_md5
        );

        clearstatcache();
        $stat_f = $stat_d = false;
        if (file_exists($dest_file)) {
            $stat_f = stat($tpl_file);
            $stat_d = stat($dest_file);
        }

        // We create template if:
        // - dest_file doest not exists
        // - we don't want cache
        // - dest_file size == 0
        // - tpl_file is more recent thant dest_file
        if (!$stat_d || !$this->use_cache || 0 == $stat_d['size'] || $stat_f['mtime'] > $stat_d['mtime']) {
            Files::makeDir(dirname($dest_file), true);

            if (false === ($fp = @fopen($dest_file, 'wb'))) {
                throw new TemplateException('Unable to create cache file');
            }

            $fc = $this->compileFile($tpl_file);
            fwrite($fp, $fc);
            fclose($fp);
            Files::inheritChmod($dest_file);
        }

        return $dest_file;
    }

    public function getFilePath(string $file): false|string
    {
        foreach ($this->tpl_path as $p) {
            if (file_exists($p . '/' . $file)) {
                return $p . '/' . $file;
            }
        }

        return false;
    }

    public function getParentFilePath(string $previous_path, string $file): false|string
    {
        $check_file = false;
        foreach ($this->tpl_path as $p) {
            if ($check_file && file_exists($p . '/' . $file)) {
                return $p . '/' . $file;
            }
            if ($p == $previous_path) {
                $check_file = true;
            }
        }

        return false;
    }

    public function getData(string $________): string
    {
        // --BEHAVIOR-- tplBeforeData
        if (App::core()->behavior('tplBeforeData')->count()) {
            self::$_r = App::core()->behavior('tplBeforeData')->call();
            if (self::$_r) {
                return self::$_r;
            }
        }

        self::$_k = array_keys($GLOBALS);

        foreach (self::$_k as self::$_n) {
            if (!in_array(self::$_n, self::$superglobals)) {
                global ${self::$_n};
            }
        }
        $dest_file = $this->getFile($________);
        ob_start();
        if (ini_get('display_errors') == true) {
            include $dest_file;
        } else {
            @include $dest_file;
        }
        self::$_r = ob_get_contents();
        ob_end_clean();

        // --BEHAVIOR-- tplAfterData
        if (App::core()->behavior('tplAfterData')->count()) {
            App::core()->behavior('tplAfterData')->call(self::$_r);
        }

        return self::$_r;
    }

    private function getCompiledTree(string $file, &$err): TplNode
    {
        $fc = file_get_contents($file);

        $this->compile_stack[] = $file;

        // Remove every PHP tags
        if ($this->remove_php) {
            $fc = preg_replace('/<\?(?=php|=|\s).*?\?>/ms', '', $fc);
        }

        // Transform what could be considered as PHP short tags
        $fc = preg_replace(
            '/(<\?(?!php|=|\s))(.*?)(\?>)/ms',
            '<?php echo "$1"; ?>$2<?php echo "$3"; ?>',
            $fc
        );

        // Remove template comments <!-- #... -->
        $fc = preg_replace('/(^\s*)?<!-- #(.*?)-->/ms', '', $fc);

        // Lexer part : split file into small pieces
        // each array entry will be either a tag or plain text
        $blocks = preg_split(
            '#(<tpl:\w+[^>]*>)|(</tpl:\w+>)|({{tpl:\w+[^}]*}})#msu',
            $fc,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        // Next : build semantic tree from tokens.
        $rootNode          = new TplNode();
        $node              = $rootNode;
        $errors            = [];
        $this->parent_file = '';
        foreach ($blocks as $id => $block) {
            $isblock = preg_match('#<tpl:(\w+)(?:(\s+.*?)>|>)|</tpl:(\w+)>|{{tpl:(\w+)(\s(.*?))?}}#ms', $block, $match);
            if (1 == $isblock) {
                if (substr($match[0], 1, 1) == '/') {
                    // Closing tag, check if it matches current opened node
                    $tag = $match[3];
                    if (($node instanceof TplNodeBlock) && $node->getTag() == $tag) {
                        $node->setClosing();
                        $node = $node->getParent();
                    } else {
                        // Closing tag does not match opening tag
                        // Search if it closes a parent tag
                        $search = $node;
                        while ($search->getTag() != 'ROOT' && $search->getTag() != $tag) {
                            $search = $search->getParent();
                        }
                        if ($search->getTag() == $tag) {
                            $errors[] = sprintf(
                                __('Did not find closing tag for block <tpl:%s>. Content has been ignored.'),
                                Html::escapeHTML($node->getTag())
                            );
                            $search->setClosing();
                            $node = $search->getParent();
                        } else {
                            $errors[] = sprintf(
                                __('Unexpected closing tag </tpl:%s> found.'),
                                $tag
                            );
                        }
                    }
                } elseif (substr($match[0], 0, 1) == '{') {
                    // Value tag
                    $tag      = $match[4];
                    $str_attr = '';
                    $attr     = new TplAttr();
                    if (isset($match[6])) {
                        $str_attr = $match[6];
                        $attr     = new TplAttr($match[6]);
                    }
                    if (strtolower($tag) == 'extends') {
                        if ($attr->isset('parent') && '' == $this->parent_file) {
                            $this->parent_file = $attr->get('parent');
                        }
                    } elseif (strtolower($tag) == 'parent') {
                        $node->addChild(new TplNodeValueParent($tag, $attr, $str_attr));
                    } else {
                        $node->addChild(new TplNodeValue($tag, $attr, $str_attr));
                    }
                } else {
                    // Opening tag, create new node and dive into it
                    $tag = $match[1];
                    if ('Block' == $tag) {
                        $newnode = new TplNodeBlockDefinition($tag, new TplAttr($match[2] ?? ''));
                    } else {
                        $newnode = new TplNodeBlock($tag, new TplAttr($match[2] ?? ''));
                    }
                    $node->addChild($newnode);
                    $node = $newnode;
                }
            } else {
                // Simple text
                $node->addChild(new TplNodeText($block));
            }
        }

        if (($node instanceof TplNodeBlock) && !$node->isClosed()) {
            $errors[] = sprintf(
                __('Did not find closing tag for block <tpl:%s>. Content has been ignored.'),
                Html::escapeHTML($node->getTag())
            );
        }

        $err = '';
        if (count($errors) > 0) {
            $err = "\n\n<!-- \n" .
            __('WARNING: the following errors have been found while parsing template file :') .
            "\n * " .
            join("\n * ", $errors) .
                "\n -->\n";
        }

        return $rootNode;
    }

    private function getCompileFileHeader($file)
    {
        $class = new Strings();
        $class->add('Dotclear\App');
        $class->add('Dotclear\Database\Param');
        $class->add('Dotclear\Helper\GPC\GPC');

        // --BEHAVIOR-- templateBeforeFile
        App::core()->behavior('templateBeforeFile')->call($file, $class);

        return self::$ton . "\n" . 'use ' . implode("; \n use ", $class->dump()) . "; \n" . self::$toff . "\n";
    }

    private function compileFile(string $file): string
    {
        $head = $this->getCompileFileHeader($file);
        $tree = null;
        $err  = '';
        while (true) {
            if ($file && !in_array($file, $this->parent_stack)) {
                $tree = $this->getCompiledTree($file, $err);

                if ('__parent__' == $this->parent_file) {
                    $this->parent_stack[] = $file;
                    $newfile              = $this->getParentFilePath(dirname($file), basename($file));
                    if (!$newfile) {
                        throw new TemplateException('No template found for ' . basename($file));
                    }
                    $file = $newfile;
                } elseif ('' != $this->parent_file) {
                    $this->parent_stack[] = $file;
                    $file                 = $this->getFilePath($this->parent_file);
                    if (!$file) {
                        throw new TemplateException('No template found for ' . $this->parent_file);
                    }
                } else {
                    return $head . $tree->compile($this) . $err;
                }
            } else {
                if (null != $tree) {
                    return $head . $tree->compile($this) . $err;
                }

                return '';
            }
        }
    }

    public function compileBlockNode(string $tag, TplAttr $attr, string $content): string
    {
        $this->current_tag = $tag;

        // --BEHAVIOR-- templateBeforeBlock
        $res = App::core()->behavior('templateBeforeBlock')->call($this->current_tag, $attr);

        // --BEHAVIOR-- templateInsideBlock
        App::core()->behavior('templateInsideBlock')->call($this->current_tag, $attr, [&$content]);

        if (isset($this->blocks[$this->current_tag])) {
            $res .= call_user_func($this->blocks[$this->current_tag], $attr, $content);
        } elseif (null != $this->unknown_block_handler) {
            $res .= call_user_func($this->unknown_block_handler, $this->current_tag, $attr, $content);
        }

        // --BEHAVIOR-- templateAfterBlock
        $res .= App::core()->behavior('templateAfterBlock')->call($this->current_tag, $attr);

        return $res;
    }

    public function compileValueNode(string $tag, TplAttr $attr, string $str_attr): string
    {
        $this->current_tag = $tag;

        // --BEHAVIOR-- templateBeforeValue
        $res = App::core()->behavior('templateBeforeValue')->call($this->current_tag, $attr);

        if (isset($this->values[$this->current_tag])) {
            $res .= call_user_func($this->values[$this->current_tag], $attr, ltrim((string) $str_attr));
        } elseif (null != $this->unknown_value_handler) {
            $res .= call_user_func($this->unknown_value_handler, $this->current_tag, $attr, $str_attr);
        }

        // --BEHAVIOR-- templateAfterValue
        $res .= App::core()->behavior('templateAfterValue')->call($this->current_tag, $attr);

        return $res;
    }

    public function setUnknownValueHandler(callable $callback): void
    {
        $this->unknown_value_handler = $callback;
    }

    public function setUnknownBlockHandler(callable $callback): void
    {
        $this->unknown_block_handler = $callback;
    }

    public function includeFile(TplAttr $attr): string
    {
        if (!$attr->isset('src')) {
            return '';
        }

        $src = Path::clean($attr->get('src'));

        $tpl_file = $this->getFilePath($src);
        if (!$tpl_file) {
            return '';
        }
        if (in_array($tpl_file, $this->compile_stack)) {
            return '';
        }

        return
        self::$ton . 'try { ' .
        'echo ' . $this->self_name . "->getData('" . str_replace("'", "\\'", $src) . "'); " .
            '} catch (\Exception) {}' . self::$toff . "\n";
    }

    public function blockSection(TplAttr $attr, string $content): string
    {
        return $content;
    }

    public function getFilters(TplAttr $attr, array $default = []): string
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

        foreach ($attr->dump() as $k => $v) {
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

    public function getSortByStr(TplAttr $attr, string $table = null): string
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

        /** @var ArrayObject<string, array> */
        $alias = new ArrayObject();

        // --BEHAVIOR-- templateCustomSortByAlias
        App::core()->behavior('templateCustomSortByAlias')->call($alias);

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

        if ($attr->isset('order') && preg_match('/^(desc|asc)$/i', $attr->get('order'))) {
            $default_order = $attr->get('order');
        }
        if ($attr->isset('sortby')) {
            $sorts = explode(',', $attr->get('sortby'));
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

    public function getAge(TplAttr $attr): string
    {
        if ($attr->isset('age') && preg_match('/^(\-[0-9]+|last).*$/i', $attr->get('age'))) {
            if ('' != ($ts = Clock::ts(date: $attr->get('age')))) {
                return Clock::str(format: '%Y-%m-%d %H:%m:%S', date: $ts);
            }
        }

        return '';
    }

    public function displayCounter(string $variable, array $values, TplAttr $attr, bool $count_only_by_default = false): string
    {
        $count_only = $attr->isset('count_only') ? (1 == $attr->get('count_only')) : $count_only_by_default;
        if ($count_only) {
            return self::$ton . 'echo ' . $variable . ';' . self::$toff;
        }

        $v = $values;
        if ($attr->isset('none')) {
            $v['none'] = addslashes($attr->get('none'));
        }
        if ($attr->isset('one')) {
            $v['one'] = addslashes($attr->get('one'));
        }
        if ($attr->isset('more')) {
            $v['more'] = addslashes($attr->get('more'));
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

    public function l10n(TplAttr $attr, string $str_attr): string
    {
        // Normalize content
        $str_attr = preg_replace('/\s+/x', ' ', $str_attr);

        return self::$ton . "echo __('" . str_replace("'", "\\'", $str_attr) . "');" . self::$toff;
    }

    public function LoopPosition(TplAttr $attr, string $content): string
    {
        $start  = $attr->isset('start') ? (int) $attr->get('start') : '0';
        $length = $attr->isset('length') ? (int) $attr->get('length') : 'null';
        $even   = $attr->isset('even') ? (int) (bool) $attr->get('even') : 'null';
        $modulo = $attr->isset('modulo') ? (int) $attr->get('modulo') : 'null';

        if (0 < $start) {
            --$start;
        }

        return
            self::$ton . 'if (App::core()->context()->loopPosition(' . $start . ',' . $length . ',' . $even . ',' . $modulo . ')) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    public function LoopIndex(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), '(!App::core()->context()->get("cur_loop") ? 0 : App::core()->context()->get("cur_loop")->index() + 1)') . ';' . self::$toff;
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
    public function Archives(TplAttr $attr, string $content): string
    {
        $p = 'if (!isset($param)) { $param = new Param(); }' . "\n";
        $p .= "\$param->set('type', 'month');\n";
        if ($attr->isset('type')) {
            $p .= "\$param->set('type', '" . addslashes($attr->get('type')) . "');\n";
        }

        if ($attr->isset('category')) {
            $p .= "\$param->set('cat_url', '" . addslashes($attr->get('category')) . "');\n";
        }

        if ($attr->isset('post_type')) {
            $p .= "\$param->set('post_type', '" . addslashes($attr->get('post_type')) . "');\n";
        }

        if ($attr->isset('post_lang')) {
            $p .= "\$param->set('post_lang', '" . addslashes($attr->get('post_lang')) . "');\n";
        }

        if ($attr->empty('no_context') && !$attr->isset('category')) {
            $p .= 'if (App::core()->context()->exists("categories")) { ' .
                "\$param->set('cat_id', App::core()->context()->get('categories')->integer('cat_id')); " .
                "}\n";
        }

        $order = 'desc';
        if ($attr->isset('order') && preg_match('/^(desc|asc)$/i', $attr->get('order'))) {
            $p .= "\$param->set('order', '" . $attr->get('order') . "');\n ";
        }

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'Archives', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("archives", App::core()->blog()->posts()->getDates(param: $param)); unset($param);' . "\n";
        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("archives")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("archives", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesHeader - - -- First archives result container -->
     */
    public function ArchivesHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("archives")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesFooter - - -- Last archives result container -->
     */
    public function ArchivesFooter(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("archives")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesYearHeader - - -- First result of year in archives container -->
     */
    public function ArchivesYearHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("archives")->yearHeader()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchivesYearFooter - - -- Last result of year in archives container -->
     */
    public function ArchivesYearFooter(TplAttr $attr, string $content): string
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
    public function ArchiveDate(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), "App::core()->context()->get('archives')->getDate('" . ($attr->empty('format') ? '%B %Y' : addslashes($attr->get('format'))) . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:ArchiveEntriesCount - O -- Current archive result number of entries -->
     */
    public function ArchiveEntriesCount(TplAttr $attr): string
    {
        return $this->displayCounter(
            sprintf($this->getFilters($attr), 'App::core()->context()->get("archives")->integer("nb_post")'),
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
    public function ArchiveNext(TplAttr $attr, string $content): string
    {
        $p = 'if (!isset($param)) { $param = new Param(); }' . "\n";
        $p .= "\$param->set('type', 'month');\n";
        if ($attr->isset('type')) {
            $p .= "\$param->set('type', '" . addslashes($attr->get('type')) . "');\n";
        }

        if ($attr->isset('post_type')) {
            $p .= "\$param->set('post_type', '" . addslashes($attr->get('post_type')) . "');\n";
        }

        if ($attr->isset('post_lang')) {
            $p .= "\$param->set('post_lang', '" . addslashes($attr->get('post_lang')) . "');\n";
        }

        $p .= "\$param->set('next', App::core()->context()->get('archives')->field('dt'));";

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'ArchiveNext', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("archives", App::core()->blog()->posts()->getDates(param: $param)); unset($param);' . "\n";
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
    public function ArchivePrevious(TplAttr $attr, string $content): string
    {
        $p = 'if (!isset($param)) { $param = new Param(); }' . "\n";
        $p .= "\$param->set('type', 'month');\n";
        if ($attr->isset('type')) {
            $p .= "\$param->set('type', '" . addslashes($attr->get('type')) . "');\n";
        }

        if ($attr->isset('post_type')) {
            $p .= "\$param->set('post_type', '" . addslashes($attr->get('post_type')) . "');\n";
        }

        if ($attr->isset('post_lang')) {
            $p .= "\$param->set('post_lang', '" . addslashes($attr->get('post_lang')) . "');\n";
        }

        $p .= "\$param->set('previous', App::core()->context()->get('archives')->field('dt'));";

        $res = self::$ton . "\n";
        $res .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'ArchivePrevious', 'method' => 'blog::getDates'],
            $attr,
            $content
        );
        $res .= $p;
        $res .= 'App::core()->context()->set("archives", App::core()->blog()->posts()->getDates(param: $param)); unset($param);' . "\n";
        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("archives")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("archives", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:ArchiveURL - O -- Current archive result URL -->
     */
    public function ArchiveURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("archives")->call("url")') . ';' . self::$toff;
    }

    // Blog -----------------------------------------------
    /*dtd
    <!ELEMENT tpl:BlogArchiveURL - O -- Blog Archives URL -->
     */
    public function BlogArchiveURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("archive")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogCopyrightNotice - O -- Blog copyrght notices -->
     */
    public function BlogCopyrightNotice(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->getGroup("system")->getSetting("copyright_notice")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogDescription - O -- Blog Description -->
     */
    public function BlogDescription(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->desc') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogEditor - O -- Blog Editor -->
     */
    public function BlogEditor(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->getGroup("system")->getSetting("editor")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogFeedID - O -- Blog Feed ID -->
     */
    public function BlogFeedID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), '"urn:md5:".App::core()->blog()->uid') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogFeedURL - O -- Blog Feed URL -->
    <!ATTLIST tpl:BlogFeedURL
    type    (rss2|atom)    #IMPLIED    -- feed type (default : rss2)
    >
     */
    public function BlogFeedURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("feed","' . ('rss2' == $attr->get('type') ? 'rss2' : 'atom') . '")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogName - O -- Blog Name -->
     */
    public function BlogName(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->name') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogLanguage - O -- Blog Language -->
     */
    public function BlogLanguage(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->getGroup("system")->getSetting("lang")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogLanguageURL - O -- Blog Localized URL -->
     */
    public function BlogLanguageURL(TplAttr $attr): string
    {
        $f = $this->getFilters($attr);

        return self::$ton . 'if (App::core()->context()->exists("cur_lang")) echo ' . sprintf($f, 'App::core()->blog()->getURLFor("lang",' .
            'App::core()->context()->cur_lang)') . ';
            else echo ' . sprintf($f, 'App::core()->blog()->url') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogThemeURL - O -- Blog's current Theme URL -->
     */
    public function BlogThemeURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("resources") . "/"') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogParentThemeURL - O -- Blog's current Theme's parent URL -->
     */
    public function BlogParentThemeURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("resources") . "/"') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogPublicURL - O -- Blog Public directory URL -->
     */
    public function BlogPublicURL(TplAttr $attr): string
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
    public function BlogUpdateDate(TplAttr $attr): string
    {
        $f = $this->getFilters($attr);

        if (!$attr->empty('rfc822')) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->blog()->getUpdateDate('rfc822')") . ';' . self::$toff;
        }
        if (!$attr->empty('iso8601')) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->blog()->getUpdateDate('iso8601')") . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf($f, "App::core()->blog()->getUpdateDate('" . ($attr->empty('format') ? '%Y-%m-%d %H:%M:%S' : addslashes($attr->get('format'))) . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogID - 0 -- Blog ID -->
     */
    public function BlogID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->id') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogRSDURL - O -- Blog RSD URL -->
     */
    public function BlogRSDURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor(\'rsd\')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogXMLRPCURL - O -- Blog XML-RPC URL -->
     */
    public function BlogXMLRPCURL(TplAttr $attr): string
    {
        $f = $this->getFilters($attr);

        return self::$ton . 'echo ' . sprintf($f, 'App::core()->blog()->getURLFor(\'xmlrpc\',App::core()->blog()->id)') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogURL - O -- Blog URL -->
     */
    public function BlogURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->url') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogQmarkURL - O -- Blog URL, ending with a question mark -->
     */
    public function BlogQmarkURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getQmarkURL()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogMetaRobots - O -- Blog meta robots tag definition, overrides robots_policy setting -->
    <!ATTLIST tpl:BlogMetaRobots
    robots    CDATA    #IMPLIED    -- can be INDEX,FOLLOW,NOINDEX,NOFOLLOW,ARCHIVE,NOARCHIVE
    >
     */
    public function BlogMetaRobots(TplAttr $attr): string
    {
        return self::$ton . "echo App::core()->context()->robotsPolicy(App::core()->blog()->settings()->getGroup('system')->getSetting('robots_policy'),'" . addslashes($attr->get('robots')) . "');" . self::$toff;
    }

    /*dtd
    <!ELEMENT gpl:BlogJsJQuery - 0 -- Blog Js jQuery version selected -->
     */
    public function BlogJsJQuery(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getJsJQuery()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogPostsURL - O -- Blog Posts URL -->
     */
    public function BlogPostsURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), ('App::core()->blog()->settings()->getGroup("system")->getSetting("static_home") ? App::core()->blog()->getURLFor("posts") : App::core()->blog()->url')) . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:IfBlogStaticEntryURL - O -- Test if Blog has a static home entry URL -->
     */
    public function IfBlogStaticEntryURL(TplAttr $attr, string $content): string
    {
        return
            self::$ton . "if (App::core()->blog()->settings()->getGroup('system')->getSetting('static_home_url') != '') :" . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogStaticEntryURL - O -- Set Blog static home entry URL -->
     */
    public function BlogStaticEntryURL(TplAttr $attr): string
    {
        return self::$ton . "\n" .
            "\$params['post_type'] = App::core()->posttype()->listItems();\n" .
            "\$params['post_url'] = " . sprintf($this->getFilters($attr), 'urldecode(App::core()->blog()->settings()->getGroup("system")->getSetting("static_home_url"))') . ";\n" .
            self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogNbEntriesFirstPage - O -- Number of entries for 1st page -->
     */
    public function BlogNbEntriesFirstPage(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->getGroup("system")->getSetting("nb_post_for_home")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:BlogNbEntriesPerPage - O -- Number of entries per page -->
     */
    public function BlogNbEntriesPerPage(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->settings()->getGroup("system")->getSetting("nb_post_per_page")') . ';' . self::$toff;
    }

    // Categories -----------------------------------------

    /*dtd
    <!ELEMENT tpl:Categories - - -- Categories loop -->
     */
    public function Categories(TplAttr $attr, string $content): string
    {
        $p = 'if (!isset($param)) $param = new Param();' . "\n";

        if ($attr->isset('url')) {
            $p .= "\$param->set('cat_url', '" . addslashes($attr->get('url')) . "');\n";
        }

        if (!$attr->empty('post_type')) {
            $p .= "\$param->set('post_type', '" . addslashes($attr->get('post_type')) . "');\n";
        }

        if (!$attr->empty('level')) {
            $p .= "\$param->set('level', " . (int) $attr->get('level') . ");\n";
        }

        if ($attr->isset('with_empty') && ((bool) $attr->get('with_empty') == true)) {
            $p .= '$param->set(\'without_empty\', false);';
        }

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'Categories', 'method' => 'blog()->categories()->getCategories'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("categories", App::core()->blog()->categories()->getCategories(param: $param));' . "\n";
        $res .= "?>\n";
        $res .= self::$ton . 'while (App::core()->context()->get("categories")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("categories", null); unset($params);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:CategoriesHeader - - -- First Categories result container -->
     */
    public function CategoriesHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("categories")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoriesFooter - - -- Last Categories result container -->
     */
    public function CategoriesFooter(TplAttr $attr, string $content): string
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
    public function CategoryIf(TplAttr $attr, string $content): string
    {
        $if = new Strings();

        if ($attr->isset('url')) {
            $url  = addslashes(trim($attr->get('url')));
            $args = preg_split('/\s*[?]\s*/', $url, -1, PREG_SPLIT_NO_EMPTY);
            $url  = array_shift($args);
            $args = array_flip($args);
            if (substr($url, 0, 1) == '!') {
                $url = substr($url, 1);
                $if->add(
                    isset($args['sub']) ?
                    '(!App::core()->blog()->categories()->isInCatSubtree(url: App::core()->context()->get("categories")->field("cat_url"), "parent: ' . $url . '"))' :
                    '(App::core()->context()->get("categories")->field("cat_url") != "' . $url . '")'
                );
            } else {
                $if->add(
                    isset($args['sub']) ?
                    '(App::core()->blog()->categories()->isInCatSubtree(url: App::core()->context()->get("categories")->field("cat_url"), "parent: ' . $url . '"))' :
                    '(App::core()->context()->get("categories")->field("cat_url") == "' . $url . '")'
                );
            }
        }

        if ($attr->isset('urls')) {
            $urls = explode(',', addslashes(trim($attr->get('urls'))));
            if (is_array($urls)) {
                foreach ($urls as $url) {
                    $args = preg_split('/\s*[?]\s*/', trim($url), -1, PREG_SPLIT_NO_EMPTY);
                    $url  = array_shift($args);
                    $args = array_flip($args);
                    if (substr($url, 0, 1) == '!') {
                        $url = substr($url, 1);
                        $if->add(
                            isset($args['sub']) ?
                            '(!App::core()->blog()->categories()->isInCatSubtree(url: App::core()->context()->get("categories")->field("cat_url"), "parent: ' . $url . '"))' :
                            '(App::core()->context()->get("categories")->field("cat_url") != "' . $url . '")'
                        );
                    } else {
                        $if->add(
                            isset($args['sub']) ?
                            '(App::core()->blog()->categories()->isInCatSubtree(url: App::core()->context()->get("categories")->field("cat_url"), "parent: ' . $url . '"))' :
                            '(App::core()->context()->get("categories")->field("cat_url") == "' . $url . '")'
                        );
                    }
                }
            }
        }

        if ($attr->isset('has_entries')) {
            $if->add('App::core()->context()->get("categories")->integer("nb_post") ' . ((bool) $attr->get('has_entries') ? '>' : '==') . ' 0');
        }

        if ($attr->isset('has_description')) {
            $if->add('App::core()->context()->get("categories")->field("cat_desc") ' . ((bool) $attr->get('has_description') ? '!=' : '==') . ' ""');
        }

        App::core()->behavior('tplIfConditions')->call('CategoryIf', $attr, $content, $if);

        if ($if->count()) {
            return self::$ton . 'if(' . implode(' ' . $this->getOperator($attr->get('operator')) . ' ', $if->dump()) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:CategoryFirstChildren - - -- Current category first children loop -->
     */
    public function CategoryFirstChildren(TplAttr $attr, string $content): string
    {
        return
            self::$ton . "\n" .
            'App::core()->context()->set("categories", App::core()->blog()->categories()->getCategoryFirstChildren(id: App::core()->context()->get("categories")->integer("cat_id")));' . "\n" .
            'while (App::core()->context()->get("categories")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("categories", null);' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryParents - - -- Current category parents loop -->
     */
    public function CategoryParents(TplAttr $attr, string $content): string
    {
        return
            self::$ton . "\n" .
            'App::core()->context()->set("categories", App::core()->blog()->categories()->getCategoryParents(id: App::core()->context()->get("categories")->integer("cat_id")));' . "\n" .
            'while (App::core()->context()->get("categories")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("categories", null);' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryFeedURL - O -- Category feed URL -->
    <!ATTLIST tpl:CategoryFeedURL
    type    (rss2|atom)    #IMPLIED    -- feed type (default : rss2)
    >
     */
    public function CategoryFeedURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("feed","category/".' .
            'App::core()->context()->get("categories")->field("cat_url")."/' . ('rss2' == $attr->get('type') ? 'rss2' : 'atom') . '")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryID - O -- Category ID -->
     */
    public function CategoryID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->integer("cat_id")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryURL - O -- Category URL (complete iabsolute URL, including blog URL) -->
     */
    public function CategoryURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("category",' .
            'App::core()->context()->get("categories")->field("cat_url"))') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryShortURL - O -- Category short URL (relative URL, from /category/) -->
     */
    public function CategoryShortURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->field("cat_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryDescription - O -- Category description -->
     */
    public function CategoryDescription(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->field("cat_desc")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryTitle - O -- Category title -->
     */
    public function CategoryTitle(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->field("cat_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CategoryEntriesCount - O -- Category number of entries -->
     */
    public function CategoryEntriesCount(TplAttr $attr): string
    {
        return $this->displayCounter(
            sprintf($this->getFilters($attr), 'App::core()->context()->get("categories")->integer("nb_post")'),
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
    public function Entries(TplAttr $attr, string $content): string
    {
        $lastn = -1;
        if ($attr->isset('lastn')) {
            $lastn = abs((int) $attr->get('lastn')) + 0;
        }

        $p = '$_page_number = App::core()->context()->page_number(); if (!$_page_number) { $_page_number = 1; }' . "\n";
        $p .= 'if (!isset($param)) { $param = new Param(); }' . "\n";

        if (0 != $lastn) {
            // Set limit (aka nb of entries needed)
            if (0 < $lastn) {
                // nb of entries per page specified in template -> regular pagination
                $p .= "\$param->set('limit', " . $lastn . ");\n";
                $p .= '$nb_entry_first_page = $nb_entry_per_page = ' . $lastn . ";\n";
            } else {
                // nb of entries per page not specified -> use ctx settings
                $p .= "\$nb_entry_first_page=App::core()->context()->get('nb_entry_first_page'); \$nb_entry_per_page = App::core()->context()->get('nb_entry_per_page');\n";
                $p .= "if (in_array(App::core()->url()->getCurrentType(), ['default', 'default-page'])) {\n";
                $p .= "    \$param->set('limit', (\$_page_number == 1 ? \$nb_entry_first_page : \$nb_entry_per_page));\n";
                $p .= "} else {\n";
                $p .= "    \$param->set('limit', \$nb_entry_per_page);\n";
                $p .= "}\n";
            }
            // Set offset (aka index of first entry)
            if (!$attr->get('ignore_pagination') || '0' == $attr->get('ignore_pagination')) {
                // standard pagination, set offset
                $p .= "if (in_array(App::core()->url()->getCurrentType(), ['default', 'default-page'])) {\n";
                $p .= "    \$param->set('limit', [(\$_page_number == 1 ? 0 : (\$_page_number - 2) * \$nb_entry_per_page + \$nb_entry_first_page),\$param->get('limit')]);\n";
                $p .= "} else {\n";
                $p .= "    \$param->set('limit', [(\$_page_number - 1) * \$nb_entry_per_page,\$param->get('limit')]);\n";
                $p .= "}\n";
            } else {
                // no pagination, get all posts from 0 to limit
                $p .= "\$param->set('limit', [0, \$param->get('limit')]);\n";
            }
        }

        if ($attr->isset('author')) {
            $p .= "\$param->set('user_id', '" . addslashes($attr->get('author')) . "');\n";
        }

        if ($attr->isset('category')) {
            $p .= "\$param->set('cat_url', '" . addslashes($attr->get('category')) . "');\n";
            $p .= "App::core()->context()->categoryPostParam(\$param);\n";
        }

        if ($attr->isset('with_category') && $attr->get('with_category')) {
            $p .= "\$param->push('sql', ' AND P.cat_id IS NOT NULL ');\n";
        }

        if ($attr->isset('no_category') && $attr->get('no_category')) {
            $p .= "\$param->push('sql', ' AND P.cat_id IS NULL ');\n";
            $p .= "\$param->unset('cat_url');\n";
        }

        if (!$attr->empty('type')) {
            $p .= "\$param->set('post_type', preg_split('/\\s*,\\s*/','" . addslashes($attr->get('type')) . "',-1,PREG_SPLIT_NO_EMPTY));\n";
        }

        if (!$attr->empty('url')) {
            $p .= "\$param->set('post_url', '" . addslashes($attr->get('url')) . "');\n";
        }

        if ($attr->empty('no_context')) {
            if (!$attr->isset('author')) {
                $p .= 'if (App::core()->context()->exists("users")) { ' .
                    "\$param->set('user_id', App::core()->context()->get('users')->field('user_id')); " .
                    "}\n";
            }

            if (!$attr->isset('category') && (!$attr->isset('no_category') || !$attr->get('no_category'))) {
                $p .= 'if (App::core()->context()->exists("categories")) { ' .
                    "\$param->set('cat_id', App::core()->context()->get('categories')->integer('cat_id').(App::core()->blog()->settings()->getGroup('system')->getSetting('inc_subcats')?' ?sub':''));" .
                    "}\n";
            }

            $p .= 'if (App::core()->context()->exists("archives")) { ' .
                "\$param->set('post_year', App::core()->context()->get('archives')->year()); " .
                "\$param->set('post_month', App::core()->context()->get('archives')->month()); ";
            if (!$attr->isset('lastn')) {
                $p .= "\$param->unset('limit'); ";
            }
            $p .= "}\n";

            $p .= 'if (App::core()->context()->exists("langs")) { ' .
                "\$param->set('post_lang', App::core()->context()->get('langs')->field('post_lang')); " .
                "}\n";

            $p .= 'if (App::core()->url()->getSearchString()) { ' .
                "\$param->set('search', App::core()->url()->getSearchString()); " .
                "}\n";
        }

        $p .= "\$param->set('order', '" . $this->getSortByStr($attr, 'post') . "');\n";

        if ($attr->isset('no_content') && $attr->get('no_content')) {
            $p .= "\$param->set('no_content', true);\n";
        }

        if ($attr->isset('selected')) {
            $p .= "\$param->set('post_selected', " . (((bool) $attr->get('selected')) ? 'true' : 'false') . ');';
        }

        if ($attr->isset('age')) {
            $age = $this->getAge($attr);
            $p .= !empty($age) ? "\$param->push('sql', ' AND P.post_dt > \\'" . $age . "\\'');\n" : '';
        }

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'Entries', 'method' => 'blog()->posts()->getPosts'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("post_param", $param);' . "\n";
        $res .= 'App::core()->context()->set("posts", App::core()->blog()->posts()->getPosts(param: $param)); unset($param);' . "\n";
        $res .= "?>\n";
        $res .= self::$ton . 'while (App::core()->context()->get("posts")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; ' .
            'App::core()->context()->set("posts", null); App::core()->context()->set("post_param", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:DateHeader - O -- Displays date, if post is the first post of the given day -->
     */
    public function DateHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("posts")->firstPostOfDay()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:DateFooter - O -- Displays date,  if post is the last post of the given day -->
     */
    public function DateFooter(TplAttr $attr, string $content): string
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
    public function EntryIf(TplAttr $attr, string $content): string
    {
        $if = new Strings();

        if ($attr->isset('type')) {
            $type = trim($attr->get('type'));
            $type = !empty($type) ? $type : 'post';
            $if->add('App::core()->context()->get("posts")->field("post_type") == "' . addslashes($type) . '"');
        }

        if ($attr->isset('url')) {
            $url = trim($attr->get('url'));
            $if->add(
                substr($url, 0, 1) == '!' ?
                'App::core()->context()->get("posts")->field("post_url") != "' . addslashes(substr($url, 1)) . '"' :
                'App::core()->context()->get("posts")->field("post_url") == "' . addslashes($url) . '"'
            );
        }

        if ($attr->isset('category')) {
            $category = addslashes(trim($attr->get('category')));
            $args     = preg_split('/\s*[?]\s*/', $category, -1, PREG_SPLIT_NO_EMPTY);
            $category = array_shift($args);
            $args     = array_flip($args);
            if (substr($category, 0, 1) == '!') {
                $category = substr($category, 1);
                $if->add(
                    isset($args['sub']) ?
                    '(!App::core()->context()->get("posts")->underCat("' . $category . '"))' :
                    '(App::core()->context()->get("posts")->field("cat_url") != "' . $category . '")'
                );
            } else {
                $if->add(
                    isset($args['sub']) ?
                    '(App::core()->context()->get("posts")->underCat("' . $category . '"))' :
                    '(App::core()->context()->get("posts")->field("cat_url") == "' . $category . '")'
                );
            }
        }

        if ($attr->isset('categories')) {
            $categories = explode(',', addslashes(trim($attr->get('categories'))));
            if (is_array($categories)) {
                foreach ($categories as $category) {
                    $args     = preg_split('/\s*[?]\s*/', trim($category), -1, PREG_SPLIT_NO_EMPTY);
                    $category = array_shift($args);
                    $args     = array_flip($args);
                    if (substr($category, 0, 1) == '!') {
                        $category = substr($category, 1);
                        $if->add(
                            isset($args['sub']) ?
                            '(!App::core()->context()->get("posts")->underCat("' . $category . '"))' :
                            '(App::core()->context()->get("posts")->field("cat_url") != "' . $category . '")'
                        );
                    } else {
                        $if->add(
                            isset($args['sub']) ?
                            '(App::core()->context()->get("posts")->underCat("' . $category . '"))' :
                            '(App::core()->context()->get("posts")->field("cat_url") == "' . $category . '")'
                        );
                    }
                }
            }
        }

        if ($attr->isset('first')) {
            $if->add('App::core()->context()->get("posts")->index() ' . ((bool) $attr->get('first') ? '=' : '!') . '= 0');
        }

        if ($attr->isset('odd')) {
            $if->add('(App::core()->context()->get("posts")->index()+1)%2 ' . ((bool) $attr->get('odd') ? '=' : '!') . '= 1');
        }

        if ($attr->isset('extended')) {
            $if->add(((bool) $attr->get('extended') ? '' : '!') . 'App::core()->context()->get("posts")->isExtended()');
        }

        if ($attr->isset('selected')) {
            $if->add(((bool) $attr->get('selected') ? '' : '!') . '(bool)App::core()->context()->get("posts")->integer("post_selected")');
        }

        if ($attr->isset('has_category')) {
            $if->add(((bool) $attr->get('has_category') ? '' : '!') . 'App::core()->context()->get("posts")->integer("cat_id")');
        }

        if ($attr->isset('comments_active')) {
            $if->add(((bool) $attr->get('comments_active') ? '' : '!') . 'App::core()->context()->get("posts")->commentsActive()');
        }

        if ($attr->isset('pings_active')) {
            $if->add(((bool) $attr->get('pings_active') ? '' : '!') . 'App::core()->context()->get("posts")->trackbacksActive()');
        }

        if ($attr->isset('has_comment')) {
            $if->add(((bool) $attr->get('has_comment') ? '' : '!') . 'App::core()->context()->get("posts")->hasComments()');
        }

        if ($attr->isset('has_ping')) {
            $if->add(((bool) $attr->get('has_ping') ? '' : '!') . 'App::core()->context()->get("posts")->hasTrackbacks()');
        }

        if ($attr->isset('show_comments')) {
            $if->add(
                ((bool) $attr->get('show_comments')) ?
                '(App::core()->context()->get("posts")->hasComments() || App::core()->context()->get("posts")->commentsActive())' :
                '(!App::core()->context()->get("posts")->hasComments() && !App::core()->context()->get("posts")->commentsActive())'
            );
        }

        if ($attr->isset('show_pings')) {
            $if->add(
                ((bool) $attr->get('show_pings')) ?
                '(App::core()->context()->get("posts")->hasTrackbacks() || App::core()->context()->get("posts")->trackbacksActive())' :
                '(!App::core()->context()->get("posts")->hasTrackbacks() && !App::core()->context()->get("posts")->trackbacksActive())'
            );
        }

        if ($attr->isset('republished')) {
            $if->add(((bool) $attr->get('republished') ? '' : '!') . '(bool)App::core()->context()->get("posts")->isRepublished()');
        }

        if ($attr->isset('author')) {
            $author = trim($attr->get('author'));
            $if->add(
                substr($author, 0, 1) == '!' ?
                'App::core()->context()->get("posts")->field("user_id") != "' . substr($author, 1) . '"' :
                'App::core()->context()->get("posts")->field("user_id") == "' . $author . '"'
            );
        }

        App::core()->behavior('tplIfConditions')->call('EntryIf', $attr, $content, $if);

        if ($if->count()) {
            return self::$ton . 'if(' . implode(' ' . $this->getOperator($attr->get('operator')) . ' ', $if->dump()) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:EntryIfFirst - O -- displays value if entry is the first one -->
    <!ATTLIST tpl:EntryIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function EntryIfFirst(TplAttr $attr): string
    {
        return
        self::$ton . 'if (App::core()->context()->get("posts")->index() == 0) { ' .
        "echo '" . addslashes(Html::escapeHTML($attr->get('return') ?: 'first')) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryIfOdd - O -- displays value if entry is in an odd position -->
    <!ATTLIST tpl:EntryIfOdd
    return    CDATA    #IMPLIED    -- value to display in case of success (default: odd)
    even      CDATA    #IMPLIED    -- value to display in case of failure (default: <empty>)
    >
     */
    public function EntryIfOdd(TplAttr $attr): string
    {
        return self::$ton . 'echo ((App::core()->context()->get("posts")->index()+1)%2 ? ' .
        '"' . addslashes(Html::escapeHTML($attr->get('return') ?: 'odd')) . '" : ' .
        '"' . addslashes(Html::escapeHTML($attr->get('even') ?: '')) . '");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryIfSelected - O -- displays value if entry is selected -->
    <!ATTLIST tpl:EntryIfSelected
    return    CDATA    #IMPLIED    -- value to display in case of success (default: selected)
    >
     */
    public function EntryIfSelected(TplAttr $attr): string
    {
        return
        self::$ton . 'if (App::core()->context()->get("posts")->integer("post_selected")) { ' .
        "echo '" . addslashes(Html::escapeHTML($attr->get('return') ?: 'selected')) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryContent -  -- Entry content -->
    <!ATTLIST tpl:EntryContent
    absolute_urls    CDATA    #IMPLIED -- transforms local URLs to absolute one
    full            (1|0)    #IMPLIED -- returns full content with excerpt
    >
     */
    public function EntryContent(TplAttr $attr): string
    {
        $urls = $attr->empty('absolute_urls') ? '0' : '1';

        $f = $this->getFilters($attr);

        if (!$attr->empty('full')) {
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
    public function EntryIfContentCut(TplAttr $attr, string $content): string
    {
        if ($attr->empty('cut_string')) {
            return '';
        }

        $urls  = $attr->empty('absolute_urls') ? '0' : '1';
        $short = $this->getFilters($attr);
        $cut   = $attr->get('cut_string');
        $attr->set('cut_string', '0');
        $full  = $this->getFilters($attr);
        $attr->set('cut_string', $cut);

        if (!$attr->empty('full')) {
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
    public function EntryExcerpt(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getExcerpt(' . ($attr->empty('absolute_urls') ? '0' : '1') . ')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorCommonName - O -- Entry author common name -->
     */
    public function EntryAuthorCommonName(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getAuthorCN()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorDisplayName - O -- Entry author display name -->
     */
    public function EntryAuthorDisplayName(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->field("user_displayname")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorID - O -- Entry author ID -->
     */
    public function EntryAuthorID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->field("user_id")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorEmail - O -- Entry author email -->
    <!ATTLIST tpl:EntryAuthorEmail
    spam_protected    (0|1)    #IMPLIED    -- protect email from spam (default: 1)
    >
     */
    public function EntryAuthorEmail(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getAuthorEmail(' . (($attr->isset('spam_protected') && !$attr->get('spam_protected')) ? 'false' : 'true') . ')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorEmailMD5 - O -- Entry author email MD5 sum -->
    >
     */
    public function EntryAuthorEmailMD5(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'md5(App::core()->context()->get("posts")->getAuthorEmail(false))') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorLink - O -- Entry author link -->
     */
    public function EntryAuthorLink(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getAuthorLink()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAuthorURL - O -- Entry author URL -->
     */
    public function EntryAuthorURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->field("user_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryBasename - O -- Entry short URL (relative to /post) -->
     */
    public function EntryBasename(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->field("post_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategory - O -- Entry category (full name) -->
     */
    public function EntryCategory(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->field("cat_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryDescription - O -- Entry category description -->
     */
    public function EntryCategoryDescription(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->field("cat_desc")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoriesBreadcrumb - - -- Current entry parents loop (without last one) -->
     */
    public function EntryCategoriesBreadcrumb(TplAttr $attr, string $content): string
    {
        return
            self::$ton . "\n" .
            'App::core()->context()->set("categories", App::core()->blog()->categories()->getCategoryParents(App::core()->context()->get("posts")->integer("cat_id")));' . "\n" .
            'while (App::core()->context()->get("categories")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("categories", null);' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryID - O -- Entry category ID -->
     */
    public function EntryCategoryID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->integer("cat_id")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryURL - O -- Entry category URL -->
     */
    public function EntryCategoryURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->getCategoryURL()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryCategoryShortURL - O -- Entry category short URL (relative URL, from /category/) -->
     */
    public function EntryCategoryShortURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->field("cat_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryFeedID - O -- Entry feed ID -->
     */
    public function EntryFeedID(TplAttr $attr): string
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
    public function EntryFirstImage(TplAttr $attr): string
    {
        $size          = $attr->get('size');
        $class         = $attr->get('class');
        $with_category = !$attr->empty('with_category') ? 1 : 0;
        $no_tag        = !$attr->empty('no_tag') ? 1 : 0;
        $content_only  = !$attr->empty('content_only') ? 1 : 0;
        $cat_only      = !$attr->empty('cat_only') ? 1 : 0;

        return self::$ton . "echo App::core()->context()->EntryFirstImageHelper('" . addslashes($size) . "'," . $with_category . ",'" . addslashes($class) . "'," .
            $no_tag . ',' . $content_only . ',' . $cat_only . ');' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryID - O -- Entry ID -->
     */
    public function EntryID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->integer("post_id")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryLang - O --  Entry language or blog lang if not defined -->
     */
    public function EntryLang(TplAttr $attr): string
    {
        $f = $this->getFilters($attr);

        return
        self::$ton . 'if (App::core()->context()->get("posts")->field("post_lang")) { ' .
        'echo ' . sprintf($f, 'App::core()->context()->get("posts")->field("post_lang")') . '; ' .
        '} else {' .
        'echo ' . sprintf($f, 'App::core()->blog()->settings()->getGroup("system")->getSetting("lang")') . '; ' .
            '}' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryNext - - -- Next entry block -->
    <!ATTLIST tpl:EntryNext
    restrict_to_category    (0|1)    #IMPLIED    -- find next post in the same category (default: 0)
    restrict_to_lang        (0|1)    #IMPLIED    -- find next post in the same language (default: 0)
    >
     */
    public function EntryNext(TplAttr $attr, string $content): string
    {
        return
            self::$ton . '$next_post = App::core()->blog()->posts()->getNextPost(App::core()->context()->get("posts"),' . ($attr->empty('restrict_to_category') ? '0' : '1') . ',' . ($attr->empty('restrict_to_lang') ? '0' : '1') . ');' . self::$toff . "\n" .
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
    public function EntryPrevious(TplAttr $attr, string $content): string
    {
        return
            self::$ton . '$prev_post = App::core()->blog()->posts()->getPreviousPost(App::core()->context()->get("posts"),' . ($attr->empty('restrict_to_category') ? '0' : '1') . ',' . ($attr->empty('restrict_to_lang') ? '0' : '1') . ');' . self::$toff . "\n" .
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
    public function EntryTitle(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("posts")->field("post_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryURL - O -- Entry URL -->
     */
    public function EntryURL(TplAttr $attr): string
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
    public function EntryDate(TplAttr $attr): string
    {
        $type    = (!$attr->empty('creadt') ? 'creadt' : '');
        $type    = (!$attr->empty('upddt') ? 'upddt' : $type);

        $f = $this->getFilters($attr);

        if (!$attr->empty('rfc822')) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('posts')->getRFC822Date('" . $type . "')") . ';' . self::$toff;
        }
        if (!$attr->empty('iso8601')) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('posts')->getISO8601Date('" . $type . "')") . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('posts')->getDate('" . addslashes($attr->get('format')) . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryTime - O -- Entry date -->
    <!ATTLIST tpl:EntryTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the post update time
    creadt    CDATA    #IMPLIED    -- if set, uses the post creation time
    >
     */
    public function EntryTime(TplAttr $attr): string
    {
        $type = !$attr->empty('upddt') ? 'upddt' : (!$attr->empty('creadt') ? 'creadt' : '');

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), "App::core()->context()->get('posts')->getTime('" . addslashes($attr->get('format')) . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntriesHeader - - -- First entries result container -->
     */
    public function EntriesHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("posts")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntriesFooter - - -- Last entries result container -->
     */
    public function EntriesFooter(TplAttr $attr, string $content): string
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
    public function EntryCommentCount(TplAttr $attr): string
    {
        return $this->displayCounter(
            (
                $attr->empty('count_all') ?
                'App::core()->context()->get("posts")->integer("nb_comment")' :
                '(App::core()->context()->get("posts")->integer("nb_comment") + App::core()->context()->get("posts")->integer("nb_trackback"))'
            ),
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
    public function EntryPingCount(TplAttr $attr): string
    {
        return $this->displayCounter(
            'App::core()->context()->get("posts")->integer("nb_trackback")',
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
    public function EntryPingData(TplAttr $attr): string
    {
        return self::$ton . "if (App::core()->context()->get('posts')->trackbacksActive()) { echo App::core()->context()->get('posts')->getTrackbackData('" . ('xml' == $attr->get('format') ? 'xml' : 'html') . "'); }" . self::$toff . "\n";
    }

    /*dtd
    <!ELEMENT tpl:EntryPingLink - O -- Entry trackback link -->
     */
    public function EntryPingLink(TplAttr $attr): string
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
    public function Languages(TplAttr $attr, string $content): string
    {
        $p = 'if (!isset($param)) { $param = new Param(); }' . "\n";

        if ($attr->isset('lang')) {
            $p = "\$param->set('post_lang', '" . addslashes($attr->get('lang')) . "');\n";
        }

        if (preg_match('/^(desc|asc)$/i', $attr->get('order'))) {
            $p .= "\$param->set('order', '" . $attr->get('order') . "');\n ";
        }

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'Languages', 'method' => 'blog()->posts()->getLangs'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("langs", App::core()->blog()->posts()->getLangs(param: $param)); unset($param);' . "\n";
        $res .= "?>\n";

        $res .= self::$ton . 'if (App::core()->context()->get("langs")->count() > 1) : ' .
            'while (App::core()->context()->get("langs")->fetch()) :' . self::$toff . $content .
            self::$ton . 'endwhile; App::core()->context()->set("langs", null); endif;' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:LanguagesHeader - - -- First languages result container -->
     */
    public function LanguagesHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("langs")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:LanguagesFooter - - -- Last languages result container -->
     */
    public function LanguagesFooter(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("lang")"->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:LanguageCode - O -- Language code -->
     */
    public function LanguageCode(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("langs")->field("post_lang")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:LanguageIfCurrent - - -- tests if post language is current language -->
     */
    public function LanguageIfCurrent(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->cur_lang == App::core()->context()->get("langs")->field("post_lang")) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:LanguageURL - O -- Language URL -->
     */
    public function LanguageURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->blog()->getURLFor("lang",' .
            'App::core()->context()->get("langs")->field("post_lang"))') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:FeedLanguage - O -- Feed Language -->
     */
    public function FeedLanguage(TplAttr $attr): string
    {
        $f = $this->getFilters($attr);

        return
        self::$ton . 'if (App::core()->context()->exists("cur_lang")) ' . "\n" .
        '   { echo ' . sprintf($f, 'App::core()->context()->get("cur_lang")') . '; }' . "\n" .
        'elseif (App::core()->context()->exists("posts") && App::core()->context()->get("posts")->exists("post_lang")) ' . "\n" .
        '   { echo ' . sprintf($f, 'App::core()->context()->get("posts")->get("post_lang")') . '; }' . "\n" .
        'else ' . "\n" .
        '   { echo ' . sprintf($f, 'App::core()->blog()->settings()->getGroup("system")->getSetting("lang")') . '; }' . self::$toff;
    }

    // Pagination -------------------------------------
    /*dtd
    <!ELEMENT tpl:Pagination - - -- Pagination container -->
    <!ATTLIST tpl:Pagination
    no_context    (0|1)    #IMPLIED    -- override test on posts count vs number of posts per page
    >
     */
    public function Pagination(TplAttr $attr, string $content): string
    {
        $p = self::$ton . "\n";
        $p .= '$param = App::core()->context()->get("post_param");' . "\n";
        $p .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'Pagination', 'method' => 'blog()->posts()->getPosts'],
            $attr,
            $content
        );
        $p .= 'App::core()->context()->set("pagination", App::core()->blog()->posts()->countPosts(param: $param)); unset($param);' . "\n";
        $p .= self::$toff . "\n";

        if ($attr->isset('no_context') && $attr->get('no_context')) {
            return $p . $content;
        }

        return
            $p .
            self::$ton . 'if (App::core()->context()->get("pagination") > App::core()->context()->get("posts")->count()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PaginationCounter - O -- Number of pages -->
     */
    public function PaginationCounter(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->PaginationNbPages()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PaginationCurrent - O -- current page -->
     */
    public function PaginationCurrent(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->PaginationPosition(' . ($attr->isset('offset') ? (int) $attr->get('offset') : 0) . ')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PaginationIf - - -- pages tests -->
    <!ATTLIST tpl:PaginationIf
    start    (0|1)    #IMPLIED    -- test if we are at first page (value : 1) or not (value : 0)
    end    (0|1)    #IMPLIED    -- test if we are at last page (value : 1) or not (value : 0)
    >
     */
    public function PaginationIf(TplAttr $attr, string $content): string
    {
        $if = new Strings();

        if ($attr->isset('start')) {
            $if->add(((bool) $attr->get('start') ? '' : '!') . 'App::core()->context()->PaginationStart()');
        }

        if ($attr->isset('end')) {
            $if->add(((bool) $attr->get('end') ? '' : '!') . 'App::core()->context()->PaginationEnd()');
        }

        App::core()->behavior('tplIfConditions')->call('PaginationIf', $attr, $content, $if);

        if ($if->count()) {
            return self::$ton . 'if(' . implode(' && ', $if->dump()) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:PaginationURL - O -- link to previoux/next page -->
    <!ATTLIST tpl:PaginationURL
    offset    CDATA    #IMPLIED    -- page offset (negative for previous pages), default: 0
    >
     */
    public function PaginationURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->PaginationURL(' . ($attr->isset('offset') ? (int) $attr->get('offset') : 0) . ')') . ';' . self::$toff;
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
    public function Comments(TplAttr $attr, string $content): string
    {
        $p = 'if (!isset($param)) { $param = new Param(); }' . "\n";
        if ($attr->empty('with_pings')) {
            $p .= "\$param->set('comment_trackback', 0);\n";
        }

        $lastn = 0;
        if ($attr->isset('lastn')) {
            $lastn = abs((int) $attr->get('lastn')) + 0;
        }

        if (0 < $lastn) {
            $p .= "\$param->set('limit'," . $lastn . ");\n";
        } else {
            $p .= "if (App::core()->context()->get('nb_comment_per_page') !== null) { \$param->set('limit', (int) App::core()->context()->get('nb_comment_per_page')); }\n";
        }

        if ($attr->empty('no_context')) {
            $p .= 'if (App::core()->context()->get("posts") !== null) { ' .
                "\$param->set('post_id', App::core()->context()->get('posts')->integer('post_id')); " .
                "App::core()->blog()->setWithPassword();\n" .
                "}\n";
            $p .= 'if (App::core()->context()->exists("categories")) { ' .
                "\$param->set('cat_id', App::core()->context()->get('categories')->integer('cat_id')); " .
                "}\n";

            $p .= 'if (App::core()->context()->exists("langs")) { ' .
                "\$param->push('sql', \"AND post_lang = '\".App::core()->con()->escape(App::core()->context()->get('langs')->field('post_lang')).\"' \"); " .
                "}\n";
        }

        if (!$attr->isset('order')) {
            $attr->set('order', 'asc');
        }

        $p .= "\$param->set('order', '" . $this->getSortByStr($attr, 'comment') . "');\n";

        if ($attr->isset('no_content') && $attr->get('no_content')) {
            $p .= "\$param->set('no_content', true);\n";
        }

        if ($attr->isset('age')) {
            $age = $this->getAge($attr);
            $p .= !empty($age) ? "@\$param->push('sql', ' AND P.post_dt > \\'" . $age . "\\'');\n" : '';
        }

        $res = self::$ton . "\n";
        $res .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'Comments', 'method' => 'blog()->comments()->getComments'],
            $attr,
            $content
        );
        $res .= $p;
        $res .= 'App::core()->context()->set("comments", App::core()->blog()->comments()->getComments(param: $param)); unset($param);' . "\n";
        $res .= "if (App::core()->context()->get('posts') !== null) { App::core()->blog()->setWithoutPassword();}\n";

        if (!$attr->empty('with_pings')) {
            $res .= 'App::core()->context()->set("pings", App::core()->context()->get("comments"));' . "\n";
        }

        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("comments")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("comments", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthor - O -- Comment author -->
     */
    public function CommentAuthor(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->field("comment_author")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorDomain - O -- Comment author website domain -->
     */
    public function CommentAuthorDomain(TplAttr $attr): string
    {
        return self::$ton . 'echo preg_replace("#^http(?:s?)://(.+?)/.*$#msu",\'$1\',App::core()->context()->get("comments")->field("comment_site"));' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorLink - O -- Comment author link -->
     */
    public function CommentAuthorLink(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getAuthorLink()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorMailMD5 - O -- Comment author email MD5 sum -->
     */
    public function CommentAuthorMailMD5(TplAttr $attr): string
    {
        return self::$ton . 'echo md5(App::core()->context()->get("comments")->field("comment_email")) ;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentAuthorURL - O -- Comment author URL -->
     */
    public function CommentAuthorURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getAuthorURL()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentContent - O --  Comment content -->
    <!ATTLIST tpl:CommentContent
    absolute_urls    (0|1)    #IMPLIED    -- convert URLS to absolute urls
    >
     */
    public function CommentContent(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getContent(' . ($attr->empty('absolute_urls') ? '0' : '1') . ')') . ';' . self::$toff;
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
    public function CommentDate(TplAttr $attr): string
    {
        $type = (!$attr->empty('upddt') ? 'upddt' : '');

        $f = $this->getFilters($attr);

        if (!$attr->empty('rfc822')) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('comments')->getRFC822Date('" . $type . "')") . ';' . self::$toff;
        }
        if (!$attr->empty('iso8601')) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('comments')->getISO8601Date('" . $type . "')") . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('comments')->getDate('" . addslashes($attr->get('format')) . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentTime - O -- Comment date -->
    <!ATTLIST tpl:CommentTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the comment update time
    >
     */
    public function CommentTime(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), "App::core()->context()->get('comments')->getTime('" . addslashes($attr->get('format')) . "','" . (!$attr->empty('upddt') ? 'upddt' : '') . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentEmail - O -- Comment author email -->
    <!ATTLIST tpl:CommentEmail
    spam_protected    (0|1)    #IMPLIED    -- protect email from spam (default: 1)
    >
     */
    public function CommentEmail(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getEmail(' . (($attr->isset('spam_protected') && !$attr->get('spam_protected')) ? 'false' : 'true') . ')') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentEntryTitle - O -- Title of the comment entry -->
     */
    public function CommentEntryTitle(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->get("post_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentFeedID - O -- Comment feed ID -->
     */
    public function CommentFeedID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getFeedID()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentID - O -- Comment ID -->
     */
    public function CommentID(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("comments")->integer("comment_id");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentIf - - -- test container for comments -->
    <!ATTLIST tpl:CommentIf
    is_ping    (0|1)    #IMPLIED    -- test if comment is a trackback (value : 1) or not (value : 0)
    >
     */
    public function CommentIf(TplAttr $attr, string $content): string
    {
        $if = new Strings();

        if ($attr->isset('is_ping')) {
            $if->add(((bool) $attr->get('is_ping') ? '' : '!') . 'App::core()->context()->get("comments")->integer("comment_trackback")');
        }

        App::core()->behavior('tplIfConditions')->call('CommentIf', $attr, $content, $if);

        if ($if->count()) {
            return self::$ton . 'if(' . implode(' && ', $if->dump()) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:CommentIfFirst - O -- displays value if comment is the first one -->
    <!ATTLIST tpl:CommentIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function CommentIfFirst(TplAttr $attr): string
    {
        return
        self::$ton . 'if (App::core()->context()->get("comments")->index() == 0) { ' .
        "echo '" . addslashes(Html::escapeHTML($attr->get('return') ?: 'first')) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentIfMe - O -- displays value if comment is the from the entry author -->
    <!ATTLIST tpl:CommentIfMe
    return    CDATA    #IMPLIED    -- value to display in case of success (default: me)
    >
     */
    public function CommentIfMe(TplAttr $attr): string
    {
        return
        self::$ton . 'if (App::core()->context()->get("comments")->isMe()) { ' .
        "echo '" . addslashes(Html::escapeHTML($attr->get('return') ?: 'me')) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentIfOdd - O -- displays value if comment is  at an odd position -->
    <!ATTLIST tpl:CommentIfOdd
    return    CDATA    #IMPLIED    -- value to display in case of success (default: odd)
    even      CDATA    #IMPLIED    -- value to display in case of failure (default: <empty>)
    >
     */
    public function CommentIfOdd(TplAttr $attr): string
    {
        return self::$ton . 'echo ((App::core()->context()->get("comments")->index()+1)%2 ? ' .
        '"' . addslashes(Html::escapeHTML($attr->get('return') ?: 'odd')) . '" : ' .
        '"' . addslashes(Html::escapeHTML($attr->get('even') ?: '')) . '");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentIP - O -- Comment author IP -->
     */
    public function CommentIP(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("comments")->field("comment_ip");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentOrderNumber - O -- Comment order in page -->
     */
    public function CommentOrderNumber(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("comments")->index()+1;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentsFooter - - -- Last comments result container -->
     */
    public function CommentsFooter(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("comments")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentsHeader - - -- First comments result container -->
     */
    public function CommentsHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("comments")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPostURL - O -- Comment Entry URL -->
     */
    public function CommentPostURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comments")->getPostURL()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:IfCommentAuthorEmail - - -- Container displayed if comment author email is set -->
     */
    public function IfCommentAuthorEmail(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("comments")->field("comment_email")) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentHelp - 0 -- Comment syntax mini help -->
     */
    public function CommentHelp(TplAttr $attr, string $content): string
    {
        return
            self::$ton . "if (App::core()->blog()->settings()->getGroup('system')->getSetting('wiki_comments')) {\n" .
            "  echo __('Comments can be formatted using a simple wiki syntax.');\n" .
            "} else {\n" .
            "  echo __('HTML code is displayed as text and web addresses are automatically converted.');\n" .
            '}' . self::$toff;
    }

    // Comment preview --------------------------------
    /*dtd
    <!ELEMENT tpl:IfCommentPreviewOptional - - -- Container displayed if comment preview is optional or currently previewed -->
     */
    public function IfCommentPreviewOptional(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->blog()->settings()->getGroup("system")->getSetting("comment_preview_optional") || App::core()->context()->get("comment_preview")?->get("preview")) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:IfCommentPreview - - -- Container displayed if comment is being previewed -->
     */
    public function IfCommentPreview(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("comment_preview")?->get("preview")) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewName - O -- Author name for the previewed comment -->
     */
    public function CommentPreviewName(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comment_preview")->get("name")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewEmail - O -- Author email for the previewed comment -->
     */
    public function CommentPreviewEmail(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comment_preview")->get("mail")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewSite - O -- Author site for the previewed comment -->
     */
    public function CommentPreviewSite(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("comment_preview")->get("site")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewContent - O -- Content of the previewed comment -->
    <!ATTLIST tpl:CommentPreviewContent
    raw    (0|1)    #IMPLIED    -- display comment in raw content
    >
     */
    public function CommentPreviewContent(TplAttr $attr): string
    {
        $co = $attr->empty('raw') ?
            'App::core()->context()->get("comment_preview")->get("content")' :
            'App::core()->context()->get("comment_preview")->get("rawcontent")';

        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), $co) . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:CommentPreviewCheckRemember - O -- checkbox attribute for "remember me" (same value as before preview) -->
     */
    public function CommentPreviewCheckRemember(TplAttr $attr): string
    {
        return self::$ton . "if (App::core()->context()->get('comment_preview')->get('remember')) { echo ' checked=\"checked\"'; }" . self::$toff;
    }

    // Trackbacks -------------------------------------
    /*dtd
    <!ELEMENT tpl:PingBlogName - O -- Trackback blog name -->
     */
    public function PingBlogName(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->field("comment_author")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingContent - O -- Trackback content -->
     */
    public function PingContent(TplAttr $attr): string
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
    public function PingDate(TplAttr $attr, string $type = ''): string
    {
        $type = $attr->empty('upddt') ? '' : 'upddt';

        $f = $this->getFilters($attr);

        if (!$attr->empty('rfc822')) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('pings')->getRFC822Date('" . $type . "')") . ';' . self::$toff;
        }
        if (!$attr->empty('iso8601')) {
            return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('pings')->getISO8601Date('" . $type . "')") . ';' . self::$toff;
        }

        return self::$ton . 'echo ' . sprintf($f, "App::core()->context()->get('pings')->getDate('" . addslashes($attr->get('format')) . "','" . $type . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingTime - O -- Trackback date -->
    <!ATTLIST tpl:PingTime
    format    CDATA    #IMPLIED    -- time format
    upddt    CDATA    #IMPLIED    -- if set, uses the comment update time
    >
     */
    public function PingTime(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), "App::core()->context()->get('pings')->getTime('" . addslashes($attr->get('format')) . "','" . ($attr->empty('upddt') ? '' : 'upddt') . "')") . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingEntryTitle - O -- Trackback entry title -->
     */
    public function PingEntryTitle(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->field("post_title")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingFeedID - O -- Trackback feed ID -->
     */
    public function PingFeedID(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->getFeedID()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingID - O -- Trackback ID -->
     */
    public function PingID(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("pings")->integer("comment_id");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingIfFirst - O -- displays value if trackback is the first one -->
    <!ATTLIST tpl:PingIfFirst
    return    CDATA    #IMPLIED    -- value to display in case of success (default: first)
    >
     */
    public function PingIfFirst(TplAttr $attr): string
    {
        return
        self::$ton . 'if (App::core()->context()->get("pings")->index() == 0) { ' .
        "echo '" . addslashes(Html::escapeHTML($attr->get('return') ?: 'first')) . "'; }" . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingIfOdd - O -- displays value if trackback is  at an odd position -->
    <!ATTLIST tpl:PingIfOdd
    return    CDATA    #IMPLIED    -- value to display in case of success (default: odd)
    even      CDATA    #IMPLIED    -- value to display in case of failure (default: <empty>)
    >
     */
    public function PingIfOdd(TplAttr $attr): string
    {
        return self::$ton . 'echo ((App::core()->context()->get("pings")->index()+1)%2 ? ' .
        '"' . addslashes(Html::escapeHTML($attr->get('return') ?: 'odd')) . '" : ' .
        '"' . addslashes(Html::escapeHTML($attr->get('even') ?: '')) . '");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingIP - O -- Trackback author IP -->
     */
    public function PingIP(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("pings")->field("comment_ip");' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingNoFollow - O -- displays 'rel="nofollow"' if set in blog -->
     */
    public function PingNoFollow(TplAttr $attr): string
    {
        return
            self::$ton . 'if(App::core()->blog()->settings()->getGroup("system")->getSetting("comments_nofollow")) { ' .
            'echo \' rel="nofollow"\';' .
            '}' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingOrderNumber - O -- Trackback order in page -->
     */
    public function PingOrderNumber(TplAttr $attr): string
    {
        return self::$ton . 'echo App::core()->context()->get("pings")->index()+1;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingPostURL - O -- Trackback Entry URL -->
     */
    public function PingPostURL(TplAttr $attr): string
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
    public function Pings(TplAttr $attr, string $content): string
    {
        $p = 'if (!isset($param)) { $param = new Param(); }' . "\n" .
            'if (App::core()->context()->get("posts") !== null) { ' .
            "\$param->set('post_id', App::core()->context()->get('posts')->integer('post_id')); " .
            "App::core()->blog()->setWithPassword();\n" .
            "}\n";

        $p .= "\$param->set('comment_trackback', 1);\n";

        $lastn = 0;
        if ($attr->isset('lastn')) {
            $lastn = abs((int) $attr->get('lastn')) + 0;
        }

        if (0 < $lastn) {
            $p .= "\$param->set('limit', " . $lastn . ");\n";
        } else {
            $p .= "if (App::core()->context()->get('nb_comment_per_page') !== null) { \$param->set('limit', App::core()->context()->get('nb_comment_per_page')); }\n";
        }

        if ($attr->empty('no_context')) {
            $p .= 'if (App::core()->context()->exists("categories")) { ' .
                "\$param->set('cat_id', App::core()->context()->get('categories')->integer('cat_id')); " .
                "}\n";

            $p .= 'if (App::core()->context()->exists("langs")) { ' .
                "\$param->push('sql', \"AND post_lang = '\".App::core()->con()->escape(App::core()->context()->get('langs')->field('post_lang')).\"' \"); " .
                "}\n";
        }

        $p .= "\$param->set('order', 'comment_dt " . (preg_match('/^(desc|asc)$/i', $attr->get('order')) ? $attr->get('order') : 'asc') . "');\n";

        if ($attr->isset('no_content') && $attr->get('no_content')) {
            $p .= "\$param->set('no_content', true);\n";
        }

        $res = self::$ton . "\n";
        $res .= $p;
        $res .= App::core()->behavior('templatePrepareParams')->call(
            ['tag' => 'Pings', 'method' => 'blog()->comments()->getComments'],
            $attr,
            $content
        );
        $res .= 'App::core()->context()->set("pings", App::core()->blog()->comments()->getComments(param: $param)); unset($param);' . "\n";
        $res .= "if (App::core()->context()->get('posts') !== null) { App::core()->blog()->setWithoutPassword();}\n";
        $res .= "?>\n";

        $res .= self::$ton . 'while (App::core()->context()->get("pings")->fetch()) :' . self::$toff . $content . self::$ton . 'endwhile; App::core()->context()->set("pings", null);' . self::$toff;

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:PingsFooter - - -- Last trackbacks result container -->
     */
    public function PingsFooter(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("pings")->isEnd()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingsHeader - - -- First trackbacks result container -->
     */
    public function PingsHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("pings")->isStart()) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingTitle - O -- Trackback title -->
     */
    public function PingTitle(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("pings")->getTrackbackTitle()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:PingAuthorURL - O -- Trackback author URL -->
     */
    public function PingAuthorURL(TplAttr $attr): string
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
    public function SysBehavior(TplAttr $attr, string $raw): string
    {
        return !$attr->isset('behavior') ? '' :
            self::$ton . 'if (App::core()->behavior(\'' . addslashes($attr->get('behavior')) . '\')->count()) { ' .
            'App::core()->behavior(\'' . addslashes($attr->get('behavior')) . '\')->call(App::core()->context());' .
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
    public function SysIf(TplAttr $attr, string $content): string
    {
        $if = new Strings();

        if ($attr->isset('categories')) {
            $if->add('App::core()->context()->get("categories") ' . ((bool) $attr->get('categories') ? '!' : '=') . '== null');
        }

        if ($attr->isset('posts')) {
            $if->add('App::core()->context()->get("posts") ' . ((bool) $attr->get('posts') ? '!' : '=') . '== null');
        }

        if ($attr->isset('blog_lang')) {
            $if->add($this->getSign('blog_lang', $attr) . "(App::core()->blog()->settings()->getGroup('system')->getSetting('lang') == '" . addslashes($attr->get('blog_lang')) . "')");
        }

        if ($attr->isset('current_tpl')) {
            $if->add($this->getSign('current_tpl', $attr) . "(App::core()->context()->get('current_tpl') == '" . addslashes($attr->get('current_tpl')) . "')");
        }

        if ($attr->isset('current_mode')) {
            $if->add($this->getSign('current_mode', $attr) . "(App::core()->url()->getCurrentType() == '" . addslashes($attr->get('current_mode')) . "')");
        }

        if ($attr->isset('has_tpl')) {
            $if->add($this->getSign('has_tpl', $attr) . "App::core()->template()->getFilePath('" . addslashes($attr->get('has_tpl')) . "') !== false");
        }

        if ($attr->isset('has_tag')) {
            $if->add($this->getSign('has_tag', $attr) . "App::core()->template()->tagExists('" . addslashes($attr->get('has_tag')) . "')");
        }

        if ($attr->isset('blog_id')) {
            $if->add($this->getSign('blog_id', $attr) . "(App::core()->blog()->id == '" . addslashes($attr->get('blog_id')) . "')");
        }

        if ($attr->isset('comments_active')) {
            $if->add(((bool) $attr->get('comments_active') ? '' : '!') . 'App::core()->blog()->settings()->getGroup("system")->getSetting("allow_comments")');
        }

        if ($attr->isset('pings_active')) {
            $if->add(((bool) $attr->get('pings_active') ? '' : '!') . 'App::core()->blog()->settings()->getGroup("system")->getSetting("allow_trackbacks")');
        }

        if ($attr->isset('wiki_comments')) {
            $if->add(((bool) $attr->get('wiki_comments') ? '' : '!') . 'App::core()->blog()->settings()->getGroup("system")->getSetting("wiki_comments")');
        }

        if ($attr->isset('search_count') && preg_match('/^((=|!|&gt;|&lt;)=|(&gt;|&lt;))\s*[0-9]+$/', trim($attr->get('search_count')))) {
            $if->add('(App::core()->url()->getSearchString() && App::core()->url()->getSearchCount() ' . Html::decodeEntities($attr->get('search_count')) . ')');
        }

        if ($attr->isset('jquery_needed')) {
            $if->add(((bool) $attr->get('jquery_needed') ? '' : '!') . 'App::core()->blog()->settings()->getGroup("system")->getSetting("jquery_needed")');
        }

        App::core()->behavior('tplIfConditions')->call('SysIf', $attr, $content, $if);

        if ($if->count()) {
            return self::$ton . 'if(' . implode(' ' . $this->getOperator($attr->get('operator')) . ' ', $if->dump()) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:SysIfCommentPublished - - -- Container displayed if comment has been published -->
     */
    public function SysIfCommentPublished(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (!GPC::get()->empty(\'pub\')) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:SysIfCommentPending - - -- Container displayed if comment is pending after submission -->
     */
    public function SysIfCommentPending(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (GPC::get()->int(\'pub\', -1) == 0) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:SysFeedSubtitle - O -- Feed subtitle -->
     */
    public function SysFeedSubtitle(TplAttr $attr): string
    {
        return self::$ton . 'if (App::core()->context()->get("feed_subtitle") !== null) { echo ' . sprintf($this->getFilters($attr), 'App::core()->context()->get("feed_subtitle")') . ';}' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:SysIfFormError - O -- Container displayed if an error has been detected after form submission -->
     */
    public function SysIfFormError(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if (App::core()->context()->get("form_error") !== null) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:SysFormError - O -- Form error -->
     */
    public function SysFormError(TplAttr $attr): string
    {
        return self::$ton . 'if (App::core()->context()->get("form_error") !== null) { echo App::core()->context()->get("form_error"); }' . self::$toff;
    }

    public function SysPoweredBy(TplAttr $attr): string
    {
        return self::$ton . 'printf(__("Powered by %s"),"<a href=\"https://dotclear.org/\">Dotclear</a>");' . self::$toff;
    }

    public function SysSearchString(TplAttr $attr): string
    {
        return self::$ton . 'if (App::core()->url()->getSearchString()) { echo sprintf(__(\'' . ($attr->get('string') ?: '%1$s') . '\'),' . sprintf($this->getFilters($attr), 'App::core()->url()->getSearchString()') . ',App::core()->url()->getSearchCount());}' . self::$toff;
    }

    public function SysSelfURI(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf($this->getFilters($attr), '\Dotclear\Helper\Network\Http::getSelfURI()') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:else - O -- else: statement -->
     */
    public function GenericElse(TplAttr $attr): string
    {
        return self::$ton . 'else:' . self::$toff;
    }

    public function getSign(string $key, TplAttr $attr): string
    {
        $sign = '';
        if (substr($attr->get($key), 0, 1) == '!') {
            $sign = '!';
            $attr->set($key, substr($attr->get($key), 1));
        }

        return $sign;
    }
}
