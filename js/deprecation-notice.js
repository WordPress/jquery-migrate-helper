jQuery( document ).ready( function( $ ) {
	function getPluginSlugFromTrace( trace ) {
		let traceLines = trace.split( "\n" ),
			regexSearch,
			matches = null;

		// Loop over each line in the stack trace
		traceLines.forEach( function( line ) {
			/*
			 * The first few lines are going to be the jquery-migrate script, first instance
			 * that is not one of them is probably a valid plugin or theme.
			 *
			 * If it's not the jquery-migrate script, identified by the version strings custom
			 * addition by this plugin, do a regex match for a plugin or theme slug.
			 *
			 * If no regex match is made, it's likely a section of code triggered by something
			 * else, so keep moving down the stack to find one.
			 */
			if ( -1 === line.indexOf( 'ejqmh' ) ) {
				regexSearch = line.match( /\/(plugins|themes)\/(.+?)\//gm );

				if ( regexSearch ) {
					matches = regexSearch[0];
					return true;
				}
			}
		} );

		// If the stack trace did not contain a matching plugin or theme, just return a null value.
		return matches;
	}

	if ( jQuery.migrateWarnings.length >= 1 ) {
		$( '#jquery-migrate-deprecation-notice' ).show();

		jQuery.migrateWarnings.forEach( function( entry ) {
			let entryCause = getPluginSlugFromTrace( entry.trace );
			let entryMessage = '<li>';

			if ( entryCause ) {
				entryMessage = entryMessage + entryCause + ': ';
			}

			entryMessage = entryMessage + entry.warning + '</li>';

			$( '#jquery-migrate-deprecation-list' ).append( entryMessage );
		} );
	}
} );
