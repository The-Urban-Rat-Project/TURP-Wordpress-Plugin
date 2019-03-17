<?php namespace The_Urban_Rat_Project;

use Microneer\Util as Util;

/* Stories from this project are always shown to all other project admins, because they're shared. */
// get_option('turp_misc_shared_project_id');

// HELPERS ----------------------------------------------------------------------------

/*
 * Return the projects where the current or provided user is listed as an administrator.
 * 
 * @param int $user The user id to check. Defaults to the global $user_ID.
 * @returns Array of Project post ids.
 * @see https://pods.io/forums/topic/multiple-choices-meta-field-if-any-condition-problem/
*/
function get_projects_with_administrator( $user_id = null ) {
	global $user_ID;
	if ( !$user_id ) $user_id = $user_ID;

	$args = array(
		'post_type'  => 'project',
		'meta_query' => array(
			array(
				'key'     => 'administrators',
				'value'   => "$user_id",
				'compare' => '=',
			),
		),
		'fields' => 'ids'
	);
	$query = new \WP_Query( $args );
	return $query->posts;
}

/*
 * Return true iff the user is listed project administrator of the provided post.
 * @param int $project_id The project post id to check.
 * @param int $user_id The user id, defaults to current user.
 * @return bool
 */
function is_user_project_administrator( $project_id, $user_id = null ){
	global $user_ID;
	if ( !$user_id ) $user_id = $user_ID;

	$args = array(
		'post_type'  => 'project',
		'p' => $project_id,
		'meta_query' => array(
			array(
				'key'     => 'administrators',
				'value'   => "$user_id",
				'compare' => '=',
			),
		),
		'fields' => 'ids'
	);
	$query = new \WP_Query( $args );
	return count($query->posts) > 0;
}

/*
 * Return true iff the user is permitted to edit the given project.
 * @param int|object $user User ID or object.
 * @param int $project_id The project post id to check.
 * @return bool
 */
function user_can_edit_project( $user, $project_id ){
	if ( is_object($user) ) {
		$user = $user->ID;
	}
	return user_can($user, 'edit_all_projects') || is_user_project_administrator($project_id, $user);
}

/*
 * Return true iff the user is permitted to edit the Story, i.e. can edit the related Project.
 * @param int|object $user User ID or object.
 * @param int $project_id The project post id to check.
 * @return bool
 */
function user_can_edit_story( $user, $story_id ) {
	return user_can_edit_project( $user, get_story_project($story_id) );
}

/**
 *	Return the id of the Project a Story relates to.
 * @param int $story_id
 * @return int
 */
function get_story_project( $story_id ) {
	return get_post_meta( $story_id , 'project' , true )['ID'];
}

// CUSTOMISE ROLES ----------------------------------------------------------------------------

add_filter( 'map_meta_cap', 'The_Urban_Rat_Project\custom_capabilities', 10, 4 );
function custom_capabilities( $caps_required, $cap, $user_id, $args ) {
	// if the capability required is something to do with a Story or Project, check $args to
	// see if the specified Story or Project is one they can edit - if not, then
	// add $caps[] = 'do_not_allow'.

	switch ( $cap ) {
		case 'edit_post':
		case 'delete_post':
			switch ( get_post_type($args[0]) ) {
				case 'story': 
					if ( !user_can_edit_story($user_id,$args[0]) ) {
						$caps_required[] = 'do_not_allow';
					}
					break;
				case 'project': 
					if ( !user_can_edit_project($user_id,$args[0]) ) {
						$caps_required[] = 'do_not_allow';
					}
					break;
			}
		break;
	}

	return $caps_required;
}

// ADMIN VIEWS ----------------------------------------------------------------------------

/* 
 * Unless user has edit_all_projects capability (Editors and Admins), show only Stories 
 * where the current user is in the Administrators field, and project 
 * @see https://codex.wordpress.org/Plugin_API/Action_Reference/pre_get_posts
 */
function get_custom_posts_for_current_user($query) {
	global $pagenow;

	// only filter on edit page and if in the admin panel and if it's the main query (otherwise it recurses)
    if( 'edit.php' != $pagenow || !$query->is_admin || !$query->is_main_query() ) {
        return $query;
	}

	// restrict if the user can't edit all Projects
	if( !current_user_can('edit_all_projects') ) {

		$allowed_project_ids = get_projects_with_administrator();

		if ( count($allowed_project_ids) == 0 ) {
			// @see https://wordpress.stackexchange.com/questions/140692/can-i-force-wp-query-to-return-no-results
			$allowed_project_ids = array(0); 
		}
		
	    switch( $query->get('post_type') ) {
			case 'project':
				// restrict to only the allowed projects
				$query->set( 'post__in', $allowed_project_ids );
				break;
			case 'story':
				// allow them to see the Stories of the shared project
				$allowed_project_ids[] = get_option('turp_misc_shared_project_id');;

				// get only those stories where the project is an allowed one
				$query->set( 'meta_query', ['relation' => 'AND', [
					'key' => 'project',
					'value' => $allowed_project_ids,
					'compare' => 'IN']]
				);
				break;
		}
	}

    return $query;
}
add_filter('pre_get_posts', 'The_Urban_Rat_Project\get_custom_posts_for_current_user');
 

// Customise admin panel story columns ----------------------------------------------
// 
add_filter( 'manage_story_posts_columns', 'The_Urban_Rat_Project\set_custom_story_columns' );
add_action( 'manage_story_posts_custom_column' , 'The_Urban_Rat_Project\do_custom_story_columns', 10, 2 );

function set_custom_story_columns($columns) {
    return [
		'cb' => $columns['cb'],
		'project' => __( 'Project', TURP_TEXT_DOMAIN ),
		'title' => $columns['title'],
		'featured_image' => __( 'Image', TURP_TEXT_DOMAIN ),
		'author' => __('Author'),
		'date' => $columns['date']
	];
}

function do_custom_story_columns( $column, $post_id ) {
    switch ( $column ) {
		case 'author':
			echo the_post_author( );
			break;
		case 'featured_image':
			echo the_post_thumbnail( 'thumbnail' );
			break;
        case 'project':
			// add the project logo as a link
			$project_id = get_story_project( $post_id );
			$url = admin_url('post.php?action=edit&post='.$project_id);
            echo '<a href="'.$url.'">'.get_the_post_thumbnail($project_id,array(200,50)).'</a>';
			break;
    }; 
}

// Customise admin panel project columns ----------------------------------------------
// 
add_filter( 'manage_project_posts_columns', 'The_Urban_Rat_Project\set_custom_project_columns' );
add_action( 'manage_project_posts_custom_column' , 'The_Urban_Rat_Project\do_custom_project_columns', 10, 2 );

function set_custom_project_columns($columns) {
    return [
		'cb' => $columns['cb'],
		'featured_image' => __( 'Image', TURP_TEXT_DOMAIN ),
		'title' => $columns['title'],
		'current_story' => __( 'Current Story', TURP_TEXT_DOMAIN ),
		'data' => __( 'Data', TURP_TEXT_DOMAIN ),
		'contact' => __( 'Contact', TURP_TEXT_DOMAIN ),
		'administrators' => __( 'Administrators', TURP_TEXT_DOMAIN ),
		// 'date' => $columns['date']
	];
}

function do_custom_project_columns( $column, $post_id ) {
    switch ( $column ) {
		case 'current_story':
			$meta = get_post_meta( $post_id , 'current_story' , true );
			if ( $meta ) {
				$story_id = $meta['ID'];
				$title = $meta['post_title'];
				$url = get_post_permalink( $story_id );
				echo "<p><a href=\"$url\">$title</a></p>";
			}
			break;
		case 'contact':
			// add the project logo as a link
			$meta = get_post_meta( $post_id , 'contact' , true );
			if ( $meta ) echo "<p>$meta</p>";
			$meta = get_post_meta( $post_id , 'contact_email' , true );
			if ( $meta ) echo "<p><a href=\"mailto:$meta\">$meta</a></p>";
			$meta = get_post_meta( $post_id , 'contact_phone' , true );
			if ( $meta ) echo "<p>$meta</p>";
			break;
		case 'administrators':
			// this is how it should be done: $pod = pods('project',$post_id);
			// $pod->display('administrators');
			$meta = get_post_meta( $post_id , 'administrators' , true );
			$user_id = $meta['ID'];
			$user_name = $meta['display_name'];
			echo '<p><a href="'.get_edit_user_link($user_id).'">'.$user_name.'</a></p>';
			break;
		case 'featured_image':
			echo the_post_thumbnail( 'thumbnail' );
			break;
		case 'data':
			// show a link to our custom data page
			echo '<a href="options.php?page=project-data&project='.$post_id.'">Download data</a>';
			break;
    }; 
}

// Create project data pages ---------------------------------------------------------------------------

// Add a hidden menu item to get a slug we can use for a project data page
add_action( 'admin_menu', 'The_Urban_Rat_Project\add_project_data_menu_item' );
function add_project_data_menu_item () {
	add_submenu_page( null, 'Project Data', 'Data', 'edit_projects', 'project-data', 'The_Urban_Rat_Project\do_project_data_page' );
}

/** 
 * When showing our submenu page, which isn't shown in the admin menu itself, ensure that the Projects 
 * menu stays open. DOESN'T WORK, FRUSTRATINGLY. 
 * @see https://wordpress.stackexchange.com/questions/73622/add-an-admin-page-but-dont-show-it-on-the-admin-menu
*/
add_filter( 'submenu_file', function($submenu_file){
    $screen = get_current_screen();
    if($screen->id === 'admin_page_project-data'){
        $submenu_file = 'edit-project';
    }
    return $submenu_file;
});


/**
 * Class for printing out a data table with columns Year, Month, Count and Download.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class Monthly_Data_Table extends \WP_List_Table {
	 public function __construct($data){
		global $status, $page;
		//Set parent defaults
		 parent::__construct( array(
		    'singular'  => 'month',     //singular name of the listed records
			'plural'    => 'months',    //plural name of the listed records
			'ajax'      => false        //does this table support ajax?
		 ) );
		 $this->items = $data;
	 }
	
	function get_columns( ) {
		return [
			'year' => 'Year',
			'month' => 'Month',
			'count' => 'Report Count',
			'download' => 'Download'
		];
	}
	
	function column_download( $item ) {
		// print the word 'Download' with a link
		$url = "options.php?page=project-data&year={$item['year']}&month={$item['month']}&project={$_GET['project']}";
		return '<a target="_blank" href="'.$url.'">CSV</a>';
	}
	
	function column_month( $item ) {
		$date = \DateTime::createFromFormat('!m', $item['month']);
		return $date->format('F'); // March	
	}
	
	function column_default( $item, $column_name ) {
		return $item[$column_name];
	}
	
	function prepare_items( ) {
		$this->_column_headers = [ $this->get_columns(),[], [] ];
	}
}

// Show project data page
function do_project_data_page( ) {
	$id = $_GET['project'];
	$project = pods('project', $id);
	?>
	<div class="wrap">
		<h2><?php echo $project->display('title');?> Data</h2>
		<p>Click on the links in the table below to download a CSV file with each month's data.</p>		
	
	<?php
	$base = get_option('turp_api_url');
	$headers = ['x-api-key'=>get_option('turp_api_key') ];
	$api = new Util\ApiResource($base.'/project/'.$id.'/reports/by-month', $headers);
	$data = $api->get_json();
	if ( $api->succeeded() ) {
		$table = new Monthly_Data_Table( $data );
		$table->prepare_items( );
		$table->display();
		global $pagenow;
	} else {
		$error_message = $api->error()->get_error_message();
		echo '<div id="message" class="error"><p>' . $error_message . '</p></div>';
	} 
	?>
	</div>
<?php
}

/**
 * Add a hook to retrieve and download a CSV file if we're on the right page.
 */
add_action( 'plugins_loaded', 'The_Urban_Rat_Project\check_for_download' );
function check_for_download() {
	global $pagenow;
	// check if this is the page where we have to download a CSV file of project monthly data
    if ( $pagenow == 'options.php' && isset($_GET['page']) && $_GET['page']=='project-data' &&
	   array_key_exists('project',$_GET) && array_key_exists('year',$_GET) && array_key_exists('month',$_GET) )
	{
		// check user has permission on this project
		if ( user_can_edit_project(wp_get_current_user(), $_GET['project']) ) {

			// we should download instead of following a normal process
			$year = $_GET['year'];
			$month = $_GET['month'];
			$base = get_option('turp_api_url');
			$headers = ['x-api-key'=>get_option('turp_api_key') ];
			$api = new Util\ApiResource($base."/project/{$_GET['project']}/reports/$year/$month",$headers);
			$filename = sprintf('TURP_Reports_%d-%02d.csv', $year, $month);
			$api->download_csv( $filename );
			if ( $api->succeeded() ) {
				exit();
			} else {
				add_action( 'admin_notices', function(){
					$error = $api->get_wp_error();
					$message = $error->get_message();
					?>
						<div class="error notice">
							<p><?php _e( 'Could not download the CSV file: '.message, TURP_TEXT_DOMAIN ); ?></p>
						</div>
					<?php			
				} );
			}
		} else {
			// user is not permitted
			add_action( 'admin_notices', function(){
				?>
					<div class="error notice">
						<p><?php _e( "You are not permitted to download that Project's data.", TURP_TEXT_DOMAIN ); ?></p>
					</div>
				<?php			
			} );
		}
	}
}


// Show Story details in Current Story dropdown -------------------------------------------------

function pods_story_pick_data($data, $name, $value, $options, $pod, $id){
    if ($name == "pods_meta_current_story") {
        foreach ($data as $id => &$value) {
			if ( $id ) {
	            $story = pods('story', $id);
    	        $project_id = $story->field('project')['ID'];
				$project = pods('project', $project_id );
				$name = $project->display('abbreviation');
				$post_date = get_the_date('Y-m', $id);
        	    $value = $name.' | '.$post_date.' | '.$value;
			}
        }
    }
    return $data;
}

add_filter('pods_field_pick_data', 'The_Urban_Rat_Project\pods_story_pick_data', 1, 6);