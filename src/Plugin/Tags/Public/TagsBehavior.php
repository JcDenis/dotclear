<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Public;

// Dotclear\Plugin\Tags\Public\TagsBehavior
use Dotclear\App;
use Dotclear\Process\Public\Template\TplAttr;

/**
 * Public behaviors for plugin Tags.
 *
 * @ingroup  Plugin Tags Behavior
 */
class TagsBehavior
{
    public function __construct()
    {
        App::core()->behavior('templateBeforeBlock')->add([$this, 'templateBeforeBlock']);
    }

    public function templateBeforeBlock(string $tag, TplAttr $attr): string
    {
        if (in_array($tag, ['Entries', 'Comments']) && $attr->isset('tag')) {
            return
            "<?php\n" .
            'if (!isset($param)) { $param = new Param(); }' . "\n" .
            "\$param->push('from', App::core()->getPrefix().'meta META');\n" .
            "\$param->push('sql', 'AND META.post_id = P.post_id ');\n" .
            "\$param->push('sql', \"AND META.meta_type = 'tag' \");\n" .
            "\$param->push('sql' \"AND META.meta_id = '" . App::core()->con()->escape($attr->get('tag')) . "' \");\n" .
                "?>\n";
        }
        if ($attr->empty('no_context') && in_array($tag, ['Entries', 'Comments'])) {
            return
                '<?php if (App::core()->context()->exists("meta") && App::core()->context()->get("meta")->rows() && App::core()->context()->get("meta")->field("meta_type") == "tag") { ' .
                'if (!isset($param)) { $param = new Param(); }' . "\n" .
                "\$param->push('from', App::core()->getPrefix().'meta META');\n" .
                "\$param->push('sql', 'AND META.post_id = P.post_id ');\n" .
                "\$param->push('sql', \"AND META.meta_type = 'tag' \");\n" .
                "\$param->push('sql', \"AND META.meta_id = '\".App::core()->con()->escape(App::core()->context()->get('meta')->field('meta_id')).\"' \");\n" .
                "} ?>\n";
        }

        return '';
    }
}
