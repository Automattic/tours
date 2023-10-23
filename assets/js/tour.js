/* global , document, window, gp_tour */
/* eslint camelcase: "off" */

jQuery( document ).ready(
	function() {
		jQuery( document ).on( 'click', '.pulse', function( event) {
			// Only fires the tour if the user clicks on the left side of the bubble element
			if ( event.clientX > jQuery(this).offset().left + 15 ) {
				return;
			}
			event.preventDefault();
			var driver = window.driver.js.driver;
			var wrapper = jQuery( this ).closest( '.pulse-wrapper' );
			var tourName = wrapper.data( 'tourname' );
			var tourTitle = window.tour[ tourName ][ 0 ].title;
			var tourSteps = jQuery.map( window.tour[ tourName ], function( item ) {
				if ( typeof item.title === 'undefined' ) {
					return {
						element: item.selector,
						popover: {
							title: tourTitle,
							description: item.html,
						},
					};
				}
			} );

			var driverObj = driver( {
				showProgress: true,
				steps: tourSteps,
			} );
			driverObj.drive();
		} );

		function addPulse( field, item, tourName, index ) {
			let pulseClass = "pulse tour-" + tourName;
			jQuery( field ).addClass( pulseClass );
			var div = jQuery( '<div class="pulse-wrapper">' );
			div.data( 'tourname', tourName ).data( 'tourindex', index ).data( 'popover-content', item.html );
			if ( ! field.closest('.pulse-wrapper').length ) {
				field.wrap( div );
			}
			if ( typeof item.css !== 'undefined' ) {
				cssString = cssObjectToString( item.css );
				jQuery( item.selector ).closest( '.pulse-wrapper' ).css( 'cssText', cssString );
			}
		}

		// Convert the CSS object to a CSS string
		function cssObjectToString( cssObject ) {
			return jQuery.map( cssObject, function( value, property ) {
				return property + ': ' + value;
			} ).join( '; ' );
		}

		window.tour = wp_tour;
		window.loadTour = function() {
			var color1 = '';
			var color2 = '';
			var styleElement = document.createElement( 'style' );
			var n;
			var style;

			document.head.appendChild( styleElement );
			style = styleElement.sheet;

			for ( n in window.tour ) {
				color1 = window.tour[ n ][ 0 ].color;
				color2 = window.tour[ n ][ 0 ].color + 'a0';

				style.insertRule( '@keyframes animation-' + n + ' {' +
					'0% {' +
					'box-shadow: 0 0 0 0 ' + color2 + ';' +
					'}' +
					'70% {' +
					'box-shadow: 0 0 0 10px ' + color1 + '00' + ';' +
					'}' +
					'100% {' +
					'box-shadow: 0 0 0 0 ' + color1 + '00' + ';' +
					'}' +
					'}',
					style.cssRules.length );

				addPulse( jQuery( window.tour[ n ][ 1 ].selector ), window.tour[ n ][ 1 ], n, 1 );
			}
		};
		window.loadTour();
	}
);
