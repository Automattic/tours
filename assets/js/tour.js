/* global tour_plugin, XMLHttpRequest */
/* eslint camelcase: "off" */

function rgbToHex( color ) {
	const rgbValues = color.match(
		/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*\d+)?\)$/i
	);

	if ( rgbValues && rgbValues.length === 4 ) {
		const hexValues = rgbValues
			.slice( 1 )
			.map( ( val ) =>
				parseInt( val, 10 ).toString( 16 ).padStart( 2, '0' )
			);
		return '#' + hexValues.join( '' );
	}

	return color;
}
function getComputedBackgroundColor( element ) {
	while ( element ) {
		const color = window.getComputedStyle( element ).backgroundColor;
		if ( 'rgba( 0, 0, 0, 0 )' === color ) {
			element = element.parentNode;
			continue;
		}
		return rgbToHex( color );
	}

	return null;
}

function getRelativeLuminance( color ) {
	const rgb = color.substring( 1 ); // Remove the leading #
	const r = parseInt( rgb.substring( 0, 2 ), 16 ) / 255;
	const g = parseInt( rgb.substring( 2, 4 ), 16 ) / 255;
	const b = parseInt( rgb.substring( 4, 6 ), 16 ) / 255;
	console.log( { color, r, g, b } );
	const sRGB = [ r, g, b ].map( ( c ) => {
		return c <= 0.03928
			? c / 12.92
			: Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
	} );

	return 0.2126 * sRGB[ 0 ] + 0.7152 * sRGB[ 1 ] + 0.0722 * sRGB[ 2 ];
}

function getContrastRatio( color1, color2 ) {
	const luminance1 = getRelativeLuminance( color1 );
	const luminance2 = getRelativeLuminance( color2 );
	return (
		( Math.max( luminance1, luminance2 ) + 0.05 ) /
		( Math.min( luminance1, luminance2 ) + 0.05 )
	);
}
function getContrastingColor( background, contrast, luminance ) {
	// Convert luminance to a valid RGB value between 0 and 255
	const luminanceValue = Math.round( luminance * 255 );

	// Attempt to increase or decrease the luminance to find a contrasting color
	for ( let delta = 1; delta <= 255; delta++ ) {
		const adjustedLuminance1 = luminanceValue + delta;
		const adjustedLuminance2 = luminanceValue - delta;

		const color1 = `#${ adjustedLuminance1
			.toString( 16 )
			.padStart( 2, '0' )
			.repeat( 3 ) }`;
		const color2 = `#${ adjustedLuminance2
			.toString( 16 )
			.padStart( 2, '0' )
			.repeat( 3 ) }`;

		const contrast1 = getContrastRatio( background, color1 );
		const contrast2 = getContrastRatio( background, color2 );

		if (
			Math.abs( contrast1 - contrast ) < Math.abs( contrast2 - contrast )
		) {
			return color1;
		}
		return color2;
	}
}

function getPulseColorOverride( background, color ) {
	if ( getContrastRatio( background, color ) > 13 ) {
		return color;
	}

	const luminance = getRelativeLuminance( background );
	const blackLuminance = getRelativeLuminance( '#000000' ); // black
	const blackContrast =
		( Math.max( luminance, blackLuminance ) + 0.05 ) /
		( Math.min( luminance, blackLuminance ) + 0.05 );

	const whiteLuminance = getRelativeLuminance( '#FFFFFF' ); // white
	const whiteContrast =
		( Math.max( luminance, whiteLuminance ) + 0.05 ) /
		( Math.min( luminance, whiteLuminance ) + 0.05 );

	if ( blackContrast >= 4.5 ) {
		return 'dark-pulse';
	}

	if ( whiteContrast >= 4.5 ) {
		return 'bright-pulse';
	}

	return null;
}
document.addEventListener( 'DOMContentLoaded', function () {
	let dismissTour;
	document.addEventListener( 'click', function ( event ) {
		if ( ! event.target.matches( '.pulse' ) ) {
			return;
		}
		event.preventDefault();
		const driver = window.driver.js.driver;
		const tourId = event.target.dataset.tourId;
		let startStep = 0;
		if ( typeof tour_plugin.progress[ tourId ] !== 'undefined' ) {
			startStep = tour_plugin.progress[ tourId ] - 1;
		}
		if ( startStep <= 0 ) {
			startStep = 0;
		}
		const tourSteps = tour_plugin.tours[ tourId ].slice( 1 );
		if ( ! tourSteps.length ) {
			return;
		}

		dismissTour = function () {
			const xhr = new XMLHttpRequest();
			xhr.open( 'POST', tour_plugin.rest_url + 'tour/v1/save-progress' );
			xhr.setRequestHeader( 'Content-Type', 'application/json' );
			xhr.setRequestHeader( 'X-WP-Nonce', tour_plugin.nonce );
			xhr.send(
				JSON.stringify( {
					tour: tourId,
					step: tour_plugin.tours[ tourId ].length,
				} )
			);

			driverObj.destroy();
		};
		tourSteps[ startStep ].element =
			event.target.closest( '.pulse-wrapper' );
		const driverObj = driver( {
			showProgress: true,
			steps: tourSteps,
			onHighlightStarted( element, step ) {
				step.popover.description +=
					'<br><button class="dismiss-tour">Dismiss the tour';
			},
			onHighlighted( element, step, options ) {
				tour_plugin.progress[ tourId ] = options.state.activeIndex + 1;
				const xhr = new XMLHttpRequest();
				xhr.open(
					'POST',
					tour_plugin.rest_url + 'tour/v1/save-progress'
				);
				xhr.setRequestHeader( 'Content-Type', 'application/json' );
				xhr.setRequestHeader( 'X-WP-Nonce', tour_plugin.nonce );
				xhr.send(
					JSON.stringify( {
						tour: tourId,
						step: options.state.activeIndex + 1,
					} )
				);
			},
			onDestroyStarted( element, step, options ) {
				if ( driverObj.hasNextStep() ) {
					addPulse( tourId, options.state.activeIndex + 1 );
				} else {
					const xhr = new XMLHttpRequest();
					xhr.open(
						'POST',
						tour_plugin.rest_url + 'tour/v1/save-progress'
					);
					xhr.setRequestHeader( 'Content-Type', 'application/json' );
					xhr.setRequestHeader( 'X-WP-Nonce', tour_plugin.nonce );
					xhr.send(
						JSON.stringify( {
							tour: tourId,
							step: tour_plugin.tours[ tourId ].length,
						} )
					);
				}
				driverObj.destroy();
			},
		} );
		driverObj.drive( startStep );
		const pulse = tourSteps[ startStep ].element.querySelector( '.pulse' );
		pulse.parentNode.removeChild( pulse );
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( ! event.target.matches( '.dismiss-tour' ) ) {
			return;
		}
		event.preventDefault();
		if ( dismissTour ) {
			dismissTour();
		}
	} );

	function addPulse( tourId, startStep ) {
		let fields;
		if ( startStep === 0 ) {
			startStep = 1;
		}
		if ( tour_plugin.tours[ tourId ].length <= startStep ) {
			return;
		}
		const selector = tour_plugin.tours[ tourId ][ startStep ].element;
		if ( typeof selector === 'string' ) {
			try {
				fields = document.querySelectorAll( selector );
			} catch {
				fields = [];
			}
		} else {
			fields = [ selector ];
		}

		for ( let i = 0; i < fields.length; i++ ) {
			const field = fields[ i ];
			let wrapper = field.closest( '.pulse-wrapper' );
			if ( ! wrapper ) {
				if ( field.hasChildNodes() ) {
					wrapper = field;
				} else {
					wrapper = document.createElement( 'div' );
					field.parentNode.insertBefore( wrapper, field );
					wrapper.appendChild( field );
				}
				wrapper.classList.add( 'pulse-wrapper' );
			}
			console.log(
				'getComputedBackgroundColor( wrapper )',
				getComputedBackgroundColor( wrapper )
			);
			if ( ! wrapper.querySelector( '.pulse' ) ) {
				const pulse = document.createElement( 'div' );
				pulse.classList.add( 'pulse' );
				pulse.classList.add(
					getPulseColorOverride(
						getComputedBackgroundColor( wrapper ),
						tour_plugin.tours[ tourId ][ 0 ].color
					)
				);
				pulse.classList.add( 'tour-' + tourId );
				pulse.dataset.tourId = tourId;
				pulse.dataset.tourTitle =
					tour_plugin.tours[ tourId ][ 0 ].title;
				if ( field.hasChildNodes() ) {
					wrapper.insertBefore( pulse, wrapper.firstChild );
				} else {
					wrapper.insertBefore( pulse, field );
				}
			}
		}
	}

	const loadTour = function () {
		const styleElement =
			document.getElementById( 'tour-styles' ) ||
			document.createElement( 'style' );
		let style = null;
		let color1 = '';
		let color2 = '';
		let startStep;

		if ( ! styleElement.id ) {
			styleElement.id = 'tour-styles';
			document.head.appendChild( styleElement );
			style = styleElement.sheet;
		}

		for ( const tourId in tour_plugin.tours ) {
			if ( style ) {
				color1 = tour_plugin.tours[ tourId ][ 0 ].color;
				color2 = tour_plugin.tours[ tourId ][ 0 ].color + 'a0';
				style.insertRule(
					'@keyframes animation-' +
						tourId +
						' {' +
						'0% {' +
						'box-shadow: 0 0 0 0 ' +
						color2 +
						';' +
						'}' +
						'70% {' +
						'box-shadow: 0 0 0 10px ' +
						color1 +
						'00' +
						';' +
						'}' +
						'100% {' +
						'box-shadow: 0 0 0 0 ' +
						color1 +
						'00' +
						';' +
						'}' +
						'}',
					style.cssRules.length
				);

				style.insertRule(
					'.tour-' +
						tourId +
						'{' +
						'box-shadow: 0 0 0 ' +
						color2 +
						';' +
						'background: ' +
						color1 +
						'ff' +
						';' +
						'-webkit-animation: animation-' +
						tourId +
						' 2s infinite;' +
						'animation: animation-' +
						tourId +
						' 2s infinite; }',
					style.cssRules.length
				);

				style.insertRule(
					'@media (prefers-reduced-motion: reduce) {' +
						'.tour-' +
						tourId +
						'{' +
						'box-shadow: none !important;' +
						'}' +
						'}',
					style.cssRules.length
				);
			}
			startStep = 0;
			if ( typeof tour_plugin.progress[ tourId ] !== 'undefined' ) {
				startStep = tour_plugin.progress[ tourId ];
			}
			addPulse( tourId, startStep );
		}
	};
	loadTour();
	filter_available_tours();
	setTimeout( filter_available_tours, 500 );

	function filter_available_tours() {
		const tourListItems = document.querySelectorAll( '.tour-list-item' );
		let foundTour = false;

		for ( let i = 0; i < tourListItems.length; i++ ) {
			let tourId = tourListItems[ i ].dataset.tourId;
			if (
				! tourId &&
				tourListItems[ i ].id.substr( 0, 18 ) === 'wp-admin-bar-tour-'
			) {
				tourId = tourListItems[ i ].id.substr( 18 );
			}

			if ( ! tour_plugin.tours[ tourId ] ) {
				continue;
			}

			const tourIsPresent =
				tour_plugin.tours[ tourId ][ 1 ] &&
				document.querySelector(
					tour_plugin.tours[ tourId ][ 1 ].element
				);
			if ( tourIsPresent ) {
				foundTour = true;
			}
			document
				.querySelectorAll(
					'.tour-list-item[data-tour-id="' +
						tourId +
						'"], #wp-admin-bar-tour-' +
						tourId
				)
				.forEach( function ( element ) {
					element.style.display = tourIsPresent ? 'block' : 'none';
				} );
		}

		if ( document.getElementById( 'wp-admin-bar-tour-list-default' ) ) {
			document
				.getElementById( 'wp-admin-bar-tour-list-default' )
				.childNodes.forEach( function ( element ) {
					if ( element.style.display !== 'none' ) {
						document.getElementById(
							'wp-admin-bar-tour-list'
						).style.display = 'block';
					}
				} );
		}

		if ( foundTour ) {
			loadTour();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		let target = event.target;
		if ( target.matches( '.tour-list-item a' ) ) {
			target = target.parentNode;
		} else if ( ! target.matches( '.tour-list-item' ) ) {
			return true;
		}
		event.preventDefault();

		let tourId = target.dataset.tourId;
		if ( ! tourId && target.id.substr( 0, 18 ) === 'wp-admin-bar-tour-' ) {
			tourId = target.id.substr( 18 );
		}

		if ( ! tour_plugin.tours[ tourId ] ) {
			return false;
		}
		let pulseToClick = document.querySelector( '.pulse.tour-' + tourId );

		if ( pulseToClick ) {
			pulseToClick.click();
		} else {
			const xhr = new XMLHttpRequest();
			xhr.open( 'POST', tour_plugin.rest_url + 'tour/v1/save-progress' );
			xhr.setRequestHeader( 'Content-Type', 'application/json' );
			xhr.setRequestHeader( 'X-WP-Nonce', tour_plugin.nonce );
			xhr.send(
				JSON.stringify( {
					tour: tourId,
					step: -1,
				} )
			);

			delete tour_plugin.progress[ tourId ];
			loadTour();
			pulseToClick = document.querySelector( '.pulse.tour-' + tourId );
			pulseToClick.click();
		}
	} );
} );
