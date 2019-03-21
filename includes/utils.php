<?php namespace Microneer\Util;


/*
 * Log something into the browser window.
 * @param string $description The text to associate with the data, printed before it.
 * @param mixed $data The thing to display using var_dump.
 * @param string $dot_deref Dot dereference string e.g. 'a.b.c', if $data is an array.
 */
function clog( $description, $data = null, $dot_deref = null ){
	if ( $dot_deref ) {
		$data = array_deref( $data, $dot_deref );
		$description .= " ($dot_deref)";
	}
  	echo "<pre>\n".$description."\n";
  	var_dump( $data );
  	echo "\n</pre>";
}

/*
 * Find an entry in an array using dot notation.
 * @param array $a The array to look into.
 * @param string $path String e.g. 'a.b.3'
 * @param mixed $default The default value to return if the dereference is invalid.
 * @return mixed
 */
function array_deref(array $a, $path, $default = null)
{
	$current = $a;
	$p = strtok($path, '.');

 	while ($p !== false) {
		if (!isset($current[$p])) {
			return $default;
		}
		$current = $current[$p];
		$p = strtok('.');
	}

	return $current;
}

/*
 * Get post content with shortcodes etc. expanded - can't believe this isn't in the WordPress core.
 */
function get_post_content( $post_id ) {
	return apply_filters('the_content', get_post_field('post_content', $post_id));
}


/*
 * POST some data to a webhook as JSON.
 * 
 * @param array|string $hook_url One or more URLs to POST to.
 * @param array $data The stuff to send.
 */
function remote_post_json( $url, array $data ) {
	// assemble arguments for HTTP POST function call
	$args = array(
		'referer' => get_bloginfo('url'),
		'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
    	'body' => json_encode($data)
	);

	// post to the web hooks
	foreach ( (array)$url as $u ) {
		wp_remote_post( $u, $args );
	}
}

function admin_notice( $message, $class = "notice-error" ) {
	add_action( 'admin_notices', function() use ($message, $class) {
		?>
			<div class="notice <?php echo $class; ?>">
				<p><?php echo $message; ?></p>
			</div>
		<?php			
	} );
}


// Access The Urban Rat Project API ----------------------------------------------

/**
 * Methods will throw a WP_Error if there's a download problem.
 */
class ApiResource {
	private $headers = [ // specify default headers here
		'User-Agent'=>'Microneer/WordPress'
	];
	private $api_key = '';
	private $resource = '';
	private $data = null;
	private $error = null;

	public function __construct( $resource = '/', $headers = [] ) {
		$this->resource = $resource;
		$this->url = $resource;
		$this->headers = array_merge($this->headers, $headers);
	}
	
	/**
	 * Sets $this->error if there was a problem, with the WP_Error that was returned.
	 * @return The body of the response, or null if an error.
	 */
	private function get( ) {
		$response = wp_remote_get( $this->url, [
			'headers' => $this->headers,
			'timeout' => 20
		]);
		
		$url = $this->url;
		add_action( 'admin_notices', function() use ($url) {
			?>
				<div class="notice notice-info">
					<p>Retrieved <?php echo $url; ?></p>
				</div>
			<?php			
		} );
		
		if ( is_wp_error($response) ) {
			$this->error = $response;
			$this->data = null;
		} else {
			$this->error = null;
			$this->data = wp_remote_retrieve_body($response);
		}
		
		return $this->data;
	}
	
	/**
	 * @return True if there was no error in executing the request.
	 */
	public function succeeded( ) {
		return $this->error == null;
	}
	
	/**
	 * @return mixed Depends on what was called.
	 */
	public function data( ) {
		return $this->data;
	}
	
	/**
	 * @return WP_Error or null
	 */
	public function get_wp_error() {
		return $this->error;
	}

	/**
	 * Add each current error (see get_wp_error) as an admin notice with class "error notice".
	 * Registered an 'admin_notices' action for each error.
	 */
	public function add_errors_as_admin_notices( ) {
		$errors = $this->get_wp_error();
		$messages = $errors->get_error_messages();
		
		add_action( 'admin_notices', function() use ($messages) {
			foreach ($messages as $message) {
			?>
				<div class="error notice">
					<p><?php echo $message; ?></p>
				</div>
			<?php			
			}
		} );
	}
	
	/**
	 * Retrieve json from the API and return as a structured array. Ensure you check
	 * succeeded() to check if the request was actually received. data() will also give 
	 * you the result after this has been called.
	 * 
	 * @return array|null 
	 */
	public function get_json( ) {
		// add default headers
		$this->headers['Accept'] = 'application/json';
		
		$this->data = json_decode($this->get(),true);
		return $this->data;
	}
	
	/**
	 * Retrieve a file from the API and download it to the user's browser.
	 */
	private function download( $filename, $download_headers = [] ) {
		// merge provided headers over top of the default ones
		$download_headers = array_merge( [
			'Content-Disposition' => 'filename='.$filename,
			'Cache-Control' => 'private',
			'Content-Type' => 'application/octet-stream'
		], $download_headers );
		
		// go grab the CSV file
		$data = $this->get( );
		
		// if we succeeded, send file headers and echo the data
		if ( $this->succeeded() )
		{
			// send headers
			foreach( $download_headers as $key=>$value ) {
				header($key.': '.$value);
			}	
		
			echo $data;
		}
	}
	
	/**
	 * You should exit() after calling this if it has succeeded(), and it needs to be called before anything 
	 * else is output to the browser.
	 */
	public function download_csv( $filename ) {
		$this->headers['Accept'] = 'text/csv'; // tell API we want a CSV file
		$this->download( $filename, ['Content-Type'=>'text/csv'] ); // tell browser it's a CSV file coming
	}	
}

// JSON POST POD DATA ON SAVE ----------------------------------------------------

/**
 * POST some JSON data every time a Pod custom post type is saved. It allows custom Pod fields to be sent
 * along with the standard content and some other metadata, including all the thumbnail (aka featured image)
 * sizes.
 *
 * Usage:
 * 		$p = new Pod_Saved_Hook( 'book', 'https://place.to.post.to', ['author'=>'fields.book_author.value'],['pending','publish'] );
 *
 */
class Pod_Saved_Hook {

	// array or string of places to POST JSON data
	private $url = [];
	// associative array of fields in the Pods $pieces array, and what JSON fields they are sent as
	private $pieces_to_send = []; 

	// which statuses the hook should be sent on
	private $statuses = [];

	/**
	 * @param string $post_type e.g. 'my_custom_post_type'
	 * @param string|array $url Url(s) to send to.
	 * @param array(string=>string) $pieces_to_send Dot-notation fields in the $pieces array from Pods, indexed by
	 *    	 field names for them in the JSON data.
	 */
	public function __construct( $post_type, $url, $pieces_to_send = [], $statuses = ['publish'] ){
		$this->url = $url;
		$this->pieces_to_send = $pieces_to_send;
		$this->statuses = $statuses;
		
		// add a filter to capture when a pod is saved
		add_action('pods_api_post_save_pod_item_'.$post_type, [$this,'do_pod_save'], 10, 3);  
	}
	
	public function do_pod_save( $pieces, $is_new_post, $post_id ) {
		// first check we should activate the hook based on the current post status
		if ( !in_array(get_post_status($post_id), $this->statuses) ) 
			return;

		$data = $this->extract_pod_saved_data( $post_id, $is_new_post, $pieces, $this->pieces_to_send );
		
		remote_post_json( $this->url, $data );
	}
	
	/**
	 * Helper for extracting data about the post and its custom fields when a Pod is saved.
	 * @param int $post_id
	 * @param boolean $is_new_post
	 * @param array(string=>mixed) $pieces As passed to pods_api_post_save_pod_item_xxx action handler
	 * @param array(string=>string) $pieces_to_extract Array of fields (dot notation specified) to extract
	 * 		from $pieces and send, indexed by the name of the field the will form in the JSON payload.
	 * @return array(string=>mixed) id, status, title, content, is_new_post, featured image (array of sizes 
	 *     with their URL, and the extracted $pieces.
	 */
	private function extract_pod_saved_data( $post_id, $is_new_post, $pieces, $pieces_to_extract = array() ) {
		$data = array(
			'id' => $post_id,
			'parent_revision_id' => wp_is_post_revision( $post_id ),
			'status' => get_post_status( $post_id ),
			'title' => get_the_title( $post_id ),
			'content' => get_post_content( $post_id ), // will be in HTML format
			'excerpt' => get_the_excerpt( $post_id ),
			'is_new' => $is_new_post,
			'featured_image' => $this->all_post_thumbnail_urls( $post_id ),
			'permalink' => get_permalink( $post_id )
		);

		// extract all the requested fields from the $pieces array
		foreach ( $pieces_to_extract as $field_name => $field_address ) {
			$data[$field_name] = array_deref( $pieces, $field_address );
		}

		return $data;
	}

	/*
	 * Get all the thumbnail urls available for the provided Post.
	 *
	 * @param int|WP_Post $post_or_id 
	 * @return array(string)
	 */
	private function all_post_thumbnail_urls( $post_or_id ){
		$thumbnails = array();
		$thumbnail_id = get_post_thumbnail_id( $post_or_id );
		if ( $thumbnail_id !== '' ) {
			$sizes = get_intermediate_image_sizes();
			foreach( $sizes as $size ) {
				// obtain the url
				$thumbnails[$size] = wp_get_attachment_image_src($thumbnail_id,$size)[0];
			}
		}
		return $thumbnails;
	}

}


// MANDATORY EXCERPT ----------------------------------------------------

/**
 * Helper class which removes specified metaboxes from specified pages. It manages setting up the hooks
 * and calling them.
 * 
 * @author: Michael Fielding
 * 
 * Usage: 
 * 		$me = new Mandatory_Excerpt()
 *		$me->add('post', 20 )
 *		   ->add('my_custom_post', 44); // repeat as needed
 */
class Mandatory_Excerpt {
	private $min_lengths = [];

	// set up the hooks we'll need
	public function __construct() {
		add_filter('wp_insert_post_data', [$this,'do_mandatory_excerpt']);
		add_action('admin_notices', [$this,'do_admin_notice']);
	}
	
	public function add( $post_type, $minimum_excerpt_length = 0 ){
		$this->min_lengths[$post_type] = $minimum_excerpt_length;
		return $this;
	}

	// handler for wp_insert_post_data hook
	public function do_mandatory_excerpt( $data ) {

		$post_type = $data['post_type'];
		// if there's a minimum excerpt length set for this post type, check it
		if ( isset($this->min_lengths[$post_type]) ) {
			$min_length = $this->min_lengths[$post_type];
			$excerpt = $data['post_excerpt'];
			if ( empty($excerpt) || strlen($excerpt)<$min_length ) {
				// if user was trying to publish the post, show a warning
				if ($data['post_status'] === 'publish') {
					// add a filter to intercept the post-save redirect and show a message
					add_filter('redirect_post_location', [$this,'do_error_message_redirect'], 10, 2);
				}
				// ensure it's not published
				if ('deleted' !== $data['post_status'] && 'trash' !== $data['post_status']) {
					$data['post_status'] = 'draft';    
				}			
			}
		}
		return $data;
	}
	
	// intercept the after-save redirect and ensure a message will be shown on the following page
	public function do_error_message_redirect($location,$post_id) {
		remove_filter('redirect_post_location', [$this,'do_error_message_redirect'], 10, 2);
		$post_type = get_post_type( $post_id );
		if ( isset($this->min_lengths[$post_type]) ) {
			$min_length = $this->min_lengths[$post_type];
			
			// add a query variable to display our admin notice
			$location = add_query_arg('excerpt_not_long_enough', $min_length, $location);
			
			// remove the post saved message
			$location = remove_query_arg( 'message', $location );
		}
		
		return $location;
	}

	/**
	 * Show a warning or error that the excerpt is required, if it is not manually defined for something where it's required.
	 */
	public function do_admin_notice() {
		// show a warning or error if there is no custom excerpt on a type where there should be
		$screen = get_current_screen();
		
		if ( $screen != NULL && $screen->base == 'post' && isset($this->min_lengths[$screen->post_type]) ) {
			$min_length = $this->min_lengths[$screen->post_type];
			$post_id = $_GET['post'];
			
			if ( !has_excerpt($post_id) || strlen(get_the_excerpt($post_id)) < $min_length ) {
				$this->echo_admin_notice(['Cannot publish this until a hand-written Excerpt of at least ',$min_length,' characters is provided.'], 'warning');
			}
			
			// check if the excerpt not long enough message was requested by being put in the query string
			if (isset($_GET['excerpt_not_long_enough'])) {
				$this->echo_admin_notice(['Publication failed because the Excerpt wasn\'t ',$min_length,' characters or more.']);
			}
		}		
	}

	/**
	 * Show an admin message. Should be called in an admin_notices hook.
	 * @param string|array(string)  $text The notice to show. This is passed through _e() for translation.
	 * 		Can be an array, in which case each string is concatenated after passing thru _e(), which allows
	 * 		parameters to be embedded.
	 * @param string $class error|warning|success|info
	 * @param boolean $is_dismissable If true, then the notice has an X to dismiss it.
	 */
	private function echo_admin_notice( $text, $class = 'error', $is_dismissable = true ) {
		$dismissable = $is_dismissable ? 'is-dismissable' : '';
		echo "<div class=\"notice notice-$class $dismissable\"><p>";
		foreach( (array)$text as $t ) {
			_e($t);
		}
		echo( "</p></div>" );
	}
	
}



// REMOVE META BOXES ----------------------------------------------------

/**
 * Helper class which removes specified metaboxes from specified pages. It manages setting up the hooks
 * and calling them.
 * 
 * Usage: 
 * 		$mb = new Remove_Metaboxes(); 
 *		$mb->remove('mymetabox_revslider_0', 'post', 'normal' ); // repeat as needed
 */
class Remove_Metaboxes {
	
	private $metaboxes_to_remove = [];
	
	public function __construct(  ) {
		// define the hooks we know about
		$metabox_hooks = ['do_meta_boxes', 'add_meta_boxes', 'wp_dashboard_setup', 'admin_menu'];

		// set up all of the defined hooks to call corresponding member functions
		foreach ( $metabox_hooks as $metabox_hook ) {
			add_action( $metabox_hook, [$this,$metabox_hook] );
		}
	}
	
	/**
	 * Define a metabox to be removed. Call this repeatedly with each metabox to be added.
	 * 
	 * @param string $metabox e.g. mymetabox_revslider_0
	 * @param string $page e.g. 'post', 'page', 'dashboard', 'my_custom_post_type'
	 * @param string $context Explained on https://codex.wordpress.org/Function_Reference/remove_meta_box
	 * @param string $hook One of the hooks given on https://codex.wordpress.org/Function_Reference/remove_meta_box
	 */
	public function remove( $metabox, $page, $context, $hook = 'do_meta_boxes' ) {
		$this->metaboxes_to_remove[$hook][] = [
			'metabox' => $metabox,
			'page' => $page,
			'context' => $context
		];
		return $this;
	}
	
	public function do_meta_boxes( ){
		$this->do_removal('do_meta_boxes');
	}
	
	public function admin_menu( ) {
		$this->do_removal('admin_menu');
	}
	
	public function wp_dashboard_setup( ) {
		$this->do_removal('wp_dashboard_setup');
	}

	public function add_meta_boxes( ) {
		$this->do_removal('add_meta_boxes');
	}
	
	// given a key into $metaboxes_to_remove, loops through all the entries there and calls remove_meta_box().
	private function do_removal( $key ) {
		// check any metaboxes with this key are set
		if ( !isset($this->metaboxes_to_remove[$key]) ) 
			return false;
		// loop through each defined metabox with this key and remove them
		foreach( $this->metaboxes_to_remove[$key] as $m ){
			remove_meta_box( $m['metabox'], $m['page'], $m['context'] );
		}
		return true;
	}
}


// ADD MENU TO TOOLBAR ------------------------------------------------------------------

/** 
 * Helper class which adds an existing Wordpress menu to the admin toolbar which 
 * appears at the top of the screen.
 */
class Toolbar_Menu {
	
	private $menu_name = null;
	
	/**
	 * @param string $menu_name The name of the Wordpress menu configured by Appearance->Menus
	 */
	public function __construct( $menu_name ) 
	{
		$this->menu_name = $menu_name;
		add_action( 'admin_bar_menu', [$this,'do_add_menu'], 500 );
	}
	
	public function do_add_menu( )
	{
		$menu = wp_get_nav_menu_object( $this->menu_name );
		$menu_items = wp_get_nav_menu_items( $menu->term_id );
		global $wp_admin_bar;
						
		foreach ($menu_items as $items) {
			$args = array( 
				'id' => 	$items->ID,
				'title' => 	$items->title,
				'parent' => $items->menu_item_parent,
				'href' 	=> 	$items->url,
				'meta' 	=> 	FALSE
			);
				
			$wp_admin_bar->add_node( $args );
		}
	}
}


// RE-ENABLE TOOLBAR FOR CERTAIN ROLES ---------------------------------------------------------

/**
 * This class is used to enable access to the backend for certain roles (or capabilities).
 * Initialise an object, then call toolbar_for() and admin_for().
 * 
 * It requires WooCommerce plugin to be active.
 * 
 * @see https://www.role-editor.com/woocommerce-admin-bar-access/
 */
class Enable_Admin {

	/** @var $ toolbar_roles Roles to enable toolbar at the top of the screen in the front end. */
	private $toolbar_roles = [];
	
	/** @var $ admin_roles roles to enable for admin access to the back end. */
	private $admin_roles = [];
	
	/**
	 * @return $this To support method chaining.
	 */
	public function __construct() {
		add_filter('woocommerce_disable_admin_bar', [$this,'do_disable_toolbar'], 20, 1);
		add_filter('woocommerce_prevent_admin_access', [$this,'do_disable_admin_access'], 20, 1);
		return $this;
	}
	
	/**
	 * Enable back end admin access for this role.
	 * @param string $role_or_capability Enable for this role, or every role with this capability. 
	 */
	public function admin_for( $role_or_capability ) {
		$this->admin_roles[] = $role_or_capability;
		return $this;
	}
	
	/**
	 * Enable the toolbar in the front end for this role.
	 * @param string $role_or_capability Enable for this role, or every role with this capability. 
	 */
	public function toolbar_for( $role_or_capability ) {
		$this->toolbar_roles[] = $role_or_capability;
		return $this;
	}	
	
	public function do_disable_admin_access( $prevent_admin_access ) {
		return $this->do_check($this->admin_roles, $prevent_admin_access);
	}
	
	public function do_disable_toolbar( $prevent_admin_access ) {
		return $this->do_check($this->toolbar_roles, $prevent_admin_access);
	}
	
	
	private function do_check( $roles_to_check, $prevent_admin_access ) {
		foreach ( $roles_to_check as $role ) {
			if ( current_user_can($role) ) 
			{
				return false; // allow users with any of the above capabilities to get their admin area
			}
		}
		return $prevent_admin_access;
	}
	
}
