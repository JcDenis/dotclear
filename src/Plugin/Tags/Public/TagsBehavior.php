<?php
/**
 * @class Dotclear\Plugin\Tags\Public\TagsBehavior
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Public;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class TagsBehavior
{
    public static function initTags()
    {
        dotclear()->behavior()->add('templateBeforeBlock', [__CLASS__, 'templateBeforeBlock']);
    }

    public static function templateBeforeBlock($b, $attr)
    {
        if (($b == 'Entries' || $b == 'Comments') && isset($attr['tag'])) {pdump($attr);
            return
            "<?php\n" .
            "if (!isset(\$params)) { \$params = []; }\n" .
            "if (!isset(\$params['from'])) { \$params['from'] = ''; }\n" .
            "if (!isset(\$params['sql'])) { \$params['sql'] = ''; }\n" .
            "\$params['from'] .= ', '.dotclear()->prefix.'meta META ';\n" .
            "\$params['sql'] .= 'AND META.post_id = P.post_id ';\n" .
            "\$params['sql'] .= \"AND META.meta_type = 'tag' \";\n" .
            "\$params['sql'] .= \"AND META.meta_id = '" . dotclear()->con()->escape($attr['tag']) . "' \";\n" .
                "?>\n";
        } elseif (empty($attr['no_context']) && ($b == 'Entries' || $b == 'Comments')) {
            return
                '<?php if (dotclear()->context()->exists("meta") && dotclear()->context()->meta->rows() && dotclear()->context()->meta->meta_type == "tag") { ' .
                "if (!isset(\$params)) { \$params = []; }\n" .
                "if (!isset(\$params['from'])) { \$params['from'] = ''; }\n" .
                "if (!isset(\$params['sql'])) { \$params['sql'] = ''; }\n" .
                "\$params['from'] .= ', '.dotclear()->prefix.'meta META ';\n" .
                "\$params['sql'] .= 'AND META.post_id = P.post_id ';\n" .
                "\$params['sql'] .= \"AND META.meta_type = 'tag' \";\n" .
                "\$params['sql'] .= \"AND META.meta_id = '\".dotclear()->con()->escape(dotclear()->context()->meta->meta_id).\"' \";\n" .
                "} ?>\n";
        }
    }
}
