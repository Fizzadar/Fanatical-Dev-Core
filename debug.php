<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_debug {
		private $start;
		private $enabled = false;
		
		public function __construct() {
			//start time
			$this->start = microtime( true );

			//debug
			$this->add( 'starting core...' );
			$this->add( 'c_debug class loaded' );

			//start session
			session_start();
			if( !isset( $_SESSION['c_debug'] ) or !is_array( $_SESSION['c_debug'] ) )
				$_SESSION['c_debug'] = array();

		}
		
		//enable the debugger
		public function enable() {
			$this->enabled = true;
		}

		//disable the debugger
		public function disable() {
			$this->enabled = false;
		}

		//add to debug
		public function add( $content, $type = 'Message', $return = false ) {
			if( !$this->enabled ) return $return;
			global $c_config;

			//add to session & debug array
			$debug = array(
				'message' => $content,
				'time' => round( ( microtime( true ) - $this->start ) * 1000, 5 ),
				'type' => $type,
				'location' => $_SERVER['REQUEST_URI']
			);
			$_SESSION['c_debug'][] = $debug;

			//false return for functions returning debug as error (can be changed as above)
			return $return;
		}
		
		//display the debug
		public function display() {
			if( !$this->enabled ) return;
			global $c_config;

				echo '
		<!--core debug-->
		<div id="c_debug" style="position:fixed;top:0;left:0;width:98%;height:98%;background:#EEEEEE;color:#1A1B1B;font-family:Arial;text-shadow:none;font-size:13px;line-height:20px;padding:1%;z-index:9999999999;display:none;overflow:auto;"><h2>Core Debug</h2>';
				
				foreach( $_SESSION['c_debug'] as $k => $v ):
					echo '<strong>' . $v['type'] . ':</strong> ' . $v['message'] . ' <small>+' . $v['time'] . 'ms / request: ' . $v['location'] . '</small>';
					echo '<br />';
				endforeach;

				echo '<br />End  Debug<small> +' . round( ( microtime( true ) - $this->start ) * 1000, 5 ) . 'ms</small>';
				echo '</div><!--end c_debug--><script type="text/javascript">if( String( window.location ).search( \'#debug\' ) > -1 ) { document.getElementById( \'c_debug\' ).style.display = \'block\'; }</script>
		<a title="View debug" href="#debug" onclick="if(document.getElementById(\'c_debug\').style.display == \'block\' ) { document.getElementById(\'c_debug\').style.display = \'none\'; history.go( -1 ); } else { document.getElementById(\'c_debug\').style.display=\'block\'; }" style="position:fixed;top:0;right:0;color:#333333;text-shadow:none;background:#F7F7F7;z-index:9999999999;font-family:Arial;font-size:12px;text-decoration:none;padding:5px;border:1px solid #D7D7D7;border-width:0 0 1px 1px;">Debug</a>
		<!--/core debug-->';

			//empty array
			$_SESSION['c_debug'] = array();
		}
	}
	
	//start debugger
	$c_debug = new c_debug();
?>