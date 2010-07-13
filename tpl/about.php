<?php
/**
 * about.php
 * 
 * Shows the about page at the admin panel.
 * 
 * PHP versions 5
 * 
 * @category  UserAccessManager
 * @package   UserAccessManager
 * @author    Alexander Schneider <alexanderschneider85@googlemail.com>
 * @copyright 2008-2010 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $Id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */

?>
<div class="wrap">
	<h2><?php echo TXT_ABOUT; ?></h2>
    <div id="poststuff">
        <div class="postbox">
        	<h3 class="hndle"><?php echo TXT_HOW_TO_SUPPORT; ?></h3>
        	<div class="inside">
        		<p><?php echo TXT_SEND_REPORTS; ?></p>
        		<p><?php echo TXT_MAKE_TRANSLATION; ?></p>
    			<p>
    				<?php echo TXT_DONATE; ?><br/>
    				<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=1947052">
    					<img style="margin:4px 0;" alt="Make payments with PayPal - it's fast, free and secure!" name="submit" src="https://www.paypal.com/en_US/i/btn/x-click-but21.gif" />
    				</a>
    			</p>
    			<p><?php echo TXT_PLACE_LINK; ?></p>
        	</div>
        </div>
        <div class="postbox">
        	<h3 class="hndle"><?php echo TXT_THANKS; ?></h3>
        	<div class="inside">
        		<p>
        		    <strong><?php echo TXT_SPECIAL_THANKS; ?></strong><br/>
        		    <br/>
        		    <?php echo TXT_THANKS_TO; ?><br/> 
        			Patric Schwarz, Mark LeRoy, Huska, macbidule, Helmut, -sCo-, all beta testers and all others I forgot.
        		</p>
        	</div>
        </div>
    </div>
</div>