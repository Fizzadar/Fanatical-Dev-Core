<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_app {
		private $app_dir;
		private $debug;
		
		public function __construct( $autoload = array(), $app_dir = 'app/' ) {
			global $c_debug, $c_config;

			$this->app_dir = $app_dir;
			//debug
			$this->debug = $c_debug;
			$this->debug->add( 'c_app loaded' );
			
			//class loading
			spl_autoload_register( array( $this, 'autoload' ), true, true );
			foreach( $autoload as $k => $v )
				$c_config['autoload_map'][$k] = $this->app_dir . 'lib/' . $v;
		}
		
		public function load( $file ) {
			global $c_config;
			if( !include( $this->app_dir . $file . '.php' ) ) $this->debug->add( 'no load file found: ' . $file );
		}

		public function autoload( $class ) {
			global $c_config;
			if( isset( $c_config['autoload_map'][$class] ) and include( $c_config['autoload_map'][$class] . '.php' ) )
				$this->debug->add( 'Class ' . $class . ' loaded from ' . $c_config['autoload_map'][$class] );
			else
				$this->debug->add( 'Failed to load class ' . $class . ' from: ' . $c_config['autoload_map'][$class] );
		}
	}
?>