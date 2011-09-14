<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_session {
		public function __construct() {
			session_start();
		}
		
		//generate a session id
		public function generate() {
			//unused token, use that
			if( isset( $_SESSION['token'] ) ) return $_SESSION['token'];
			//generate a new random token
			$_SESSION['token'] = md5( uniqid( rand(), true ) );
			return $_SESSION['token'];
		}
		
		//check & destroy session token
		public function validate( $token ) {
			//no token?
			if( !isset( $_SESSION['token'] ) ) return false;
			//get return
			$return = ( $token == $_SESSION['token'] );
			//kill session, remove token
			unset( $_SESSION['token'] );
			//return
			return $return;
		}
	}
?>