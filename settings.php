<?php
/*
 * This file allows the Settings>Media Cloud Files Uploader Settings to function
 */
if (! class_exists( 'SBTS_CF_Plugin_Settings' ) ) {
	class SBTS_CF_Plugin_Settings {

		public function __construct() {
			// register actions
			add_action( 'admin_init', array( &$this, 'admin_init' ) );
		}

		public function admin_init() {
        	// register your plugin's settings
        	register_setting( 'media', 'sbts_cf_uploader_username' );
        	register_setting( 'media', 'sbts_cf_uploader_api_key' );

        	// add your settings section
        	add_settings_section(
        	    'sbts_cf_plugin_settings-section',
        	    'Cloud Files Settings',
        	    array( &$this, 'settings_section_sbts_cf_uploader' ),
				'media'
        	);

        	// add your setting's fields
            add_settings_field(
                'sbts_cf_plugin_settings-sbts_cf_uploader_username',
                'Cloud Files username',
                array( &$this, 'settings_field_input_text' ),
				'media',
                'sbts_cf_plugin_settings-section',
                array(
                    'field' => 'sbts_cf_uploader_username',
					'type'	=> 'text'
                )
            );
            add_settings_field(
                'sbts_cf_plugin_settings-sbts_cf_uploader_api_key',
                'Cloud Files API Key',
                array( &$this, 'settings_field_input_text' ),
				'media',
                'sbts_cf_plugin_settings-section',
                array(
					'field' => 'sbts_cf_uploader_api_key',
					'type'	=> 'password'
                )
            );
		}

		public function settings_section_sbts_cf_uploader() {
			echo 'Complete these settings to connect Cloud File Uploader to Rackspace Cloud Files';
		}

        /**
         * This function provides text inputs for settings fields
         */
        public function settings_field_input_text( $args ) {
            // Get the field name from the $args array
            $field	= $args['field'];
			$type	= $args['type'];
            // Get the value of this setting
            $value = get_option( $field );
            // echo a proper input type="text"
            echo sprintf( '<input type="%s" name="%s" id="%s" value="%s" />', $type, $field, $field, $value );
        }

		public function get_settings() {
			return array(
				'username'	=> get_option( 'sbts_cf_uploader_username' ),
				'api_key'	=> get_option( 'sbts_cf_uploader_api_key' )
			);
		}
	}
}
