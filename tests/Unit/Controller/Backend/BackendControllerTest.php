<?php
/**
 * BackendControllerTest.php
 *
 * The BackendControllerTest unit test class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Tests\Unit\Controller\Backend;

use UserAccessManager\Controller\Backend\BackendController;
use UserAccessManager\UserAccessManager;
use UserAccessManager\Tests\Unit\UserAccessManagerTestCase;
use Vfs\FileSystem;
use Vfs\Node\Directory;
use Vfs\Node\File;

/**
 * Class BackendControllerTest
 *
 * @package UserAccessManager\Tests\Unit\Controller\Backend
 * @coversDefaultClass \UserAccessManager\Controller\Backend\BackendController
 */
class BackendControllerTest extends UserAccessManagerTestCase
{
    /**
     * @var FileSystem
     */
    private $root;

    /**
     * Setup virtual file system.
     */
    public function setUp()
    {
        $this->root = FileSystem::factory('vfs://');
        $this->root->mount();

        /**
         * @var Directory $rootDir
         */
        $rootDir = $this->root->get('/');
        $rootDir->add('root', new Directory([
            'src' => new Directory([
                'View'  => new Directory([
                    'AdminNotice.php' => new File('<?php echo \'AdminNotice\';')
                ])
            ])
        ]));
    }

    /**
     * Tear down virtual file system.
     */
    public function tearDown()
    {
        $this->root->unmount();
    }

    /**
     * @group  unit
     * @covers ::__construct()
     */
    public function testCanCreateInstance()
    {
        $backendController = new BackendController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getWordpressConfig(),
            $this->getUserHandler(),
            $this->getFileHandler()
        );

        self::assertInstanceOf(BackendController::class, $backendController);
    }

    /**
     * @group  unit
     * @covers ::showDatabaseNotice()
     *
     * @return BackendController
     */
    public function testShowDatabaseNotice()
    {
        $php = $this->getPhp();

        $wordpressConfig = $this->getWordpressConfig();
        $wordpressConfig->expects($this->once())
            ->method('getRealPath')
            ->will($this->returnValue('vfs://root/'));

        $backendController = new BackendController(
            $php,
            $this->getWordpress(),
            $wordpressConfig,
            $this->getUserHandler(),
            $this->getFileHandler()
        );

        $php->expects($this->once())
            ->method('includeFile')
            ->with($backendController, 'vfs://root/src/View/AdminNotice.php')
            ->will($this->returnCallback(function () {
                echo 'DatabaseNotice';
            }));

        $backendController->showDatabaseNotice();

        self::assertAttributeEquals(
            sprintf(TXT_UAM_NEED_DATABASE_UPDATE, 'admin.php?page=uam_setup'),
            'notice',
            $backendController
        );
        self::expectOutputString('DatabaseNotice');

        return $backendController;
    }

    /**
     * @group   unit
     * @covers  ::getNotice()
     * @depends testShowDatabaseNotice
     *
     * @param BackendController $databaseNoticeBackendController
     */
    public function testGetNotice(BackendController $databaseNoticeBackendController)
    {
        self::assertEquals(
            sprintf(TXT_UAM_NEED_DATABASE_UPDATE, 'admin.php?page=uam_setup'),
            $databaseNoticeBackendController->getNotice()
        );
    }

    /**
     * @group  unit
     * @covers ::registerStylesAndScripts()
     * @covers ::enqueueStylesAndScripts()
     */
    public function testStylesAndScripts()
    {
        $wordpress = $this->getWordpress();
        $wordpress->expects($this->once())
            ->method('registerStyle')
            ->with(
                BackendController::HANDLE_STYLE_ADMIN,
                'url/assets/css/uamAdmin.css',
                [],
                UserAccessManager::VERSION,
                'screen'
            )
            ->will($this->returnValue('a'));

        $wordpress->expects($this->exactly(3))
            ->method('registerScript')
            ->withConsecutive(
                [
                    BackendController::HANDLE_SCRIPT_GROUP_SUGGEST,
                    'url/assets/js/jquery.uam-group-suggest.js',
                    ['jquery'],
                    UserAccessManager::VERSION
                ],
                [
                BackendController::HANDLE_SCRIPT_TIME_INPUT,
                    'url/assets/js/jquery.uam-time-input.js',
                    ['jquery'],
                    UserAccessManager::VERSION
                ],
                [
                    BackendController::HANDLE_SCRIPT_ADMIN,
                    'url/assets/js/functions.js',
                    ['jquery'],
                    UserAccessManager::VERSION
                ]
            );

        $wordpress->expects($this->once())
            ->method('enqueueStyle')
            ->with(BackendController::HANDLE_STYLE_ADMIN);

        $wordpress->expects($this->exactly(3))
            ->method('enqueueScript')
            ->withConsecutive(
                [BackendController::HANDLE_SCRIPT_GROUP_SUGGEST],
                [BackendController::HANDLE_SCRIPT_TIME_INPUT],
                [BackendController::HANDLE_SCRIPT_ADMIN]
            );

        $wordpressConfig = $this->getWordpressConfig();
        $wordpressConfig->expects($this->once())
            ->method('getUrlPath')
            ->will($this->returnValue('url/'));

        $backendController = new BackendController(
            $this->getPhp(),
            $wordpress,
            $wordpressConfig,
            $this->getUserHandler(),
            $this->getFileHandler()
        );

        $backendController->enqueueStylesAndScripts();
    }

    /**
     * @group  unit
     * @covers ::setupAdminDashboard()
     */
    public function testSetupAdminDashboard()
    {
        global $metaBoxes;
        $metaBoxes = [
            'dashboard' => [
                'normal' => [
                    'core' => [
                        'dashboard_recent_comments' => true
                    ]
                ]
            ]
        ];
        $originalMetaBoxes = $metaBoxes;

        $wordpress = $this->getWordpress();
        $wordpress->expects($this->once())
            ->method('getMetaBoxes')
            ->will($this->returnCallback(function () {
                global $metaBoxes;
                return $metaBoxes;
            }));

        $wordpress->expects($this->once())
            ->method('setMetaBoxes')
            ->will($this->returnCallback(function ($newMetaBoxes) {
                global $metaBoxes;
                $metaBoxes = $newMetaBoxes;
            }));

        $userHandler = $this->getUserHandler();
        $userHandler->expects($this->exactly(3))
            ->method('checkUserAccess')
            ->with('manage_user_groups')
            ->will($this->onConsecutiveCalls(true, true, false));

        $backendController = new BackendController(
            $this->getPhp(),
            $wordpress,
            $this->getWordpressConfig(),
            $userHandler,
            $this->getFileHandler()
        );

        $backendController->setupAdminDashboard();
        self::assertEquals($originalMetaBoxes, $metaBoxes);

        $backendController->setupAdminDashboard();
        self::assertEquals($originalMetaBoxes, $metaBoxes);

        $backendController->setupAdminDashboard();
        unset($originalMetaBoxes['dashboard']['normal']['core']['dashboard_recent_comments']);
        self::assertEquals($originalMetaBoxes, $metaBoxes);
    }

    /**
     * @group  unit
     * @covers ::updatePermalink()
     */
    public function testUpdatePermalink()
    {
        $fileHandler = $this->getFileHandler();
        $fileHandler->expects($this->once())
            ->method('createFileProtection');

        $backendController = new BackendController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getWordpressConfig(),
            $this->getUserHandler(),
            $fileHandler
        );

        $backendController->updatePermalink();
    }
}
