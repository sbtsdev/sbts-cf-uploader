<?php
require 'vendor/autoload.php';
use \OpenCloud\Rackspace;
if (! class_exists( 'Cloud_Files_Manager' ) ) {
	class Cloud_Files_Manager {
		private $cf_settings;
		private $cf_obj_store_service;
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
			}

		}

		public function connect( $force_new = false ) {
            if ( (! $force_new ) && isset( $this->cf_obj_store_service ) ) {
                return $this->cf_obj_store_service;
            }
			$credentials = array(
				'username'	=> $this->cf_settings['username'],
				'apiKey'	=> $this->cf_settings['api_key']
			);
			try {
				$cf_conn = new Rackspace( Rackspace::US_IDENTITY_ENDPOINT, $credentials );
				$this->cf_obj_store_service = $cf_conn->objectStoreService( null, 'DFW' );
			} catch ( Exception $e ) {
				throw $e;
			}
		}

        public function get_container( $req_container ) {
            $this->connect();
            $container = $this->cf_obj_store_service->getContainer( $req_container );
            return $container;
        }

		public function get_containers() {
			if ( isset( $this->cf_containers ) && (! empty( $this->cf_containers ) ) ) {
				return $this->cf_containers;
			}
			$this->connect();
			$list = $this->cf_obj_store_service->listContainers();
			foreach ( $list as $container ) {
                if ($container->isCdnEnabled()) {
                    $cdn_container = $container->getCdn();
                    try {
                        $cdn_url = $cdn_container->getCdnUri();
                    } catch ( Exceptions $e ) {
                        $cdn_url = '';
                        continue;
                    }
                    $count = $container->getObjectCount();
                    $bytes = $container->getBytesUsed();
                    $this->cf_containers[] = array(
                        'name'	=> $cdn_container->name,
                        'count'	=> $count,
                        'bytes'	=> $bytes,
                        'url'	=> $cdn_url
                    );
                }
			}
			return $this->cf_containers;
		}

		public function get_file( $req_container, $req_file_name ) {
			$file = array();
			try {
				$this->connect();
				$container = $this->cf_obj_store_service->getContainer( $req_container );
				$object = $container->getObject( $req_file_name );
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
				$container = $this->cf_obj_store_service->getContainer( $req_container );
				$objects = $container->objectList( array( 'prefix', $req_prefix ) );
				foreach ($objects as $object) {
					// TODO manage the pseudo-directories too
					if ( 'application/directory' !== $object->getContentType() ) {
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
				$container = $this->cf_obj_store_service->getContainer( $req_container );
				foreach ( $files as $file ) {
					try {
						// should we bother using move_uploaded_file to verify that the file "was uploaded using PHP's HTTP POST upload mechanism"?
                        // try to add audio duration to metadata
                        $metadata = array();
                        if ( $mp3_info && ( substr( $file['name'], -4 ) === '.mp3' ) ) {
                            $mp3_data = $mp3_info->GetMp3Info( $file['location'] );
                            if ( $mp3_data && isset( $mp3_data['playtime_string'] ) ) {
                                $metadata['Rduration'] = $mp3_data['playtime_string'];
                            }
                        }
                        $container->uploadObject( $file['name'], fopen( $file['location'], 'r+' ), $metadata );
						$names[] = $file['name'];
					} catch ( Exception $e ) {
						throw $e;
					}
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
				$container = $this->cf_obj_store_service->getContainer( $req_container );
				$to_delete = $this->verify_uploads( $container, $names );
				foreach ( $to_delete as $delete_obj ) {
					try {
                        $container->deleteObject($delete_obj['full_name']);
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
				$obj = $container->getObject( $name );
				$files[] = $this->make_file( $obj );
			}
			return $files;
		}

		private function make_file( $obj ) {
            $fullName = $obj->getName();
			$bg = strrpos( $fullName, '/' );
			$path = '/' . ( $bg ? substr( $fullName, 0, $bg + 1 ) : '' );
			$name = substr( $fullName, $bg ? $bg + 1 : 0 );
            $bytes = $obj->getContentLength();
			$size = round( $bytes / ( 1024.00 * 1024.00 ), 2 );

			$mdata = $obj->getMetadata()->toArray();

			$dt = strtotime( $obj->getLastModified() );
			$last_mod = ( $dt ? date( 'Y-m-d H:i:s', $dt ) : '' );

            $cdn_uri = $obj->getContainer()->getCdn()->getCdnUri();

			return array(
				'full_name'	=> $fullName,
				'path'		=> $path,
				'name'		=> $name,
				'uri'		=> $cdn_uri. '/' . $fullName,
				'type'		=> $obj->getContentType(),
				'rsize'		=> $bytes,
				'size'		=> $size . " Mb",
				'mdata'		=> $mdata,
				'last_mod'	=> $last_mod
			);
		}
	}
}
?>
