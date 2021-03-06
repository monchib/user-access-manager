<?php
/**
 * ShortCodeController.php
 *
 * The ShortCodeController class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Controller\Frontend;

use UserAccessManager\Access\AccessHandler;
use UserAccessManager\Config\WordpressConfig;
use UserAccessManager\Controller\Controller;
use UserAccessManager\Wrapper\Php;
use UserAccessManager\Wrapper\Wordpress;

/**
 * Class ShortCodeController
 *
 * @package UserAccessManager\Controller\Frontend
 */
class ShortCodeController extends Controller
{
    use LoginControllerTrait;

    /**
     * @var AccessHandler
     */
    protected $accessHandler;

    /**
     * ShortCodeController constructor.
     *
     * @param Php             $php
     * @param Wordpress       $wordpress
     * @param WordpressConfig $wordpressConfig
     * @param AccessHandler   $userHandler
     */
    public function __construct(
        Php $php,
        Wordpress $wordpress,
        WordpressConfig $wordpressConfig,
        AccessHandler $userHandler
    ) {
        parent::__construct($php, $wordpress, $wordpressConfig);
        $this->accessHandler = $userHandler;
    }

    /**
     * @return Wordpress
     */
    protected function getWordpress()
    {
        return $this->wordpress;
    }

    /**
     * Returns the login bar.
     *
     * @return string
     */
    public function getLoginFormHtml()
    {
        $loginForm = '';

        if ($this->wordpress->isUserLoggedIn() === false) {
            $loginForm = $this->getIncludeContents('LoginForm.php');
        }

        return $this->wordpress->applyFilters('uam_login_form', $loginForm);
    }

    /**
     * Handles the login form short code.
     *
     * @return string
     */
    public function loginFormShortCode()
    {
        return $this->getLoginFormHtml();
    }

    /**
     * Handles the public short code.
     *
     * @param array  $attributes
     * @param string $content
     *
     * @return string
     */
    public function publicShortCode($attributes, $content = '')
    {
        return ($this->wordpress->isUserLoggedIn() === false) ? $this->wordpress->doShortCode($content) : '';
    }

    /**
     * Returns the user group map from the short code attribute.
     *
     * @param array $attributes
     *
     * @return array
     */
    private function getUserGroupsMapFromAttributes(array $attributes)
    {
        $userGroups = (isset($attributes['group']) === true) ? explode(',', $attributes['group']) : [];
        return (array)array_flip(array_map('trim', $userGroups));
    }

    /**
     * Handles the private short code.
     *
     * @param array  $attributes
     * @param string $content
     *
     * @return string
     */
    public function privateShortCode($attributes, $content = '')
    {
        if ($this->wordpress->isUserLoggedIn() === true) {
            $userGroupMap = $this->getUserGroupsMapFromAttributes($attributes);

            if ($userGroupMap === []) {
                return $this->wordpress->doShortCode($content);
            }

            $userUserGroups = $this->accessHandler->getUserGroupsForUser();

            foreach ($userUserGroups as $userGroup) {
                if (isset($userGroupMap[$userGroup->getId()])
                    || isset($userGroupMap[$userGroup->getName()])
                ) {
                    return $this->wordpress->doShortCode($content);
                }
            }
        }

        return '';
    }
}
