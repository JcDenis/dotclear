<?php
/**
 * @class Dotclear\Core\Xmlrpc\Xmlrpc
 * @brief Xmlrpc server
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Xmlrpc;

use ArrayObject;
use Dotclear\Container\UserContainer;
use Dotclear\Core\Trackback\Trackback;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Text;
use Dotclear\Helper\Dt;
use Dotclear\Helper\Network\Xmlrpc\IntrospectionServer as XmlrpcIntrospectionServer;
use Dotclear\Helper\Network\Xmlrpc\Date as XmlrpcDate;

class Xmlrpc extends XmlrpcIntrospectionServer
{
    private $blog_loaded    = false;
    private $debug          = false;
    private $debug_file     = '/tmp/dotclear-xmlrpc.log';
    private $trace_args     = true;
    private $trace_response = true;

    public function __construct(private string|null $blog_id)
    {
        parent::__construct();

        # Blogger methods
        $this->addCallback('blogger.newPost', [$this, 'blogger_newPost'],
            ['string', 'string', 'string', 'string', 'string', 'string', 'integer'],
            'New post');

        $this->addCallback('blogger.editPost', [$this, 'blogger_editPost'],
            ['boolean', 'string', 'string', 'string', 'string', 'string', 'integer'],
            'Edit a post');

        $this->addCallback('blogger.getPost', [$this, 'blogger_getPost'],
            ['struct', 'string', 'integer', 'string', 'string'],
            'Return a posts by ID');

        $this->addCallback('blogger.deletePost', [$this, 'blogger_deletePost'],
            ['string', 'string', 'string', 'string', 'string', 'integer'],
            'Delete a post');

        $this->addCallback('blogger.getRecentPosts', [$this, 'blogger_getRecentPosts'],
            ['array', 'string', 'string', 'string', 'string', 'integer'],
            'Return a list of recent posts');

        $this->addCallback('blogger.getUsersBlogs', [$this, 'blogger_getUserBlogs'],
            ['struct', 'string', 'string', 'string'],
            "Return user's blog");

        $this->addCallback('blogger.getUserInfo', [$this, 'blogger_getUserInfo'],
            ['struct', 'string', 'string', 'string'],
            'Return User Info');

        # Metaweblog methods
        $this->addCallback('metaWeblog.newPost', [$this, 'mw_newPost'],
            ['string', 'string', 'string', 'string', 'struct', 'boolean'],
            'Creates a new post, and optionnaly publishes it.');

        $this->addCallback('metaWeblog.editPost', [$this, 'mw_editPost'],
            ['boolean', 'string', 'string', 'string', 'struct', 'boolean'],
            'Updates information about an existing entry');

        $this->addCallback('metaWeblog.getPost', [$this, 'mw_getPost'],
            ['struct', 'string', 'string', 'string'],
            'Returns information about a specific post');

        $this->addCallback('metaWeblog.getRecentPosts', [$this, 'mw_getRecentPosts'],
            ['array', 'string', 'string', 'string', 'integer'],
            'List of most recent posts in the system');

        $this->addCallback('metaWeblog.getCategories', [$this, 'mw_getCategories'],
            ['array', 'string', 'string', 'string'],
            'List of all categories defined in the weblog');

        $this->addCallback('metaWeblog.newMediaObject', [$this, 'mw_newMediaObject'],
            ['struct', 'string', 'string', 'string', 'struct'],
            'Upload a file on the web server');

        # MovableType methods
        $this->addCallback('mt.getRecentPostTitles', [$this, 'mt_getRecentPostTitles'],
            ['array', 'string', 'string', 'string', 'integer'],
            'List of most recent posts in the system');

        $this->addCallback('mt.getCategoryList', [$this, 'mt_getCategoryList'],
            ['array', 'string', 'string', 'string'],
            'List of all categories defined in the weblog');

        $this->addCallback('mt.getPostCategories', [$this, 'mt_getPostCategories'],
            ['array', 'string', 'string', 'string'],
            'List of all categories to which the post is assigned');

        $this->addCallback('mt.setPostCategories', [$this, 'mt_setPostCategories'],
            ['boolean', 'string', 'string', 'string', 'array'],
            'Sets the categories for a post');

        $this->addCallback('mt.publishPost', [$this, 'mt_publishPost'],
            ['boolean', 'string', 'string', 'string'],
            'Retrieve pings list for a post');

        $this->addCallback('mt.supportedMethods', [$this, 'listMethods'],
            [], 'Retrieve information about the XML-RPC methods supported by the server.');

        $this->addCallback('mt.supportedTextFilters', [$this, 'mt_supportedTextFilters'],
            [], 'Retrieve information about supported text filters.');

        # WordPress methods
        $this->addCallback('wp.getUsersBlogs', [$this, 'wp_getUsersBlogs'],
            ['array', 'string', 'string'],
            'Retrieve the blogs of the user.');

        $this->addCallback('wp.getPage', [$this, 'wp_getPage'],
            ['struct', 'integer', 'integer', 'string', 'string'],
            'Get the page identified by the page ID.');

        $this->addCallback('wp.getPages', [$this, 'wp_getPages'],
            ['array', 'integer', 'string', 'string', 'integer'],
            'Get an array of all the pages on a blog.');

        $this->addCallback('wp.newPage', [$this, 'wp_newPage'],
            ['integer', 'integer', 'string', 'string', 'struct', 'boolean'],
            'Create a new page.');

        $this->addCallback('wp.deletePage', [$this, 'wp_deletePage'],
            ['boolean', 'integer', 'string', 'string', 'integer'],
            'Removes a page from the blog.');

        $this->addCallback('wp.editPage', [$this, 'wp_editPage'],
            ['boolean', 'integer', 'integer', 'string', 'string', 'struct', 'boolean'],
            'Make changes to a blog page.');

        $this->addCallback('wp.getPageList', [$this, 'wp_getPageList'],
            ['array', 'integer', 'string', 'string'],
            'Get an array of all the pages on a blog. Just the minimum details, lighter than wp.getPages.');

        $this->addCallback('wp.getAuthors', [$this, 'wp_getAuthors'],
            ['array', 'integer', 'string', 'string'],
            'Get an array of users for the blog.');

        $this->addCallback('wp.getCategories', [$this, 'wp_getCategories'],
            ['array', 'integer', 'string', 'string'],
            'Get an array of available categories on a blog.');

        $this->addCallback('wp.getTags', [$this, 'wp_getTags'],
            ['array', 'integer', 'string', 'string'],
            'Get list of all tags for the blog.');

        $this->addCallback('wp.newCategory', [$this, 'wp_newCategory'],
            ['integer', 'integer', 'string', 'string', 'struct'],
            'Create a new category.');

        $this->addCallback('wp.deleteCategory', [$this, 'wp_deleteCategory'],
            ['boolean', 'integer', 'string', 'string', 'integer'],
            'Delete a category with a given ID.');

        $this->addCallback('wp.suggestCategories', [$this, 'wp_suggestCategories'],
            ['array', 'integer', 'string', 'string', 'string', 'integer'],
            'Get an array of categories that start with a given string.');

        $this->addCallback('wp.uploadFile', [$this, 'wp_uploadFile'],
            ['struct', 'integer', 'string', 'string', 'struct'],
            'Upload a file');

        $this->addCallback('wp.getPostStatusList', [$this, 'wp_getPostStatusList'],
            ['array', 'integer', 'string', 'string'],
            'Retrieve all of the post statuses.');

        $this->addCallback('wp.getPageStatusList', [$this, 'wp_getPageStatusList'],
            ['array', 'integer', 'string', 'string'],
            'Retrieve all of the pages statuses.');

        $this->addCallback('wp.getPageTemplates', [$this, 'wp_getPageTemplates'],
            ['struct', 'integer', 'string', 'string'],
            'Retrieve page templates.');

        $this->addCallback('wp.getOptions', [$this, 'wp_getOptions'],
            ['struct', 'integer', 'string', 'string', 'array'],
            'Retrieve blog options');

        $this->addCallback('wp.setOptions', [$this, 'wp_setOptions'],
            ['struct', 'integer', 'string', 'string', 'struct'],
            'Update blog options');

        $this->addCallback('wp.getComment', [$this, 'wp_getComment'],
            ['struct', 'integer', 'string', 'string', 'integer'],
            "Gets a comment, given it's comment ID.");

        $this->addCallback('wp.getCommentCount', [$this, 'wp_getCommentCount'],
            ['array', 'integer', 'string', 'string', 'integer'],
            'Retrieve comment count.');

        $this->addCallback('wp.getComments', [$this, 'wp_getComments'],
            ['array', 'integer', 'string', 'string', 'struct'],
            'Gets a set of comments for a given post.');

        $this->addCallback('wp.deleteComment', [$this, 'wp_deleteComment'],
            ['boolean', 'integer', 'string', 'string', 'integer'],
            'Delete a comment with given ID.');

        $this->addCallback('wp.editComment', [$this, 'wp_editComment'],
            ['boolean', 'integer', 'string', 'string', 'integer', 'struct'],
            'Edit a comment with given ID.');

        $this->addCallback('wp.newComment', [$this, 'wp_newComment'],
            ['integer', 'integer', 'string', 'string', 'integer', 'struct'],
            'Create a new comment for a given post ID.');

        $this->addCallback('wp.getCommentStatusList', [$this, 'wp_getCommentStatusList'],
            ['array', 'integer', 'string', 'string'],
            'Retrieve all of the comment statuses.');

        # Pingback support
        $this->addCallback('pingback.ping', [$this, 'pingback_ping'],
            ['string', 'string', 'string'],
            'Notify a link to a post.');
    }

    public function serve(mixed $data = false): void
    {
        parent::serve(false);
    }

    public function call(string $methodname, mixed $args): mixed
    {
        try {
            $rsp = @parent::call($methodname, $args);
            $this->debugTrace($methodname, $args, $rsp);

            return $rsp;
        } catch (\Exception $e) {
            $this->debugTrace($methodname, $args, [$e->getMessage(), $e->getCode()]);

            throw $e;
        }
    }

    private function debugTrace(string $methodname, mixed $args, mixed $rsp): void
    {
        if (false == $this->debug) {
            return;
        }

        if (false !== ($fp = @fopen($this->debug_file, 'a'))) {
            fwrite($fp, '[' . date('r') . ']' . ' ' . $methodname);

            if ($this->trace_args) {
                fwrite($fp, "\n- args ---\n" . var_export($args, true));
            }

            if ($this->trace_response) {
                fwrite($fp, "\n- response ---\n" . var_export($rsp, true));
            }
            fwrite($fp, "\n");
            fclose($fp);
        }
    }

    /* Internal methods
    --------------------------------------------------- */
    private function setUser($user_id, $pwd)
    {
        if (empty($pwd) || dotclear()->user()->checkUser($user_id, $pwd) !== true) {
            throw new CoreException('Login error');
        }

        return true;
    }

    private function setBlog($bypass = false)
    {
        if (!$this->blog_id) {
            throw new CoreException('No blog ID given.');
        }

        if ($this->blog_loaded) {
            return true;
        }

        dotclear()->setBlog($this->blog_id);
        $this->blog_loaded = true;

        if (!dotclear()->blog()->id) {
            dotclear()->unsetBlog();

            throw new CoreException('Blog does not exist.');
        }

        if (!$bypass && (!dotclear()->blog()->settings()->get('system')->get('enable_xmlrpc') || !dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id))) {
            dotclear()->unsetBlog();

            throw new CoreException('Not enough permissions on this blog.');
        }

        if (dotclear()->plugins()) {
            foreach (dotclear()->plugins()->getModules() as $module) {
                dotclear()->plugins()->loadModuleL10N($module->id(), dotclear()->lang(), 'xmlrpc');
            }
        }

        return true;
    }

    private function getPostRS($post_id, $user, $pwd, $post_type = 'post')
    {
        $this->setUser($user, $pwd);
        $this->setBlog();
        $rs = dotclear()->blog()->posts()->getPosts([
            'post_id'   => (int) $post_id,
            'post_type' => $post_type
        ]);

        if ($rs->isEmpty()) {
            throw new CoreException('This entry does not exist');
        }

        return $rs;
    }

    private function getCatID($cat_url)
    {
        $rs = dotclear()->blog()->categories()->getCategories(['cat_url' => $cat_url]);

        return $rs->isEmpty() ? null : $rs->fInt('cat_id');
    }

    /* Generic methods
    --------------------------------------------------- */
    private function newPost($blog_id, $user, $pwd, $content, $struct = [], $publish = true)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $title        = !empty($struct['title']) ? $struct['title'] : '';
        $excerpt      = !empty($struct['mt_excerpt']) ? $struct['mt_excerpt'] : '';
        $description  = !empty($struct['description']) ? $struct['description'] : null;
        $dateCreated  = !empty($struct['dateCreated']) ? $struct['dateCreated'] : null;
        $open_comment = $struct['mt_allow_comments'] ?? 1;
        $open_tb      = $struct['mt_allow_pings']    ?? 1;

        if ($description !== null) {
            $content = $description;
        }

        if (!$title) {
            $title = Text::cutString(Html::clean($content), 25) . '...';
        }

        $excerpt_xhtml = dotclear()->formater()->callEditorFormater('LegacyEditor', 'xhtml', $excerpt);
        $content_xhtml = dotclear()->formater()->callEditorFormater('LegacyEditor', 'xhtml', $content);

        if (empty($content)) {
            throw new CoreException('Cannot create an empty entry');
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

        $cur->setField('user_id', dotclear()->user()->userID());
        $cur->setField('post_lang', dotclear()->user()->getInfo('user_lang'));
        $cur->setField('post_title', trim($title));
        $cur->setField('post_content', $content);
        $cur->setField('post_excerpt', $excerpt);
        $cur->setField('post_content_xhtml', $content_xhtml);
        $cur->setField('post_excerpt_xhtml', $excerpt_xhtml);
        $cur->setField('post_open_comment', (int) ($open_comment == 1));
        $cur->setField('post_open_tb', (int) ($open_tb == 1));
        $cur->setField('post_status', (int) $publish);
        $cur->setField('post_format', 'xhtml');

        if ($dateCreated) {
            if ($dateCreated instanceof xmlrpcDate) {
                $cur->setField('post_dt', date('Y-m-d H:i:00', $dateCreated->getTimestamp()));
            } elseif (is_string($dateCreated) && @strtotime($dateCreated)) {
                $cur->setField('post_dt', date('Y-m-d H:i:00', strtotime($dateCreated)));
            }
        }

        # Categories in an array
        if (isset($struct['categories']) && is_array($struct['categories'])) {
            $categories = $struct['categories'];
            $cat_id     = !empty($categories[0]) ? $categories[0] : null;

            $cur->setField('cat_id', $this->getCatID($cat_id));
        }

        if (isset($struct['wp_slug'])) {
            $cur->setField('post_url', $struct['wp_slug']);
        }

        if (isset($struct['wp_password'])) {
            $cur->setField('post_password', $struct['wp_password']);
        }

        $cur->setField('post_type', 'post');
        if (!empty($struct['post_type'])) {
            $cur->setField('post_type', $struct['post_type']);
        }

        if ('post' == $cur->getField('post_type')) {
            # --BEHAVIOR-- xmlrpcBeforeNewPost
            dotclear()->behavior()->call('xmlrpcBeforeNewPost', $this, $cur, $content, $struct, $publish);

            $post_id = dotclear()->blog()->posts()->addPost($cur);

            # --BEHAVIOR-- xmlrpcAfterNewPost
            dotclear()->behavior()->call('xmlrpcAfterNewPost', $this, $post_id, $cur, $content, $struct, $publish);
        } elseif ('page' == $cur->getField('post_type')) {
            if (isset($struct['wp_page_order'])) {
                $cur->setField('post_position', (int) $struct['wp_page_order']);
            }

            dotclear()->blog()->settings()->get('system')->set('post_url_format', '{t}');

            $post_id = dotclear()->blog()->posts()->addPost($cur);
        } else {
            throw new CoreException('Invalid post type', 401);
        }

        return (string) $post_id;
    }

    private function editPost($post_id, $user, $pwd, $content, $struct = [], $publish = true)
    {
        $post_id = (int) $post_id;

        $post_type = 'post';
        if (!empty($struct['post_type'])) {
            $post_type = $struct['post_type'];
        }

        $post = $this->getPostRS($post_id, $user, $pwd, $post_type);

        $title        = (!empty($struct['title'])) ? $struct['title'] : '';
        $excerpt      = (!empty($struct['mt_excerpt'])) ? $struct['mt_excerpt'] : '';
        $description  = (!empty($struct['description'])) ? $struct['description'] : null;
        $dateCreated  = !empty($struct['dateCreated']) ? $struct['dateCreated'] : null;
        $open_comment = (isset($struct['mt_allow_comments'])) ? $struct['mt_allow_comments'] : 1;
        $open_tb      = (isset($struct['mt_allow_pings'])) ? $struct['mt_allow_pings'] : 1;

        if ($description !== null) {
            $content = $description;
        }

        if (!$title) {
            $title = Text::cutString(Html::clean($content), 25) . '...';
        }

        $excerpt_xhtml = dotclear()->formater()->callEditorFormater('LegacyEditor', 'xhtml', $excerpt);
        $content_xhtml = dotclear()->formater()->callEditorFormater('LegacyEditor', 'xhtml', $content);

        if (empty($content)) {
            throw new CoreException('Cannot create an empty entry');
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

        $cur->setField('post_type', $post_type);
        $cur->setField('post_title', trim($title));
        $cur->setField('post_content', $content);
        $cur->setField('post_excerpt', $excerpt);
        $cur->setField('post_content_xhtml', $content_xhtml);
        $cur->setField('post_excerpt_xhtml', $excerpt_xhtml);
        $cur->setField('post_open_comment', (int) ($open_comment == 1));
        $cur->setField('post_open_tb', (int) ($open_tb == 1));
        $cur->setField('post_status', (int) $publish);
        $cur->setField('post_format', 'xhtml');
        $cur->setField('post_url', $post->f('post_url'));

        if ($dateCreated) {
            if ($dateCreated instanceof xmlrpcDate) {
                $cur->setField('post_dt', date('Y-m-d H:i:00', $dateCreated->getTimestamp()));
            } elseif (is_string($dateCreated) && @strtotime($dateCreated)) {
                $cur->setField('post_dt', date('Y-m-d H:i:00', strtotime($dateCreated)));
            }
        } else {
            $cur->setField('post_dt', $post->post_dt);
        }

        # Categories in an array
        if (isset($struct['categories']) && is_array($struct['categories'])) {
            $categories = $struct['categories'];
            $cat_id     = !empty($categories[0]) ? $categories[0] : null;

            $cur->setField('cat_id', $this->getCatID($cat_id));
        }

        if (isset($struct['wp_slug'])) {
            $cur->setField('post_url', $struct['wp_slug']);
        }

        if (isset($struct['wp_password'])) {
            $cur->setField('post_password', $struct['wp_password']);
        }

        if ('post' == $cur->getField('post_type')) {
            # --BEHAVIOR-- xmlrpcBeforeEditPost
            dotclear()->behavior()->call('xmlrpcBeforeEditPost', $this, $post_id, $cur, $content, $struct, $publish);

            dotclear()->blog()->posts()->updPost($post_id, $cur);

            # --BEHAVIOR-- xmlrpcAfterEditPost
            dotclear()->behavior()->call('xmlrpcAfterEditPost', $this, $post_id, $cur, $content, $struct, $publish);
        } elseif ('page' == $cur->getField('post_type')) {
            if (isset($struct['wp_page_order'])) {
                $cur->setField('post_position', (int) $struct['wp_page_order']);
            }

            dotclear()->blog()->settings()->get('system')->set('post_url_format', '{t}');

            dotclear()->blog()->posts()->updPost($post_id, $cur);
        } else {
            throw new CoreException('Invalid post type', 401);
        }

        return true;
    }

    private function getPost($post_id, $user, $pwd, $type = 'mw')
    {
        $post_id = (int) $post_id;

        $post = $this->getPostRS($post_id, $user, $pwd);

        $res = new ArrayObject();

        $res['dateCreated'] = new xmlrpcDate($post->getTS());
        $res['userid']      = $post->f('user_id');
        $res['postid']      = $post->f('post_id');

        if ($post->f('cat_id')) {
            $res['categories'] = [$post->f('cat_url')];
        }

        if ('blogger' == $type) {
            $res['content'] = $post->f('post_content_xhtml');
        }

        if ('mt' == $type || 'mw' == $type) {
            $res['title'] = $post->f('post_title');
        }

        if ('mw' == $type) {
            $res['description']       = $post->f('post_content_xhtml');
            $res['link']              = $res['permaLink']              = $post->getURL();
            $res['mt_excerpt']        = $post->f('post_excerpt_xhtml');
            $res['mt_text_more']      = '';
            $res['mt_allow_comments'] = (int) $post->f('post_open_comment');
            $res['mt_allow_pings']    = (int) $post->f('post_open_tb');
            $res['mt_convert_breaks'] = '';
            $res['mt_keywords']       = '';
        }

        # --BEHAVIOR-- xmlrpcGetPostInfo
        dotclear()->behavior()->call('xmlrpcGetPostInfo', $this, $type, [&$res]);

        return $res;
    }

    private function deletePost($post_id, $user, $pwd)
    {
        $post_id = (int) $post_id;

        $this->getPostRS($post_id, $user, $pwd);
        dotclear()->blog()->posts()->delPost($post_id);

        return true;
    }

    private function getRecentPosts($blog_id, $user, $pwd, $nb_post, $type = 'mw')
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $nb_post = (int) $nb_post;

        if ($nb_post > 50) {
            throw new CoreException('Cannot retrieve more than 50 entries');
        }

        $params          = [];
        $params['limit'] = $nb_post;

        $posts = dotclear()->blog()->posts()->getPosts($params);

        $res = [];
        while ($posts->fetch()) {
            $tres = [];

            $tres['dateCreated'] = new xmlrpcDate($posts->getTS());
            $tres['userid']      = $posts->f('ser_id');
            $tres['postid']      = $posts->fInt('post_id');

            if ($posts->f('cat_id')) {
                $tres['categories'] = [$posts->f('cat_url')];
            }

            if ('blogger' == $type) {
                $tres['content'] = $posts->f('post_content_xhtml');
            }

            if ('mt' == $type || 'mw' == $type) {
                $tres['title'] = $posts->f('post_title');
            }

            if ('mw' == $type) {
                $tres['description']       = $posts->f('post_content_xhtml');
                $tres['link']              = $tres['permaLink']              = $posts->getURL();
                $tres['mt_excerpt']        = $posts->f('post_excerpt_xhtml');
                $tres['mt_text_more']      = '';
                $tres['mt_allow_comments'] = (int) $posts->f('post_open_comment');
                $tres['mt_allow_pings']    = (int) $posts->f('post_open_tb');
                $tres['mt_convert_breaks'] = '';
                $tres['mt_keywords']       = '';
            }

            # --BEHAVIOR-- xmlrpcGetPostInfo
            dotclear()->behavior()->call('xmlrpcGetPostInfo', $this, $type, [&$tres]);

            $res[] = $tres;
        }

        return $res;
    }

    private function getUserBlogs($user, $pwd)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        return [[
            'url'      => dotclear()->blog()->url,
            'blogid'   => '1',
            'blogName' => dotclear()->blog()->name
        ]];
    }

    private function getUserInfo($user, $pwd)
    {
        $this->setUser($user, $pwd);

        return [
            'userid'    => dotclear()->user()->userID(),
            'firstname' => dotclear()->user()->getInfo('user_firstname'),
            'lastname'  => dotclear()->user()->getInfo('user_name'),
            'nickname'  => dotclear()->user()->getInfo('user_displayname'),
            'email'     => dotclear()->user()->getInfo('user_email'),
            'url'       => dotclear()->user()->getInfo('user_url')
        ];
    }

    private function getCategories($blog_id, $user, $pwd)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();
        $rs = dotclear()->blog()->categories()->getCategories();

        $res = [];

        $l      = $rs->fInt('level');
        $stack  = ['', $rs->f('cat_url')];
        $parent = '';

        while ($rs->fetch()) {
            $d = $rs->fInt('level') - $l;
            if ($d == 0) {
                array_pop($stack);
                $parent = end($stack);
            } elseif ($d > 0) {
                $parent = end($stack);
            } elseif ($d < 0) {
                $D = abs($d);
                for ($i = 0; $i <= $D; $i++) {
                    array_pop($stack);
                }
                $parent = end($stack);
            }

            $res[] = [
                'categoryId'   => $rs->f('cat_url'),
                'parentId'     => $parent,
                'description'  => $rs->f('cat_title'),
                'categoryName' => $rs->f('cat_url'),
                'htmlUrl'      => dotclear()->blog()->getURLFor('category', $rs->f('cat_url')),
                'rssUrl'       => dotclear()->blog()->getURLFor('feed', 'category/' . $rs->f('cat_url') . '/rss2')
            ];

            $stack[] = $rs->f('cat_url');
            $l       = $rs->fInt('level');
        }

        return $res;
    }

    private function getPostCategories($post_id, $user, $pwd)
    {
        $post_id = (int) $post_id;

        $post = $this->getPostRS($post_id, $user, $pwd);

        return [
            [
                'categoryName' => $post->f('cat_url'),
                'categoryId'   => (string) $post->f('cat_url'),
                'isPrimary'    => true
            ]
        ];
    }

    private function setPostCategories($post_id, $user, $pwd, $categories)
    {
        $post_id = (int) $post_id;

        $post = $this->getPostRS($post_id, $user, $pwd);

        $cat_id = (!empty($categories[0]['categoryId'])) ? $categories[0]['categoryId'] : null;

        foreach ($categories as $v) {
            if (isset($v['isPrimary']) && $v['isPrimary']) {
                $cat_id = $v['categoryId'];

                break;
            }
        }

        # w.bloggar sends -1 for no category.
        if ($cat_id == -1) {
            $cat_id = null;
        }

        if ($cat_id) {
            $cat_id = $this->getCatID($cat_id);
        }

        dotclear()->blog()->posts()->updPostCategory($post_id, (int) $cat_id);

        return true;
    }

    private function publishPost($post_id, $user, $pwd)
    {
        $post_id = (int) $post_id;

        $this->getPostRS($post_id, $user, $pwd);

        # --BEHAVIOR-- xmlrpcBeforePublishPost
        dotclear()->behavior()->call('xmlrpcBeforePublishPost', $this, $post_id);

        dotclear()->blog()->posts()->updPostStatus($post_id, 1);

        # --BEHAVIOR-- xmlrpcAfterPublishPost
        dotclear()->behavior()->call('xmlrpcAfterPublishPost', $this, $post_id);

        return true;
    }

    private function newMediaObject($blog_id, $user, $pwd, $file)
    {
        if (!dotclear()->media()) {
            throw new CoreException('No media path');
        }

        if (empty($file['name'])) {
            throw new CoreException('No file name');
        }

        if (empty($file['bits'])) {
            throw new CoreException('No file content');
        }

        $file_name = $file['name'];
        $file_bits = $file['bits'];

        $this->setUser($user, $pwd);
        $this->setBlog();

        $dir_name  = Path::clean(dirname($file_name));
        $file_name = basename($file_name);
        $dir_name  = preg_replace('!^/!', '', $dir_name);
        if ($dir_name != '') {
            $dir = explode('/', $dir_name);
            $cwd = './';
            foreach ($dir as $v) {
                $v = Files::tidyFileName($v);
                $cwd .= $v . '/';
                dotclear()->media()->makeDir($v);
                dotclear()->media()->chdir($cwd);
            }
        }

        $media_id = dotclear()->media()->uploadMediaBits($file_name, $file_bits);

        $f = dotclear()->media()->getFile($media_id);

        return [
            'file' => $file_name,
            'url'  => $f->file_url,
            'type' => Files::getMimeType($file_name)
        ];
    }

    private function translateWpStatus($s)
    {
        $status = [
            'draft'     => -2,
            'pending'   => -2,
            'private'   => 0,
            'publish'   => 1,
            'scheduled' => -1
        ];

        if (is_int($s)) {
            $status = array_flip($status);

            return $status[$s] ?? $status[-2];
        }

        return $status[$s] ?? $status['pending'];
    }

    private function translateWpCommentstatus($s)
    {
        $status = [
            'hold'    => -1,
            'approve' => 0,
            'spam'    => -2
        ];

        if (is_int($s)) {
            $status = array_flip($status);

            return $status[$s] ?? $status[0];
        }

        return $status[$s] ?? $status['approve'];
    }

    private function translateWpOptions($options = [])
    {
        $timezone = 0;
        if (dotclear()->blog()->settings()->get('system')->get('blog_timezone')) {
            $timezone = Dt::getTimeOffset(dotclear()->blog()->settings()->get('system')->get('blog_timezone')) / 3600;
        }

        $res = [
            'software_name' => [
                'desc'     => 'Software Name',
                'readonly' => true,
                'value'    => 'Dotclear'
            ],
            'software_version' => [
                'desc'     => 'Software Version',
                'readonly' => true,
                'value'    => dotclear()->config()->get('core_version')
            ],
            'blog_url' => [
                'desc'     => 'Blog URL',
                'readonly' => true,
                'value'    => dotclear()->blog()->url
            ],
            'time_zone' => [
                'desc'     => 'Time Zone',
                'readonly' => true,
                'value'    => (string) $timezone
            ],
            'blog_title' => [
                'desc'     => 'Blog Title',
                'readonly' => false,
                'value'    => dotclear()->blog()->name
            ],
            'blog_tagline' => [
                'desc'     => 'Blog Tagline',
                'readonly' => false,
                'value'    => dotclear()->blog()->desc
            ],
            'date_format' => [
                'desc'     => 'Date Format',
                'readonly' => false,
                'value'    => dotclear()->blog()->settings()->get('system')->get('date_format')
            ],
            'time_format' => [
                'desc'     => 'Time Format',
                'readonly' => false,
                'value'    => dotclear()->blog()->settings()->get('system')->get('time_format')
            ]
        ];

        if (!empty($options)) {
            $r = [];
            foreach ($options as $v) {
                if (isset($res[$v])) {
                    $r[$v] = $res[$v];
                }
            }

            return $r;
        }

        return $res;
    }

    private function getPostStatusList($blog_id, $user, $pwd)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        return [
            'draft'     => 'Draft',
            'pending'   => 'Pending Review',
            'private'   => 'Private',
            'publish'   => 'Published',
            'scheduled' => 'Scheduled'
        ];
    }

    private function getPageStatusList($blog_id, $user, $pwd)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();
        $this->checkPagesPermission();

        return [
            'draft'     => 'Draft',
            'private'   => 'Private',
            'published' => 'Published',
            'scheduled' => 'Scheduled'
        ];
    }

    private function checkPagesPermission()
    {
        if (!dotclear()->plugins()?->hasModule('Pages')) {
            throw new CoreException('Pages management is not available on this blog.');
        }

        if (!dotclear()->user()->check('pages,contentadmin', dotclear()->blog()->id)) {
            throw new CoreException('Not enough permissions to edit pages.', 401);
        }
    }

    private function getPages($blog_id, $user, $pwd, $limit = null, $id = null)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();
        $this->checkPagesPermission();

        $params = [
            'post_type' => 'page',
            'order'     => 'post_position ASC, post_title ASC'
        ];

        if ($id) {
            $params['post_id'] = (int) $id;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }

        $posts = dotclear()->blog()->posts()->getPosts($params);

        $res = [];
        while ($posts->fetch()) {
            $tres = [
                'dateCreated'            => new xmlrpcDate($posts->getTS()),
                'userid'                 => $posts->f('user_id'),
                'page_id'                => $posts->fInt('post_id'),
                'page_status'            => $this->translateWpStatus($posts->fInt('post_status')),
                'description'            => $posts->f('post_content_xhtml'),
                'title'                  => $posts->f('post_title'),
                'link'                   => $posts->getURL(),
                'permaLink'              => $posts->getURL(),
                'categories'             => [],
                'excerpt'                => $posts->f('post_excerpt_xhtml'),
                'text_more'              => '',
                'mt_allow_comments'      => (int) $posts->f('post_open_comment'),
                'mt_allow_pings'         => (int) $posts->f('post_open_tb'),
                'wp_slug'                => $posts->f('post_url'),
                'wp_password'            => $posts->f('post_password'),
                'wp_author'              => $posts->getAuthorCN(),
                'wp_page_parent_id'      => 0,
                'wp_page_parent_title'   => '',
                'wp_page_order'          => $posts->f('post_position'),
                'wp_author_id'           => $posts->f('user_id'),
                'wp_author_display_name' => $posts->getAuthorCN(),
                'date_created_gmt'       => new xmlrpcDate(Dt::iso8601($posts->getTS(), $posts->f('post_tz'))),
                'custom_fields'          => [],
                'wp_page_template'       => 'default'
            ];

            # --BEHAVIOR-- xmlrpcGetPageInfo
            dotclear()->behavior()->call('xmlrpcGetPageInfo', $this, [&$tres]);

            $res[] = $tres;
        }

        return $res;
    }

    private function newPage($blog_id, $user, $pwd, $struct, $publish)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();
        $this->checkPagesPermission();

        $struct['post_type'] = 'page';

        return $this->newPost($blog_id, $user, $pwd, null, $struct, $publish);
    }

    private function editPage($page_id, $user, $pwd, $struct, $publish)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();
        $this->checkPagesPermission();

        $struct['post_type'] = 'page';

        return $this->editPost($page_id, $user, $pwd, null, $struct, $publish);
    }

    private function deletePage($page_id, $user, $pwd)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();
        $this->checkPagesPermission();

        $page_id = (int) $page_id;

        $this->getPostRS($page_id, $user, $pwd, 'page');
        dotclear()->blog()->posts()->delPost($page_id);

        return true;
    }

    private function getAuthors($user, $pwd)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $rs  = dotclear()->blogs()->getBlogPermissions(dotclear()->blog()->id);
        $res = [];

        foreach ($rs as $k => $v) {
            $res[] = [
                'user_id'      => $k,
                'user_login'   => $k,
                'display_name' => UserContainer::getUserCN($k, $v['name'], $v['firstname'], $v['displayname'])
            ];
        }

        return $res;
    }

    private function getTags($user, $pwd)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $tags = dotclear()->meta()->getMetadata(['meta_type' => 'tag']);
        $tags = dotclear()->meta()->computeMetaStats($tags);
        $tags->sort('meta_id_lower', 'asc');

        $res = [];
        while ($tags->fetch()) {
            $res[] = [
                'tag_id'   => $tags->f('meta_id'),
                'name'     => $tags->f('meta_id'),
                'count'    => $tags->f('count'),
                'slug'     => $tags->f('meta_id'),
                'html_url' => sprintf(dotclear()->blog()->getURLFor('tag', '%s'), $tags->f('meta_id')),
                'rss_url'  => sprintf(dotclear()->blog()->getURLFor('tag_feed', '%s'), $tags->f('meta_id'))
            ];
        }

        return $res;
    }

    private function newCategory($user, $pwd, $struct)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        if (empty($struct['name'])) {
            throw new CoreException('You mus give a category name.');
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'category');
        $cur->setField('cat_title', $struct['name']);

        if (!empty($struct['slug'])) {
            $cur->setField('cat_url', $struct['slug']);
        }
        if (!empty($struct['category_description'])) {
            $cur->setField('cat_desc', $struct['category_description']);
            if (Html::clean($cur->getField('cat_desc')) == $cur->getField('cat_desc')) {
                $cur->setField('cat_desc', '<p>' . $cur->getField('cat_desc') . '</p>');
            }
        }

        $parent = !empty($struct['category_parent']) ? (int) $struct['category_parent'] : 0;

        $id = dotclear()->blog()->categories()->addCategory($cur, $parent);
        $rs = dotclear()->blog()->categories()->getCategory($id);

        return $rs->f('cat_url');
    }

    private function deleteCategory($user, $pwd, $cat_id)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $rs = dotclear()->blog()->categories()->getCategories(['cat_url' => $cat_id]);
        if ($rs->isEmpty()) {
            throw new CoreException(__('This category does not exist.'));
        }
        $cat_id = $rs->fInt('cat_id');
        unset($rs);

        dotclear()->blog()->categories()->delCategory($cat_id);

        return true;
    }

    private function searchCategories($user, $pwd, $category, $limit)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $strReq = 'SELECT cat_id, cat_title, cat_url ' .
        'FROM ' . dotclear()->prefix . 'category ' .
        "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
        "AND LOWER(cat_title) LIKE LOWER('%" . dotclear()->con()->escape($category) . "%') " .
            ($limit > 0 ? dotclear()->con()->limit($limit) : '');

        $rs = dotclear()->con()->select($strReq);

        $res = [];
        while ($rs->fetch()) {
            $res[] = [
                'category_id'   => $rs->f('cat_url'),
                'category_name' => $rs->f('cat_url')
            ];
        }

        return $res;
    }

    private function countComments($user, $pwd, $post_id)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $res = [
            'approved'            => 0,
            'awaiting_moderation' => 0,
            'spam'                => 0,
            'total'               => 0
        ];
        $rs = dotclear()->blog()->comments()->getComments(['post_id' => $post_id]);

        while ($rs->fetch()) {
            $res['total']++;
            if ($rs->fInt('comment_status') == 1) {
                $res['approved']++;
            } elseif ($rs->fInt('comment_status') == -2) {
                $res['spam']++;
            } else {
                $res['awaiting_moderation']++;
            }
        }

        return $res;
    }

    private function getComments($user, $pwd, $struct, $id = null)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $params = [];

        if (!empty($struct['status'])) {
            $params['comment_status'] = $this->translateWpCommentstatus($struct['status']);
        }

        if (!empty($struct['post_id'])) {
            $params['post_id'] = (int) $struct['post_id'];
        }

        if (isset($id)) {
            $params['comment_id'] = $id;
        }

        $offset          = !empty($struct['offset']) ? (int) $struct['offset'] : 0;
        $limit           = !empty($struct['number']) ? (int) $struct['number'] : 10;
        $params['limit'] = [$offset, $limit];

        $rs  = dotclear()->blog()->comments()->getComments($params);
        $res = [];
        while ($rs->fetch()) {
            $res[] = [
                'date_created_gmt' => new xmlrpcDate($rs->getTS()),
                'user_id'          => $rs->f('user_id'),
                'comment_id'       => $rs->f('comment_id'),
                'parent'           => 0,
                'status'           => $this->translateWpCommentstatus($rs->fInt('comment_status')),
                'content'          => $rs->f('comment_content'),
                'link'             => $rs->getPostURL() . '#c' . $rs->f('comment_id'),
                'post_id'          => $rs->fInt('post_id'),
                'post_title'       => $rs->f('post_title'),
                'author'           => $rs->f('comment_author'),
                'author_url'       => $rs->f('comment_site'),
                'author_email'     => $rs->f('comment_email'),
                'author_ip'        => $rs->f('comment_ip')
            ];
        }

        return $res;
    }

    private function addComment($user, $pwd, $post_id, $struct)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        if (empty($struct['content'])) {
            throw new CoreException('Sorry, you cannot post an empty comment', 401);
        }

        if (is_numeric($post_id)) {
            $p['post_id'] = $post_id;
        } else {
            $p['post_url'] = $post_id;
        }
        $rs = dotclear()->blog()->posts()->getPosts($p);
        if ($rs->isEmpty()) {
            throw new CoreException('Sorry, no such post.', 404);
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');

        $cur->setField('comment_author', dotclear()->user()->userCN());
        $cur->setField('comment_email', dotclear()->user()->getInfo('user_email'));
        $cur->setField('comment_site', dotclear()->user()->getInfo('user_url'));

        $cur->setField('comment_content', $struct['content']);
        $cur->setField('post_id', (int) $post_id);

        $id = dotclear()->blog()->comments()->addComment($cur);

        return $id;
    }

    private function updComment($user, $pwd, $comment_id, $struct)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');

        if (isset($struct['status'])) {
            $cur->setField('comment_status', $this->translateWpCommentstatus($struct['status']));
        }

        if (isset($struct['date_created_gmt'])) {
            if ($struct['date_created_gmt'] instanceof xmlrpcDate) {
                $cur->setField('comment_dt', date('Y-m-d H:i:00', $struct['date_created_gmt']->getTimestamp()));
            } elseif (is_string($struct['date_created_gmt']) && @strtotime($struct['date_created_gmt'])) {
                $cur->setField('comment_dt', date('Y-m-d H:i:00', strtotime($struct['date_created_gmt'])));
            }
            $cur->setField('comment_dt', $struct['date_created_gmt']);
        }

        if (isset($struct['content'])) {
            $cur->setField('comment_content', $struct['content']);
        }

        if (isset($struct['author'])) {
            $cur->setField('comment_author', $struct['author']);
        }

        if (isset($struct['author_url'])) {
            $cur->setField('comment_site', $struct['author_url']);
        }

        if (isset($struct['author_email'])) {
            $cur->setField('comment_email', $struct['author_email']);
        }

        dotclear()->blog()->comments()->updComment($comment_id, $cur);

        return true;
    }

    private function delComment($user, $pwd, $comment_id)
    {
        $this->setUser($user, $pwd);
        $this->setBlog();

        dotclear()->blog()->comments()->delComment($comment_id);

        return true;
    }

    /* Blogger methods
    --------------------------------------------------- */
    public function blogger_newPost($appkey, $blogid, $username, $password, $content, $publish)
    {
        return $this->newPost($blogid, $username, $password, $content, [], $publish);
    }

    public function blogger_editPost($appkey, $postid, $username, $password, $content, $publish)
    {
        return $this->editPost($postid, $username, $password, $content, [], $publish);
    }

    public function blogger_getPost($appkey, $postid, $username, $password)
    {
        return $this->getPost($postid, $username, $password, 'blogger');
    }

    public function blogger_deletePost($appkey, $postid, $username, $password, $publish)
    {
        return $this->deletePost($postid, $username, $password);
    }

    public function blogger_getRecentPosts($appkey, $blogid, $username, $password, $numberOfPosts)
    {
        return $this->getRecentPosts($blogid, $username, $password, $numberOfPosts, 'blogger');
    }

    public function blogger_getUserBlogs($appkey, $username, $password)
    {
        return $this->getUserBlogs($username, $password);
    }

    public function blogger_getUserInfo($appkey, $username, $password)
    {
        return $this->getUserInfo($username, $password);
    }

    /* Metaweblog methods
    ------------------------------------------------------- */
    public function mw_newPost($blogid, $username, $password, $content, $publish)
    {
        return $this->newPost($blogid, $username, $password, '', $content, $publish);
    }

    public function mw_editPost($postid, $username, $password, $content, $publish)
    {
        return $this->editPost($postid, $username, $password, '', $content, $publish);
    }

    public function mw_getPost($postid, $username, $password)
    {
        return $this->getPost($postid, $username, $password, 'mw');
    }

    public function mw_getRecentPosts($blogid, $username, $password, $numberOfPosts)
    {
        return $this->getRecentPosts($blogid, $username, $password, $numberOfPosts, 'mw');
    }

    public function mw_getCategories($blogid, $username, $password)
    {
        return $this->getCategories($blogid, $username, $password);
    }

    public function mw_newMediaObject($blogid, $username, $password, $file)
    {
        return $this->newMediaObject($blogid, $username, $password, $file);
    }

    /* MovableType methods
    --------------------------------------------------- */
    public function mt_getRecentPostTitles($blogid, $username, $password, $numberOfPosts)
    {
        return $this->getRecentPosts($blogid, $username, $password, $numberOfPosts, 'mt');
    }

    public function mt_getCategoryList($blogid, $username, $password)
    {
        return $this->getCategories($blogid, $username, $password);
    }

    public function mt_getPostCategories($postid, $username, $password)
    {
        return $this->getPostCategories($postid, $username, $password);
    }

    public function mt_setPostCategories($postid, $username, $password, $categories)
    {
        return $this->setPostCategories($postid, $username, $password, $categories);
    }

    public function mt_publishPost($postid, $username, $password)
    {
        return $this->publishPost($postid, $username, $password);
    }

    public function mt_supportedTextFilters()
    {
        return [];
    }

    /* WordPress methods
    --------------------------------------------------- */
    public function wp_getUsersBlogs($username, $password)
    {
        return $this->getUserBlogs($username, $password);
    }

    public function wp_getPage($blogid, $pageid, $username, $password)
    {
        $res = $this->getPages($blogid, $username, $password, null, $pageid);

        if (empty($res)) {
            throw new CoreException('Sorry, no such page', 404);
        }

        return $res[0];
    }

    public function wp_getPages($blogid, $username, $password, $num = 10)
    {
        return $this->getPages($blogid, $username, $password, $num);
    }

    public function wp_newPage($blogid, $username, $password, $content, $publish)
    {
        return $this->newPage($blogid, $username, $password, $content, $publish);
    }

    public function wp_deletePage($blogid, $username, $password, $pageid)
    {
        return $this->deletePage($pageid, $username, $password);
    }

    public function wp_editPage($blogid, $pageid, $username, $password, $content, $publish)
    {
        return $this->editPage($pageid, $username, $password, $content, $publish);
    }

    public function wp_getPageList($blogid, $username, $password)
    {
        $A   = $this->getPages($blogid, $username, $password);
        $res = [];
        foreach ($A as $v) {
            $res[] = [
                'page_id'          => $v['page_id'],
                'page_title'       => $v['title'],
                'page_parent_id'   => $v['wp_page_parent_id'],
                'dateCreated'      => $v['dateCreated'],
                'date_created_gmt' => $v['date_created_gmt']
            ];
        }

        return $res;
    }

    public function wp_getAuthors($blogid, $username, $password)
    {
        return $this->getAuthors($username, $password);
    }

    public function wp_getCategories($blogid, $username, $password)
    {
        return $this->getCategories($blogid, $username, $password);
    }

    public function wp_getTags($blogid, $username, $password)
    {
        return $this->getTags($username, $password);
    }

    public function wp_newCategory($blogid, $username, $password, $content)
    {
        return $this->newCategory($username, $password, $content);
    }

    public function wp_deleteCategory($blogid, $username, $password, $categoryid)
    {
        return $this->deleteCategory($username, $password, $categoryid);
    }

    public function wp_suggestCategories($blogid, $username, $password, $category, $max_results = 0)
    {
        return $this->searchCategories($username, $password, $category, $max_results);
    }

    public function wp_uploadFile($blogid, $username, $password, $file)
    {
        return $this->newMediaObject($blogid, $username, $password, $file);
    }

    public function wp_getPostStatusList($blogid, $username, $password)
    {
        return $this->getPostStatusList($blogid, $username, $password);
    }

    public function wp_getPageStatusList($blogid, $username, $password)
    {
        return $this->getPostStatusList($blogid, $username, $password);
    }

    public function wp_getPageTemplates($blogid, $username, $password)
    {
        return ['Default' => 'default'];
    }

    public function wp_getOptions($blogid, $username, $password, $options = [])
    {
        $this->setUser($username, $password);
        $this->setBlog();

        return $this->translateWpOptions($options);
    }

    public function wp_setOptions($blogid, $username, $password, $options)
    {
        $this->setUser($username, $password);
        $this->setBlog();

        if (!dotclear()->user()->check('admin', dotclear()->blog()->id)) {
            throw new CoreException('Not enough permissions to edit options.', 401);
        }

        $opt = $this->translateWpOptions();

        $done         = [];
        $blog_changes = false;
        $cur          = dotclear()->con()->openCursor(dotclear()->prefix . 'blog');

        foreach ($options as $name => $value) {
            if (!isset($opt[$name]) || $opt[$name]['readonly']) {
                continue;
            }

            switch ($name) {
                case 'blog_title':
                    $blog_changes   = true;
                    $cur->setField('blog_name', $value);
                    $done[]         = $name;

                    break;
                case 'blog_tagline':
                    $blog_changes   = true;
                    $cur->setField('blog_desc', $value);
                    $done[]         = $name;

                    break;
                case 'date_format':
                    dotclear()->blog()->settings()->get('system')->put('date_format', $value);
                    $done[] = $name;

                    break;
                case 'time_format':
                    dotclear()->blog()->settings()->get('system')->put('time_format', $value);
                    $done[] = $name;

                    break;
            }
        }

        if ($blog_changes) {
            dotclear()->blogs()->updBlog(dotclear()->blog()->id, $cur);
            dotclear()->setBlog(dotclear()->blog()->id);
        }

        return $this->translateWpOptions($done);
    }

    public function wp_getComment($blogid, $username, $password, $commentid)
    {
        $res = $this->getComments($username, $password, [], $commentid);

        if (empty($res)) {
            throw new CoreException('Sorry, no such comment', 404);
        }

        return $res[0];
    }

    public function wp_getCommentCount($blogid, $username, $password, $postid)
    {
        return $this->countComments($username, $password, $postid);
    }

    public function wp_getComments($blogid, $username, $password, $struct)
    {
        return $this->getComments($username, $password, $struct);
    }

    public function wp_deleteComment($blogid, $username, $password, $commentid)
    {
        return $this->delComment($username, $password, $commentid);
    }

    public function wp_editComment($blogid, $username, $password, $commentid, $content)
    {
        return $this->updComment($username, $password, $commentid, $content);
    }

    public function wp_newComment($blogid, $username, $password, $postid, $content)
    {
        return $this->addComment($username, $password, $postid, $content);
    }

    public function wp_getCommentStatusList($blogid, $username, $password)
    {
        $this->setUser($username, $password);
        $this->setBlog();

        return [
            'hold'    => 'Unapproved',
            'approve' => 'Approved',
            'spam'    => 'Spam'
        ];
    }

    /* Pingback support
    --------------------------------------------------- */
    public function pingback_ping($from_url, $to_url)
    {
        $trackback = new Trackback();
        $trackback->checkURLs($from_url, $to_url);

        $args = ['type' => 'pingback', 'from_url' => $from_url, 'to_url' => $to_url];

        # Time to get things done...
        $this->setBlog(true);

        # --BEHAVIOR-- publicBeforeReceiveTrackback
        dotclear()->behavior()->call('publicBeforeReceiveTrackback', $args);

        return $trackback->receivePingback($from_url, $to_url);
    }
}
