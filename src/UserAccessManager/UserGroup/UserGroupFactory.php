<?php
/**
 * UserGroupFactory.php
 *
 * The UserGroupFactory class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\UserGroup;

use UserAccessManager\Cache\Cache;
use UserAccessManager\Config\Config;
use UserAccessManager\Database\Database;
use UserAccessManager\ObjectHandler\ObjectHandler;
use UserAccessManager\Util\Util;
use UserAccessManager\Wrapper\Wordpress;

/**
 * Class UserGroupFactory
 *
 * @package UserAccessManager\UserGroup
 */
class UserGroupFactory
{
    /**
     * @var Wordpress
     */
    protected $wordpress;

    /**
     * @var Database
     */
    protected $database;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var Util
     */
    protected $util;

    /**
     * @var ObjectHandler
     */
    protected $objectHandler;

    /**
     * UserGroupFactory constructor.
     *
     * @param Wordpress     $wordpress
     * @param Database      $database
     * @param Config        $config
     * @param Util          $util
     * @param ObjectHandler $objectHandler
     */
    public function __construct(
        Wordpress $wordpress,
        Database $database,
        Config $config,
        Util $util,
        ObjectHandler $objectHandler
    ) {
        $this->wordpress = $wordpress;
        $this->database = $database;
        $this->config = $config;
        $this->util = $util;
        $this->objectHandler = $objectHandler;
    }

    /**
     * Creates a new user group object.
     *
     * @param string $id
     *
     * @return UserGroup
     */
    public function createUserGroup($id = null)
    {
        return new UserGroup(
            $this->wordpress,
            $this->database,
            $this->config,
            $this->util,
            $this->objectHandler,
            $id
        );
    }
}
