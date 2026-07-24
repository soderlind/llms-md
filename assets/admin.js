( function () {
	'use strict';

	var form = document.getElementById( 'llms-md-regen-form' );
	if ( form ) {
		form.addEventListener( 'submit', function () {
			var wrap = document.getElementById( 'llms-md-progress-wrap' );
			if ( wrap ) {
				wrap.style.display = 'block';
			}

			var submit = form.querySelector( '[type=submit]' );
			if ( submit ) {
				submit.disabled = true;
			}
		} );
	}

	var closeBtn = document.getElementById( 'llms-md-preview-close' );
	var panel = document.getElementById( 'llms-md-preview-panel' );
	var jsonNode = document.getElementById( 'llms-md-preview-json' );

	if ( jsonNode && typeof window.aisctxPreviewData === 'string' ) {
		var pretty = window.aisctxPreviewData;

		try {
			pretty = JSON.stringify( JSON.parse( window.aisctxPreviewData ), null, 2 );
		} catch ( e ) {
			// Keep raw content if JSON parsing fails unexpectedly.
		}

		jsonNode.innerHTML = highlightJson( pretty );
	}

	if ( closeBtn && panel ) {
		closeBtn.addEventListener( 'click', function () {
			panel.style.display = 'none';
		} );

		closeBtn.addEventListener( 'mouseenter', function () {
			closeBtn.style.color = '#d63638';
		} );

		closeBtn.addEventListener( 'mouseleave', function () {
			closeBtn.style.color = '#50575e';
		} );
	}

	function highlightJson( json ) {
		var escaped = json
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );

		return escaped.replace(
			/("(?:\\u[0-9a-fA-F]{4}|\\[^u]|[^\\"])*"\s*:?)|(\btrue\b|\bfalse\b|\bnull\b)|(-?\d+(?:\.\d+)?(?:[eE][+\-]?\d+)?)/g,
			function ( match, stringToken, literalToken, numberToken ) {
				if ( stringToken ) {
					if ( /:$/.test( stringToken ) ) {
						return '<span class="aisctx-json-key">' + stringToken + '</span>';
					}

					return '<span class="aisctx-json-string">' + stringToken + '</span>';
				}

				if ( literalToken ) {
					if ( literalToken === 'null' ) {
						return '<span class="aisctx-json-null">' + literalToken + '</span>';
					}

					return '<span class="aisctx-json-bool">' + literalToken + '</span>';
				}

				if ( numberToken ) {
					return '<span class="aisctx-json-number">' + numberToken + '</span>';
				}

				return match;
			}
		);
	}
}() );
