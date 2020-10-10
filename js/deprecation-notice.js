/**
 * Show all jQuery Migrate warnings in the UI.
 */
jQuery( document ).ready( function( $ ) {
	const notice       = $( '.notice.jquery-migrate-deprecation-notice' );
	const warnings     = jQuery.migrateWarnings;
	const adminbar     = $( '#wp-admin-bar-enable-jquery-migrate-helper' );
	const countWrapper = $( '.count-wrapper', adminbar );

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
				line.indexOf( '/' + JQMH.plugin_slug + '/js' ) === -1 &&
				( line.indexOf( '/plugins/' ) > -1 || line.indexOf( '/themes/' ) > -1 )
			) {
				match = line.replace( /.*?http/, 'http' );
			}
		} );

		// If the stack trace did not contain a matching plugin or theme, just return a null value.
		return match;
	}

	/**
	 * Update the count of deprecations found on this page.
	 *
	 * @param count
	 */
	function setAdminBarCount( count ) {
		if ( ! adminbar.length ) {
			return;
		}

		if ( ! countWrapper.is( ':visible' ) ) {
			countWrapper.show();

			countWrapperVisibility();
		}

		$( '.count', adminbar ).text( count );
	}

	/**
	 * Set the admin bar visibility level based on the warning counters.
	 */
	function countWrapperVisibility() {
		if ( countWrapper.is( ':visible' ) ) {
			adminbar
				.css( 'background-color', '#be4400' )
				.css( 'color', '#eeeeee' );
		} else {
			adminbar
				.css( 'background-color', '' )
				.css( 'color', '' );
		}
	}

	/**
	 * Append the deprecation to the admin dashbaord, if applicable.
	 *
	 * @param message
	 */
	function appendNoticeDisplay( message ) {
		const list = notice.find( '.jquery-migrate-deprecation-list' );

		if ( ! notice.length ) {
			return;
		}

		if ( ! notice.is( ':visible' ) ) {
			notice.show();
		}

		list.append( $( '<li></li>' ).text( message ) );
	}

	/**
	 * Try to log the deprecation for the admin area.
	 *
	 * @param message
	 */
	function reportDeprecation( message ) {
		// Do not write to the logfile if this is the backend, and the notices are written to the screen.
		if ( JQMH.backend && notice.length ) {
			return;
		}

		let data = {
			action: 'jquery-migrate-log-notice',
			notice: message,
			nonce: JQMH.report_nonce,
			backend: JQMH.backend,
			url: window.location.href,
		};

		$.post( {
			url: JQMH.ajaxurl,
			data
		} );
	}

	if ( warnings.length ) {
		warnings.forEach( function( entry ) {
			const trace = getPluginSlugFromTrace( entry.trace );
			let message = trace ? trace + ': ' : '';

			message += entry.warning;

			appendNoticeDisplay( message );

			reportDeprecation( message );
		} );

		setAdminBarCount( warnings.length );
	}

	// Add handler for dismissing of the dashboard notice.
	$( document ).on( 'click', '.jquery-migrate-dashboard-notice .notice-dismiss', function() {
		const $notice = $( this ).closest( '.notice' );
		const notice_id = $notice.data( 'notice-id' );

		$.post( {
			url: window.ajaxurl,
			data: {
				action: 'jquery-migrate-dismiss-notice',
				'notice': notice_id,
				'dismiss-notice-nonce': $( '#' + notice_id + '-nonce' ).val(),
			},
		} );
	} );

	// When the previous deprecations are dismissed, reset the admin bar log display.
	$( document ).on( 'click', '.jquery-migrate-previous-deprecations .notice-dismiss', function() {
		countWrapper.hide();
		countWrapperVisibility();
	} );

	// Check if the counter is visible on page load.
	countWrapperVisibility();
} );
