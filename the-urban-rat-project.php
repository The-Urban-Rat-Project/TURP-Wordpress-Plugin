<?php
/**
 * The Urban Rat Project
 *
 * @package     TheUrbanRatProject
 * @author      Michael Fielding
 * @copyright   2017 Microneer Limited
 * @license     None
 *
 * @wordpress-plugin
 * Plugin Name: The Urban Rat Project
 * Plugin URI:  http://ratproject.org
 * Description: Adds specific functionality for The Urban Rat Project
 * Version:     0.1.0
 * Author:      Michael Fielding
 * Text Domain: the-urban-rat-project
 * License:     None
 */
 
//Get the absolute path of the directory that contains the file, with trailing slash.
define('TURP_PLUGIN_PATH', plugin_dir_path(__FILE__)); 

// text domain for translation file
define('TURP_TEXT_DOMAIN', 'turp');

use The_Urban_Rat_Project as TURP;
use Microneer\Util as Util;

require_once TURP_PLUGIN_PATH . 'includes/utils.php';

// Add our own special menu items to the toolbar for admin users
$am = new Util\Toolbar_Menu( 'Toolbar Menu' );

// enable access to the back end and the toolbar for anyone who can edit stories
$ea = new Util\Enable_Admin();
$ea->toolbar_for('edit_projects')->admin_for('edit_projects')
   ->toolbar_for('edit_stories')->admin_for('edit_stories');

// initialise what whe need to initialise
if ( is_admin() ){
	require_once TURP_PLUGIN_PATH . 'includes/permissions.php';
	require_once TURP_PLUGIN_PATH . 'includes/options.php';

	$mb = new Util\Remove_Metaboxes();
	$mb->remove( 'mymetabox_revslider_0', 'story', 'normal' );
	$mb->remove( 'mymetabox_revslider_0', 'project', 'normal' );

	$me = new Util\Mandatory_Excerpt();
	$me->add( 'story', 20 );
	
	// post JSON for saved stories to Zapier
	$ss = new Util\Pod_Saved_Hook( 'story', get_option('turp_webhook_story_update_url'), [
		'project_id' => 'fields.project.value', 
		'more_info_url' => 'fields.more_info_url.value'
	]);
	
	// post JSON for saved projects to Zapier
	$ps = new Util\Pod_Saved_Hook( 'project', get_option('turp_webhook_project_update_url'), [
		'contact' => 'fields.contact.value',
		'contact_email' => 'fields.contact_email.value',
		'contact_phone' => 'fields.contact_phone.value',
		'website_url' => 'fields.website.value',
		'facebook_url' => 'fields.facebook.value',
		'current_story_id' => 'fields.current_story.value'
	]);
	
} 
else // not in the backend admin section
{
	
}
