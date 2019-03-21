<?php namespace The_Urban_Rat_Project;

/* Set up the plugin's options page */

// register all the options we need
add_action(
	'admin_init', 
	function (){
		
		// register all the settings
		register_setting( 
			'turp', 
			'turp_api_url', 
			[
				'type' => 'string', 
				'sanitize_callback' => 'esc_url',
				'default' => 'https://api.ratproject.org/dev/'
            ] 
		);
		register_setting( 
			'turp', 
			'turp_api_key', 
			[
				'type' => 'string', 
				// 'sanitize_callback' => 'intval'
            ] 
		);
		// url called when a project is updated
		register_setting( 
			'turp', 
			'turp_webhook_project_update_url', 
			[
				'type' => 'string', 
				'sanitize_callback' => 'esc_url'
            ] 
		);
		// url called when a story is updated
		register_setting( 
			'turp', 
			'turp_webhook_story_update_url', 
			[
				'type' => 'string', 
				'sanitize_callback' => 'esc_url'
            ] 
		);
		register_setting( 
			'turp', 
			'turp_misc_shared_project_id', 
			[
				'type' => 'int', 
				'sanitize_callback' => 'absint',
				'default' => '798'
            ] 
		);
	} 
);


// register our custom options page
add_action(	
	'admin_menu', 
	function () {
		// register settings sections --------
		
		// API access
		add_settings_section( 'turp_api', 'API access', function(){
			echo "Configure access to the API used to access The Urban Rat Project's data.";
		}, 'turp');
		
		add_settings_field( 
			'turp_api_url',
			'API endpoint (with trailing /)',
			function(){ 
				echo '<input type="url" size="60" name="turp_api_url" value="'.get_option('turp_api_url').'" />';
			},
			'turp',
			'turp_api',
			[ 'label_for' => 'turp_api_url' ]
		);
		add_settings_field( 
			'turp_api_key',
			'API key',
			function(){ 
				echo '<input type="text" size="60" name="turp_api_key" value="'.get_option('turp_api_key').'" />';
			},
			'turp',
			'turp_api',
			[ 'label_for' => 'turp_api_key' ]
		);

		// webhooks
		add_settings_section( 'turp_webhook', 'Webhooks', function(){
			echo "Configure URLs called when website content is updated.";
		}, 'turp');
		
		add_settings_field( 
			'turp_webhook_project_update_url',
			'Project update URL',
			function(){ 
				echo '<input type="url" size="60" name="turp_webhook_project_update_url" value="'.get_option('turp_webhook_project_update_url').'" />';
			},
			'turp',
			'turp_webhook',
			[ 'label_for' => 'turp_webhook_project_update_url' ]
		);
		add_settings_field( 
			'turp_webhook_story_update_url',
			'Story update URL',
			function(){ 
				echo '<input type="url" size="60" name="turp_webhook_story_update_url" value="'.get_option('turp_webhook_story_update_url').'" />';
			},
			'turp',
			'turp_webhook',
			[ 'label_for' => 'turp_webhook_story_update_url' ]
		);
		
		// miscellaneous
		add_settings_section( 'turp_misc', 'Miscellaneous', function(){
			echo "Other configuration settings.";
		}, 'turp');
		
		add_settings_field( 
			'turp_misc_shared_project_id',
			'Shared Project (the Project that contains Stories which all other Projects can access)',
			function(){ 
				echo '<input type="number" size="6" name="turp_misc_shared_project_id" value="'.get_option('turp_misc_shared_project_id').'" />';
			},
			'turp',
			'turp_misc',
			[ 'label_for' => 'turp_misc_shared_project_id' ]
		);
		
		
		// register the option page that shows all the above and the menu item to get to it -----------------------
		add_options_page('The Urban Rat Project', 'TURP', 'manage_options', 'turp', function(){
			// From here is the options page
			?>
			<h1>The Urban Rat Project Settings</h1>
			<form method="POST" action="options.php">
			<?php settings_fields( 'turp' );	//pass slug name of page, also referred
														//to in Settings API as option group name
			do_settings_sections( 'turp' ); 	//pass slug name of page
			submit_button();
			?>
</form>

<?php
		});
	}
);


