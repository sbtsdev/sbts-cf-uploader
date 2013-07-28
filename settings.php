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
        	register_setting( 'media', 'sbts_cf_uploader_wp_upload_container' );

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
            add_settings_field(
                'sbts_cf_plugin_settings-sbts_cf_uploader_wp_upload_container',
                'Cloud Files container for native Wordpress uploads (sync only server to container not container back to server)',
                array( &$this, 'settings_field_select_container' ),
				'media',
                'sbts_cf_plugin_settings-section',
                array(
					'field' => 'sbts_cf_uploader_wp_upload_container',
					'type'	=> 'select'
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

		public function settings_field_select_container( $args ) {
			$containers = array();
			if ( class_exists( 'Cloud_Files_Manager' ) ) {
				$cfm = new Cloud_Files_Manager( $this->get_settings() );
				try {
					$containers = $cfm->get_containers();
				} catch ( Exception $e ) {
					error_log( 'SBTS CF Uploader: Settings could not retrieve containers for WP upload option. ' . $e );
				}
			}
			if ( count( $containers ) > 0 ) {
				$field	= $args['field'];
				$type	= $args['type'];
				$value	= get_option( 'sbts_cf_uploader_wp_upload_container' );
				echo	sprintf( '<select id="%s" name="%s">', $field, $field );
				echo	sprintf( '	<option value="">Do not upload/sideload Wordpress uploads</option>' );
				foreach ( $containers as $cont ) {
					$selected = ( $value === $cont['name'] ) ? ' selected="selected"' : '';
					$size = ( $cont['bytes'] > 1024 * 1024 * 1024 ? round( $cont['bytes'] / ( 1024 * 1024 * 1024 ), 2 ) . ' Gb' : round( $cont['bytes'] / ( 1024 * 1024 ), 2 ) . ' Mb' );
					echo	sprintf( '	<option value="%s"%s>%s (%s) [%s]</option>', $cont['name'], $selected, $cont['name'], $cont['count'], $size );
				}
				echo	sprintf( '</select>' );
			}
		}

		public function get_settings() {
			return array(
				'username'	=> get_option( 'sbts_cf_uploader_username' ),
				'api_key'	=> get_option( 'sbts_cf_uploader_api_key' ),
				'wp_upload_container'	=> get_option( 'sbts_cf_uploader_wp_upload_container' )
			);
		}
	}
}
