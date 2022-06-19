<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Public;

// Dotclear\Theme\Ductile\Public\DuctileTemplate
use Dotclear\App;
use Dotclear\Helper\File\Files;
use Dotclear\Process\Public\Template\TplAttr;

/**
 * Public templates for theme Ductile.
 *
 * @ingroup  Theme Ductile Template
 */
class DuctileTemplate
{
    // \cond
    // php tags break doxygen parser...
    private static $toff = ' ?>';
    private static $ton  = '<?php ';
    // \endcond

    public function __construct()
    {
        App::core()->template()->addValue('ductileEntriesList', [$this, 'ductileEntriesList']);
        App::core()->template()->addBlock('EntryIfContentIsCut', [$this, 'EntryIfContentIsCut']);
        App::core()->template()->addValue('ductileNbEntryPerPage', [$this, 'ductileNbEntryPerPage']);
        App::core()->template()->addValue('ductileLogoSrc', [$this, 'ductileLogoSrc']);
        App::core()->template()->addBlock('IfPreviewIsNotMandatory', [$this, 'IfPreviewIsNotMandatory']);
    }

    public function ductileNbEntryPerPage(TplAttr $attr): string
    {
        return self::$ton . __CLASS__ . '::ductileNbEntryPerPageHelper(' . strval((int) ($attr->get('nb') ?: null)) . ');' . self::$toff;
    }

    public static function ductileNbEntryPerPageHelper(int $nb): void
    {
        $nb_other = $nb_first = 0;

        $s = App::core()->blog()->settings()->getGroup('themes')->getSetting(App::core()->blog()->settings()->getGroup('system')->getSetting('theme') . '_entries_counts');
        if (null !== $s) {
            $s = @unserialize($s);
            if (is_array($s)) {
                switch (App::core()->url()->getCurrentType()) {
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
                        if (isset($s[App::core()->url()->getCurrentType()])) {
                            // Nb de billets par page défini par la config du thème
                            $nb_first = $nb_other = (int) $s[App::core()->url()->getCurrentType()];
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
            App::core()->context()->set('nb_entry_per_page', $nb_other);
        }
        if (0 < $nb_first) {
            App::core()->context()->set('nb_entry_first_page', $nb_first);
        }
    }

    public function EntryIfContentIsCut(TplAttr $attr, string $content): string
    {
        if (empty($attr->get('cut_string')) || !empty($attr->get('full'))) {
            return '';
        }

        $urls = '0';
        if (!empty($attr->get('absolute_urls'))) {
            $urls = '1';
        }

        $short = App::core()->template()->getFilters($attr);
        $cut   = $attr->get('cut_string');
        $attr->set('cut_string', '0');
        $full  = App::core()->template()->getFilters($attr);
        $attr->set('cut_string', $cut);

        return self::$ton . 'if (strlen(' . sprintf($full, 'App::core()->context()->get("posts")->getContent(' . $urls . ')') . ') > ' .
        'strlen(' . sprintf($short, 'App::core()->context()->get("posts")->getContent(' . $urls . ')') . ')) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    public function ductileEntriesList(TplAttr $attr): string
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

        $default = $attr->has('default') ? trim($attr->get('default')) : 'short';
        $ret     = self::$ton . "\n" .
        'switch (' . __CLASS__ . '::ductileEntriesListHelper(\'' . $default . '\')) {' . "\n";

        foreach ($list_types as $v) {
            $ret .= '   case \'' . $v . '\':' . "\n" .
            self::$toff . "\n" .
            App::core()->template()->includeFile(new TplAttr('src="_entry-' . $v . '.html"')) . "\n" .
                self::$ton . "\n" .
                '       break;' . "\n";
        }

        $ret .= '}' . "\n" .
            self::$toff;

        return $ret;
    }

    public static function ductileEntriesListHelper(string $default): string
    {
        $s = App::core()->blog()->settings()->getGroup('themes')->getSetting(App::core()->blog()->settings()->getGroup('system')->getSetting('theme') . '_entries_lists');
        if (null !== $s) {
            $s = @unserialize($s);
            if (is_array($s)) {
                if (isset($s[App::core()->url()->getCurrentType()])) {
                    return $s[App::core()->url()->getCurrentType()];
                }
            }
        }

        return $default;
    }

    public function ductileLogoSrc(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . __CLASS__ . '::ductileLogoSrcHelper();' . self::$toff;
    }

    public static function ductileLogoSrcHelper()
    {
        $img_url = App::core()->blog()->getURLFor('resources', 'img/logo.png');

        $s = App::core()->blog()->settings()->getGroup('themes')->getSetting(App::core()->blog()->settings()->getGroup('system')->getSetting('theme') . '_style');
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
                        $img_url = App::core()->blog()->getURLFor('resources', 'img/' . $s['logo_src']);
                    }
                }
            }
        }

        return $img_url;
    }

    public function IfPreviewIsNotMandatory(TplAttr $attr, string $content): string
    {
        $s = App::core()->blog()->settings()->getGroup('themes')->getSetting(App::core()->blog()->settings()->getGroup('system')->getSetting('theme') . '_style');
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
