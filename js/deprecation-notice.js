jQuery( document ).ready( function( $ ) {
	if ( jQuery.migrateWarnings.length >= 1 ) {
		$( '#jquery-migrate-deprecation-notice' ).show();

		jQuery.migrateWarnings.forEach( function( entry ) {
			$( '#jquery-migrate-deprecation-list' ).append( '<li>' + entry + '</li>' );
		} );
	}
} );
