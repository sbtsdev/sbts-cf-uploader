<?php
if (! class_exists( 'Cloud_Files_Manager' ) ) {
	class Cloud_Files_Manager {
		private $cf_settings;
		private $cf_conn;
		private $cf_containers;

		function __construct( $settings ) {
			$this->cf_settings = $settings;
			$this->cf_containers = array();
			if (! class_exists( 'Mp3Info' ) ) {
				require_once( 'inc/mp3info.class.php' );
			}
		}

		public function connect() {
			$auth = new CF_Authentication( $this->cf_settings['username'], $this->cf_settings['api_key'] );
			try {
				$auth->authenticate();
				$this->cf_conn = new CF_Connection( $auth );
			} catch ( Exception $e ) {
				throw $e;
			}
		}

		public function get_containers() {
			// TODO only return containers that have CDN enabled
			try {
				$this->connect();
				$list = $this->cf_conn->get_containers();
				foreach ( $list as $container ) {
					$this->cf_containers[] = array(
						'name'	=> $container->name,
						'count'	=> $container->object_count,
						'bytes'	=> $container->bytes_used
					);
				}
			} catch ( Exception $e ) {
				throw $e;
			}
			return $this->cf_containers;
		}

		public function get_file( $req_container, $req_file_name ) {
			$file = array();
			try {
				$this->connect();
				$container = $this->cf_conn->get_container( $req_container );
				$object = $container->get_object( $req_file_name );
				$file[] = $this->make_file( $object );
			} catch ( Exception $e ) {
				throw $e;
			}
			return $file;
		}

		public function get_files( $req_container, $req_prefix = NULL ) {
			$files = array();
			try {
				$this->connect();
				$container = $this->cf_conn->get_container( $req_container );
				$objects = $container->get_objects(0, $req_prefix );
				foreach ( $objects as $object ) {
					// TODO manage the pseudo-directories too
					if ( 'application/directory' !== $object->content_type ) {
						$files[] = $this->make_file( $object );
					}
				}
			} catch ( Exception $e ) {
				throw $e;
			}
			return $files;
		}

		public function upload_files( $req_container, $req_path ) {
			$names = array();
			if ( class_exists( 'Mp3Info' ) ) {
				$mp3_info = new Mp3Info();
			}
			if ( is_array( $_FILES['file']['name'] ) ) {
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
				$this->connect();
				$container = $this->cf_conn->get_container( $req_container );
				foreach ( $_FILES['file']['name'] as $index => $fname ) {
					// TODO check file type $_FILES['file']['type'] against array of predefined permissible types (audio/mpeg or video/x-flv or video/mp4 etc.)
					$cf_obj = $container->create_object( $req_path . $fname );
					// try to add audio duration to metadata
					if ( $mp3_info ) {
						$mp3_data = $mp3_info->GetMp3Info( $_FILES['file']['tmp_name'][$index] );
						if ( $mp3_data ) {
							$mdata = $cf_obj->metadata;
							$mdata['Rduration'] = $mp3_data['playtime_string'];
							$cf_obj->metadata = $mdata;
						}
					}
					try {
						// should we bother using move_uploaded_file to verify that the file "was uploaded using PHP's HTTP POST upload mechanism"?
						// load_from_filename either returns true or throws an error
						$cf_obj->load_from_filename( $_FILES['file']['tmp_name'][$index] );
						// null the object for faster garbage collection
						$cf_obj = null;
						$names[] = $req_path . $fname;
					} catch ( Exception $e ) {
						throw $e;
					}
				}
				return $this->verify_uploads( $container, $names );
			}
			return $names;
		}

		public function delete_files( $req_container, $names ) {
			$to_delete = array();
			$deleted = array();
			if ( is_array( $names ) ) {
				// TODO make '/' a configurable delimiter
				// sanitize path so /media/audio/ or /media/audio or media/audio
				// becomes a usable media/audio/ to prepend to the file name
				$this->connect();
				$container = $this->cf_conn->get_container( $req_container );
				$to_delete = $this->verify_uploads( $container, $names );
				foreach ( $to_delete as $delete_obj ) {
					try {
						$container->delete_object( $delete_obj['full_name'] );
						$deleted[] = $delete_obj;
					} catch ( Exception $e ) {
						throw $e;
					}
				}
			}
			return $deleted;
		}

		private function verify_uploads( $container, $names ) {
			$files = array();
			foreach ( $names as $name ) {
				if ( '/' === substr( $name, 0, 1 ) ) {
					$name = substr( $name, 1 );
				}
				$obj = $container->get_object( $name );
				$files[] = $this->make_file( $obj );
			}
			return $files;
		}

		private function make_file( $obj ) {
			$bg = strrpos( $obj->name, '/' );
			$path = '/' . ( $bg ? substr( $obj->name, 0, $bg + 1 ) : '' );
			$name = substr( $obj->name, $bg ? $bg + 1 : 0 );
			$size = round( $obj->content_length / ( 1024.00 * 1024.00 ), 2 );
			$dt = strtotime( $obj->last_modified );
			$last_mod = ( $dt ? date( 'Y-m-d H:i:s', $dt ) : '' );
			return array(
				'full_name'	=> $obj->name,
				'path'		=> $path,
				'name'		=> $name,
				'uri'		=> $obj->public_uri(),
				'type'		=> $obj->content_type,
				'rsize'		=> $obj->content_length,
				'size'		=> $size . " Mb",
				'mdata'		=> $obj->metadata,
				'last_mod'	=> $last_mod
			);
		}
	}
}
?>
