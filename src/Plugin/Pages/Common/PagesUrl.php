<?php
/**
 * @note Dotclear\Plugin\Pages\Common\PagesUrl
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Common;

use ArrayObject;
use Dotclear\Core\Url\Url;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\HtmlFilter;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;
use Exception;

class PagesUrl extends Url
{
    public function __construct()
    {
        dotclear()->url()->register('pages', 'pages', '^pages/(.+)$', [$this, 'pages']);
        dotclear()->url()->register('pagespreview', 'pagespreview', '^pagespreview/(.+)$', [$this, 'pagespreview']);

        dotclear()->posttype()->setPostType('page', '?handler=admin.plugin.Page&id=%d', dotclear()->url()->getURLFor('pages', '%s'), 'Pages');
    }

    public function pages(string $args): void
    {
        if ('' == $args) {
            // No page was specified.
            dotclear()->url()->p404();
        } else {
            dotclear()->blog()->withoutPassword(false);

            $params = new ArrayObject([
                'post_type' => 'page',
                'post_url'  => $args, ]);

            dotclear()->behavior()->call('publicPagesBeforeGetPosts', $params, $args);

            dotclear()->context()->set('posts', dotclear()->blog()->posts()->getPosts($params));

            $cp               = new ArrayObject();
            $cp['content']    = '';
            $cp['rawcontent'] = '';
            $cp['name']       = '';
            $cp['mail']       = '';
            $cp['site']       = '';
            $cp['preview']    = false;
            $cp['remember']   = false;
            dotclear()->context()->set('comment_preview', $cp);

            dotclear()->blog()->withoutPassword(true);

            if (dotclear()->context()->get('posts')->isEmpty()) {
                // The specified page does not exist.
                dotclear()->url()->p404();
            } else {
                $post_id       = dotclear()->context()->get('posts')->fInt('post_id');
                $post_password = dotclear()->context()->get('posts')->f('post_password');

                // Password protected entry
                if ('' != $post_password && !dotclear()->context()->get('preview')) {
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
                        dotclear()->url()->serveDocument('password-form.html', 'text/html', false);

                        return;
                    }
                }

                $post_comment = isset($_POST['c_name'], $_POST['c_mail'], $_POST['c_site'], $_POST['c_content']) && dotclear()->context()->get('posts')->commentsActive();

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
                        $buffer = dotclear()->behavior()->call('publicBeforeCommentTransform', $content);
                        if ('' != $buffer) {
                            $content = $buffer;
                        } else {
                            if (dotclear()->blog()->settings()->get('system')->get('wiki_comments')) {
                                dotclear()->wiki()->initWikiComment();
                            } else {
                                dotclear()->wiki()->initWikiSimpleComment();
                            }
                            $content = dotclear()->wiki()->wikiTransform($content);
                        }
                        $content = new HtmlFilter($content);
                    }

                    $cp               = dotclear()->context()->get('comment_preview');
                    $cp['content']    = $content;
                    $cp['rawcontent'] = $_POST['c_content'];
                    $cp['name']       = $name;
                    $cp['mail']       = $mail;
                    $cp['site']       = $site;

                    if ($preview) {
                        // --BEHAVIOR-- publicBeforeCommentPreview
                        dotclear()->behavior()->call('publicBeforeCommentPreview', $cp);

                        $cp['preview'] = true;
                    } else {
                        // Post the comment
                        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');
                        $cur->setField('comment_author', $name);
                        $cur->setField('comment_site', Html::clean($site));
                        $cur->setField('comment_email', Html::clean($mail));
                        $cur->setField('comment_content', $content);
                        $cur->setField('post_id', dotclear()->context()->get('posts')->fInt('post_id'));
                        $cur->setField('comment_status', dotclear()->blog()->settings()->get('system')->get('comments_pub') ? 1 : -1);
                        $cur->setField('comment_ip', Http::realIP());

                        $redir = dotclear()->context()->get('posts')->getURL();
                        $redir .= dotclear()->blog()->settings()->get('system')->get('url_scan') == 'query_string' ? '&' : '?';

                        try {
                            if (!Text::isEmail($cur->getField('comment_email'))) {
                                throw new AdminException(__('You must provide a valid email address.'));
                            }

                            // --BEHAVIOR-- publicBeforeCommentCreate
                            dotclear()->behavior()->call('publicBeforeCommentCreate', $cur);
                            if ($cur->getField('post_id')) {
                                $comment_id = dotclear()->blog()->comments()->addComment($cur);

                                // --BEHAVIOR-- publicAfterCommentCreate
                                dotclear()->behavior()->call('publicAfterCommentCreate', $cur, $comment_id);
                            }

                            if (1 == $cur->getField('comment_status')) {
                                $redir_arg = 'pub=1';
                            } else {
                                $redir_arg = 'pub=0';
                            }

                            header('Location: ' . $redir . $redir_arg);
                        } catch (Exception $e) {
                            dotclear()->context()->set('form_error', $e->getMessage());
                        }
                    }
                    dotclear()->context()->set('comment_preview', $cp);
                }

                // The entry
                if (dotclear()->context()->get('posts')->trackbacksActive()) {
                    header('X-Pingback: ' . dotclear()->blog()->getURLFor('xmlrpc', dotclear()->blog()->id));
                }

                // Serve page
                dotclear()->url()->serveDocument('page.html');
            }
        }
    }

    public function pagespreview(string $args): void
    {
        if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#', $args, $m)) {
            // The specified Preview URL is malformed.
            dotclear()->url()->p404();
        } else {
            $user_id  = $m[1];
            $user_key = $m[2];
            $post_url = $m[3];
            if (!dotclear()->user()->checkUser($user_id, null, $user_key)) {
                // The user has no access to the entry.
                dotclear()->url()->p404();
            } else {
                dotclear()->user()->preview = true;
                if (dotclear()->processed('Admin')) {
                    dotclear()->user()->xframeoption = dotclear()->config()->get('admin_url');
                }

                $this->pages($post_url);
            }
        }
    }
}
