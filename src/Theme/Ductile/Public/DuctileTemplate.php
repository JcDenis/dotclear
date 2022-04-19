<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Public;

use ArrayObject;
use Dotclear\Helper\File\Files;

/**
 * Public templates for theme Ductile.
 *
 * \Dotclear\Theme\Ductile\Public\DuctileTemplate
 *
 * @ingroup  Theme Ductile
 */
class DuctileTemplate
{
    public function __construct()
    {
        dotclear()->template()->addValue('ductileEntriesList', [$this, 'ductileEntriesList']);
        dotclear()->template()->addBlock('EntryIfContentIsCut', [$this, 'EntryIfContentIsCut']);
        dotclear()->template()->addValue('ductileNbEntryPerPage', [$this, 'ductileNbEntryPerPage']);
        dotclear()->template()->addValue('ductileLogoSrc', [$this, 'ductileLogoSrc']);
        dotclear()->template()->addBlock('IfPreviewIsNotMandatory', [$this, 'IfPreviewIsNotMandatory']);
    }

    public function ductileNbEntryPerPage(ArrayObject $attr): string
    {
        $nb = $attr['nb'] ?? null;

        return '<?php ' . __CLASS__ . '::ductileNbEntryPerPageHelper(' . strval((int) $nb) . '); ?>';
    }

    public static function ductileNbEntryPerPageHelper(int $nb): void
    {
        $nb_other = $nb_first = 0;

        $s = dotclear()->blog()->settings()->get('themes')->get(dotclear()->blog()->settings()->get('system')->get('theme') . '_entries_counts');
        if (null !== $s) {
            $s = @unserialize($s);
            if (is_array($s)) {
                switch (dotclear()->url()->type) {
                    case 'default':
                    case 'default-page':
                        if (isset($s['default'])) {
                            $nb_first = $nb_other = (int) $s['default'];
                        }
                        if (isset($s['default-page'])) {
                            $nb_other = (int) $s['default-page'];
                        }

                        break;

                    default:
                        if (isset($s[dotclear()->url()->type])) {
                            // Nb de billets par page défini par la config du thème
                            $nb_first = $nb_other = (int) $s[dotclear()->url()->type];
                        }

                        break;
                }
            }
        }

        if (0 == $nb_other) {
            if ($nb) {
                // Nb de billets par page défini par défaut dans le template
                $nb_other = $nb_first = $nb;
            }
        }

        if (0 < $nb_other) {
            dotclear()->context()->set('nb_entry_per_page', $nb_other);
        }
        if (0 < $nb_first) {
            dotclear()->context()->set('nb_entry_first_page', $nb_first);
        }
    }

    public function EntryIfContentIsCut(ArrayObject $attr, string $content): string
    {
        if (empty($attr['cut_string']) || !empty($attr['full'])) {
            return '';
        }

        $urls = '0';
        if (!empty($attr['absolute_urls'])) {
            $urls = '1';
        }

        $short              = dotclear()->template()->getFilters($attr);
        $cut                = $attr['cut_string'];
        $attr['cut_string'] = 0;
        $full               = dotclear()->template()->getFilters($attr);
        $attr['cut_string'] = $cut;

        return '<?php if (strlen(' . sprintf($full, 'dotclear()->context()->get("posts")->getContent(' . $urls . ')') . ') > ' .
        'strlen(' . sprintf($short, 'dotclear()->context()->get("posts")->getContent(' . $urls . ')') . ')) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    public function ductileEntriesList(ArrayObject $attr): string
    {
        $tpl_path   = __DIR__ . '/../templates/tpl/';
        $list_types = ['title', 'short', 'full'];

        // Get all _entry-*.html in tpl folder of theme
        $list_types_templates = Files::scandir($tpl_path, true, false);
        if (is_array($list_types_templates)) {
            foreach ($list_types_templates as $v) {
                if (preg_match('/^_entry\-(.*)\.html$/', $v, $m)) {
                    if (isset($m[1])) {
                        if (!in_array($m[1], $list_types)) {
                            // template not already in full list
                            $list_types[] = $m[1];
                        }
                    }
                }
            }
        }

        $default = isset($attr['default']) ? trim($attr['default']) : 'short';
        $ret     = '<?php ' . "\n" .
        'switch (' . __CLASS__ . '::ductileEntriesListHelper(\'' . $default . '\')) {' . "\n";

        foreach ($list_types as $v) {
            $ret .= '   case \'' . $v . '\':' . "\n" .
            '?>' . "\n" .
            dotclear()->template()->includeFile(['src' => '_entry-' . $v . '.html']) . "\n" .
                '<?php ' . "\n" .
                '       break;' . "\n";
        }

        $ret .= '}' . "\n" .
            '?>';

        return $ret;
    }

    public static function ductileEntriesListHelper(string $default): string
    {
        $s = dotclear()->blog()->settings()->get('themes')->get(dotclear()->blog()->settings()->get('system')->get('theme') . '_entries_lists');
        if (null !== $s) {
            $s = @unserialize($s);
            if (is_array($s)) {
                if (isset($s[dotclear()->url()->type])) {
                    return $s[dotclear()->url()->type];
                }
            }
        }

        return $default;
    }

    public function ductileLogoSrc(ArrayObject $attr): string
    {
        return '<?php echo ' . __CLASS__ . '::ductileLogoSrcHelper(); ?>';
    }

    public static function ductileLogoSrcHelper()
    {
        $img_url = dotclear()->blog()->getURLFor('resources', 'img/logo.png');

        $s = dotclear()->blog()->settings()->get('themes')->get(dotclear()->blog()->settings()->get('system')->get('theme') . '_style');
        if (null === $s) {
            // no settings yet, return default logo
            return $img_url;
        }
        $s = @unserialize($s);
        if (!is_array($s)) {
            // settings error, return default logo
            return $img_url;
        }

        if (isset($s['logo_src'])) {
            if (null !== $s['logo_src']) {
                if ('' != $s['logo_src']) {
                    if ((substr($s['logo_src'], 0, 1) == '/') || (parse_url($s['logo_src'], PHP_URL_SCHEME) != '')) {
                        // absolute URL
                        $img_url = $s['logo_src'];
                    } else {
                        // relative URL (base = img folder of ductile theme)
                        $img_url = dotclear()->blog()->getURLFor('resources', 'img/' . $s['logo_src']);
                    }
                }
            }
        }

        return $img_url;
    }

    public function IfPreviewIsNotMandatory(ArrayObject $attr, string $content): string
    {
        $s = dotclear()->blog()->settings()->get('themes')->get(dotclear()->blog()->settings()->get('system')->get('theme') . '_style');
        if (null !== $s) {
            $s = @unserialize($s);
            if (is_array($s)) {
                if (isset($s['preview_not_mandatory'])) {
                    if ($s['preview_not_mandatory']) {
                        return $content;
                    }
                }
            }
        }

        return '';
    }
}
