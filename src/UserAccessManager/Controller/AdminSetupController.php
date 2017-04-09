<?php
/**
 * AdminSetupController.php
 *
 * The AdminSetupController class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Controller;

use UserAccessManager\Config\Config;
use UserAccessManager\Database\Database;
use UserAccessManager\SetupHandler\SetupHandler;
use UserAccessManager\Wrapper\Php;
use UserAccessManager\Wrapper\Wordpress;

/**
 * Class AdminSetupController
 *
 * @package UserAccessManager\Controller
 */
class AdminSetupController extends Controller
{
    const SETUP_UPDATE_NONCE = 'uamSetupUpdate';
    const SETUP_RESET_NONCE = 'uamSetupReset';
    const UPDATE_BLOG = 'blog';
    const UPDATE_NETWORK = 'network';

    /**
     * @var SetupHandler
     */
    protected $setupHandler;

    /**
     * @var Database
     */
    protected $database;

    /**
     * @var string
     */
    protected $template = 'AdminSetup.php';

    /**
     * AdminSetupController constructor.
     *
     * @param Php          $php
     * @param Wordpress    $wordpress
     * @param Config       $config
     * @param Database     $database
     * @param SetupHandler $setupHandler
     */
    public function __construct(
        Php $php,
        Wordpress $wordpress,
        Config $config,
        Database $database,
        SetupHandler $setupHandler
    ) {
        parent::__construct($php, $wordpress, $config);
        $this->database = $database;
        $this->setupHandler = $setupHandler;
    }

    /**
     * Returns if a database update is necessary.
     *
     * @return bool
     */
    public function isDatabaseUpdateNecessary()
    {
        return $this->setupHandler->isDatabaseUpdateNecessary();
    }

    /**
     * Checks if a network update is nessary.
     *
     * @return bool
     */
    public function showNetworkUpdate()
    {
        return $this->wordpress->isSuperAdmin() === true
            && defined('MULTISITE') === true && MULTISITE === true
            && defined('WP_ALLOW_MULTISITE') === true && WP_ALLOW_MULTISITE === true;
    }

    /**
     * The database update action.
     */
    public function updateDatabaseAction()
    {
        $this->verifyNonce(self::SETUP_UPDATE_NONCE);
        $update = $this->getRequestParameter('uam_update_db');

        if ($update === self::UPDATE_BLOG || $update === self::UPDATE_NETWORK) {
            if ($update === self::UPDATE_NETWORK) {
                $blogIds = $this->setupHandler->getBlogIds();

                if (count($blogIds) > 0) {
                    $currentBlogId = $this->database->getCurrentBlogId();

                    foreach ($blogIds as $blogId) {
                        $this->wordpress->switchToBlog($blogId);
                        $this->setupHandler->update();
                    }

                    $this->wordpress->switchToBlog($currentBlogId);
                }
            } else {
                $this->setupHandler->update();
            }

            $this->setUpdateMessage(TXT_UAM_UAM_DB_UPDATE_SUCSUCCESS);
        }
    }

    /**
     * The reset action.
     */
    public function resetUamAction()
    {
        $this->verifyNonce(self::SETUP_RESET_NONCE);
        $reset = $this->getRequestParameter('uam_reset');

        if ($reset === 'reset') {
            $this->setupHandler->uninstall();
            $this->setupHandler->install();
            $this->setUpdateMessage(TXT_UAM_UAM_RESET_SUCCESS);
        }
    }
}
