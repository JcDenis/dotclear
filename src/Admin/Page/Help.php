<?php
/**
 * @class Dotclear\Admin\Page\Help
 * @brief Dotclear admin help page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Help extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->check('usage,contentadmin');

        $help_page     = !empty($_GET['page']) ? Html::escapeHTML($_GET['page']) : 'index';
        $content_array = $this->helpPage($help_page);
        if (($content_array['content'] == '') || ($help_page == 'index')) {
            $content_array = $this->helpPage('index');
        }
        if ($content_array['title'] != '') {
            $breadcrumb = $this->breadcrumb(
                [
                    __('Global help')       => $core->adminurl->get('admin.help'),
                    $content_array['title'] => ''
                ]);
        } else {
            $breadcrumb = $this->breadcrumb(
                [
                    __('Global help') => ''
                ]);
        }

        /* DISPLAY
        -------------------------------------------------------- */
        $this->open(__('Global help'),
            self::jsPageTabs('first-step'),
            $breadcrumb
        );

        echo $content_array['content'];

        // Prevents global help link display
        $this->core->__resources['ctxhelp'] = true;

        $this->close();
    }

    private function helpPage(...$args): array
    {
        $ret = ['content' => '', 'title' => ''];

        if (empty($args)) {
            return $ret;
        }

        if (empty($this->core->_resources['help'])) {
            return $ret;
        }

        $content = '';
        $title   = '';
        foreach ($args as $v) {
            if (is_object($v) && isset($v->content)) {
                $content .= $v->content;

                continue;
            }

            if (!isset($this->core->_resources['help'][$v])) {
                continue;
            }
            $f = $this->core->_resources['help'][$v];
            if (!file_exists($f) || !is_readable($f)) {
                continue;
            }

            $fc = file_get_contents($f);
            if (preg_match('|<body[^>]*?>(.*?)</body>|ms', $fc, $matches)) {
                $content .= $matches[1];
                if (preg_match('|<title[^>]*?>(.*?)</title>|ms', $fc, $matches)) {
                    $title = $matches[1];
                }
            } else {
                $content .= $fc;
            }
        }

        if (trim($content) == '') {
            return $ret;
        }

        $ret['content'] = $content;
        if ($title != '') {
            $ret['title'] = $title;
        }

        return $ret;
    }
}
