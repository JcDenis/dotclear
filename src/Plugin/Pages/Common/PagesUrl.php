<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Common;

// Dotclear\Plugin\Pages\Common\PagesUrl
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Url\Url;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\HtmlFilter;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;
use Exception;

/**
 * URL methods for plugin Pages.
 *
 * @ingroup  Plugin Pages Url
 */
class PagesUrl extends Url
{
    public function __construct()
    {
        App::core()->url()->register('pages', 'pages', '^pages/(.+)$', [$this, 'pages']);
        App::core()->url()->register('pagespreview', 'pagespreview', '^pagespreview/(.+)$', [$this, 'pagespreview']);

        App::core()->posttype()->setPostType('page', '?handler=admin.plugin.Page&id=%d', App::core()->url()->getURLFor('pages', '%s'), __('Pages'));
    }

    public function pages(string $args): void
    {
        if ('' == $args) {
            // No page was specified.
            App::core()->url()->p404();
        } else {
            App::core()->blog()->withoutPassword(false);

            $params = new ArrayObject([
                'post_type' => 'page',
                'post_url'  => $args, ]);

            App::core()->behavior()->call('publicPagesBeforeGetPosts', $params, $args);

            App::core()->context()->set('posts', App::core()->blog()->posts()->getPosts($params));

            $cp               = new ArrayObject();
            $cp['content']    = '';
            $cp['rawcontent'] = '';
            $cp['name']       = '';
            $cp['mail']       = '';
            $cp['site']       = '';
            $cp['preview']    = false;
            $cp['remember']   = false;
            App::core()->context()->set('comment_preview', $cp);

            App::core()->blog()->withoutPassword(true);

            if (App::core()->context()->get('posts')->isEmpty()) {
                // The specified page does not exist.
                App::core()->url()->p404();
            } else {
                $post_id       = App::core()->context()->get('posts')->fInt('post_id');
                $post_password = App::core()->context()->get('posts')->f('post_password');

                // Password protected entry
                if ('' != $post_password && !App::core()->context()->get('preview')) {
                    // Get passwords cookie
                    if (isset($_COOKIE['dc_passwd'])) {
                        $pwd_cookie = json_decode($_COOKIE['dc_passwd']);
                        if (null === $pwd_cookie) {
                            $pwd_cookie = [];
                        } else {
                            $pwd_cookie = (array) $pwd_cookie;
                        }
                    } else {
                        $pwd_cookie = [];
                    }

                    // Check for match
                    // Note: We must prefix post_id key with '#'' in pwd_cookie array in order to avoid integer conversion
                    // because MyArray["12345"] is treated as MyArray[12345]
                    if ((!empty($_POST['password']) && $_POST['password'] == $post_password)
                        || (isset($pwd_cookie['#' . $post_id]) && $pwd_cookie['#' . $post_id] == $post_password)) {
                        $pwd_cookie['#' . $post_id] = $post_password;
                        setcookie('dc_passwd', json_encode($pwd_cookie), 0, '/');
                    } else {
                        App::core()->url()->serveDocument('password-form.html', 'text/html', false);

                        return;
                    }
                }

                $post_comment = isset($_POST['c_name'], $_POST['c_mail'], $_POST['c_site'], $_POST['c_content']) && App::core()->context()->get('posts')->commentsActive();

                // Posting a comment
                if ($post_comment) {
                    // Spam trap
                    if (!empty($_POST['f_mail'])) {
                        Http::head(412, 'Precondition Failed');
                        header('Content-Type: text/plain');
                        echo 'So Long, and Thanks For All the Fish';
                        // Exits immediately the application to preserve the server.
                        exit;
                    }

                    $name    = $_POST['c_name'];
                    $mail    = $_POST['c_mail'];
                    $site    = $_POST['c_site'];
                    $content = $_POST['c_content'];
                    $preview = !empty($_POST['preview']);

                    if ('' != $content) {
                        // --BEHAVIOR-- publicBeforeCommentTransform
                        $buffer = App::core()->behavior()->call('publicBeforeCommentTransform', $content);
                        if ('' != $buffer) {
                            $content = $buffer;
                        } else {
                            if (App::core()->blog()->settings()->get('system')->get('wiki_comments')) {
                                App::core()->wiki()->initWikiComment();
                            } else {
                                App::core()->wiki()->initWikiSimpleComment();
                            }
                            $content = App::core()->wiki()->wikiTransform($content);
                        }
                        $content = new HtmlFilter($content);
                    }

                    $cp               = App::core()->context()->get('comment_preview');
                    $cp['content']    = $content;
                    $cp['rawcontent'] = $_POST['c_content'];
                    $cp['name']       = $name;
                    $cp['mail']       = $mail;
                    $cp['site']       = $site;

                    if ($preview) {
                        // --BEHAVIOR-- publicBeforeCommentPreview
                        App::core()->behavior()->call('publicBeforeCommentPreview', $cp);

                        $cp['preview'] = true;
                    } else {
                        // Post the comment
                        $cur = App::core()->con()->openCursor(App::core()->prefix . 'comment');
                        $cur->setField('comment_author', $name);
                        $cur->setField('comment_site', Html::clean($site));
                        $cur->setField('comment_email', Html::clean($mail));
                        $cur->setField('comment_content', $content);
                        $cur->setField('post_id', App::core()->context()->get('posts')->fInt('post_id'));
                        $cur->setField('comment_status', App::core()->blog()->settings()->get('system')->get('comments_pub') ? 1 : -1);
                        $cur->setField('comment_ip', Http::realIP());

                        $redir = App::core()->context()->get('posts')->getURL();
                        $redir .= App::core()->blog()->settings()->get('system')->get('url_scan') == 'query_string' ? '&' : '?';

                        try {
                            if (!Text::isEmail($cur->getField('comment_email'))) {
                                throw new AdminException(__('You must provide a valid email address.'));
                            }

                            // --BEHAVIOR-- publicBeforeCommentCreate
                            App::core()->behavior()->call('publicBeforeCommentCreate', $cur);
                            if ($cur->getField('post_id')) {
                                $comment_id = App::core()->blog()->comments()->addComment($cur);

                                // --BEHAVIOR-- publicAfterCommentCreate
                                App::core()->behavior()->call('publicAfterCommentCreate', $cur, $comment_id);
                            }

                            if (1 == $cur->getField('comment_status')) {
                                $redir_arg = 'pub=1';
                            } else {
                                $redir_arg = 'pub=0';
                            }

                            header('Location: ' . $redir . $redir_arg);
                        } catch (Exception $e) {
                            App::core()->context()->set('form_error', $e->getMessage());
                        }
                    }
                    App::core()->context()->set('comment_preview', $cp);
                }

                // The entry
                if (App::core()->context()->get('posts')->trackbacksActive()) {
                    header('X-Pingback: ' . App::core()->blog()->getURLFor('xmlrpc', App::core()->blog()->id));
                }

                // Serve page
                App::core()->url()->serveDocument('page.html');
            }
        }
    }

    public function pagespreview(string $args): void
    {
        if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#', $args, $m)) {
            // The specified Preview URL is malformed.
            App::core()->url()->p404();
        } else {
            $user_id  = $m[1];
            $user_key = $m[2];
            $post_url = $m[3];
            if (!App::core()->user()->checkUser($user_id, null, $user_key)) {
                // The user has no access to the entry.
                App::core()->url()->p404();
            } else {
                App::core()->user()->preview = true;
                if (App::core()->processed('Admin')) {
                    App::core()->user()->xframeoption = App::core()->config()->get('admin_url');
                }

                $this->pages($post_url);
            }
        }
    }
}
