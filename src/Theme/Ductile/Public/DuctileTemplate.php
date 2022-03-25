<?php
/**
 * @class Dotclear\Theme\Ductile\Public\DuctileTemplate
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeDuctile
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Public;

use Dotclear\Helper\File\Files;

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

    public function ductileNbEntryPerPage($attr)
    {
        $nb = $attr['nb'] ?? null;

        return '<?php ' . __CLASS__ . '::ductileNbEntryPerPageHelper(' . strval((int) $nb) . '); ?>';
    }

    public static function ductileNbEntryPerPageHelper(int $nb)
    {
        $nb_other = $nb_first = 0;

        $s = dotclear()->blog()->settings()->themes->get(dotclear()->blog()->settings()->system->theme . '_entries_counts');
        if ($s !== null) {
            $s = @unserialize($s);
            if (is_array($s)) {
                switch (dotclear()->url()->type) {
                    case 'default':
                    case 'default-page':
                        if (isset($s['default'])) {
                            $nb_first = $nb_other = (integer) $s['default'];
                        }
                        if (isset($s['default-page'])) {
                            $nb_other = (integer) $s['default-page'];
                        }

                        break;
                    default:
                        if (isset($s[dotclear()->url()->type])) {
                            // Nb de billets par page défini par la config du thème
                            $nb_first = $nb_other = (integer) $s[dotclear()->url()->type];
                        }

                        break;
                }
            }
        }

        if ($nb_other == 0) {
            if ($nb) {
                // Nb de billets par page défini par défaut dans le template
                $nb_other = $nb_first = $nb;
            }
        }

        if ($nb_other > 0) {
            dotclear()->context()->nb_entry_per_page = $nb_other;
        }
        if ($nb_first > 0) {
            dotclear()->context()->nb_entry_first_page = $nb_first;
        }
    }

    public function EntryIfContentIsCut($attr, $content)
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

        return '<?php if (strlen(' . sprintf($full, 'dotclear()->context()->posts->getContent(' . $urls . ')') . ') > ' .
        'strlen(' . sprintf($short, 'dotclear()->context()->posts->getContent(' . $urls . ')') . ')) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    public function ductileEntriesList($attr)
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

    public static function ductileEntriesListHelper($default)
    {
        $s = dotclear()->blog()->settings()->themes->get(dotclear()->blog()->settings()->system->theme . '_entries_lists');
        if ($s !== null) {
            $s = @unserialize($s);
            if (is_array($s)) {
                if (isset($s[dotclear()->url()->type])) {
                    $model = $s[dotclear()->url()->type];

                    return $model;
                }
            }
        }

        return $default;
    }

    public function ductileLogoSrc($attr)
    {
        return '<?php echo ' . __CLASS__ . '::ductileLogoSrcHelper(); ?>';
    }

    public static function ductileLogoSrcHelper()
    {
        $img_url = dotclear()->blog()->getURLFor('resources', 'img/logo.png');

        $s = dotclear()->blog()->settings()->themes->get(dotclear()->blog()->settings()->system->theme . '_style');
        if ($s === null) {
            // no settings yet, return default logo
            return $img_url;
        }
        $s = @unserialize($s);
        if (!is_array($s)) {
            // settings error, return default logo
            return $img_url;
        }

        if (isset($s['logo_src'])) {
            if ($s['logo_src'] !== null) {
                if ($s['logo_src'] != '') {
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

    public function IfPreviewIsNotMandatory($attr, $content)
    {
        $s = dotclear()->blog()->settings()->themes->get(dotclear()->blog()->settings()->system->theme . '_style');
        if ($s !== null) {
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