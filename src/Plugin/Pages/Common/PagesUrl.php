<?php
/**
 * @class Dotclear\Plugin\Pages\Common\PagesUrl
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Common;

use ArrayObject;

use Dotclear\Core\Url\Url;
use Dotclear\Html\Html;
use Dotclear\Html\HtmlFilter;
use Dotclear\Exception\AdminException;
use Dotclear\Network\Http;
use Dotclear\Utils\Text;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class PagesUrl extends Url
{
    public static function initPages()
    {
        dotclear()->url()->register('pages', 'pages', '^pages/(.+)$', [__CLASS__, 'pages']);
        dotclear()->url()->register('pagespreview', 'pagespreview', '^pagespreview/(.+)$', [__CLASS__, 'pagespreview']);

        dotclear()->posttype()->setPostType('page', '?handler=admin.plugin.Page&id=%d', dotclear()->url()->getURLFor('pages', '%s'), 'Pages');
    }

    public static function pages($args)
    {
        if ($args == '') {
            # No page was specified.
            dotclear()->url()->p404();
        } else {
            dotclear()->blog()->withoutPassword(false);

            $params = new ArrayObject([
                'post_type' => 'page',
                'post_url'  => $args, ]);

            dotclear()->behavior()->call('publicPagesBeforeGetPosts', $params, $args);

            dotclear()->context()->posts = dotclear()->blog()->posts()->getPosts($params);

            dotclear()->context()->comment_preview               = new ArrayObject();
            dotclear()->context()->comment_preview['content']    = '';
            dotclear()->context()->comment_preview['rawcontent'] = '';
            dotclear()->context()->comment_preview['name']       = '';
            dotclear()->context()->comment_preview['mail']       = '';
            dotclear()->context()->comment_preview['site']       = '';
            dotclear()->context()->comment_preview['preview']    = false;
            dotclear()->context()->comment_preview['remember']   = false;

            dotclear()->blog()->withoutPassword(true);

            if (dotclear()->context()->posts->isEmpty()) {
                # The specified page does not exist.
                dotclear()->url()->p404();
            } else {
                $post_id       = dotclear()->context()->posts->post_id;
                $post_password = dotclear()->context()->posts->post_password;

                # Password protected entry
                if ($post_password != '' && !dotclear()->context()->preview) {
                    # Get passwords cookie
                    if (isset($_COOKIE['dc_passwd'])) {
                        $pwd_cookie = json_decode($_COOKIE['dc_passwd']);
                        if ($pwd_cookie === null) {
                            $pwd_cookie = [];
                        } else {
                            $pwd_cookie = (array) $pwd_cookie;
                        }
                    } else {
                        $pwd_cookie = [];
                    }

                    # Check for match
                    # Note: We must prefix post_id key with '#'' in pwd_cookie array in order to avoid integer conversion
                    # because MyArray["12345"] is treated as MyArray[12345]
                    if ((!empty($_POST['password']) && $_POST['password'] == $post_password)
                        || (isset($pwd_cookie['#' . $post_id]) && $pwd_cookie['#' . $post_id] == $post_password)) {
                        $pwd_cookie['#' . $post_id] = $post_password;
                        setcookie('dc_passwd', json_encode($pwd_cookie), 0, '/');
                    } else {
                        dotclear()->url()->serveDocument('password-form.html', 'text/html', false);

                        return;
                    }
                }

                $post_comment = isset($_POST['c_name']) && isset($_POST['c_mail']) && isset($_POST['c_site']) && isset($_POST['c_content']) && dotclear()->context()->posts->commentsActive();

                # Posting a comment
                if ($post_comment) {
                    # Spam trap
                    if (!empty($_POST['f_mail'])) {
                        Http::head(412, 'Precondition Failed');
                        header('Content-Type: text/plain');
                        echo 'So Long, and Thanks For All the Fish';
                        # Exits immediately the application to preserve the server.
                        exit;
                    }

                    $name    = $_POST['c_name'];
                    $mail    = $_POST['c_mail'];
                    $site    = $_POST['c_site'];
                    $content = $_POST['c_content'];
                    $preview = !empty($_POST['preview']);

                    if ($content != '') {
                        # --BEHAVIOR-- publicBeforeCommentTransform
                        $buffer = dotclear()->behavior()->call('publicBeforeCommentTransform', $content);
                        if ($buffer != '') {
                            $content = $buffer;
                        } else {
                            if (dotclear()->blog()->settings()->system->wiki_comments) {
                                dotclear()->wiki()->initWikiComment();
                            } else {
                                dotclear()->wiki()->initWikiSimpleComment();
                            }
                            $content = dotclear()->wiki()->wikiTransform($content);
                        }
                        $content = new HtmlFilter($content);
                    }

                    dotclear()->context()->comment_preview['content']    = $content;
                    dotclear()->context()->comment_preview['rawcontent'] = $_POST['c_content'];
                    dotclear()->context()->comment_preview['name']       = $name;
                    dotclear()->context()->comment_preview['mail']       = $mail;
                    dotclear()->context()->comment_preview['site']       = $site;

                    if ($preview) {
                        # --BEHAVIOR-- publicBeforeCommentPreview
                        dotclear()->behavior()->call('publicBeforeCommentPreview', dotclear()->context()->comment_preview);

                        dotclear()->context()->comment_preview['preview'] = true;
                    } else {
                        # Post the comment
                        $cur                  = dotclear()->con()->openCursor($core->prefix . 'comment');
                        $cur->comment_author  = $name;
                        $cur->comment_site    = Html::clean($site);
                        $cur->comment_email   = Html::clean($mail);
                        $cur->comment_content = $content;
                        $cur->post_id         = dotclear()->context()->posts->post_id;
                        $cur->comment_status  = dotclear()->blog()->settings()->system->comments_pub ? 1 : -1;
                        $cur->comment_ip      = Http::realIP();

                        $redir = dotclear()->context()->posts->getURL();
                        $redir .= dotclear()->blog()->settings()->system->url_scan == 'query_string' ? '&' : '?';

                        try {
                            if (!Text::isEmail($cur->comment_email)) {
                                throw new AdminException(__('You must provide a valid email address.'));
                            }

                            # --BEHAVIOR-- publicBeforeCommentCreate
                            dotclear()->behavior()->call('publicBeforeCommentCreate', $cur);
                            if ($cur->post_id) {
                                $comment_id = dotclear()->blog()->comments()->addComment($cur);

                                # --BEHAVIOR-- publicAfterCommentCreate
                                $core->callBehavior('publicAfterCommentCreate', $cur, $comment_id);
                            }

                            if ($cur->comment_status == 1) {
                                $redir_arg = 'pub=1';
                            } else {
                                $redir_arg = 'pub=0';
                            }

                            header('Location: ' . $redir . $redir_arg);
                        } catch (\Exception $e) {
                            dotclear()->context()->form_error = $e->getMessage();
                        }
                    }
                }

                # The entry
                if (dotclear()->context()->posts->trackbacksActive()) {
                    header('X-Pingback: ' . dotclear()->blog()->url . dotclear()->url()->getURLFor('xmlrpc', dotclear()->blog()->id));
                }

                # Serve page
                dotclear()->url()->serveDocument('page.html');
            }
        }
    }

    public static function pagespreview($args)
    {
        if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#', $args, $m)) {
            # The specified Preview URL is malformed.
            dotclear()->url()->p404();
        } else {
            $user_id  = $m[1];
            $user_key = $m[2];
            $post_url = $m[3];
            if (!dotclear()->user()->checkUser($user_id, null, $user_key)) {
                # The user has no access to the entry.
                dotclear()->url()->p404();
            } else {
                dotclear()->user()->preview = true;
                if (DOTCLEAR_PROCESS == 'Admin') {
                    dotclear()->user()->xframeoption = dotclear()->config()->admin_url;
                }

                self::pages($post_url);
            }
        }
    }
}
