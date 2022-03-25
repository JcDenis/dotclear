<?php
/**
 * @class Dotclear\Plugin\LegacyEditor\Admin\LegacyEditorRest
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginLegacyEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

use Dotclear\Helper\Html\XmlTag;

class LegacyEditorRest
{
    public function __construct()
    {
        dotclear()->rest()->addFunction('wikiConvert', [$this, 'convert']);
    }

    public function convert($get, $post)
    {
        $wiki = $post['wiki'] ?? '';
        $rsp  = new XmlTag('wiki');

        $ret  = false;
        $html = '';
        if ($wiki !== '') {
            dotclear()->wiki()->initWikiPost();
            $html = dotclear()->formater()->callEditorFormater('LegacyEditor', 'wiki', $wiki);
            $ret  = strlen($html) > 0;

            if ($ret) {
                $media_root = dotclear()->blog()->host;
                $html       = preg_replace_callback('/src="([^\"]*)"/', function ($matches) use ($media_root) {
                    if (!preg_match('/^http(s)?:\/\//', $matches[1])) {
                        // Relative URL, convert to absolute
                        return 'src="' . $media_root . $matches[1] . '"';
                    }
                    // Absolute URL, do nothing
                    return $matches[0];
                }, $html);
            }
        }

        $rsp->ret = $ret;
        $rsp->msg = $html;

        return $rsp;
    }
}