<?php
/**
 * ApacheFileProtection.php
 *
 * The ApacheFileProtection class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\File;

use UserAccessManager\Object\ObjectHandler;

/**
 * Class ApacheFileProtection
 *
 * @package UserAccessManager\FileHandler
 */
class ApacheFileProtection extends FileProtection implements FileProtectionInterface
{
    const FILE_NAME = '.htaccess';

    /**
     * Returns the file types.
     *
     * @return null|string
     */
    private function getFileTypes()
    {
        $fileTypes = null;
        $lockFileTypes = $this->mainConfig->getLockFileTypes();

        if ($lockFileTypes === 'selected') {
            $fileTypes = $this->cleanUpFileTypes($this->mainConfig->getLockedFileTypes());
            $fileTypes = ($fileTypes !== '') ? "\.({$fileTypes})" : null;
        } elseif ($lockFileTypes === 'not_selected') {
            $fileTypes = $this->cleanUpFileTypes($this->mainConfig->getNotLockedFileTypes());
            $fileTypes = ($fileTypes !== '') ? "^\.({$fileTypes})" : null;
        }

        return $fileTypes;
    }

    /**
     * Creates the file content if permalinks are active.
     *
     * @param string $directory
     * @param string $fileTypes
     *
     * @return string
     */
    private function getPermalinkFileContent($directory, $fileTypes)
    {
        $areaName = 'WP-Files';
        // make .htaccess and .htpasswd
        $content = "AuthType Basic"."\n";
        $content .= "AuthName \"{$areaName}\""."\n";
        $content .= "AuthUserFile {$directory}.htpasswd"."\n";
        $content .= "require valid-user"."\n";

        if ($fileTypes !== null) {
            /** @noinspection */
            $content = "<FilesMatch '{$fileTypes}'>\n{$content}</FilesMatch>\n";
        }

        return $content;
    }

    /**
     * Creates the file content if no permalinks are active.
     *
     * @param string $fileTypes
     * @param string $objectType
     *
     * @return string
     */
    private function getFileContent($fileTypes, $objectType)
    {
        if ($objectType === null) {
            $objectType = ObjectHandler::ATTACHMENT_OBJECT_TYPE;
        }

        $homeRoot = parse_url($this->wordpress->getHomeUrl());
        $homeRoot = (isset($homeRoot['path']) === true) ? '/'.trim($homeRoot['path'], '/\\').'/' : '/';

        $content = "RewriteEngine On\n";
        $content .= "RewriteBase {$homeRoot}\n";
        $content .= "RewriteRule ^index\\.php$ - [L]\n";
        $content .= "RewriteRule ^([^?]*)$ {$homeRoot}index.php?uamfiletype={$objectType}&uamgetfile=$1 [QSA,L]\n";
        $content .= "RewriteRule ^(.*)\\?(((?!uamfiletype).)*)$ ";
        $content .= "{$homeRoot}index.php?uamfiletype={$objectType}&uamgetfile=$1&$2 [QSA,L]\n";
        $content .= "RewriteRule ^(.*)\\?(.*)$ {$homeRoot}index.php?uamgetfile=$1&$2 [QSA,L]\n";

        if ($fileTypes !== null) {
            /** @noinspection */
            $content = "<FilesMatch '{$fileTypes}'>\n{$content}</FilesMatch>\n";
        }

        $content = "<IfModule mod_rewrite.c>\n$content</IfModule>\n";

        return $content;
    }

    /**
     * Generates the htaccess file.
     *
     * @param string $directory
     * @param string $objectType
     *
     * @return bool
     */
    public function create($directory, $objectType = null)
    {
        $directory = rtrim($directory, '/').'/';
        $fileTypes = $this->getFileTypes();

        if ($this->wordpressConfig->isPermalinksActive() === false) {
            $content = $this->getPermalinkFileContent($directory, $fileTypes);
            $this->createPasswordFile(true, $directory);
        } else {
            $content = $this->getFileContent($fileTypes, $objectType);
        }

        // save files
        $fileWithPath = $directory.self::FILE_NAME;

        try {
            file_put_contents($fileWithPath, $content);
            return true;
        } catch (\Exception $exception) {
            // Because file_put_contents can throw exceptions we use this try catch block
            // to return the success result instead of an exception
        }

        return false;
    }

    /**
     * Deletes the htaccess files.
     *
     * @param string $directory
     *
     * @return bool
     */
    public function delete($directory)
    {
        return $this->deleteFiles($directory);
    }
}
