<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_debug {
		private $start;
		
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
		
		//add to debug
		public function add( $content, $type = 'message', $return = false, $log = false ) {
			global $c_config;

			//add to session
			$debug = array(
				'message' => $content,
				'time' => round( ( microtime( true ) - $this->start ) * 1000, 5 ),
				'type' => strtolower( $type ),
				'location' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : ''
			);
			$_SESSION['c_debug'][] = $debug;

			//now write to relevant file
			if( $log ):
				$file = fopen( $c_config['core_dir'] . '/log/' . $type . '.txt', 'a' );
				fwrite( $file, '+' . round( ( microtime( true ) - $this->start ) * 1000, 5 ) . ' ===> ' . $content . "\n" );
			endif;

			//false return for functions returning debug as error (can be changed as above)
			return $return;
		}
		
		//display the debug
		public function display() {
			//work out bits
			$messages = array();
			$queries = array();

			//loop messages
			foreach( $_SESSION['c_debug'] as $k => $v )
				if( in_array( $v['type'], array( 'mysql_query', 'mysql_query_error' ) ) )
					$queries[] = $v;
				else
					$messages[] = $v;

			//empty array
			$_SESSION['c_debug'] = array();

			//time
			$time = round( ( microtime( true ) - $this->start ) * 1000, 3 );

			//memory usage
			$memory = round( memory_get_usage() / ( 1024 * 1024 ), 3 );
		?>
			<style type="text/css">
				div#c_debug {
					position: fixed;
					bottom: 0;
					left: 0;
					height: 300px;
					width: 100%;
					background: #F7F7F7;
					border-top: 1px solid #D7D7D7;
					z-index: 99999999999;
					overflow: auto;
					font-family: Arial;
					font-size: 12px;
					display: none;
				}
					div#c_debug h2 {
						margin: 20px 0 0 20px;
					}
						div#c_debug h2 small {
							font-weight: normal;
						}
						div#c_debug h2 span.right {
							float: right;
							margin-right: 20px;
						}
					div#c_debug div {
						float: left;
						width: 32%;
						margin-right: 2%;
					}
					div#c_debug div.queries {
						width: 64%;
					}
						div#c_debug div h3 {
							margin: 5px 0 0 20px;
							font-weight: normal;
						}
				ul#c_debug_messages {
					height: 210px;
					background: #FFF;
					overflow: auto;
					width: 100%;
					float: left;
					border: 1px solid #D7D7D7;
					margin: -5px 0 0 20px;
					padding: 5px;
				}
					ul#c_debug_messages li {
						list-style: none;
					}

				a.c_debug_open {
					position: fixed;
					bottom: 20px;
					right: 20px;
					font-family: Arial;
					font-size: 18px;
					font-weight: bold;
				}
			</style>
			<a class="c_debug_open" href="#" onclick="document.getElementById( 'c_debug' ).style.display = 'block'; sessionStorage.setItem( 'c_debug', 'true' ); return false;">open debug</a>
			<div id="c_debug">
				<h2>
					Debug <small>memory: <?php echo $memory; ?>mb / time: <?php echo $time; ?>ms</small>
					<span class="right"><a href="#" onclick="document.getElementById( 'c_debug' ).style.display = 'none'; sessionStorage.setItem( 'c_debug', 'false' ); return false;">close debug</a></span>
				</h2>
				<div>
					<h3>Messages (<?php echo count( $messages ); ?>)</h3>
					<ul id="c_debug_messages">
						<?php foreach( $messages as $k => $v ):
							echo '<li><strong>' . $v['type'] . ':</strong> ' . $v['message'] . ' <small>+' . $v['time'] . 'ms / request: ' . $v['location'] . '</small></li>';
						endforeach; ?>
					</ul><!--end c_debug_messages-->
				</div>
				<div class="queries">
					<h3>Queries (<?php echo count( $queries ); ?>)</h3>
					<ul id="c_debug_messages">
						<?php foreach( $queries as $k => $v ):
							echo '<pre>' . str_replace( "\t", '', $v['message'] ) . '</pre> <small>+' . $v['time'] . 'ms / request: ' . $v['location'] . '</small></li>';
						endforeach; ?>
					</ul><!--end c_debug_messages-->
				</div>
			</div><!--end c_debug-->
			<script type="text/javascript">
				if( sessionStorage.getItem( 'c_debug' ) == 'true' ) {
					document.getElementById( 'c_debug' ).style.display = 'block';
				}
			</script>
		<?php
		}
	}
	
	//start debugger
	$c_debug = new c_debug();
?>