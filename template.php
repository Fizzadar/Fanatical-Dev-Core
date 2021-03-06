<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );

	class c_template {
		private $template_dir;
		public $content = array();
		private $debug;
		
		public function __construct( $template_dir = 'app/templates/' ) {
			global $c_debug;
			//set defaults (with defaults)
			$this->template_dir = __DIR__ . '/../' . $template_dir;
			//debug
			$this->debug = $c_debug;
			$this->debug->add( 'c_template class loaded' );
		}
		
		//add content to current page
		public function add( $key, $value ) {
			$this->content[$key] = $value;
		}

		//return some content
		public function get( $key ) {
			return isset( $this->content[$key] ) ? $this->content[$key] : false;
		}
		
		//load template
		public function load( $template ) {
			global $c_config;
			//include
			$this->debug->add( 'Loading template: ' . $template, 'template' );
			if( !include( $this->template_dir . $template . '.php' ) ) return $this->debug->add( 'Template not found: ' . $template, 'template' );
		}
	}
?>