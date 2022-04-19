<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Core\Xmlrpc\Xmlrpc as CoreXmlrpc;
use Dotclear\Helper\Network\Http;

/**
 * Admin xmlrpc page.
 *
 * \Dotclear\Process\Admin\Handler\Xmlrpc
 *
 * @ingroup  Admin Xmlrpc Handler
 */
class Xmlrpc extends AbstractPage
{
    protected function getPermissions(): string|null|false
    {
        return false;
    }

    protected function getPagePrepend(): ?bool
    {
        if (isset($_SERVER['PATH_INFO'])) {
            $blog_id = trim($_SERVER['PATH_INFO']);
            $blog_id = preg_replace('#^/#', '', $blog_id);
        } elseif (!empty($_GET['b'])) {
            $blog_id = $_GET['b'];
        }

        if (empty($blog_id)) {
            header('Content-Type: text/plain');
            Http::head(412);
            echo 'No blog ID given';

            return null;
        }

        // Avoid plugins warnings, set a default blog
        dotclear()->setBlog($blog_id);

        // Start XML-RPC server
        $xmlrpc = new CoreXmlrpc($blog_id);
        $xmlrpc->serve();

        return null;
    }
}
