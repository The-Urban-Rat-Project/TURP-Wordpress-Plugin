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
	
	// Require user's first and last names when they register, and show a message about not needing an 
	// account to receive reminders.
	
	//1. Add a new form element...
	add_action( 'register_form', 'turp_register_form' );
	function turp_register_form() {

		$first_name = ( ! empty( $_POST['first_name'] ) ) ? sanitize_text_field( $_POST['first_name'] ) : '';
		$last_name = ( ! empty( $_POST['first_name'] ) ) ? sanitize_text_field( $_POST['first_name'] ) : '';
			
		?>
		<p>
			<label for="first_name"><?php _e( 'First Name', TURP_TEXT_DOMAIN ) ?><br />
				<input type="text" name="first_name" id="first_name" class="input" value="<?php echo esc_attr(  $first_name  ); ?>" size="25" /></label>
		</p>
		<p>
			<label for="last_name"><?php _e( 'Last Name', TURP_TEXT_DOMAIN ) ?><br />
				<input type="text" name="last_name" id="last_name" class="input" value="<?php echo esc_attr(  $last_name  ); ?>" size="25" /></label>
		</p>
		<?php
	
		// add in a message about not using Register to get reminders
		$content = ob_get_contents();
		$content = str_replace( 'Register For This Site', 'If you want to receive reminders and file reports then don\'t register for a full project administrator account here - instead <a href="/join">sign up for reminders</a>.', $content );
		ob_get_clean();
		echo $content;
    }

    //2. Add validation. In this case, we make sure first_name is required.
    add_filter( 'registration_errors', 'turp_registration_errors', 10, 3 );
    function turp_registration_errors( $errors, $sanitized_user_login, $user_email ) {
        
        if ( empty( $_POST['first_name'] ) || ! empty( $_POST['first_name'] ) && trim( $_POST['first_name'] ) == '' ) {
        $errors->add( 'first_name_error', sprintf('<strong>%s</strong>: %s',__( 'ERROR', TURP_TEXT_DOMAIN ),__( 'You must include a first name.', TURP_TEXT_DOMAIN ) ) );

        }
        if ( empty( $_POST['last_name'] ) || ! empty( $_POST['last_name'] ) && trim( $_POST['last_name'] ) == '' ) {
        $errors->add( 'last_name_error', sprintf('<strong>%s</strong>: %s',__( 'ERROR', TURP_TEXT_DOMAIN ),__( 'You must include a last name.', TURP_TEXT_DOMAIN ) ) );

        }

        return $errors;
    }

    //3. Finally, save our extra registration user meta.
    add_action( 'user_register', 'turp_user_register' );
    function turp_user_register( $user_id ) {
        if ( ! empty( $_POST['first_name'] ) ) {
            update_user_meta( $user_id, 'first_name', sanitize_text_field( $_POST['first_name'] ) );
        }
        if ( ! empty( $_POST['last_name'] ) ) {
            update_user_meta( $user_id, 'last_name', sanitize_text_field( $_POST['last_name'] ) );
        }
    }
}
