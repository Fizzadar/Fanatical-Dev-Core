<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_app {
		private $app_dir;
		private $debug;
		private $autoload_map;
		
		public function __construct( $app_dir = 'app/' ) {
			global $c_debug, $c_config;

			$this->app_dir = $app_dir;
			//debug
			$this->debug = $c_debug;
			$this->debug->add( 'c_app loaded' );

			
			if(isset($c_config['autoload'])):
				spl_autoload_register(array($this, 'autoload'), true, true);
				if(isset($c_config['autoload_map']))
					$this->autoload_map = $c_config['autoload_map'];
			endif;
		}
		
		public function load( $file ) {
			global $c_config;
			if( !include( $this->app_dir . $file . '.php' ) ) $this->debug->add( 'no load file found: ' . $file );
		}

		public function autoload($class)
		{
			global $c_config;
			if(isset($this->autoload_map[$class]))
				$path = $this->autoload_map[$class];
			else
				$path =   $c_debug['core_dir'] . DIRECTORY_SEPARATOR
						. str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
				
		
			if(!include $path)
				$this->debug->add("Failed to load $class @ '$path'");
			else
				$this->debug->add("$class autoloaded from '$path'");	
		}
	}
?>