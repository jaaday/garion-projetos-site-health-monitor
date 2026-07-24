( function ( wp, data ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch ) {
		return;
	}

	if ( data.restNonce && wp.apiFetch.createNonceMiddleware ) {
		wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( data.restNonce ) );
	}

	var i18n = data.i18n || {};

	function t( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	function format( template, value ) {
		return template.replace( '%d', value ).replace( '%1$d', value );
	}

	/**
	 * Minimal accessible modal used for the "ignore this check" reason prompt,
	 * consistent with the rest of the admin UI instead of a native prompt().
	 * Resolves with `false` when cancelled, or the reason string when confirmed.
	 */
	function gpshmModal( options ) {
		return new Promise( function ( resolve ) {
			var overlay = document.createElement( 'div' );
			overlay.className = 'gpshm-modal-overlay';

			var box = document.createElement( 'div' );
			box.className = 'gpshm-modal';
			box.setAttribute( 'role', 'dialog' );
			box.setAttribute( 'aria-modal', 'true' );

			var titleEl = document.createElement( 'h2' );
			titleEl.textContent = options.title || t( 'ignoreTitle', 'Ignore' );
			box.appendChild( titleEl );

			if ( options.message ) {
				var messageEl = document.createElement( 'p' );
				messageEl.textContent = options.message;
				box.appendChild( messageEl );
			}

			var textarea = document.createElement( 'textarea' );
			textarea.rows = 3;
			textarea.setAttribute( 'aria-label', options.title || '' );
			box.appendChild( textarea );

			var actions = document.createElement( 'div' );
			actions.className = 'gpshm-modal__actions';

			var cancelButton = document.createElement( 'button' );
			cancelButton.type = 'button';
			cancelButton.className = 'button';
			cancelButton.textContent = t( 'cancel', 'Cancel' );

			var confirmButton = document.createElement( 'button' );
			confirmButton.type = 'button';
			confirmButton.className = 'button button-primary';
			confirmButton.textContent = t( 'confirm', 'Confirm' );

			actions.appendChild( cancelButton );
			actions.appendChild( confirmButton );
			box.appendChild( actions );
			overlay.appendChild( box );
			document.body.appendChild( overlay );

			var previouslyFocused = document.activeElement;

			function close( result ) {
				document.removeEventListener( 'keydown', onKeydown );
				overlay.remove();
				if ( previouslyFocused && previouslyFocused.focus ) {
					previouslyFocused.focus();
				}
				resolve( result );
			}

			function onKeydown( event ) {
				if ( 'Escape' === event.key ) {
					close( false );
				}
			}

			overlay.addEventListener( 'mousedown', function ( event ) {
				if ( event.target === overlay ) {
					close( false );
				}
			} );

			cancelButton.addEventListener( 'click', function () {
				close( false );
			} );

			confirmButton.addEventListener( 'click', function () {
				close( textarea.value || '' );
			} );

			document.addEventListener( 'keydown', onKeydown );
			confirmButton.focus();
		} );
	}

	/**
	 * A dismissible toast with an optional action button (used for the
	 * "ignored — undo" notice). Auto-dismisses after `duration` ms.
	 */
	function gpshmToast( message, actionLabel, onAction, duration ) {
		var existing = document.querySelector( '.gpshm-toast' );
		if ( existing ) {
			existing.remove();
		}

		var toast = document.createElement( 'div' );
		toast.className = 'gpshm-toast';
		toast.setAttribute( 'role', 'status' );

		var text = document.createElement( 'span' );
		text.textContent = message;
		toast.appendChild( text );

		var timer = null;

		if ( actionLabel && onAction ) {
			var action = document.createElement( 'button' );
			action.type = 'button';
			action.className = 'gpshm-toast__action';
			action.textContent = actionLabel;
			action.addEventListener( 'click', function () {
				clearTimeout( timer );
				toast.remove();
				onAction();
			} );
			toast.appendChild( action );
		}

		document.body.appendChild( toast );
		timer = setTimeout( function () {
			toast.remove();
		}, duration || 6000 );
	}

	/**
	 * Persists a pending "undo ignore" hint across the full-page reload that
	 * follows a successful ignore action, so the undo toast can still be
	 * shown after the reload instead of only in the instant before it.
	 */
	function rememberUndo( key ) {
		try {
			sessionStorage.setItem( 'gpshmUndo', JSON.stringify( { key: key, ts: Date.now() } ) );
		} catch ( e ) {
			// sessionStorage unavailable (private mode, etc.) — undo toast is skipped, ignore still succeeds.
		}
	}

	function consumePendingUndo() {
		var raw;
		try {
			raw = sessionStorage.getItem( 'gpshmUndo' );
			sessionStorage.removeItem( 'gpshmUndo' );
		} catch ( e ) {
			return;
		}
		if ( ! raw ) {
			return;
		}

		var parsed;
		try {
			parsed = JSON.parse( raw );
		} catch ( e ) {
			return;
		}

		if ( ! parsed || ! parsed.key || Date.now() - parsed.ts > 15000 ) {
			return;
		}

		gpshmToast( t( 'ignored', 'Check ignored.' ), t( 'undo', 'Undo' ), function () {
			wp.apiFetch( { path: '/' + data.restNamespace + '/issues/' + parsed.key + '/reopen', method: 'POST' } ).then( function () {
				window.location.reload();
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {

		consumePendingUndo();

		/* --- Run check now (header, every tab) --------------------------- */

		var runButton  = document.getElementById( 'gpshm-run-check' );
		var runStatus  = document.getElementById( 'gpshm-run-status' );
		var overviewEl = document.getElementById( 'gpshm-overview-content' );

		if ( runButton ) {
			runButton.addEventListener( 'click', function () {
				runButton.disabled = true;
				runButton.classList.add( 'is-loading' );
				if ( runStatus ) { runStatus.textContent = t( 'running', 'Running...' ); }

				wp.apiFetch( { path: '/' + data.restNamespace + '/report', method: 'POST' } ).then( function ( response ) {
					runButton.classList.remove( 'is-loading' );
					if ( overviewEl && response.html ) {
						overviewEl.innerHTML = response.html;
						if ( runStatus ) { runStatus.textContent = t( 'done', 'Check completed.' ); }
						runButton.disabled = false;
					} else {
						// Not on the Overview tab: the fragment has nowhere to go, so reload to reflect fresh values here too.
						window.location.reload();
					}
				} ).catch( function ( error ) {
					runButton.classList.remove( 'is-loading' );
					if ( runStatus ) { runStatus.textContent = error && error.message ? error.message : t( 'failed', 'The check could not be completed.' ); }
					runButton.disabled = false;
				} );
			} );
		}

		/* --- Row expand/collapse (detail row) ----------------------------- */

		function toggleDetail( key, forceOpen ) {
			var row = document.querySelector( 'tr[data-gpshm-check-key="' + key + '"]' );
			var detail = document.getElementById( 'gpshm-detail-' + key );
			var toggle = row ? row.querySelector( '.gpshm-row-toggle' ) : null;

			if ( ! detail ) {
				return null;
			}

			var shouldOpen = undefined !== forceOpen ? forceOpen : detail.hidden;
			detail.hidden = ! shouldOpen;
			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', shouldOpen ? 'true' : 'false' );
			}

			return shouldOpen ? detail : null;
		}

		document.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest ? event.target.closest( '.gpshm-row-toggle' ) : null;
			if ( ! toggle ) {
				return;
			}
			var row = toggle.closest( 'tr[data-gpshm-check-key]' );
			if ( row ) {
				toggleDetail( row.getAttribute( 'data-gpshm-check-key' ) );
			}
		} );

		/* --- Detail-panel actions (rerun / copy / ignore / reopen) -------- */

		document.addEventListener( 'click', function ( event ) {
			var item = event.target.closest ? event.target.closest( '[data-gpshm-menu-action]' ) : null;
			if ( ! item ) {
				return;
			}

			var action = item.getAttribute( 'data-gpshm-menu-action' );
			var key    = item.getAttribute( 'data-gpshm-check-key' );

			if ( 'rerun' === action ) {
				wp.apiFetch( { path: '/' + data.restNamespace + '/report', method: 'POST' } ).then( function () {
					window.location.reload();
				} );
				return;
			}

			if ( 'copy' === action ) {
				var payload = item.getAttribute( 'data-gpshm-payload' ) || '';
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( payload ).then( function () {
						gpshmToast( t( 'copySuccess', 'Technical data copied to clipboard.' ) );
					}, function () {
						gpshmToast( t( 'copyFailed', 'Could not copy to clipboard.' ) );
					} );
				} else {
					gpshmToast( t( 'copyFailed', 'Could not copy to clipboard.' ) );
				}
				return;
			}

			if ( 'ignore' === action ) {
				gpshmModal( {
					title: t( 'ignoreTitle', 'Ignore this check' ),
					message: t( 'ignoreHelp', '' ),
				} ).then( function ( reason ) {
					if ( false === reason ) { return; }
					item.disabled = true;
					wp.apiFetch( { path: '/' + data.restNamespace + '/issues/' + key + '/ignore', method: 'POST', data: { reason: reason } } ).then( function () {
						rememberUndo( key );
						window.location.reload();
					} ).catch( function ( error ) {
						item.disabled = false;
						window.alert( error && error.message ? error.message : t( 'failed', 'Failed.' ) ); // eslint-disable-line no-alert -- REST failures still need a blocking, unmissable notice.
					} );
				} );
				return;
			}

			if ( 'reopen' === action ) {
				item.disabled = true;
				wp.apiFetch( { path: '/' + data.restNamespace + '/issues/' + key + '/reopen', method: 'POST' } ).then( function () {
					window.location.reload();
				} ).catch( function ( error ) {
					item.disabled = false;
					window.alert( error && error.message ? error.message : t( 'failed', 'Failed.' ) ); // eslint-disable-line no-alert -- REST failures still need a blocking, unmissable notice.
				} );
			}
		} );

		/* --- Bulk selection (Problems tab) --------------------------------- */

		var bulkBar    = document.getElementById( 'gpshm-bulk-bar' );
		var issuesTable = document.querySelector( '.gpshm-checks-table' );

		function selectedKeys() {
			if ( ! issuesTable ) { return []; }
			return Array.prototype.slice.call( issuesTable.querySelectorAll( '.gpshm-bulk-checkbox:checked' ) ).map( function ( cb ) {
				return cb.value;
			} );
		}

		function refreshBulkBar() {
			if ( ! bulkBar ) { return; }
			var keys = selectedKeys();
			bulkBar.hidden = 0 === keys.length;
			var countEl = bulkBar.querySelector( '.gpshm-bulk-bar__count' );
			if ( countEl ) {
				countEl.textContent = format( t( 'selectedCount', '%d selected' ), keys.length );
			}
		}

		if ( issuesTable ) {
			issuesTable.addEventListener( 'change', function ( event ) {
				if ( event.target.classList && event.target.classList.contains( 'gpshm-bulk-checkbox' ) ) {
					refreshBulkBar();
				}
			} );
		}

		function runBulk( action ) {
			var keys = selectedKeys();
			if ( ! keys.length ) { return; }

			var confirmMessage = 'ignore' === action ? t( 'bulkConfirmIgnore', 'Ignore all selected checks?' ) : t( 'bulkConfirmReopen', 'Reopen all selected checks?' );
			if ( ! window.confirm( confirmMessage ) ) { return; } // eslint-disable-line no-alert -- plain yes/no confirmation before a multi-row action.

			Promise.all(
				keys.map( function ( key ) {
					return wp.apiFetch( { path: '/' + data.restNamespace + '/issues/' + key + '/' + action, method: 'POST', data: {} } );
				} )
			).then( function () {
				window.location.reload();
			} ).catch( function ( error ) {
				window.alert( error && error.message ? error.message : t( 'failed', 'Failed.' ) ); // eslint-disable-line no-alert
			} );
		}

		if ( bulkBar ) {
			var bulkIgnore = bulkBar.querySelector( '.gpshm-bulk-ignore' );
			var bulkReopen = bulkBar.querySelector( '.gpshm-bulk-reopen' );
			if ( bulkIgnore ) { bulkIgnore.addEventListener( 'click', function () { runBulk( 'ignore' ); } ); }
			if ( bulkReopen ) { bulkReopen.addEventListener( 'click', function () { runBulk( 'reopen' ); } ); }
		}

		/* --- Filters, sort and pagination (Problems tab) ------------------ */

		var searchInput    = document.getElementById( 'gpshm-issues-search' );
		var severitySelect = document.getElementById( 'gpshm-issues-severity' );
		var categorySelect = document.getElementById( 'gpshm-issues-category' );
		var statusSelect   = document.getElementById( 'gpshm-issues-status' );
		var periodSelect   = document.getElementById( 'gpshm-issues-period' );
		var sortSelect     = document.getElementById( 'gpshm-issues-sort' );
		var clearButton    = document.getElementById( 'gpshm-issues-clear' );
		var noResultsEl    = document.getElementById( 'gpshm-issues-no-results' );
		var pagination     = document.querySelector( '.gpshm-pagination' );

		var currentPage = 1;

		function checkRows() {
			if ( ! issuesTable ) { return []; }
			return Array.prototype.slice.call( issuesTable.querySelectorAll( 'tbody tr[data-gpshm-check-key]' ) );
		}

		function detailRowFor( row ) {
			var next = row.nextElementSibling;
			return next && next.classList.contains( 'gpshm-detail-row' ) ? next : null;
		}

		function applyFilters() {
			var rows = checkRows();
			if ( ! rows.length ) { return; }

			var term     = searchInput ? searchInput.value.trim().toLowerCase() : '';
			var severity = severitySelect ? severitySelect.value : '';
			var category = categorySelect ? categorySelect.value : '';
			var status   = statusSelect ? statusSelect.value : '';
			var period   = periodSelect ? parseInt( periodSelect.value, 10 ) : 0;
			var sort     = sortSelect ? sortSelect.value : 'priority';

			var visible = [];

			rows.forEach( function ( row ) {
				var matchesTerm     = ! term || row.textContent.toLowerCase().indexOf( term ) !== -1;
				var matchesSeverity = ! severity || row.getAttribute( 'data-gpshm-status' ) === severity;
				var matchesCategory = ! category || row.getAttribute( 'data-gpshm-group' ) === category;
				var ignoredFlag     = '1' === row.getAttribute( 'data-gpshm-ignored' );
				var matchesStatus   = ! status || ( 'active' === status && ! ignoredFlag ) || ( 'ignored' === status && ignoredFlag );

				var matchesPeriod = true;
				if ( period ) {
					var detectedAt = row.getAttribute( 'data-gpshm-detected-at' );
					if ( detectedAt ) {
						var detectedDate = new Date( detectedAt.replace( ' ', 'T' ) + 'Z' );
						var days = ( Date.now() - detectedDate.getTime() ) / 86400000;
						matchesPeriod = days <= period;
					}
				}

				var show = matchesTerm && matchesSeverity && matchesCategory && matchesStatus && matchesPeriod;
				row.dataset.gpshmVisible = show ? '1' : '0';
				if ( show ) { visible.push( row ); }

				var detail = detailRowFor( row );
				if ( ! show && detail ) {
					detail.hidden = true;
					var toggle = row.querySelector( '.gpshm-row-toggle' );
					if ( toggle ) { toggle.setAttribute( 'aria-expanded', 'false' ); }
				}
			} );

			// Sort the visible rows (and their detail rows travel with them).
			var rank = { critical: 2, warning: 1, ok: 0 };
			visible.sort( function ( a, b ) {
				if ( 'date' === sort ) {
					var da = a.getAttribute( 'data-gpshm-detected-at' ) || '';
					var db = b.getAttribute( 'data-gpshm-detected-at' ) || '';
					return db.localeCompare( da );
				}
				var ra = rank[ a.getAttribute( 'data-gpshm-status' ) ] || 0;
				var rb = rank[ b.getAttribute( 'data-gpshm-status' ) ] || 0;
				return rb - ra;
			} );

			var tbody = issuesTable.querySelector( 'tbody' );
			visible.forEach( function ( row ) {
				var detail = detailRowFor( row );
				tbody.appendChild( row );
				if ( detail ) { tbody.appendChild( detail ); }
			} );

			if ( noResultsEl ) {
				noResultsEl.hidden = rows.length === 0 || visible.length > 0;
			}

			currentPage = 1;
			applyPagination( visible );
		}

		function applyPagination( visibleRows ) {
			if ( ! pagination ) {
				checkRows().forEach( function ( row ) {
					row.style.display = '1' === row.dataset.gpshmVisible ? '' : 'none';
				} );
				return;
			}

			var perPage = parseInt( pagination.getAttribute( 'data-gpshm-per-page' ), 10 ) || 10;
			var totalPages = Math.max( 1, Math.ceil( visibleRows.length / perPage ) );
			currentPage = Math.min( currentPage, totalPages );

			var start = ( currentPage - 1 ) * perPage;
			var end   = start + perPage;

			checkRows().forEach( function ( row ) {
				var visible = '1' === row.dataset.gpshmVisible;
				var index   = visibleRows.indexOf( row );
				var onPage  = visible && index >= start && index < end;
				row.style.display = onPage ? '' : 'none';

				var detail = detailRowFor( row );
				if ( detail && ! onPage ) {
					detail.style.display = 'none';
				} else if ( detail ) {
					detail.style.display = '';
				}
			} );

			var statusEl = pagination.querySelector( '.gpshm-pagination__status' );
			if ( statusEl ) {
				statusEl.textContent = visibleRows.length
					? t( 'pageOf', 'Page %1$d of %2$d' ).replace( '%1$d', currentPage ).replace( '%2$d', totalPages )
					: '';
			}

			var prevBtn = pagination.querySelector( '.gpshm-pagination__prev' );
			var nextBtn = pagination.querySelector( '.gpshm-pagination__next' );
			if ( prevBtn ) { prevBtn.disabled = currentPage <= 1; }
			if ( nextBtn ) { nextBtn.disabled = currentPage >= totalPages; }

			pagination.dataset.gpshmVisibleCount = visibleRows.length;
		}

		if ( pagination ) {
			var prevBtn = pagination.querySelector( '.gpshm-pagination__prev' );
			var nextBtn = pagination.querySelector( '.gpshm-pagination__next' );
			if ( prevBtn ) {
				prevBtn.addEventListener( 'click', function () {
					currentPage = Math.max( 1, currentPage - 1 );
					applyPagination( checkRows().filter( function ( row ) { return '1' === row.dataset.gpshmVisible; } ) );
				} );
			}
			if ( nextBtn ) {
				nextBtn.addEventListener( 'click', function () {
					currentPage = currentPage + 1;
					applyPagination( checkRows().filter( function ( row ) { return '1' === row.dataset.gpshmVisible; } ) );
				} );
			}
		}

		[ searchInput, severitySelect, categorySelect, statusSelect, periodSelect, sortSelect ].forEach( function ( el ) {
			if ( ! el ) { return; }
			el.addEventListener( 'input', applyFilters );
			el.addEventListener( 'change', applyFilters );
		} );

		if ( clearButton ) {
			clearButton.addEventListener( 'click', function () {
				if ( searchInput ) { searchInput.value = ''; }
				if ( severitySelect ) { severitySelect.value = ''; }
				if ( categorySelect ) { categorySelect.value = ''; }
				if ( statusSelect ) { statusSelect.value = ''; }
				if ( periodSelect ) { periodSelect.value = ''; }
				if ( sortSelect ) { sortSelect.value = 'priority'; }
				applyFilters();
			} );
		}

		if ( issuesTable ) {
			applyFilters();
		}

		/* --- Endpoint card: copy + real self-test -------------------------- */

		document.querySelectorAll( '.gpshm-endpoint-copy' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var url = button.getAttribute( 'data-gpshm-url' );
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( url ).then( function () {
						gpshmToast( t( 'copySuccess', 'Copied.' ) );
					} );
				}
			} );
		} );

		document.querySelectorAll( '.gpshm-endpoint-test' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var url = button.getAttribute( 'data-gpshm-url' );
				var resultEl = button.parentElement ? button.parentElement.querySelector( '.gpshm-endpoint__result' ) : null;
				button.disabled = true;
				if ( resultEl ) { resultEl.textContent = t( 'testing', 'Testing...' ); }

				fetch( url, { credentials: 'omit' } ).then( function ( response ) {
					button.disabled = false;
					if ( resultEl ) {
						resultEl.textContent = response.ok ? t( 'endpointOk', 'Reachable.' ) : t( 'endpointFailed', 'Could not reach the endpoint.' );
					}
				} ).catch( function () {
					button.disabled = false;
					if ( resultEl ) { resultEl.textContent = t( 'endpointFailed', 'Could not reach the endpoint.' ); }
				} );
			} );
		} );

		/* --- Settings: unsaved-changes warning + restore defaults ---------- */

		var settingsForm = document.getElementById( 'gpshm-settings-form' );

		if ( settingsForm ) {
			var dirty = false;

			settingsForm.addEventListener( 'input', function () { dirty = true; } );
			settingsForm.addEventListener( 'change', function () { dirty = true; } );
			settingsForm.addEventListener( 'submit', function () {
				dirty = false;
				var statusEl = document.getElementById( 'gpshm-settings-status' );
				if ( statusEl ) { statusEl.textContent = t( 'savingSettings', 'Saving...' ); }
			} );

			window.addEventListener( 'beforeunload', function ( event ) {
				if ( ! dirty ) { return; }
				event.preventDefault();
				event.returnValue = '';
			} );

			var restoreButton = document.getElementById( 'gpshm-settings-restore' );
			if ( restoreButton && data.settingsDefaults ) {
				restoreButton.addEventListener( 'click', function () {
					Object.keys( data.settingsDefaults ).forEach( function ( key ) {
						var field = settingsForm.querySelector( '[id="' + key + '"]' );
						if ( ! field ) { return; }
						var value = data.settingsDefaults[ key ];
						if ( 'checkbox' === field.type ) {
							field.checked = !! value;
						} else {
							field.value = value;
						}
					} );
					dirty = true;
					var statusEl = document.getElementById( 'gpshm-settings-status' );
					if ( statusEl ) { statusEl.textContent = t( 'unsavedChanges', 'You have unsaved changes.' ); }
				} );
			}
		}
	} );
} )( window.wp, window.gpshmData || {} );
