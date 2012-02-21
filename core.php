<?php
	//define the core
	define( 'C_CORE_VER', '1.3.0' );

	//start debug
	require( __DIR__ . '/debug.php' ); //important

	//fd config, auto-generated array of useful shit
	$c_config = array(
		'root' => ( !empty( $_SERVER['HTTPS'] ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . rtrim( dirname( $_SERVER['PHP_SELF'] ), '/' ), //base for all index.php routing-based apps
		'base' => ( !empty( $_SERVER['HTTPS'] ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'],
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