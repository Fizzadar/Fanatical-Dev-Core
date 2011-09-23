<?php
	//define the core
	define( 'C_CORE_VER', '1.2.2' );

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

		// the autoloader only kicks in after c_app::__construct,
		// and only if this configuration param is set to true.
		'autoload' => true,

		// map classes with names that do not correspond to their
		// actual locations to an absolute path:
		// NOTE: underscores are replaced with DIRECTORY_SEPARATOR,
		// by default. Given the original naming scheme, a class
		// like c_app would point to c/app.php, hence the presence
		// of every core class in this list.
		'autoload_map' => array(
			'LightOpenID' => 'lib/LightOpenID.php',
			'c_app' 	  => 'app.php',
			'c_db' 		  => 'database.php',
			'c_template'  => 'template.php',
			'c_debug'     => 'debug.php',
			'c_session'   => 'session.php',
			'c_user'	  => 'user.php'
		)
	);
	$c_debug->add( 'config loaded' );
	
	//load other files
	$files = array(
		'message',
		'time',
		'app'
	);

	foreach( $files as $file ):
		if( !include( $c_config['core_dir'] . '/' . $file . '.php' ) ) $c_debug->add( 'Core file not found/loaded: ' . $file );
	endforeach;
	
	//send some debug
	$c_debug->add( 'core is loaded' );
	new c_app;
	new LightOpenID;
	$c_debug->display();
?>