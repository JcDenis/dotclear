<?php
/**
 * @class Dotclear\Process\Admin\Handler\Help
 * @brief Dotclear admin help page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Process\Admin\Page\Page;
use Dotclear\Helper\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Help extends Page
{
    private $help_title   = '';
    private $help_content = '';

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $help_page = !empty($_GET['page']) ? Html::escapeHTML($_GET['page']) : 'index';

        $this->getHelpContent($help_page);
        if (($this->help_content == '') || ($help_page == 'index')) {
            $this->getHelpContent('index');
        }

        if ($this->help_title != '') {
            $this->setPageBreadcrumb([
                __('Global help')       => dotclear()->adminurl()->get('admin.help'),
                $this->help_title => ''
            ]);
        } else {
            $this->setPageBreadcrumb([__('Global help') => '']);
        }

        $this
            ->setPageTitle(__('Global help'))
            ->setPageHead(dotclear()->resource()->pageTabs('first-step'))
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo $this->help_content;

        # Prevents global help link display
        dotclear()->__resources['ctxhelp'] = true;
    }

    private function getHelpContent(...$args): void
    {
        if (empty($args) || empty(dotclear()->resources['help'])) {
            return;
        }

        foreach ($args as $v) {
            if (is_object($v) && isset($v->content)) {
                $this->help_content .= $v->content;

                continue;
            }

            if (!isset(dotclear()->resources['help'][$v])) {
                continue;
            }
            $f = dotclear()->resources['help'][$v];
            if (!file_exists($f) || !is_readable($f)) {
                continue;
            }

            $fc = file_get_contents($f);
            if (preg_match('|<body[^>]*?>(.*?)</body>|ms', $fc, $matches)) {
                $this->help_content .= $matches[1];
                if (preg_match('|<title[^>]*?>(.*?)</title>|ms', $fc, $matches)) {
                    $this->help_title = $matches[1];
                }
            } else {
                $this->help_content .= $fc;
            }
        }

        if (trim($this->help_content) == '') {
            $this->help_content = $this->help_title = '';
            return;
        }

        return;
    }
}
