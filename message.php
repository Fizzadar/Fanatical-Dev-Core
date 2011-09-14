<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
	//display messages based on an array
	function c_messages( $messages ) {
		//lets build an array
		$return = array();
		//get active messages
		$matches = array();
		if( isset( $_GET['notice'] ) ) $matches = explode( ',', $_GET['notice'] );
		//loop the message config
		foreach( $messages as $key => $message ):
			foreach( $matches as $match ):
				if( $match == $key ):
					$return[] = $message;
				endif;
			endforeach;
			if( isset( $message['global'] ) and $message['global'] ):
				$return[] = $message;
			endif;
		endforeach;
		return $return;
	}
?>