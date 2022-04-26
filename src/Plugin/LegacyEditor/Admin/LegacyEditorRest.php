<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

// Dotclear\Plugin\LegacyEditor\Admin\LegacyEditorRest
use Dotclear\App;
use Dotclear\Helper\Html\XmlTag;

/**
 * Admin REST methods for plugin LegacyEditor.
 *
 * @ingroup  Plugin LegacyEditor Rest
 */
class LegacyEditorRest
{
    public function __construct()
    {
        App::core()->rest()->addFunction('wikiConvert', [$this, 'convert']);
    }

    public function convert(array $get, array $post): XmlTag
    {
        $wiki = $post['wiki'] ?? '';
        $rsp  = new XmlTag('wiki');

        $ret  = false;
        $html = '';
        if ('' !== $wiki) {
            App::core()->wiki()->initWikiPost();
            $html = App::core()->formater()->callEditorFormater('LegacyEditor', 'wiki', $wiki);
            $ret  = strlen($html) > 0;

            if ($ret) {
                $media_root = App::core()->blog()->host;
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

        $rsp->insertAttr('ret', $ret);
        $rsp->insertAttr('msg', $html);

        return $rsp;
    }
}
