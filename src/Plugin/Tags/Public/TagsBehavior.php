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

use ArrayObject;

class TagsBehavior
{
    public function __construct()
    {
        dotclear()->behavior()->add('templateBeforeBlock', [$this, 'templateBeforeBlock']);
    }

    public function templateBeforeBlock(string $tag, ArrayObject $attr): string
    {
        if (in_array($tag, ['Entries', 'Comments']) && isset($attr['tag'])) {
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
        } elseif (empty($attr['no_context']) && in_array($tag, ['Entries', 'Comments'])) {
            return
                '<?php if (dotclear()->context()->exists("meta") && dotclear()->context()->get("meta")->rows() && dotclear()->context()->get("meta")->f("meta_type") == "tag") { ' .
                "if (!isset(\$params)) { \$params = []; }\n" .
                "if (!isset(\$params['from'])) { \$params['from'] = ''; }\n" .
                "if (!isset(\$params['sql'])) { \$params['sql'] = ''; }\n" .
                "\$params['from'] .= ', '.dotclear()->prefix.'meta META ';\n" .
                "\$params['sql'] .= 'AND META.post_id = P.post_id ';\n" .
                "\$params['sql'] .= \"AND META.meta_type = 'tag' \";\n" .
                "\$params['sql'] .= \"AND META.meta_id = '\".dotclear()->con()->escape(dotclear()->context()->get('meta')->f('meta_id')).\"' \";\n" .
                "} ?>\n";
        }

        return '';
    }
}
