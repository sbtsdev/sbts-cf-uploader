<?php
/*
Plugin Name: Cloud Files Uploader
Plugin URI: http://github.com/sbtsdev/cloud-files-uploader
Description: This plugin allows a user to upload files to Rackspace Cloud Files and interact with them.
Version: v0.2.1
Author: Joshua Cottrell
Author URI: http://github.com/jcottrell
License: GPL2
*/

/*  Copyright 2013  Joshua Cottrell  (email : jcottrell [a-t sign] sbts [] edu)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
 * Version History
 * See CHANGELOG.md
*/

if ( !class_exists( 'SBTS_CF_Plugin' ) ) {
	class SBTS_CF_Plugin {
		private $plugin_dir;
		private $sbts_cf_plugin_settings;
		private $cfm;
		private $ret;

		/**
		* Construct the plugin object
		*/
		public function __construct() {
			$this->plugin_dir = dirname( __FILE__ );

			/* Initialization section */
			// Settings
			require_once( sprintf( "%s/settings.php", $this->plugin_dir ) );
			$this->sbts_cf_plugin_settings = new SBTS_CF_Plugin_Settings();
			// Cloud Files Manager
			require_once( sprintf( "%s/cloud-files-manager.php", $this->plugin_dir ) );
			$this->cfm = new Cloud_Files_Manager( $this->sbts_cf_plugin_settings->get_settings() );
			// Uploader itself
			add_action( 'admin_menu', array( &$this, 'add_menu' ) );
			// Return message, the basic pattern of all returned data
			$this->ret = array( 'success' => false, 'message' => 'Unable to connect.', 'pl' => array() );

			/* Attach things to the interace (ours or WP) */
			// Add ajax functionality for page interaction
			add_action( 'wp_ajax_get_containers', array( &$this, 'get_containers' ) );
			add_action( 'wp_ajax_get_files', array( &$this, 'get_files' ) );
			add_action( 'wp_ajax_upload_files', array( &$this, 'upload_files' ) );
			add_action( 'wp_ajax_delete_files', array( &$this, 'delete_files' ) );
			/* Wordpress Uploads Actions and Filters */
			add_filter( 'wp_update_attachment_metadata', array( &$this, 'wp_upload_via_metadata' ), 10, 2 ); // called when metadata is created and updated
			add_action( 'delete_attachment', array( &$this, 'delete_attachment' ) ); // called when an attachment is deleted through the WP interface
			add_filter( 'wp_get_attachment_url', array( &$this, 'rewrite_attachment_url' ), 10, 2 ); // try to get any reference to the link, especially the link that shows directly after the upload!
		}

		public function add_menu() {
			$media_page_hook = add_media_page(
				'Cloud Files Uploader',
				'Cloud Files Uploader',
				'upload_files',
				'sbts_cf_plugin',
				array( &$this, 'plugin_page' )
			);
			add_action( 'admin_print_styles-' . $media_page_hook, array( &$this, 'add_css' ) );
			add_action( 'admin_print_scripts-' . $media_page_hook, array( &$this, 'add_js' ) );
		}

		public function plugin_page() {
        	if (! current_user_can( 'upload_files' ) ) {
        		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        	}

			include( sprintf(  "%s/templates/uploader-page.php", $this->plugin_dir  ) );
		}

		public function add_css() {
			wp_enqueue_style( 'sbts-cf-uploader-css', plugin_dir_url( '' ) . 'sbts-cf-uploader/css/sbts-cf-uploader-style.css' );
		}

		public function add_js() {
			wp_enqueue_script( 'sbts-cf-uploader-handlebars', plugin_dir_url( '' ) . 'sbts-cf-uploader/js/handlebars.js', array(), false, true );
			wp_enqueue_script( 'sbts-cf-uploader-zeroclipboard', plugin_dir_url( '' ) . 'sbts-cf-uploader/js/ZeroClipboard.min.js', array(), false, true );
			wp_enqueue_script( 'sbts-cf-uploader-js', plugin_dir_url( '' ) . 'sbts-cf-uploader/js/sbts-cf-uploader-core.js', array( 'jquery', 'sbts-cf-uploader-handlebars' ), false, true );
			$sbts_cf_uploader = array( 'url' => plugin_dir_url( '' ) . 'sbts-cf-uploader/' );
			wp_localize_script( 'sbts-cf-uploader-js', 'sbts_cf_uploader', $sbts_cf_uploader );
		}

		public function my_get_containers() {
			try {
				return $this->cfm->get_containers();
			} catch ( Exception $e ) {
				$this->error_ret( $e );
			}
			return array();
		}

		public function get_containers() {
			// TODO uncomment for production
			//$this->check_ajax_referer( 'get_containers', 'sbts_cf_auth', true );
			$this->ret['pl'] = $this->my_get_containers();
			$this->create_ret( 'found', 'container', 'containers', '' );
			$this->send_ret();
		}

		public function my_get_file( $container, $file_name ) {
			try {
				return $this->cfm->get_file( $container, $file_name );
			} catch ( Exception $e ) {
				$this->error_ret( $e );
			}
			return array();
		}

		public function get_file() {
			try {
				$this->ret['pl'] = $this->my_get_file( $_GET['sbts_cf_cont'], $_GET['sbts_cf_file_name'] );
				$this->create_ret( 'found', 'file', 'files', '' );
			} catch ( Exception $e ) {
				$this->error_ret( $e );
			}
			$this->send_ret();
		}

		public function get_files() {
			// TODO uncomment for production
			//$this->check_ajax_referer( 'get_files', 'sbts_cf_auth', true );
			try {
				$this->ret['pl'] = $this->cfm->get_files( $_GET['sbts_cf_cont'] );
				$this->create_ret( 'found', 'file', 'files', '' );
			} catch ( Exception $e ) {
				$this->error_ret( $e );
			}
			$this->send_ret();
		}

		public function my_upload_files( $container, $files ) {
			set_time_limit( 0 );
			try {
				$this->ret['pl'] = $this->cfm->upload_files( $container, $files );
				$this->create_ret( 'uploaded', 'file', 'files', ' Files did not upload to the server.' );
			} catch ( Exception $e ) {
				$this->error_ret( $e );
			}
		}

		public function upload_files() {
			$files = $this->file_array_from_raw_upload( $_POST['sbts_cf_path'], $_FILES );
			$this->my_upload_files( $_POST['sbts_cf_cont'], $files );
			$this->send_ret();
		}

		public function my_delete_files( $container, $file_names ) {
			try {
				$this->ret['pl'] = $this->cfm->delete_files( $container, $file_names );
				$this->create_ret( 'deleted', 'file', 'files', '' );
			} catch ( Exception $e ) {
				$this->error_ret( $e );
			}
		}

		public function delete_files() {
			$files = array();
			foreach ( $_POST['file'] as $file ) {
				$files[] = $this->wp_file_mover_info( $file );
			}
			$this->my_delete_files( $_POST['sbts_cf_cont'], $files );
			if ( $this->ret['success'] ) {
				// check to see if there was a corresponding Wordpress attachment
				global $wpdb;
				foreach ( $this->ret['pl'] as $file ) {
					$id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s", $file['full_name'] ) );
					if (! empty( $id ) ) {
						delete_post_meta( $id, '_sbts_cf_container' );
					}
				}
			}
			$this->send_ret();
		}

		public function wp_upload_via_metadata( $metadata, $attach_id ) {
			// $this->debug_test('wp_update_attachment_metadata (' . $attach_id . ')', $metadata );
			$files = array();
			if ( empty( $metadata ) ) {
				$file = get_post_meta( $attach_id, '_wp_attached_file', true );
				if (! empty( $file ) ) {
					$files[] = $this->wp_file_mover_info( $file );
				}
			} else {
				$files = $this->file_array_from_metadata( $metadata );
			}
			$settings = $this->sbts_cf_plugin_settings->get_settings();
			if ( isset( $settings ) && isset( $settings['wp_upload_container'] ) && ( strlen( $settings['wp_upload_container'] ) > 0 ) ) {
				$container = $settings['wp_upload_container'];
				$this->my_upload_files( $container, $files );
				if ( $this->ret['success'] ) {
					// add custom field with container and url
					if (! empty( $this->ret['pl'][0]['full_name'] ) ) {
						$cont_url = rtrim( substr( $this->ret['pl'][0]['uri'], 0, strrpos( $this->ret['pl'][0]['uri'], $this->ret['pl'][0]['full_name'] ) ), '/' );
						$cdn_url = array( 'name' => $container, 'url' => $cont_url );
						update_post_meta( $attach_id, '_sbts_cf_container', $cdn_url );
					}
					// if present, add the duration to the attachment
					if (! empty( $this->ret['pl'][0]['mdata']['X-Object-Meta-Rduration'] ) ) {
						update_post_meta( $attach_id, '_sbts_cf_duration', $this->ret['pl'][0]['mdata']['X-Object-Meta-Rduration'] );
					}
				}
			}
			return $metadata;
		}

		public function delete_attachment( $attach_id ) {
			$settings = $this->sbts_cf_plugin_settings->get_settings();
			if ( isset( $settings ) && isset( $settings['wp_upload_container'] ) && ( strlen( $settings['wp_upload_container'] ) > 0 ) ) {
				$container = $settings['wp_upload_container'];
				$metadata = wp_get_attachment_metadata( $attach_id );
				$files = $this->file_array_from_metadata( $metadata );
				$this->my_delete_files( $container, $files );
			}
		}

		public function rewrite_attachment_url( $url, $attach_id ) {
			// $this->debug_test('wp_get_attachment_url (' . $attach_id . ')', $url);
			$settings = $this->sbts_cf_plugin_settings->get_settings();
			if ( isset( $settings ) && isset( $settings['wp_upload_container'] ) && ( strlen( $settings['wp_upload_container'] ) > 0 ) ) {
				$files = array();
				$is_on_cf = false;
				$container = $settings['wp_upload_container'];
				$metadata = wp_get_attachment_metadata( $attach_id );
				if ( empty( $metadata ) ) {
					$file = get_post_meta( $attach_id, '_wp_attached_file', true );
					if (! empty( $file ) ) {
						$files[] = $this->wp_file_mover_info( $file );
					}
				} else {
					$files = $this->file_array_from_metadata( $metadata );
				}
				foreach ( $files as $file ) {
					if ( strrpos( $url, $file['name'] ) !== false ) {
						$file_name = $file['name'];
						break;
					}
				}
				if ( isset( $file_name ) ) {
					// make sure the container they want to use and the one the file is in are the same
					$file_container = get_post_meta( $attach_id, '_sbts_cf_container', true );
					if ( (! empty( $file_container ) ) && ( $file_container['name'] === $container ) ) {
						/* if yet-to-be-created $setting['cdn_url'] then replace with that instead
						if ( isset( $settings['cdn_url'] ) && ( strlen( $settings['cdn_url'] ) > 0 ) ) {
							$upload_dir = wp_upload_dir();
							$url = str_replace( rtrim( $upload_dir['baseurl'], '/' ), 'http://cdn.albertmohler.com', $url );
						} else { */
						if ( class_exists( 'MP_WP_Root_Relative_URLS' ) ) {
							 // Root Relative plugin mangles url when parse_url returns no properties (perhaps caused by long urls or dashes in subdomain)
							remove_filter( 'image_send_to_editor', array( 'MP_WP_Root_Relative_URLS', 'root_relative_image_urls' ), 1, 8  );
							remove_filter( 'media_send_to_editor', array( 'MP_WP_Root_Relative_URLS', 'root_relative_media_urls' ), 1, 3 );
						}
						$url = rtrim( $file_container['url'], '/' ) . '/' . $file_name;
					}
				}
			}

			return $url;
		}

		public function debug_test( $caller, $var = null ) {
			$out = $caller;
			if ( $var ) {
				$out .= ', ' . print_r( $var, true );
			}
			error_log( 'SBTS CF Uploader - ' . $out );
		}

		private function file_array_from_raw_upload( $req_path, $underscore_files ) {
			$files = array();
			if ( is_array( $underscore_files['file']['name'] ) ) {
				// TODO make '/' a configurable delimiter
				// sanitize path so /media/audio/ or /media/audio or media/audio
				// becomes a usable media/audio/ to prepend to the file name
				if ( '/' === $req_path ) {
					$req_path = '';
				} else {
					if ( '/' === substr( $req_path, 0, 1 ) ) {
						$req_path = substr( $req_path, 1 );
					}
					$req_path = rtrim( $req_path, '/' ) . '/';
				}
				foreach ( $underscore_files['file']['name'] as $index => $fname ) {
					// TODO check file type $_FILES['file']['type'] against array of predefined permissible types (audio/mpeg or video/x-flv or video/mp4 etc.)
					$files[] = $this->file_mover_info( $req_path . $fname, $underscore_files['file']['tmp_name'][$index] );
				}
			}
			return $files;
		}

		private function file_array_from_metadata( $metadata ) {
			$files = array();
			if (! empty( $metadata ) ) {
				// $this->debug_test( 'file_array_from_metadata', $metadata );
				$subdir_arr = explode( DIRECTORY_SEPARATOR, $metadata['file'] );
				array_pop( $subdir_arr ); // not concerned with the linux/windows file name right now
				$files[] = $this->wp_file_mover_info( $metadata['file'] );
				if ( isset( $metadata['sizes'] ) ) {
					foreach ( $metadata['sizes'] as $s_key => $s_val ) {
						$files[] = $this->wp_file_mover_info( implode( DIRECTORY_SEPARATOR, array_merge( $subdir_arr, array( $s_val['file'] ) ) ) );
					}
				}
			}
			return $files;
		}

		private function wp_file_mover_info( $name ) {
			$upload_dir = wp_upload_dir();
			$location = rtrim( $upload_dir['basedir'], '/' ) . '/' . $name;
			return $this->file_mover_info( $name, $location );
		}

		private function file_mover_info( $name, $location ) {
			return array(
				'name'		=> $name,
				'location'	=> $location
			);
		}

		private function create_ret( $verb, $single, $plural, $error_msg ) {
			$count = count( $this->ret['pl'] );
			$this->ret['success'] = ( $count > 0 );
			if ( $this->ret['success'] ) {
				$this->ret['message'] = ucfirst( $verb ) . ' ' . $count . ' ' . ( $count !== 1 ? $plural : $single ) . '.';
			} else {
				$this->ret['message'] = 'No ' . $plural . ' ' . $verb . '.' . $error_msg;
			}
		}

		private function error_ret( $e ) {
			$this->ret['message'] = sprintf( "%s: %s", get_class( $e ), $e->getMessage() );
			$this->ret['pl'] = array();
		}

		private function send_ret() {
			echo json_encode( $this->ret );
			die;
		}

		/**
		* Activate the plugin
		*/
		public static function activate() {
			// Do nothing
		}

		/**
		* Deactivate the plugin
		*/
		public static function deactivate() {
			// Do nothing
		}

		/**
		 * same as pluggable.php's check_ajax_referer but not prone to being overwriten
		 */
		private function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
			if ( $query_arg ) {
				$nonce = $_REQUEST[$query_arg];
			} else {
				$nonce = isset($_REQUEST['_ajax_nonce']) ? $_REQUEST['_ajax_nonce'] : $_REQUEST['_wpnonce'];
			}

			$result = wp_verify_nonce( $nonce, $action );

			if ( $die && false == $result ) {
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					wp_die( -1 );
				} else {
					die( '-1' );
				}
			}

			return $result;
		}
	}
}
if ( class_exists( 'SBTS_CF_Plugin' ) ) {
    // Installation and uninstallation hooks
    register_activation_hook( __FILE__, array( 'SBTS_CF_Plugin', 'activate' ) );
    register_deactivation_hook( __FILE__, array( 'SBTS_CF_Plugin', 'deactivate' ) );

	if (! isset( $sbts_cf_plugin ) ) {
		// instantiate the plugin class
		$sbts_cf_plugin = new SBTS_CF_Plugin();
	}
}
?>
