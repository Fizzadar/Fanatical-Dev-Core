<?php
	//define the core
	define( 'C_CORE_VER', '1.3.0' );

	//start debug
	if( !file_exists( __DIR__ . '/debug.php' ) ) die( 'Core fatal error: no debug.php' );
	require( __DIR__ . '/debug.php' ); //important
	
	//no $_SERVER (command line/etc)
	if( !isset( $_SERVER ) ) $_SERVER = array();
	if( !isset( $_SERVER['HTTPS'] ) ) $_SERVER['HTTPS'] = '';
	if( !isset( $_SERVER['HTTP_HOST'] ) ) $_SERVER['HTTP_HOST'] = '';
	if( !isset( $_SERVER['PHP_SELF'] ) ) $_SERVER['PHP_SELF'] = '';
	if( !isset( $_SERVER['REQUEST_URI'] ) ) $_SERVER['REQUEST_URI'] = '';

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
			//externals
			'LightOpenID' => 'lib/LightOpenID', //openid
			'Facebook' => 'lib/facebook', //facebook (& base fb)
			'TwitterOAuth' => 'lib/twitteroauth', //twitter (& oauth)
			//internals
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