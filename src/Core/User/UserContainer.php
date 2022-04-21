<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\User;

// Dotclear\Core\User\UserContainer
use Dotclear\Database\Record;
use Dotclear\Helper\AbstractContainer;

/**
 * Container to acces user properties with fixed type.
 *
 * @ingroup  Core User Container
 */
class UserContainer extends AbstractContainer
{
    protected $id   = 'user';
    protected $info = [
        'user_id'           => '',
        'user_super'        => 0,
        'user_pwd'          => '',
        'user_change_pwd'   => 0,
        'user_name'         => '',
        'user_firstname'    => '',
        'user_displayname'  => '',
        'user_email'        => '',
        'user_url'          => '',
        'user_lang'         => 'en',
        'user_tz'           => 'Europe/London',
        'user_post_status'  => -2,
        'user_creadt'       => '',
        'user_upddt'        => '',
        'user_default_blog' => 'default',
        'user_options'      => '',
    ];

    public function fromRecord(Record $rs = null): void
    {
        parent::fromRecord($rs);

        // Use custom method for user options
        if (null != $rs && $rs->exists('user_options')) {
            $this->setOptions($rs->call('options'));
        }
    }

    /**
     * Static function that returns user's common name given to his
     * <var>user_id</var>, <var>user_name</var>, <var>user_firstname</var> and
     * <var>user_displayname</var>.
     *
     * @param string      $user_id          The user identifier
     * @param null|string $user_name        The user name
     * @param null|string $user_firstname   The user firstname
     * @param null|string $user_displayname The user displayname
     *
     * @return string the user cn
     */
    public static function getUserCN(string $user_id, ?string $user_name, ?string $user_firstname, ?string $user_displayname): string
    {
        if (!empty($user_displayname)) {
            return $user_displayname;
        }

        if (!empty($user_name)) {
            if (!empty($user_firstname)) {
                return $user_firstname . ' ' . $user_name;
            }

            return $user_name;
        }
        if (!empty($user_firstname)) {
            return $user_firstname;
        }

        return $user_id;
    }

    /**
     * Returns user default settings in an associative array with setting names in keys.
     *
     * @return array<string, mixed> user default options
     */
    public static function defaultOptions(): array
    {
        return [
            'edit_size'      => 24,
            'enable_wysiwyg' => true,
            'toolbar_bottom' => false,
            'editor'         => ['xhtml' => 'dcCKEditor', 'wiki' => 'LegacyEditor'],
            'post_format'    => 'xhtml',
        ];
    }

    /**
     * Set user options.
     *
     * @param array<string, mixed> $arg User options
     *
     * @return array<string, mixed> User options
     */
    private function setOptions(array $arg): array
    {
        $this->set('user_options', serialize(array_merge($this->getOptions(), $arg)));

        return $this->getOptions();
    }

    /**
     * Get user options.
     *
     * @return array<string, mixed> user options
     */
    public function getOptions(): array
    {
        return array_merge($this->defaultOptions(), empty($this->get('user_options')) ? [] : unserialize($this->get('user_options')));
    }

    /**
     * Set a user option.
     *
     * @param string $key Option key
     * @param mixed  $val Option value
     *
     * @return mixed Option value
     */
    public function setOption(string $key, mixed $val): mixed
    {
        $this->setOptions([$key => $val]);

        return $val;
    }

    /**
     * Get a user option.
     *
     * @param string $key Option key
     *
     * @return mixed Option value
     */
    public function getOption(string $key): mixed
    {
        $opt = $this->getOptions();

        return $opt[$key] ?? null;
    }
}
