<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_app {
		private $app_dir;
		private $debug;
		
		public function __construct( $app_dir = 'app/' ) {
			global $c_debug;
			$this->app_dir = $app_dir;
			//debug
			$this->debug = $c_debug;
			$this->debug->add( 'c_app loaded' );
		}
		
		public function load( $file ) {
			global $c_config;
			if( !include( $this->app_dir . $file . '.php' ) ) $this->debug->add( 'no load file found: ' . $file );
		}
	}
?>