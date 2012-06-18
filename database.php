<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	//database core
	class c_db {
		//la-di-da
		private $host;
		private $user;
		private $pass;
		private $name;
		private $conn;
		private $data;
		private $debug;
		public $queries;
		private $memcache = false;
		
		//start this, no connection, setup only!
		public function __construct( $db_host, $db_user, $db_pass, $db_name, $mod_memcache = false ) {
			global $c_debug;
			
			//set some vars
			$this->host = $db_host;
			$this->user = $db_user;
			$this->pass = $db_pass;
			$this->name = $db_name;
			$this->conn = false;
			$this->queries = 0;

			//memcache?
			if( $mod_memcache )
				$this->memcache = $mod_memcache;

			//debug
			$this->debug = $c_debug;
			$this->debug->add( 'c_db class loaded' );
		}

		//destruct
		public function __destruct() {
			//close connection
			if( $this->conn ) @mysql_close( $this->conn );
		}
		
		//clean data, self referencing function
		public function clean( $data ) {
			//check mysql connection (and connect)
			if( !$this->conn and !$this->connect() ):
				$this->debug->add( 'No MySQL Connection', 'mysql' );
				return false;
			endif;

			//loop our data recursively
			if( !is_array( $data ) ):
				return mysql_real_escape_string( trim( $data ) );
			else:
				$newd = array();
				foreach( $data as $k => $v ):
					$newd[$k] = $this->clean( $v );
				endforeach;
				return $newd;
			endif;
		}
		
		//connection function!
		public function connect() {
			//connect @ incase of no sql, fails at end
			$this->conn = @mysql_connect( $this->host, $this->user, $this->pass );
			if( !$this->conn ) return $this->debug->add( 'Failed to connect to mysql' . mysql_error(), 'mysql_error', false, true );
			//select db
			mysql_select_db( $this->name, $this->conn );
			//before anything happens, clean all public data
			$_GET = $this->clean( $_GET );
			$_POST = $this->clean( $_POST );
			$_COOKIE = $this->clean( $_COOKIE );
			//debug
			$this->debug->add( 'connecting to MySQL... ' . ( $this->conn ? 'success' : 'failed' ), 'mysql' );
			//return the connection
			return $this->conn ? true : false;
		}
		
		//query funtion, public, returns an array of rows on select, default on other
		public function query( $sql, $cache = false, $cache_time = 0 ) {
			//cached query?
			if( $cache and $this->memcache ):
				$cached_name = 'fd_core_query_' . sha1( $sql );

				//attempt to fetch data
				$data = @$this->memcache->get( $cached_name );
				if( is_array( $data ) ):
					$this->debug->add( 'Query from cache: ' . $cached_name, 'mysql' );
					return $data;
				else:
					$this->debug->add( 'Not found in query cache: ' . $cached_name, 'mysql' );
				endif;
			endif;

			//check mysql connection (and connect)
			if( !$this->conn and !$this->connect() ):
				$this->debug->add( 'No MySQL Connection', 'MySQL' );
				return false;
			endif;

			//do the query
			$this->data = mysql_query( $sql, $this->conn );
			$err = mysql_error();

			//error handle
			if( !empty( $err ) )
				$this->debug->add( $err . '<br />' . $sql, 'mysql_query_error', false, true );

			//debug
			$this->debug->add( $sql, 'mysql_query' );

			//query count
			$this->queries++;

			//handle the data
			if( is_resource( $this->data ) ):
				if( mysql_num_rows( $this->data ) == 1 ):
					$data = array( mysql_fetch_assoc( $this->data ) );
				else:
					$data = array();
					//add data
					while( $v = mysql_fetch_assoc( $this->data ) )
						$data[] = $v;
				endif;

				//caching?
				if( $cache and $this->memcache )
					if( $cache_time <= 0 )
						@$this->memcache->set( $cached_name, $data, 0 );
					else
						$this->memcache->set( $cached_name, $data, 0, $cache_time );

				//return our data!
				return $data;
			else:
				//return the return if not resource
				return $this->data;
			endif;
		}
		
		//get insert id
		public function insert_id() {
			return $this->conn ? mysql_insert_id( $this->conn ) : $this->debug->add( 'insert_id: failed: ' . mysql_error(), 'mysql_error', false, true );
		}

		//affected rows
		public function affected_rows() {
			return $this->conn ? mysql_affected_rows( $this->conn ) : $this->debug->add( 'affected_rows: failed: ' . mysql_error(), 'mysql_error', false, true );
		}
	}
?>