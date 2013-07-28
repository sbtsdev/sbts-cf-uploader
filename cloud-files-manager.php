<?php
use \OpenCloud\Rackspace;
if (! class_exists( 'Cloud_Files_Manager' ) ) {
	class Cloud_Files_Manager {
		private $cf_settings;
		private $cf_obj_store;
		private $cf_containers;

		function __construct( $settings ) {
			$this->cf_settings = $settings;
			$this->cf_containers = array();
			if (! class_exists( 'Mp3Info' ) ) {
				require_once( 'inc/mp3info.class.php' );
			}
			// Initialize Cloud Files API for Manager to use
			if (! class_exists( 'ClassLoader' ) ) { // play nice with 'other' plugins (and hope they're up to date)
				define( 'RAXSDK_TIMEOUT', 0 ); // we'll wait forever
				require_once( 'php-opencloud/lib/php-opencloud.php' );
			}

		}

		public function connect() {
			$credentials = array(
				'username'	=> $this->cf_settings['username'],
				'apiKey'	=> $this->cf_settings['api_key']
			);
			try {
				$cf_conn = new Rackspace( RACKSPACE_US, $credentials );
				$this->cf_obj_store = $cf_conn->ObjectStore( 'cloudFiles', 'ORD' );
			} catch ( Exception $e ) {
				throw $e;
			}
		}

		public function get_containers() {
			// TODO only return containers that have CDN enabled
			$this->connect();
			$list = $this->cf_obj_store->CDN()->ContainerList( array( 'enabled_only' => true ) );
			while ( $cdn_container = $list->Next() ) {
				try {
					$container = $this->cf_obj_store->Container( $cdn_container->Name() );
				} catch ( OpenCloud\Common\Exceptions\ContainerNotFoundError $e ) {
					error_log( 'Edge case: Related private container not found.' );
					continue;
				}
				try {
					$cont_url = $container->Url();
				} catch ( Exceptions\NoNameError $e ) {
					$cont_url = '';
				}
				$this->cf_containers[] = array(
					'name'	=> $container->name,
					'count'	=> $container->count,
					'bytes'	=> $container->bytes,
					'url'	=> $cont_url
				);
			}
			return $this->cf_containers;
		}

		public function get_file( $req_container, $req_file_name ) {
			$file = array();
			try {
				$this->connect();
				$container = $this->cf_obj_store->Container( $req_container );
				$object = $container->DataObject( $req_file_name );
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
				$container = $this->cf_obj_store->Container( $req_container );
				$objects = $container->ObjectList( array( 'prefix', $req_prefix ) );
				while ( $object = $objects->Next() ) {
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

		public function upload_files( $req_container, $files ) {
			$names = array();
			if ( class_exists( 'Mp3Info' ) ) {
				$mp3_info = new Mp3Info();
			}
			if ( is_array( $files ) ) {
				$this->connect();
				$container = $this->cf_obj_store->Container( $req_container );
				foreach ( $files as $file ) {
					$cf_obj = $container->DataObject();
					try {
						// should we bother using move_uploaded_file to verify that the file "was uploaded using PHP's HTTP POST upload mechanism"?
						$cf_obj->Create( array( 'name' => $file['name'] ), $file['location'] );
						$names[] = $file['name'];
					} catch ( Exception $e ) {
						throw $e;
					}
					// try to add audio duration to metadata
					if ( $mp3_info && ( substr( $file['name'], -4 ) === '.mp3' ) ) {
						$mp3_data = $mp3_info->GetMp3Info( $file['location'] );
						if ( $mp3_data && isset( $mp3_data['playtime_string'] ) ) {
							$cf_obj->metadata->Rduration = $mp3_data['playtime_string'];
							$cf_obj->UpdateMetadata();
						}
					}
					// null the object for faster garbage collection
					$cf_obj = null;
				}
				return $this->verify_uploads( $container, $names );
			}
			return $names;
		}

		public function delete_files( $req_container, $files ) {
			$names = array();
			$to_delete = array();
			$deleted = array();
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					$names[] = $file['name'];
				}
				$this->connect();
				$container = $this->cf_obj_store->Container( $req_container );
				$to_delete = $this->verify_uploads( $container, $names );
				$tmp_deleter = $container->DataObject();
				foreach ( $to_delete as $delete_obj ) {
					try {
						$tmp_deleter->Delete( array( 'name' => $delete_obj['full_name'] ) );
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
				$obj = $container->DataObject( $name );
				$files[] = $this->make_file( $obj );
			}
			return $files;
		}

		private function make_file( $obj ) {
			$bg = strrpos( $obj->name, '/' );
			$path = '/' . ( $bg ? substr( $obj->name, 0, $bg + 1 ) : '' );
			$name = substr( $obj->name, $bg ? $bg + 1 : 0 );
			$size = round( $obj->bytes / ( 1024.00 * 1024.00 ), 2 );

			$mdata = $obj->MetadataHeaders();

			$dt = strtotime( $obj->last_modified );
			$last_mod = ( $dt ? date( 'Y-m-d H:i:s', $dt ) : '' );

			return array(
				'full_name'	=> $obj->name,
				'path'		=> $path,
				'name'		=> $name,
				'uri'		=> $obj->PublicURL(),
				'type'		=> $obj->content_type,
				'rsize'		=> $obj->bytes,
				'size'		=> $size . " Mb",
				'mdata'		=> $mdata,
				'last_mod'	=> $last_mod
			);
		}
	}
}
?>
