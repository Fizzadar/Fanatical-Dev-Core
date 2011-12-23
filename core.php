<?php
	//define the core
	define( 'C_CORE_VER', '1.2.4' );

	//start debug
	if( !file_exists( __DIR__ . '/debug.php' ) ) die( 'Core fatal error: no debug.php' );
	require( __DIR__ . '/debug.php' ); //important
	
	//fd config, auto-generated array of useful shit
	$c_config = array(
		'root' => ( !empty( $_SERVER['HTTPS'] ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . rtrim( dirname( $_SERVER['PHP_SELF'] ), '/' ), //base for all index.php routing-based apps
		'dir' => str_replace( ' ', '%20', rtrim( dirname( $_SERVER['PHP_SELF'] ), '/' ) ),
		'base' => ( !empty( $_SERVER['HTTPS'] ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'],
		'lang' => 'en',
		'debug' => true,
		'core_dir' => __DIR__,
		'core_ver' => C_CORE_VER,
		'autoload_map' => array(
			'LightOpenID' => 'lib/LightOpenID',
			'Facebook' => 'lib/facebook',
			'BaseFacebook' => 'lib/base_facebook',
			'c_app' 	  => 'app',
			'c_db' 		  => 'database',
			'c_template'  => 'template',
			'c_debug'     => 'debug',
			'c_session'   => 'session',
			'c_user'	  => 'user'
		)
	);
	$c_debug->add( 'config loaded' );
	
	//include app.php
	if( !include( $c_config['core_dir'] . '/app.php' ) ) $c_debug->add( 'Core file not found/loaded: ' . $file );
	
	//send some debug
	$c_debug->add( 'core is loaded' );
?>