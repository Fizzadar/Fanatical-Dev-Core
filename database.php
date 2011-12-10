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
		
		//close the connection if it exsits
		public function __destruct() {
			//close connection
			if( $this->conn ) mysql_close( $this->conn );
			//debug
			$this->debug->add( 'Queries: ' . $this->queries, 'MySQL' );
		}
		
		//clean data, self referencing function
		public function clean( $data ) {
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
			//connect
			$this->conn = @mysql_connect( $this->host, $this->user, $this->pass );
			//select db
			@mysql_select_db( $this->name, $this->conn );
			//before anything happens, clean all public data
			$_GET = $this->clean( $_GET );
			$_POST = $this->clean( $_POST );
			$_COOKIE = $this->clean( $_COOKIE );
			//debug
			$this->debug->add( 'connecting to MySQL... ' . ( $this->conn ? 'success' : 'failed' ), 'MySQL' );
			//return the connection
			return $this->conn ? true : false;
		}
		
		//query funtion, public
		public function query( $sql, $cache = false ) {
			//cached query?
			if( $cache and $this->memcache ):
				if( $data = $this->memcache->get( 'fd_core_query_' . sha1( $sql ) ) ):
					$this->debug->add( '<strong>Query from cache:</strong>' . sha1( $sql ), 'MySQL' );
					return $data;
				endif;
			endif;
			//check mysql connection
			if( !$this->conn and !$this->connect() ):
				$this->debug->add( 'No MySQL Connection', 'MySQL' );
				return false;
			endif;
			//do the query
			$this->data = mysql_query( $sql, $this->conn );
			$err = mysql_error();
			//error handle
			if( !empty( $err ) )
				$this->debug->add( $err . '<br /><strong>Query:</strong><pre>' . $sql . '</pre>', 'MySQL' );
			//debug
			$this->debug->add( '<strong>Query:</strong><br /><pre>' . str_replace( '	', '', $sql ) . '</pre>', 'MySQL' );
			//query count
			$this->queries++;
			//handle the data
			if( is_resource( $this->data ) ):
				if( mysql_num_rows( $this->data ) == 1 ):
					$data = array( mysql_fetch_array( $this->data ) );
				else:
					$data = array();
					//add data
					while( $v = mysql_fetch_array( $this->data ) )
						$data[] = $v;
				endif;
				//strip numbered keys
				foreach( $data as $k => $v )
					foreach( $v as $c => $d )
						if( is_numeric( $c ) )
							unset( $data[$k][$c] );
				//caching?
				if( $cache and $this->memcache )
					@$this->memcache->add( 'fd_core_query_' . sha1( $sql ), $data );
				return $data;
			else:
				return $this->data;
			endif;
		}
		
		//get num/affected rows
		public function num_rows() {
			if( is_resource( $this->data ) ):
				if( mysql_num_rows( $this->data ) ) return mysql_num_rows( $this->data );
				if( mysql_affected_rows( $this->data ) ) return mysql_affected_rows( $this->data );
			endif;
			return false;
		}
		
		//get insert id
		public function insert_id() {
			return $this->conn ? mysql_insert_id( $this->conn ) : false;
		}

		//affected rows
		public function affected_rows() {
			return $this->conn ? mysql_affected_rows( $this->conn ) : false;
		}
	}
?>