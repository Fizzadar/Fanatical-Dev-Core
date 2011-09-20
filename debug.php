<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_debug {
		private $debugs;
		public $start;
		
		public function __construct() {
			$this->debugs = array();
			$this->start = microtime( true );
			//debug
			$this->add( 'starting core...' );
			$this->add( 'c_debug class loaded' );
		}
		
		//add to debug
		public function add( $content, $type = 'Message' ) {
			$this->debugs[] = array( $content, microtime( true ), $type );
			//false return for functions adding debug as error
			return false;
		}
		
		//display the debug
		public function display( $ignore = array() ) {
			global $c_config;
			if( $c_config['debug'] ):
				echo '<!--core debug-->
		<a title="View debug" href="#debug" onclick="if(document.getElementById(\'c_debug\').style.display == \'block\' ) { document.getElementById(\'c_debug\').style.display = \'none\'; history.go( -1 ); } else { document.getElementById(\'c_debug\').style.display=\'block\'; }" style="position:fixed;top:0;right:0;color:#333333;text-shadow:none;background:#F7F7F7;z-index:99999999999999;font-family:Arial;font-size:12px;text-decoration:none;padding:5px;border:1px solid #D7D7D7;border-width:0 0 1px 1px;">Debug</a>
		<div id="c_debug" style="position:fixed;top:0;left:0;width:98%;height:98%;background:#EEEEEE;color:#1A1B1B;font-family:Arial;text-shadow:none;font-size:13px;line-height:20px;padding:1%;z-index:9999998;display:none;overflow:auto;"><h2>Core Debug</h2>';
				foreach( $this->debugs as $k => $v ):
					if( in_array( $k, $ignore ) ) continue;
					echo '<strong>' . $v[2] . ':</strong> ' . $v[0] . ' <small>+' . round( ( $v[1] - $this->start ) * 1000, 5 ) . 'ms</small><br />';
				endforeach;
				echo '<br />End  Debug<small> +' . round( ( microtime( true ) - $this->start ) * 1000, 5 ) . 'ms</small>';
				echo '</div><!--end c_debug--><script type="text/javascript">if( String( window.location ).search( \'#debug\' ) > -1 ) { document.getElementById( \'c_debug\' ).style.display = \'block\'; }</script>
		<!--/core debug-->';
			endif;
		}
	}
	
	//start debugger
	$c_debug = new c_debug();
?>