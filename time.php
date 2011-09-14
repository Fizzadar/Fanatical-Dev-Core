<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	//time a go thing
	function c_time_since( $past ) {
		//get the seconds past
		$time = time() - $past;
		
		//less than 60 seconds, go!
		if( $time < 60 ):
			return $time . ' second' . ( $time == 1 ? '' : 's' ) . ' ago';
		endif;
		
		//less than an hour?
		if( $time < 60 * 60 ):
			$time = round( $time / 60 );
			return $time . ' minute' . ( $time == 1 ? '' : 's' ) . ' ago';
		endif;
		
		//less than... a day?
		if( $time < 60 * 60 * 24 ):
			$time = round( $time / 3600 );
			return $time . ' hour' . ( $time == 1 ? '' : 's' ) . ' ago';
		endif;
		
		//less than... a week?
		if( $time < 60 * 60 * 24 * 7 ):
			$time = round( $time / ( 60 * 60 * 24 ) );
			return $time . ' day' . ( $time == 1 ? '' : 's' ) . ' ago';
		endif;
		
		//less than a YEAR?!
		if( $time < 60 * 60 * 24 * 365 ):
			$time = round( $time / ( 60 * 60 * 24 * 7 ) );
			return $time . ' week' . ( $time == 1 ? '' : 's' ) . ' ago';
		endif;
		
		//fine, years it is
		$time = round( $time / ( 60 * 60 * 24 * 365 ) );
		return $time . ' year' . ( $time == 1 ? '' : 's' ) . ' ago';
	}
?>