<?php
/**
 * @note Dotclear\Plugin\Antispam\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Admin;

use Dotclear\Database\Structure;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Antispam\Common\Antispam;
use Dotclear\Plugin\Antispam\Common\AntispamUrl;
use Dotclear\Plugin\Antispam\Common\Filter\FilterWords;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        if (!defined('DC_ANTISPAM_CONF_SUPER')) {
            define('DC_ANTISPAM_CONF_SUPER', false);
        }

        // Menu and favs
        $this->addStandardMenu('Plugins');
        $this->addStandardFavorites('admin');

        new Antispam();
        new AntispamUrl();
        new AntispamBehavior();
    }

    public function installModule(): ?bool
    {
        $s = new Structure(dotclear()->con(), dotclear()->prefix);

        $s->table('comment')
            ->field('comment_spam_status', 'varchar', 128, true, 0)
            ->field('comment_spam_filter', 'varchar', 32, true, null)
        ;

        $s->table('spamrule')
            ->field('rule_id', 'bigint', 0, false)
            ->field('blog_id', 'varchar', 32, true)
            ->field('rule_type', 'varchar', 16, false, "'word'")
            ->field('rule_content', 'varchar', 128, false)

            ->primary('pk_spamrule', 'rule_id')
        ;

        $s->table('spamrule')->index('idx_spamrule_blog_id', 'btree', 'blog_id');
        $s->table('spamrule')->reference('fk_spamrule_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');

        if ('pgsql' == $s->driver()) {
            $s->table('spamrule')->index('idx_spamrule_blog_id_null', 'btree', '(blog_id IS NULL)');
        }

        // Schema installation
        $si      = new Structure(dotclear()->con(), dotclear()->prefix);
        $changes = $si->synchronize($s);

        // Creating default wordslist
        if (null === dotclear()->version()->get('Antispam')) {
            $_o = new FilterWords();
            $_o->defaultWordsList();
            unset($_o);
        }

        dotclear()->blog()->settings()->get('antispam')->put('antispam_moderation_ttl', 0, 'integer', 'Antispam Moderation TTL (days)', false);

        return true;
    }
}
