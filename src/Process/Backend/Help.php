<?php

/**
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Process\Backend;

use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @since 2.27 Before as admin/help.php
 */
class Help
{
    use TraitProcess;

    public static function init(): bool
    {
        App::backend()->page()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]));

        return self::status(true);
    }

    public static function render(): void
    {
        /**
         * $helpPage() get help content depending on context
         *
         * @param      mixed  ...$args  The arguments
         *
         * @return     array
         */
        $helpPage = function (...$args): array {
            // Init return value
            $ret = [
                'content' => '',
                'title'   => '',
            ];

            if ($args === []) {
                // No context given
                return $ret;
            }

            if (App::backend()->resources()->entries('help') === []) {
                // No available help
                return $ret;
            }

            $content = '';
            $title   = '';
            foreach ($args as $v) {
                if (is_object($v) && isset($v->content)) {  // @phpstan-ignore-line: ->content may be present
                    $content .= $v->content;

                    continue;
                }

                $f = App::backend()->resources()->entry('help', $v);
                if ($f === '' || !file_exists($f) || !is_readable($f)) {
                    continue;
                }

                $fc = (string) file_get_contents($f);
                if (preg_match('|<body[^>]*?>(.*?)</body>|ms', $fc, $matches)) {
                    $content .= $matches[1];
                    if (preg_match('|<title[^>]*?>(.*?)</title>|ms', $fc, $matches)) {
                        $title = $matches[1];
                    }
                } else {
                    $content .= $fc;
                }
            }

            if (trim($content) === '') {
                return $ret;
            }

            $ret['content'] = $content;
            if ($title !== '') {
                $ret['title'] = $title;
            }

            return $ret;
        };

        $help_page = empty($_GET['page']) ? 'index' : Html::escapeHTML($_GET['page']);

        $content_array = $helpPage($help_page);

        if (($content_array['content'] === '') || ($help_page === 'index')) {
            $content_array = $helpPage('index');
        }

        if ($content_array['title'] !== '') {
            $breadcrumb = App::backend()->page()->breadcrumb(
                [
                    __('Global help')       => App::backend()->url()->get('admin.help'),
                    $content_array['title'] => '',
                ]
            );
        } else {
            $breadcrumb = App::backend()->page()->breadcrumb(
                [
                    __('Global help') => '',
                ]
            );
        }

        App::backend()->page()->open(
            __('Global help'),
            App::backend()->page()->jsPageTabs('first-step'),
            $breadcrumb
        );

        echo $content_array['content'];

        // Prevents global help link display
        App::backend()->resources()->context(true);

        App::backend()->page()->close();
    }
}
