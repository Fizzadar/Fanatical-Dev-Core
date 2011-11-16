<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_user {
		public static $cookie_id;
		private $c_app_id;
		private $c_app_secret;
		protected static $db_conn;
		private $checked_login = false;
		protected static $debug;
		protected static $cookie_dir;
		
		public function __construct( $c_db, $cookie_id = '', $c_app_id = '', $c_app_secret = '' ) {
			global $c_debug, $c_config;
			//make sure database is up
			if( !method_exists( $c_db, 'query' ) ) echo '[Core] <strong>Fatal Error:</strong> Cannot start class: c_user, no database class (c_db) present';
			$this->db_conn = $c_db;
			$this->cookie_id = $cookie_id;
			$this->c_app_id = $c_app_id;
			$this->c_app_secret = $c_app_secret;
			$this->cookie_dir = $c_config['dir'];
			//debug
			$this->debug = $c_debug;
			$this->debug->add( 'c_user class loaded' );
		}
		
		/*
		login function (also register function)
		returns:
			c_debug() = fail
			1 = logged in
			2 = registered, possibly ask for username/email, or just go
			3 = openid added to current account
		*/
		protected function login( $openid, $register_name = '' ) {
			if( $openid == 'http://facebook.com/profile.php?id=' )
				return $this->debug->add( 'no fb id', 'Error' );
			//are we currently logged in? adding an openid?
			if( $this->check_login() ):
				$check_openid = $this->db_conn->query( '
					SELECT user_id
					FROM core_user_openids
					WHERE open_id = "' . $openid . '"
					LIMIT 1
				' );
				//openid already tied to an account?
				if( count( $check_openid ) > 0 ) return ( $check_openid[0]['user_id'] == $_COOKIE[$this->cookie_id . 'c_userid'] ) ? $this->debug->add( 'openid_already_owned', 'Error' ) : $this->debug->add( 'openid_exists', 'Error' );
				//all good, add the openid for this user
				$add_openid = $this->db_conn->query( '
					INSERT INTO core_user_openids
					( user_id, open_id )
					VALUES ( "' . $_COOKIE[$this->cookie_id . 'c_userid'] . '", "' . $openid . '" )
				' );
				return $add_openid ? 3 : $this->debug->add( 'mysql_error', 'Error' );
			endif;
			//see if this user exists with this openid
			$checkuser = $this->db_conn->query( '
				SELECT core_user_openids.user_id, core_user.group, core_user.name, core_user.auth_key
				FROM core_user_openids, core_user
				WHERE core_user_openids.open_id = "' . $openid . '"
				AND core_user.id = core_user_openids.user_id
				LIMIT 1
			' );
			//generate new auth key
			$authkey = hash( 'sha512', $openid . microtime() );
			//no user? register!
			if( !$checkuser or !is_array( $checkuser[0] ) ):
				//work out the name
				$name = empty( $register_name ) ? 'User' . substr( $authkey, 0, 4 ) : $register_name;
				$group = 1; //default register group
				//register
				$registeruser = $this->db_conn->query( '
					INSERT INTO core_user
					( name, auth_key, registration_time, login_time )
					VALUES ( "' . $name . '", "' . $authkey . '", ' . time() . ', ' . time() . ' )
				' );
				if( !$registeruser ) return $this->debug->add( 'mysql_error', 'Error' );
				//get id, register openid
				$userid = $this->db_conn->insert_id();
				$registeruser = $this->db_conn->query( '
					INSERT INTO core_user_openids
					( user_id, open_id )
					VALUES ( "' . $userid . '", "' . $openid . '" )
				' );
				if( !$registeruser ): return $this->debug->add( 'mysql_error', 'Error' ); endif;
			else:
				//set the details
				$userid = $checkuser[0]['user_id'];
				$group = $checkuser[0]['group'];
				$name = $checkuser[0]['name'];
				$this->db_conn->query( '
					UPDATE core_user
					SET auth_key = "' . $authkey . '",
					login_time = ' . time() . '
					WHERE id = "' . $userid . '"
					LIMIT 1
				' );
			endif;
			//finally, login using $userid
			if( $userid and is_numeric( $userid ) ):
				//basic info
				setcookie( $this->cookie_id . 'c_userid', $userid, time() + 60 * 60 * 24 * 365, $this->cookie_dir );
				setcookie( $this->cookie_id . 'c_authkey', $authkey, time() + 60 * 60 * 24 * 365, $this->cookie_dir );
				setcookie( $this->cookie_id . 'c_name', $name, time() + 60 * 60 * 24 * 365, $this->cookie_dir );
				//get permissions
				$perms = $this->db_conn->query( '
					SELECT permission
					FROM core_user_permissions
					WHERE group_id = ' . $group . '
				' );
				//make the permissions look nice
				$permissions = '';
				foreach( $perms as $id => $perm ):
					$permissions .= $perm['permission'] . ( count( $perms ) == $id + 1 ? '' : ',' );
				endforeach;
				setcookie( $this->cookie_id . 'c_permissions', $permissions, time() + 60 * 60 * 24 * 365, $this->cookie_dir );
				//return register or not
				return ( isset( $registeruser ) and $registeruser ) ? 2 : 1;
			endif;
			//whaaat?
			return false;
		}
		
		//openid login
		public function openid_login() {
			if( !$_GET['openid_mode'] ) return $this->debug->add( 'openid_error', 'Error' );
			//start openid
			$openid = new LightOpenID;
			//validate the login
			try {
				$result = $openid->validate();
			} catch( Exception $e ) {
				return false;
			}
			if( !$result ) return $this->debug->add( 'openid_error', 'Error' );
			//login!
			return $this->login( $_GET['openid_identity'] );
		}
		
		//facebook cookie function
		private function fb_cookie() {
			//must have app_id & app_secret
			if( empty( $this->c_app_id ) or empty( $this->c_app_secret ) ) return false;
			//no cookie at all?
			if( !isset( $_COOKIE['fbs_' . $this->c_app_id ] ) ) return $this->debug->add( 'facebook_error', 'Error' );
			//parse the fb cookie
			$args = array();
			parse_str( trim( $_COOKIE['fbs_' . $this->c_app_id], '\\"' ), $args );
			ksort( $args );
			$payload = '';
			foreach ($args as $key => $value):
				if( $key != 'sig' ):
					$payload .= $key . '=' . $value;
				endif;
			endforeach;
			//wrong cookie stuff?
			if( md5( $payload . $this->c_app_secret ) != $args['sig'] ) return $this->debug->add( 'facebook_error', 'Error' );
			//return the args!
			return $args;
		}
		
		//facebook login
		public function fb_login() {
			//get the cookie
			$cookie = $this->fb_cookie();
			//remove the cookie (un-needed)
			setcookie( 'fbs_' . $this->c_app_id, '', time() - 1, $this->cookie_dir );
			//login!
			return $this->login( 'http://facebook.com/profile.php?id=' . $cookie['uid'] );
		}
		
		//logout, remove the cookies
		public function logout() {
			//remove our cookies
			setcookie( $this->cookie_id . 'c_userid', '', time() - 1, $this->cookie_dir );
			setcookie( $this->cookie_id . 'c_authkey', '', time() - 1, $this->cookie_dir );
			setcookie( $this->cookie_id . 'c_name', '', time() - 1, $this->cookie_dir );
			setcookie( $this->cookie_id . 'c_permissions', '', time() - 1, $this->cookie_dir );
			setcookie( 'fbs_' . $this->c_app_id, '', time() - 1, '/' );
			//return
			return true;
		}
		
		//session based login check
		public function session_login() {
			if( !isset( $_COOKIE[$this->cookie_id . 'c_userid'] ) or empty( $_COOKIE[$this->cookie_id . 'c_userid'] ) )
				return false;
			if( !isset( $_COOKIE[$this->cookie_id . 'c_authkey'] ) or empty( $_COOKIE[$this->cookie_id . 'c_authkey'] ) )
				return false;
			if( !isset( $_COOKIE[$this->cookie_id . 'c_name'] ) )
				return false;
			return true;
		}
		
		//session based permission check
		public function session_permission( $permission ) {
			if( !$this->session_login() ) return false;
			if( !isset( $_COOKIE[$this->cookie_id . 'c_permissions'] ) ) return false;
			return in_array( $permission, explode( ',', $_COOKIE[$this->cookie_id . 'c_permissions'] ) );
		}
		
		//check a permission
		public function check_permission( $permission ) {
			if( !$this->check_login() ) return false;
			//query the permission tied to the group of the current user
			$result = $this->db_conn->query( '
				SELECT core_user.id
				FROM core_user, core_user_permissions
				WHERE core_user.id = "' . $_COOKIE[$this->cookie_id . 'c_userid'] . '"
				AND core_user_permissions.permission = "' . $permission . '"
				AND core_user.group = core_user_permissions.group_id
			' );
			//return
			return ( isset( $result[0]['id'] ) and $result[0]['id'] == $_COOKIE[$this->cookie_id . 'c_userid'] ) ? true : false;
		}
		
		//database based login check
		public function check_login() {
			//already checked?
			if( $this->checked_login ) return true;
			if( !$this->session_login() ) return false;
			//lets go!
			$result = $this->db_conn->query( '
				SELECT id
				FROM core_user
				WHERE id = "' . $_COOKIE[$this->cookie_id . 'c_userid'] . '"
				AND auth_key = "' . $_COOKIE[$this->cookie_id . 'c_authkey'] . '"
				LIMIT 1
			' );
			//return/speed up
			if( isset( $result[0]['id'] ) ):
				$this->checked_login = true;
				return true;
			else:
				return false;
			endif;
		}
		
		//set users name
		public function set_name( $name ) {
			if( !$this->check_login() ) return false;
			$result = $this->set_data( 'name', $name );
			if( $result ) setcookie( $this->cookie_id . 'c_name', $name, time() + 60 * 60 * 24 * 365, $this->cookie_dir );
			return $result;
		}
		
		//set user data
		public function set_data( $vars ) {
			if( !$this->check_login() ) return false;
			$sql = 'UPDATE core_user SET';
			foreach( $vars as $k => $v ):
				$sql .= ' ' . $k . ' = "' . $v . '",';
			endforeach;
			$sql = rtrim( $sql, ',' );
			$sql .= ' WHERE id = "' . $_COOKIE[$this->cookie_id . 'c_userid'] . '"';
			//change the email
			$result = $this->db_conn->query( $sql );
			return $result;
		}
		
		//get user data
		public function get_data( $userid = false ) {
			if( !$userid ):
				if( !$this->check_login() ) return false;
				$userid = $_COOKIE[$this->cookie_id . 'c_userid'];
			endif;
			$result = $this->db_conn->query( '
				SELECT *
				FROM core_user
				WHERE id = "' . $userid . '"
			' );
			$result = $result[0];
			return $result ? $result : false;
		}
		
		//return userid (after check login!)
		public function get_userid() {
			if( !$this->check_login() ) return false;
			return $_COOKIE[$this->cookie_id . 'c_userid'];
		}
		
		//return a list of attached openids
		public function get_openids() {
			if( !$this->check_login() ) return false;
			//grab them openid's!
			$result = $this->db_conn->query( '
				SELECT open_id
				FROM core_user_openids
				WHERE user_id = "' . $_COOKIE[$this->cookie_id . 'c_userid'] . '"
			' );
			return is_array( $result ) ? $result : false;
		}
		
		//delete a users openid
		public function delete_openid( $openid ) {
			if( !$this->check_login() ) return false;
			//if this is the last id, don't delete
			$check_ids = $this->db_conn->query( '
				SELECT user_id
				FROM core_user_openids
				WHERE user_id = "' . $_COOKIE[$this->cookie_id . 'c_userid'] . '"
			' );
			if( $check_ids and count( $check_ids ) == 1 ) return $this->debug->add( 'last_openid', 'Error' );
			//clean openid
			$cleaner = new c_data();
			$openid = $cleaner->clean( $openid );
			//delete the openid
			$result = $this->db_conn->query( '
				DELETE
				FROM core_user_openids
				WHERE user_id = "' . $_COOKIE[$this->cookie_id . 'c_userid'] . '"
				AND open_id = "' . $openid . '"
				LIMIT 1
			' );
			return $result;
		}
		
		//generate some openid stuff
		public function openid_urls() {
			return array(
				'google' => 'https://www.google.com/accounts/o8/id',
				'steam' => 'https://steamcommunity.com/openid',
				'yahoo' => 'https://me.yahoo.com',
			);
		}
	}
?>