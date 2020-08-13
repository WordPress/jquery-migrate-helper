/**
 * Show all jQuery Migrate warnings in the UI.
 */
jQuery( document ).ready( function( $ ) {
	const notice   = $( '.notice.jquery-migrate-deprecation-notice' );
	const warnings = jQuery.migrateWarnings;

	/**
	 * Filter the trace, return the first URI that is to a plugin or theme script.
	 */
	function getPluginSlugFromTrace( trace ) {
		let traceLines = trace.split( '\n' ),
			match = null;

		// Loop over each line in the stack trace
		traceLines.forEach( function( line ) {
			if ( ! line ) {
				return;
			}

			// Remove cache-busting.
			line = line.split( '?' )[0];

			// The first few lines are going to be references to the jquery-migrate script.
			// The first instance that is not one of them is probably a valid plugin or theme.
			if (
				! match &&
				line.indexOf( '/jquery-migrate-helper/js' ) === -1 &&
				line.indexOf( '/enable-jquery-migrate-helper/js' ) === -1 &&
				( line.indexOf( '/plugins/' ) > -1 || line.indexOf( '/themes/' ) > -1 )
			) {
				match = line.replace( /.*?http/, 'http' );
			}
		} );

		// If the stack trace did not contain a matching plugin or theme, just return a null value.
		return match;
	}

	if ( notice.length && warnings.length ) {
		const list = notice.find( '.jquery-migrate-deprecation-list' );

		notice.show();

		warnings.forEach( function( entry ) {
			const trace = getPluginSlugFromTrace( entry.trace );
			let message = trace ? trace + ': ' : '';

			message += entry.warning;
			list.append( $( '<li></li>' ).text( message ) );
		} );
	}

	// Add handler for dismissing of the dashboard notice.
	$( document ).on( 'click', '.jquery-migrate-dashboard-notice .notice-dismiss', function() {
		$.post( {
			url: window.ajaxurl,
			data: {
				action: 'jquery-migrate-dismiss-notice',
				'dismiss-notice-nonce': $( '#jquery-migrate-notice-nonce' ).val(),
			},
		} );
	} );
} );
