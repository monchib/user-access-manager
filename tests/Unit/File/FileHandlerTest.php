<?php
/**
 * FileHandlerTest.php
 *
 * The FileHandlerTest unit test class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Tests\Unit\File;

use UserAccessManager\File\ApacheFileProtection;
use UserAccessManager\File\FileHandler;
use UserAccessManager\File\NginxFileProtection;
use UserAccessManager\Tests\Unit\UserAccessManagerTestCase;
use Vfs\FileSystem;
use Vfs\Node\Directory;
use Vfs\Node\File;

/**
 * Class FileHandlerTest
 *
 * @package UserAccessManager\Tests\Unit\File
 * @coversDefaultClass \UserAccessManager\File\FileHandler
 */
class FileHandlerTest extends UserAccessManagerTestCase
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
        $fileHandler = new FileHandler(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getWordpressConfig(),
            $this->getMainConfig(),
            $this->getFileProtectionFactory()
        );

        self::assertInstanceOf(FileHandler::class, $fileHandler);
    }

    /**
     * @group  unit
     * @covers ::getFile()
     * @covers ::getFileMineType()
     * @covers ::clearBuffer()
     * @covers ::deliverFile()
     * @covers ::deliverFileViaFopen()
     * @runInSeparateProcess
     */
    public function testGetFile()
    {
        $php = $this->getPhp();
        $php->expects($this->exactly(9))
            ->method('functionExists')
            ->withConsecutive(
                ['finfo_open'],
                ['finfo_open'],
                ['finfo_open'],
                ['finfo_open'],
                ['mime_content_type'],
                ['finfo_open'],
                ['mime_content_type'],
                ['finfo_open'],
                ['mime_content_type']
            )
            ->will($this->onConsecutiveCalls(
                true,
                true,
                true,
                false,
                true,
                false,
                false,
                false,
                false
            ));

        $php->expects($this->exactly(4))
            ->method('iniGet')
            ->with('safe_mode')
            ->will($this->onConsecutiveCalls('On', '', 'On', 'On'));

        $php->expects($this->exactly(3))
            ->method('setTimeLimit')
            ->with(30);

        $php->expects($this->exactly(4))
            ->method('fread')
            ->with($this->anything(), 1024)
            ->will($this->returnCallback(function ($handle, $length) {
                return fread($handle, $length);
            }));

        $php->expects($this->exactly(6))
            ->method('callExit');

        $wordpress = $this->getWordpress();
        $wordpress->expects($this->once())
            ->method('wpDie')
            ->with(TXT_UAM_FILE_NOT_FOUND_ERROR_MESSAGE, TXT_UAM_FILE_NOT_FOUND_ERROR_TITLE, ['response' => 404])
            ->will($this->returnValue(null));

        $wordpressConfig = $this->getWordpressConfig();
        $wordpressConfig->expects($this->exactly(6))
            ->method('getMimeTypes')
            ->will($this->onConsecutiveCalls(
                ['txt' => 'textFile'],
                ['txt' => 'textFile'],
                ['txt' => 'textFile'],
                ['txt' => 'textFile'],
                ['txt' => 'textFile'],
                ['jpg' => 'pictureFile']
            ));

        $mainConfig = $this->getMainConfig();
        $mainConfig->expects($this->exactly(6))
            ->method('getDownloadType')
            ->will($this->onConsecutiveCalls(null, 'fopen', 'fopen', 'fopen', 'fopen', 'fopen'));

        $fileHandler = new FileHandler(
            $php,
            $wordpress,
            $wordpressConfig,
            $mainConfig,
            $this->getFileProtectionFactory()
        );

        /**
         * @var Directory $rootDir
         */
        $rootDir = $this->root->get('/');
        $rootDir->add('testDir', new Directory([
            'testFile.txt' => new File('Test text'),
            'testFile2.txt' => new File('Test text2'),
            'testFile3.txt' => new File('Test text3')
        ]));

        $testDir = 'vfs://testDir/';
        $notExistingFile = $testDir.'notExistingFile.txt';

        $fileHandler->getFile($notExistingFile, false);

        $testFileOne = $testDir.'testFile.txt';
        $testFileTwo = $testDir.'testFile2.txt';
        $testFileThree = $testDir.'testFile3.txt';

        echo 'output'; //Test output must be cleared by getFile method
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals('Test text', self::getActualOutput());
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Content-Length: 9'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(false, http_response_code());

        $fileHandler->getFile($testFileTwo, true);
        self::assertEquals('Test text2', self::getActualOutput());
        self::assertEquals(
            [
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Transfer-Encoding: binary',
                'Content-Length: 10'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(false, http_response_code());

        $fileHandler->getFile($testFileThree, false);
        self::assertEquals('Test text3', self::getActualOutput());
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile3.txt"',
                'Content-Transfer-Encoding: binary',
                'Content-Length: 10'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(false, http_response_code());

        $fileHandler->getFile($testFileOne, false);
        self::assertEquals('Test text', self::getActualOutput());
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-type: text/plain;charset=UTF-8',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Content-Length: 9'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(false, http_response_code());

        $fileHandler->getFile($testFileTwo, false);
        self::assertEquals('Test text2', self::getActualOutput());
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: textFile',
                'Content-Disposition: attachment; filename="testFile2.txt"',
                'Content-Transfer-Encoding: binary',
                'Content-Length: 10'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(false, http_response_code());

        $_SERVER['REQUEST_METHOD'] = 'something=0-4';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals('Test text', self::getActualOutput());
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: application/octet-stream',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Content-Length: 9'
            ],
            xdebug_get_headers()
        );
        self::expectOutputString('Test text');
        self::assertEquals(false, http_response_code());
    }

    /**
     * @group  unit
     * @covers ::getFile()
     * @covers ::getFileMineType()
     * @covers ::clearBuffer()
     * @covers ::deliverFilePartial()
     * @covers ::getSeekStartEnd()
     * @runInSeparateProcess
     */
    public function testGetPartialFile()
    {
        $php = $this->getPhp();
        $php->expects($this->exactly(10))
            ->method('functionExists')
            ->with('finfo_open')
            ->will($this->returnValue(true));

        $php->expects($this->exactly(7))
            ->method('fread')
            ->with($this->anything(), 8192)
            ->will($this->returnCallback(function ($handle, $length) {
                return fread($handle, $length);
            }));

        $php->expects($this->exactly(10))
            ->method('callExit');

        $php->expects($this->once())
            ->method('fClose');

        $php->expects($this->exactly(7))
            ->method('connectionStatus')
            ->will($this->onConsecutiveCalls(0, 0, 0, 0, 0, 0, 1));

        $wordpressConfig = $this->getWordpressConfig();
        $wordpressConfig->expects($this->exactly(10))
            ->method('getMimeTypes')
            ->will($this->returnValue(['txt' => 'textFile']));

        $mainConfig = $this->getMainConfig();
        $mainConfig->expects($this->exactly(2))
            ->method('getDownloadType')
            ->will($this->returnValue(null));

        $fileHandler = new FileHandler(
            $php,
            $this->getWordpress(),
            $wordpressConfig,
            $mainConfig,
            $this->getFileProtectionFactory()
        );

        /**
         * @var Directory $rootDir
         */
        $rootDir = $this->root->get('/');
        $rootDir->add('testDir', new Directory([
            'testFile.txt' => new File('Test text'),
            'testFile2.txt' => new File('Test text2'),
            'testFile3.txt' => new File('Test text3')
        ]));

        $testDir = 'vfs://testDir/';
        $notExistingFile = $testDir.'notExistingFile.txt';

        $fileHandler->getFile($notExistingFile, false);

        $testFileOne = $testDir.'testFile.txt';

        $_SERVER['REQUEST_METHOD'] = 'something=0-4';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals('Test text', self::getActualOutput());
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Content-Length: 9'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(false, http_response_code());

        unset($_SERVER['HTTP_RANGE']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals('Test text', self::getActualOutput());
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Content-Length: 9'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(false, http_response_code());

        $_SERVER['HTTP_RANGE'] = 'something=0-4';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals(
            [
                'Content-Transfer-Encoding: binary',
                'Content-Length: 9',
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(416, http_response_code());

        $_SERVER['HTTP_RANGE'] = 'bytes';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Accept-Ranges: bytes',
                'Content-Range: bytes 0-8/9',
                'Content-Length: 9'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(206, http_response_code());

        $_SERVER['HTTP_RANGE'] = 'bytes=4-4';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Accept-Ranges: bytes',
                'Content-Range: bytes 4-4/9',
                'Content-Length: 1'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(206, http_response_code());

        $_SERVER['HTTP_RANGE'] = 'bytes=5-4';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Accept-Ranges: bytes',
                'Content-Range: bytes 0-4/9',
                'Content-Length: 5'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(206, http_response_code());

        $_SERVER['HTTP_RANGE'] = 'bytes=1-4';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Accept-Ranges: bytes',
                'Content-Range: bytes 1-4/9',
                'Content-Length: 4'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(206, http_response_code());

        $_SERVER['HTTP_RANGE'] = 'bytes=2';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Accept-Ranges: bytes',
                'Content-Range: bytes 2-8/9',
                'Content-Length: 7'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(206, http_response_code());

        $_SERVER['HTTP_RANGE'] = 'bytes=a-10';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Accept-Ranges: bytes',
                'Content-Range: bytes 0-8/9',
                'Content-Length: 9'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(206, http_response_code());

        $_SERVER['HTTP_RANGE'] = 'bytes=10-a';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $fileHandler->getFile($testFileOne, false);
        self::assertEquals(
            [
                'Content-Description: File Transfer',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Disposition: attachment; filename="testFile.txt"',
                'Content-Transfer-Encoding: binary',
                'Accept-Ranges: bytes',
                'Content-Range: bytes 0-8/9',
                'Content-Length: 9'
            ],
            xdebug_get_headers()
        );
        self::assertEquals(206, http_response_code());
    }

    /**
     * @group  unit
     * @covers ::createFileProtection()
     */
    public function testCreateFileProtection()
    {
        $wordpress = $this->getWordpress();
        $wordpress->expects($this->exactly(7))
            ->method('isNginx')
            ->will($this->onConsecutiveCalls(false, false, false, true, true, true, true));

        $wordpressConfig = $this->getWordpressConfig();
        $wordpressConfig->expects($this->exactly(6))
            ->method('getUploadDirectory')
            ->will($this->onConsecutiveCalls(
                null,
                'uploadDirectory',
                'uploadDirectory',
                'uploadDirectory',
                'uploadDirectory',
                'uploadDirectory',
                'uploadDirectory'
            ));

        $apacheFileProtection = $this->createMock(ApacheFileProtection::class);
        $apacheFileProtection->expects($this->exactly(3))
            ->method('create')
            ->withConsecutive(['uploadDirectory', null], ['uploadDirectory', null], ['otherDirectory', 'objectType'])
            ->will($this->onConsecutiveCalls(false, true, true));

        $nginxFileProtection = $this->createMock(NginxFileProtection::class);
        $nginxFileProtection->expects($this->exactly(4))
            ->method('create')
            ->withConsecutive(
                ['uploadDirectory', null],
                ['uploadDirectory', null],
                ['uploadDirectory', null],
                ['otherDirectory', 'objectType']
            )
            ->will($this->onConsecutiveCalls(false, true, true, true));

        $fileProtectionFactory = $this->getFileProtectionFactory();
        $fileProtectionFactory->expects($this->exactly(3))
            ->method('createApacheFileProtection')
            ->will($this->returnValue($apacheFileProtection));
        $fileProtectionFactory->expects($this->exactly(4))
            ->method('createNginxFileProtection')
            ->will($this->returnValue($nginxFileProtection));

        $fileHandler = new FileHandler(
            $this->getPhp(),
            $wordpress,
            $wordpressConfig,
            $this->getMainConfig(),
            $fileProtectionFactory
        );

        self::assertFalse($fileHandler->createFileProtection());

        self::assertFalse($fileHandler->createFileProtection());
        self::assertTrue($fileHandler->createFileProtection());
        self::assertTrue($fileHandler->createFileProtection('otherDirectory', 'objectType'));

        self::assertFalse($fileHandler->createFileProtection());
        self::assertTrue($fileHandler->createFileProtection());
        self::assertTrue($fileHandler->createFileProtection());
        self::assertTrue($fileHandler->createFileProtection('otherDirectory', 'objectType'));
    }

    /**
     * @group  unit
     * @covers ::deleteFileProtection()
     */
    public function testDeleteFileProtection()
    {
        $wordpress = $this->getWordpress();
        $wordpress->expects($this->exactly(7))
            ->method('isNginx')
            ->will($this->onConsecutiveCalls(false, false, false, true, true, true, true));

        $wordpressConfig = $this->getWordpressConfig();
        $wordpressConfig->expects($this->exactly(6))
            ->method('getUploadDirectory')
            ->will($this->onConsecutiveCalls(
                null,
                'uploadDirectory',
                'uploadDirectory',
                'uploadDirectory',
                'uploadDirectory',
                'uploadDirectory'
            ));

        $apacheFileProtection = $this->createMock(ApacheFileProtection::class);
        $apacheFileProtection->expects($this->exactly(3))
            ->method('delete')
            ->withConsecutive(['uploadDirectory'], ['uploadDirectory'], ['otherDirectory'])
            ->will($this->onConsecutiveCalls(false, true, true));

        $nginxFileProtection = $this->createMock(NginxFileProtection::class);
        $nginxFileProtection->expects($this->exactly(4))
            ->method('delete')
            ->withConsecutive(['uploadDirectory'], ['uploadDirectory'], ['uploadDirectory'], ['otherDirectory'])
            ->will($this->onConsecutiveCalls(false, true, true, true));

        $fileProtectionFactory = $this->getFileProtectionFactory();
        $fileProtectionFactory->expects($this->exactly(3))
            ->method('createApacheFileProtection')
            ->will($this->returnValue($apacheFileProtection));
        $fileProtectionFactory->expects($this->exactly(4))
            ->method('createNginxFileProtection')
            ->will($this->returnValue($nginxFileProtection));

        $fileHandler = new FileHandler(
            $this->getPhp(),
            $wordpress,
            $wordpressConfig,
            $this->getMainConfig(),
            $fileProtectionFactory
        );

        self::assertFalse($fileHandler->deleteFileProtection());

        self::assertFalse($fileHandler->deleteFileProtection());
        self::assertTrue($fileHandler->deleteFileProtection());
        self::assertTrue($fileHandler->deleteFileProtection('otherDirectory'));

        self::assertFalse($fileHandler->deleteFileProtection());
        self::assertTrue($fileHandler->deleteFileProtection());
        self::assertTrue($fileHandler->deleteFileProtection());
        self::assertTrue($fileHandler->deleteFileProtection('otherDirectory'));
    }
}