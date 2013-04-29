<?php
/*
Plugin Name: Cloud Files Uploader
Plugin URI: http://github.com/sbtsdev/cloud-files-uploader
Description: This plugin allows a user to upload files to Rackspace Cloud Files and interact with them.
Version: v0.1.1
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
Date		Version	Explanation
----		-------	-----------
20130429	0.1.2	Temp fix for uploads so Firefox could do upload; fix to ZeroClipboard to reposition Copy button
20130318	0.1.1	Fixed bug: make sure we anticipate another plugin using Cloud Files' php api
20130315	0.1.0	Initial use: upload (single and multiple), list, delete, switch containers all working

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

			// Initialize Settings
			require_once( sprintf( "%s/settings.php", $this->plugin_dir ) );
			$this->sbts_cf_plugin_settings = new SBTS_CF_Plugin_Settings();

			// Initialize Cloud Files API for Manager to use
			if (! class_exists( 'CF_Authentication' ) ) { // play nice with 'other' plugins (and hope they're up to date)
				require_once( sprintf( "%s/php-cloudfiles/cloudfiles.php", $this->plugin_dir ) );
			}

			// Initialize Cloud Files Manager
			require_once( sprintf( "%s/cloud-files-manager.php", $this->plugin_dir ) );
			$this->cfm = new Cloud_Files_Manager( $this->sbts_cf_plugin_settings->get_settings() );

			// Initialize uploader itself
			add_action( 'admin_menu', array( &$this, 'add_menu' ) );

			// add ajax functionality for page interaction
			add_action( 'wp_ajax_get_containers', array( &$this, 'get_containers' ) );
			add_action( 'wp_ajax_get_files', array( &$this, 'get_files' ) );
			add_action( 'wp_ajax_upload_files', array( &$this, 'upload_files' ) );
			add_action( 'wp_ajax_delete_files', array( &$this, 'delete_files' ) );

			// Initialize the return message, the basic pattern of all returned data
			$this->ret = array( 'success' => false, 'message' => 'Unable to connect.', 'pl' => array() );
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
			wp_enqueue_style( 'sbts-cf-uploader-css', plugin_dir_url() . 'sbts-cf-uploader/css/sbts-cf-uploader-style.css' );
		}

		public function add_js() {
			wp_enqueue_script( 'sbts-cf-uploader-handlebars', plugin_dir_url() . 'sbts-cf-uploader/js/handlebars.js', array(), false, true );
			wp_enqueue_script( 'sbts-cf-uploader-zeroclipboard', plugin_dir_url() . 'sbts-cf-uploader/js/ZeroClipboard.min.js', array(), false, true );
			wp_enqueue_script( 'sbts-cf-uploader-js', plugin_dir_url() . 'sbts-cf-uploader/js/sbts-cf-uploader-core.js', array( 'jquery', 'sbts-cf-uploader-handlebars' ), false, true );
			$sbts_cf_uploader = array( 'url' => plugin_dir_url() . 'sbts-cf-uploader/' );
			wp_localize_script( 'sbts-cf-uploader-js', 'sbts_cf_uploader', $sbts_cf_uploader );
		}

		public function get_containers() {
			// TODO uncomment for production
			//$this->check_ajax_referer( 'get_containers', 'sbts_cf_auth', true );
			try {
				$this->ret['pl'] = $this->cfm->get_containers();
				$this->create_ret( 'found', 'container', 'containers', ' Use Wordpress -> Settings -> Media to create.' );
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

		public function upload_files() {
			try {
				$this->ret['pl'] = $this->cfm->upload_files( $_POST['sbts_cf_cont'], $_POST['sbts_cf_path'] );
				$this->create_ret( 'uploaded', 'file', 'files', ' Files did not upload to the server.' );
			} catch ( Exception $e ) {
				$this->error_ret( $e );
			}
			$this->send_ret();
		}

		public function delete_files() {
			try {
				$this->ret['pl'] = $this->cfm->delete_files( $_POST['sbts_cf_cont'], $_POST['file'] );
				$this->create_ret( 'deleted', 'file', 'files', '' );
			} catch ( Exception $e ) {
				$this->error_ret( $e );
			}
			$this->send_ret();
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
