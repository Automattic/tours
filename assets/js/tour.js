/* global tour_plugin, XMLHttpRequest */
/* eslint camelcase: "off" */

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
					'<br><button class="dismiss-tour">Do not show this again';
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
			if ( ! wrapper.querySelector( '.pulse' ) ) {
				const pulse = document.createElement( 'div' );
				pulse.classList.add( 'pulse' );
				pulse.classList.add(
					getAnimationClassName(
						ensureContrast(
							tour_plugin.tours[ tourId ][ 0 ].color,
							getComputedBackgroundColor( wrapper ),
							3
						)
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

	function rgbToHex( rgb ) {
		return (
			'#' +
			rgb
				.map( ( value ) => value.toString( 16 ).padStart( 2, '0' ) )
				.join( '' )
		);
	}

	function getContrastRatio( color1, color2 ) {
		const luminance1 = calculateLuminance( color1 );
		const luminance2 = calculateLuminance( color2 );
		const lighter = Math.max( luminance1, luminance2 );
		const darker = Math.min( luminance1, luminance2 );
		return ( lighter + 0.05 ) / ( darker + 0.05 );
	}

	function calculateLuminance( color ) {
		const rgb = hexToRgb( color );
		const [ r, g, b ] = rgb.map( ( value ) => {
			value /= 255;
			return value <= 0.03928
				? value / 12.92
				: Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * r + 0.7152 * g + 0.0722 * b;
	}

	function hexToRgb( hex ) {
		const shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
		hex = hex.replace(
			shorthandRegex,
			( _, r, g, b ) => r + r + g + g + b + b
		);
		const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec( hex );
		return result
			? [
					parseInt( result[ 1 ], 16 ),
					parseInt( result[ 2 ], 16 ),
					parseInt( result[ 3 ], 16 ),
			  ]
			: null;
	}

	function rgbToHsl( r, g, b ) {
		r /= 255;
		g /= 255;
		b /= 255;
		const max = Math.max( r, g, b ),
			min = Math.min( r, g, b );
		let h, s;
		const l = ( max + min ) / 2;

		if ( max === min ) {
			h = s = 0; // achromatic
		} else {
			const d = max - min;
			s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );
			switch ( max ) {
				case r:
					h = ( g - b ) / d + ( g < b ? 6 : 0 );
					break;
				case g:
					h = ( b - r ) / d + 2;
					break;
				case b:
					h = ( r - g ) / d + 4;
					break;
			}
			h /= 6;
		}

		return [ h, s, l ];
	}

	function hslToRgb( h, s, l ) {
		let r, g, b;

		if ( s === 0 ) {
			r = g = b = l; // achromatic
		} else {
			const hue2rgb = ( p, q, t ) => {
				if ( t < 0 ) t += 1;
				if ( t > 1 ) t -= 1;
				if ( t < 1 / 6 ) return p + ( q - p ) * 6 * t;
				if ( t < 1 / 2 ) return q;
				if ( t < 2 / 3 ) return p + ( q - p ) * ( 2 / 3 - t ) * 6;
				return p;
			};

			const q = l < 0.5 ? l * ( 1 + s ) : l + s - l * s;
			const p = 2 * l - q;
			r = hue2rgb( p, q, h + 1 / 3 );
			g = hue2rgb( p, q, h );
			b = hue2rgb( p, q, h - 1 / 3 );
		}

		return [
			Math.round( r * 255 ),
			Math.round( g * 255 ),
			Math.round( b * 255 ),
		];
	}
	function ensureContrast( foregroundColor, backgroundColor, threshold = 3 ) {
		let contrastRatio = getContrastRatio(
			foregroundColor,
			backgroundColor
		);

		if ( contrastRatio >= threshold ) {
			return foregroundColor;
		}

		const hslValues = rgbToHsl( ...hexToRgb( foregroundColor ) );

		const backgroundLuminance = calculateLuminance( backgroundColor );

		// Determine whether to increase or decrease the lightness based on background luminance
		const lightnessChange = backgroundLuminance < 0.5 ? 0.01 : -0.01;
		let newLightness = hslValues[ 2 ];

		// Adjust the lightness until the desired contrast ratio is met
		while ( contrastRatio < threshold ) {
			newLightness += lightnessChange;

			// Limit the lightness between 0 and 1
			newLightness = Math.max( 0, Math.min( 1, newLightness ) );

			foregroundColor = rgbToHex(
				hslToRgb( hslValues[ 0 ], hslValues[ 1 ], newLightness )
			);

			contrastRatio = getContrastRatio(
				foregroundColor,
				backgroundColor
			);
		}

		return foregroundColor;
	}

	function getComputedBackgroundColor( element ) {
		while ( element ) {
			const color = window.getComputedStyle( element ).backgroundColor;
			if ( 'rgba(0, 0, 0, 0)' === color ) {
				element = element.parentNode;
				continue;
			}
			const rgbValues = color.match( /\d+/g );
			if ( rgbValues.length === 3 ) {
				return rgbToHex(
					rgbValues.map( ( value ) => parseInt( value, 10 ) )
				);
			}

			return null;
		}

		return null;
	}

	const getAnimationClassName = function ( color ) {
		const styleElement =
			document.getElementById( 'tour-styles' ) ||
			document.createElement( 'style' );
		let style = null;
		const className = 'tour-color-' + color.substr( 1 );
		const color1 = color;
		const color2 = color + 'a0';

		if ( ! styleElement.id ) {
			styleElement.id = 'tour-styles';
			document.head.appendChild( styleElement );
		}
		style = styleElement.sheet;

		style.insertRule(
			'@keyframes animation-' +
				className +
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
			'.' +
				className +
				'{' +
				'box-shadow: 0 0 0 ' +
				color2 +
				';' +
				'background: ' +
				color1 +
				'ff' +
				';' +
				'-webkit-animation: animation-' +
				className +
				' 2s infinite;' +
				'animation: animation-' +
				className +
				' 2s infinite; }',
			style.cssRules.length
		);

		return className;
	};

	const loadTour = function () {
		const styleElement =
			document.getElementById( 'tour-styles' ) ||
			document.createElement( 'style' );
		let style = null;
		let startStep;

		if ( ! styleElement.id ) {
			styleElement.id = 'tour-styles';
			document.head.appendChild( styleElement );
			style = styleElement.sheet;
		}

		for ( const tourId in tour_plugin.tours ) {
			if ( style ) {
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
