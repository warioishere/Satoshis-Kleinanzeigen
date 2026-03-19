/**
 * SK Dashboard live navigation — AJAX content switching.
 *
 * Intercepts sidebar nav clicks, fetches the target page, swaps the
 * .sk-dashboard-content innerHTML, updates the active nav item, and
 * pushes the URL to the browser history.  Back/forward buttons are
 * handled via the popstate event.
 *
 * @since 4.0.0
 */
( function () {
	'use strict';

	var SIDEBAR_SEL  = '.sk-dash-sidebar';
	var CONTENT_SEL  = '.sk-dashboard-content';
	var ACTIVE_CLASS = 'active';
	var LOADING_CLASS = 'sk-loading';

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Re-execute any inline <script> tags found inside a DOM node.
	 *
	 * Browsers do not run scripts injected via innerHTML, so we clone each
	 * script element and append it to <body> which triggers execution.
	 *
	 * @param {Element} container
	 */
	function rerunScripts( container ) {
		var scripts = container.querySelectorAll( 'script' );
		scripts.forEach( function ( oldScript ) {
			var newScript = document.createElement( 'script' );
			// Copy all attributes (type, src, …)
			Array.prototype.forEach.call( oldScript.attributes, function ( attr ) {
				newScript.setAttribute( attr.name, attr.value );
			} );
			newScript.textContent = oldScript.textContent;
			document.body.appendChild( newScript );
			document.body.removeChild( newScript );
		} );
	}

	/**
	 * Update the active class on sidebar nav items to match the given URL.
	 *
	 * @param {string} url
	 */
	function updateActiveNav( url ) {
		var sidebar = document.querySelector( SIDEBAR_SEL );
		if ( ! sidebar ) return;

		sidebar.querySelectorAll( 'li' ).forEach( function ( li ) {
			var a = li.querySelector( 'a' );
			if ( ! a ) return;

			// Compare path segments, ignoring trailing slash differences
			var liPath  = a.href.replace( /\/$/, '' );
			var curPath = url.replace( /\/$/, '' );

			if ( liPath === curPath ) {
				li.classList.add( ACTIVE_CLASS );
			} else {
				li.classList.remove( ACTIVE_CLASS );
			}
		} );
	}

	/**
	 * Show spinner on the content area.
	 *
	 * @param {Element} contentEl
	 */
	function showLoading( contentEl ) {
		contentEl.classList.add( LOADING_CLASS );
	}

	/**
	 * Remove spinner from the content area.
	 *
	 * @param {Element} contentEl
	 */
	function hideLoading( contentEl ) {
		contentEl.classList.remove( LOADING_CLASS );
	}

	// -------------------------------------------------------------------------
	// Core fetch + swap
	// -------------------------------------------------------------------------

	/**
	 * Fetch a dashboard URL, extract .sk-dashboard-content, and swap it in.
	 *
	 * @param {string}  url            Full URL to load
	 * @param {boolean} pushState      Whether to call history.pushState
	 */
	function loadPage( url, pushState ) {
		var contentEl = document.querySelector( CONTENT_SEL );
		if ( ! contentEl ) return;

		showLoading( contentEl );

		fetch( url, {
			method:      'GET',
			credentials: 'same-origin',
			headers:     { 'X-SK-Ajax-Nav': '1' }
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.text();
			} )
			.then( function ( html ) {
				// Parse response and find the new content element
				var parser    = new DOMParser();
				var doc       = parser.parseFromString( html, 'text/html' );
				var newContent = doc.querySelector( CONTENT_SEL );

				if ( ! newContent ) {
					// Fallback: full reload if structure not found
					window.location.href = url;
					return;
				}

				// Swap the innerHTML
				contentEl.innerHTML = newContent.innerHTML;
				hideLoading( contentEl );

				// Re-execute inline scripts in the new content
				rerunScripts( contentEl );

				// Update browser URL
				if ( pushState ) {
					history.pushState( { skNav: url }, '', url );
				}

				// Sync active nav highlight
				updateActiveNav( url );

				// Scroll content area to top
				contentEl.scrollTop = 0;
				window.scrollTo( 0, 0 );

				// Close mobile hamburger menu after AJAX navigation
				var mobileToggle = document.getElementById( 'toggle-mobile-menu' );
				if ( mobileToggle ) mobileToggle.checked = false;

				// Notify other scripts that new content was loaded
				document.dispatchEvent( new CustomEvent( 'sk:nav-loaded', { detail: { url: url } } ) );
			} )
			.catch( function () {
				// On error fall back to a normal page load
				hideLoading( contentEl );
				window.location.href = url;
			} );
	}

	// -------------------------------------------------------------------------
	// Click interceptor
	// -------------------------------------------------------------------------

	function shouldIntercept( anchor ) {
		if ( ! anchor ) return false;

		var href = anchor.getAttribute( 'href' );
		if ( ! href ) return false;

		// Skip external links, hash-only, new-tab
		if ( anchor.target === '_blank' ) return false;
		if ( href.startsWith( '#' ) ) return false;
		if ( anchor.hostname && anchor.hostname !== window.location.hostname ) return false;

		// Only intercept links inside the sidebar
		var sidebar = document.querySelector( SIDEBAR_SEL );
		if ( ! sidebar || ! sidebar.contains( anchor ) ) return false;

		return true;
	}

	document.addEventListener( 'click', function ( e ) {
		var anchor = e.target.closest( 'a' );
		if ( ! shouldIntercept( anchor ) ) return;

		e.preventDefault();
		loadPage( anchor.href, true );
	} );

	// -------------------------------------------------------------------------
	// Browser back / forward
	// -------------------------------------------------------------------------

	window.addEventListener( 'popstate', function ( e ) {
		if ( e.state && e.state.skNav ) {
			loadPage( e.state.skNav, false );
		} else {
			// No state — reload to sync cleanly
			window.location.reload();
		}
	} );

	// Seed the initial history entry so popstate fires on back from first page
	history.replaceState( { skNav: window.location.href }, '' );

} )();
