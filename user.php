<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	class c_user {
		public $cookie_id;
		private $fb_id;
		private $fb_secret;
		private $tw_key;
		private $tw_secret;
		private $db_conn;
		private $checked_login = false;
		private $checked_permissions = array();
		private $debug;
		private $cookie_dir;
		
		public function __construct( $c_db, $cookie_id = '' ) {
			global $c_debug, $c_config;
			//make sure database is up
			if( !method_exists( $c_db, 'query' ) ) return $this->debug->add( 'Cannot start c_user, no database query method', 'Error' );
			$this->db_conn = $c_db;
			$this->cookie_id = $cookie_id;
			$this->cookie_dir = '/';
			//debug
			$this->debug = $c_debug;
			$this->debug->add( 'c_user class loaded' );
		}

		//set facebook details
		public function set_facebook( $app_id, $secret ) {
			$this->fb_id = $app_id;
			$this->fb_secret = $secret;
		}

		//set twitter details
		public function set_twitter( $key, $secret ) {
			$this->tw_key = $key;
			$this->tw_secret = $secret;
		}
		
		//openid login
		public function openid_login() {
			//required items for oid
			if( !$_GET['openid_mode'] or !isset( $_GET['openid_identity'] ) ) return $this->debug->add( 'Invalid oid data sent' );

			//start openid
			$openid = new LightOpenID;

			//validate the login
			try {
				$result = $openid->validate();
			} catch( Exception $e ) {
				return $this->debug->add( 'Couldnt validate oid', 'Login' );
			}
			if( !$result ) return $this->debug->add( 'Couldnt validate oid, result: ' . $result, 'Login' );

			//login via openid
			return $this->login_oid( $_GET['openid_identity'] );
		}
		
		//facebook login
		public function fb_login() {
			//start the facebook class
			$fb = new Facebook( array(
				'appId' => $this->fb_id,
				'secret' => $this->fb_secret,
				'cookie' => true
			) );

			//get our user
			if( !$fb->getUser() ) return $this->debug->add( 'Failed to get facebook user', 'Login' );
			$uid = $fb->getUser();

			//get the access token (hopefully)
			$token = $fb->getAccessToken() ? $fb->getAccessToken() : '';

			//login via oauth
			return $this->login_oau( 'facebook', $uid, $token );
		}

		//twitter login
		public function tw_login() {
			//start twitter class
			$tw = new TwitterOAuth( $this->tw_key, $this->tw_secret, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret'] );

			//get access token
			$token = @$tw->getAccessToken( $_GET['oauth_verifier'] );
			if( !$token ) return $this->debug->add( 'Could not verify token with twitter', 'Login' );

			//get user
			$user = $tw->get( 'account/verify_credentials' );
			if( !isset( $user->id ) or !empty( $user->error ) ) return $this->debug->add( 'Could not verify token with twitter: ' . $user->error, 'Login' );
			$uid = $user->id;

			//login via oauth
			return $this->login_oau( 'twitter', $uid, $token['oauth_token'], $token['oauth_token_secret'] );
		}

		//login via user_id (state: 1 = logged in, 2 = new user logged in, 3 = openid/oauth account added to user, 4 = already logged in and has id)
		private function login( $id, $state ) {
			//get user data
			$user = $this->get_data( $id );
			//uh oh, there is no user!
			if( !$user ) return $this->debug->add( 'Failed to load user', 'Login' );

			//update login time
			$this->db_conn->query( '
				UPDATE core_user
				SET login_time = ' . time() . '
				WHERE id = ' . $id . '
				LIMIT 1
			' );

			//get permissions
			$perms = $this->db_conn->query( '
				SELECT permission
				FROM core_user_permissions
				WHERE group_id = ' . $user['group'] . '
			' );
			//make the permissions look nice
			$permissions = '';
			foreach( $perms as $id => $perm ):
				$permissions .= $perm['permission'] . ( count( $perms ) == $id + 1 ? '' : ',' );
			endforeach;

			//now set our cookies & login!
			setcookie( $this->cookie_id . 'c_userid', $user['id'], time() + 60 * 60 * 24 * 365, $this->cookie_dir );
			$_COOKIE[$this->cookie_id . 'c_userid'] = $user['id'];
			//auth key
			setcookie( $this->cookie_id . 'c_authkey', $user['auth_key'], time() + 60 * 60 * 24 * 365, $this->cookie_dir );
			$_COOKIE[$this->cookie_id . 'c_authkey'] = $user['auth_key'];
			//name
			setcookie( $this->cookie_id . 'c_name', $user['name'], time() + 60 * 60 * 24 * 365, $this->cookie_dir );
			$_COOKIE[$this->cookie_id . 'c_name'] = $user['name'];
			//permissions
			setcookie( $this->cookie_id . 'c_permissions', $permissions, time() + 60 * 60 * 24 * 365, $this->cookie_dir );
			$_COOKIE[$this->cookie_id . 'c_permissions'] = $permissions;

			//and we're done!
			return $state;
		}

		//login via openid (assuming detailes verified)
		private function login_oid( $openid ) {
			//get our user id
			$id = $this->get_userid();
			
			//get the openid
			$oid = $this->db_conn->query( '
				SELECT user_id
				FROM core_user_openids
				WHERE open_id = "' . $openid . '"
				LIMIT 1
			' );
			if( !is_array( $oid ) )
				return $this->debug->add( 'Error checking oid', 'Login' );

			//no user and no openid? create a user
			if( !$id and count( $oid ) != 1 ):
				$id = $this->register();
				if( !$id ) return $this->debug->add( 'Failed to register new user', 'Login' );
				$state = 2; //new user
			//no user and got id? login as user
			elseif( !$id and count( $oid ) == 1 ):
				$id = $oid[0]['user_id'];
				$state = 1; //normal login
			//got user and no openid? reg id
			elseif( $id and count( $oid ) != 1 ):
				$state = 3; //add openid
			//got both?
			else:
				if( $id != $oid[0]['user_id'] ) return $this->debug->add( 'This is not your openid!', 'Login' );
				$state = 4; //normal re-login
			endif;

			//no openid?, add to the user
			if( count( $oid ) != 1 ):
				//add the openid
				$i = $this->db_conn->query( '
					INSERT INTO core_user_openids
					( user_id, open_id )
					VALUES ( ' . $id . ', "' . $openid . '" )
				' );
				//fail?
				if( !$i ) return $this->debug->add( 'Failed to add oid', 'Login' );
			endif;

			//and finally, lets login
			return $this->login( $id, $state );
		}

		//login via oauth (assuming all detailes verified)
		private function login_oau( $provider, $oid, $token = '', $secret = '' ) {
			//get our user id
			$id = $this->get_userid();

			//get the oauth
			$oau = $this->db_conn->query( '
				SELECT user_id, token
				FROM core_user_oauths
				WHERE provider = "' . $provider . '"
				AND o_id = "' . $oid . '"
				LIMIT 1
			' );
			if( !is_array( $oau ) )
				return $this->debug->add( 'Error checking oau', 'Login' );

			//no user and no oauth? create a user
			if( !$id and count( $oau ) != 1 ):
				$id = $this->register();
				if( !$id ) return $this->debug->add( 'Failed to register new user', 'Login' );
				$state = 2; //new user
			//no user and got oauth? login as user
			elseif( !$id and count( $oau ) == 1 ):
				$id = $oau[0]['user_id'];
				$state = 1; //normal login
			//got user and no oauth? reg id
			elseif( $id and count( $oau ) != 1 ):
				$state = 3; //add oauth
			//got both?
			else:
				if( $id != $oau[0]['user_id'] ) return $this->debug->add( 'This is not your oauth!', 'Login' );
				$state = 4; //normal re-login
			endif;

			//no oauth selected?, add to the user
			if( count( $oau ) != 1 ):
				//add the openid
				$i = $this->db_conn->query( '
					INSERT INTO core_user_oauths
					( user_id, provider, o_id, token, secret )
					VALUES ( ' . $id . ', "' . $provider . '", "' . $oid . '", "' . $token . '", "' . $secret . '" )
				' );
				//fail?
				if( !$i ) return $this->debug->add( 'Failed to add oau', 'Login' );
			endif;
			
			//new token?
			if( count( $oau ) == 1 and $oau[0]['token'] != $token ):
				$this->db_conn->query( '
					UPDATE core_user_oauths
					SET token = "' . $token . '"
					WHERE provider = "' . $provider . '"
					AND user_id = ' . $oau[0]['user_id'] . '
					AND o_id = ' . $oid . '
					LIMIT 1
				' );
			endif;

			//finally, login
			return $this->login( $id, $state );
		}

		//register a user
		private function register() {
			//make our name & key
			$name = 'User' . substr( md5( mt_rand( 1, 1000 ) ), 0, 4 );
			$auth_key = hash( 'sha512', uniqid() . $name );

			//insert our new user
			$i = $this->db_conn->query( '
				INSERT INTO core_user
				( registration_time, name, auth_key )
				VALUES ( ' . time() . ', "' . $name . '", "' . $auth_key . '" )
			' );

			//and return the id (if it worked)
			return $i ? $this->db_conn->insert_id() : false;
		}

		//generate openid url
		public function oid_out( $provider_url, $return_url ) {
			global $c_config;
			//start the openid
			$openid = new LightOpenID;
			$openid->identity = $provider_url;
			$openid->realm = $c_config['base'];
			$openid->returnUrl = $return_url;

			//find our url
			$out_url = false;
			try {
				$out_url = $openid->authUrl();
			} catch( Exception $e ) {
				return false;
			}
			//return our url
			return $out_url;
		}

		//generate facebook url
		public function fb_out( $return_url, $scope = '' ) {
			//start the facebook class
			$fb = new Facebook( array(
				'appId' => $this->fb_id,
				'secret' => $this->fb_secret,
				'cookie' => true
			) );

			//go!
			return $fb->getLoginUrl( 
				array( 
					'scope' => $scope,
					'redirect_uri' => $return_url
				)
			);
		}

		//generate twitter url
		public function tw_out( $return_url ) {
			//start twitter class
			$tw = new TwitterOAuth( $this->tw_key, $this->tw_secret );

			//get request token
			$request_token = $tw->getRequestToken( $return_url );

			//set token data
			$_SESSION['oauth_token'] = $request_token['oauth_token'];
			$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

			//all good?
			if( $tw->http_code != 200 ) return $this->debug->add( 'Failed to get reqeust token from twitter', 'Login' );

			//generate the url
			return $tw->getAuthorizeURL( $request_token['oauth_token'] );
		}

		//logout, remove the cookies
		public function logout() {
			//remove our cookies
			setcookie( $this->cookie_id . 'c_userid', '', time() - 1, $this->cookie_dir );
			unset( $_COOKIE[$this->cookie_id . 'c_userid'] );

			setcookie( $this->cookie_id . 'c_authkey', '', time() - 1, $this->cookie_dir );
			unset( $_COOKIE[$this->cookie_id . 'c_authkey'] );

			setcookie( $this->cookie_id . 'c_name', '', time() - 1, $this->cookie_dir );
			unset( $_COOKIE[$this->cookie_id . 'c_name'] );

			setcookie( $this->cookie_id . 'c_permissions', '', time() - 1, $this->cookie_dir );
			unset( $_COOKIE[$this->cookie_id . 'c_permissions'] );

			//return
			return true;
		}
		
		//reload session data
		public function relogin() {
			//if we have a user, make sure return state = relogin (4)
			if( $id = $this->get_userid() )
				return $this->login( $id, 4 ) == 4;
			else
				return false;
		}

		//session based login check
		public function session_login() {
			//check each session bit
			if( !isset( $_COOKIE[$this->cookie_id . 'c_userid'] ) or empty( $_COOKIE[$this->cookie_id . 'c_userid'] ) )
				return false;
			if( !isset( $_COOKIE[$this->cookie_id . 'c_authkey'] ) or empty( $_COOKIE[$this->cookie_id . 'c_authkey'] ) )
				return false;
			if( !isset( $_COOKIE[$this->cookie_id . 'c_name'] ) )
				return false;
			
			//return true
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
			if( in_array( $permission, $this->checked_permissions ) ) return true;
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
			if( isset( $result[0]['id'] ) and $result[0]['id'] == $_COOKIE[$this->cookie_id . 'c_userid'] ):
				$this->checked_permissions[] = $permission;
				return true;
			else:
				return false;
			endif;
		}
		
		//database based login check
		public function check_login() {
			//already checked?
			if( $this->checked_login ) return true;

			//session vars not set?
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

		//session id
		public function session_userid() {
			if( !$this->session_login() ) return false;
			return $_COOKIE[$this->cookie_id . 'c_userid'];
		}

		//session username (aka get username without db)
		public function session_username() {
			if( !$this->session_login() ) return false;
			return $_COOKIE[$this->cookie_id . 'c_name'];
		}

		//delete openid
		public function delete_openid( $oid ) {
			//only if logged in!
			if( !$this->check_login() ) return false;
		}

		//delete oauth
		public function delete_oauth( $provider, $oid ) {
			//only if logged in!
			if( !$this->check_login() ) return false;
		}

		//get openids
		public function get_openids( $provider = '' ) {
			//only if logged in!
			if( !$this->check_login() ) return false;

			//select our oids for user
			$oids = $this->db_conn->query( '
				SELECT open_id
				FROM core_user_openids
				WHERE user_id = ' . $this->get_userid() . '
			' );

			//return 'em
			return $oids;
		}

		//get oauths
		public function get_oauths( $provider = '' ) {
			//only if logged in!
			if( !$this->check_login() ) return false;

			//select our oauths for the user
			$oauths = $this->db_conn->query( '
				SELECT provider, o_id, token, secret
				FROM core_user_oauths
				WHERE user_id = ' . $this->get_userid() . '
				' . ( !empty( $provider ) ? 'AND provider = "' . $provider . '"' : '' ) . '
			' );

			//return them
			return $oauths;
		}
	}
?>