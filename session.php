<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_session {
		private $validated = false;

		public function __construct() {
			session_start();
		}
		
		//generate a session id
		public function generate() {
			//unused token, use that
			if( isset( $_SESSION['fd_core_token'] ) ) return $_SESSION['fd_core_token'];
			//generate a new random token
			$_SESSION['fd_core_token'] = md5( uniqid( rand(), true ) );
			return $_SESSION['fd_core_token'];
		}
		
		//check & destroy session token
		public function validate( $token ) {
			//already validated this load?
			if( $this->validated )
				return true;
			//no token?
			if( !isset( $_SESSION['fd_core_token'] ) ) return false;
			//get return
			$return = ( $token == $_SESSION['fd_core_token'] );
			//kill session, remove token
			unset( $_SESSION['fd_core_token'] );
			//return good?
			if( $return )
				$this->validated = true;
			//return
			return $return;
		}
	}
?>