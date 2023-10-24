/* global , document, window, wp_tour */
/* eslint camelcase: "off" */

document.addEventListener('DOMContentLoaded', function() {
	document.addEventListener('click', function( event ) {
		if ( ! event.target.matches( '.pulse' ) ) {
			return;
		}
		event.preventDefault();
		const driver = window.driver.js.driver;
		const tourName = event.target.dataset.tourName;
		const n = event.target.dataset.n;
		var startStep = 0;
		if ( typeof wp_tour_settings.progress[tourName] !== 'undefined' ) {
			startStep = wp_tour_settings.progress[tourName] + 1;
		}

		var tourSteps = window.tour[ tourName ].slice(1);
		if ( ! tourSteps.length ) {
			return;
		}
		tourSteps[startStep].element = event.target.closest('.pulse-wrapper');
		var driverObj = driver( {
			showProgress: true,
			steps: tourSteps,
			onHighlighted: function( element, step, options )  {
				var xhr = new XMLHttpRequest();
				xhr.open('POST', wp_tour_settings.rest_url + 'tour/v1/save-progress');
				xhr.setRequestHeader('Content-Type', 'application/json');
				xhr.setRequestHeader('X-WP-Nonce', wp_tour_settings.nonce);
				xhr.send(JSON.stringify({
					tour: tourName,
					step: options.state.activeIndex - 1
				}));
			},
			onDestroyStarted: function( element, step, options ) {
				if ( driverObj.hasNextStep() ) {
					addPulse( tourName, n, options.state.activeIndex + 1 );
				} else {
					var xhr = new XMLHttpRequest();
					xhr.open('POST', wp_tour_settings.rest_url + 'tour/v1/save-progress');
					xhr.setRequestHeader('Content-Type', 'application/json');
					xhr.setRequestHeader('X-WP-Nonce', wp_tour_settings.nonce);
					xhr.send(JSON.stringify({
						tour: tourName,
						step: options.state.activeIndex
					}));
				}
				driverObj.destroy();
			}
		} );
		driverObj.drive( startStep );
		const pulse = tourSteps[startStep].element.querySelector('.pulse');
		pulse.parentNode.removeChild( pulse );
	} );

	function addPulse(tourName,n, startStep) {
		let fields;
		if ( window.tour[tourName].length <= startStep ) {
			return;
		}
		const selector = window.tour[tourName][startStep].element;
		if ( typeof selector === 'string' ) {
			fields = document.querySelectorAll(selector);
		} else {
			fields = [ selector ];
		}
		for (let i = 0; i < fields.length; i++) {
			let field = fields[i];
			let wrapper = field.closest('.pulse-wrapper');
			if (!wrapper) {
				if ( field.hasChildNodes() ) {
					wrapper = field;
				} else {
					wrapper = document.createElement('div');
					field.parentNode.insertBefore(wrapper, field);
					wrapper.appendChild(field);
				}
				wrapper.classList.add("pulse-wrapper");
			}
			if ( ! wrapper.querySelector('.pulse') ) {
				const pulse = document.createElement('div');
				pulse.classList.add("pulse");
				pulse.classList.add("tour-" + n);
				pulse.dataset.tourName = tourName;
				pulse.dataset.n = n;
				if ( field.hasChildNodes() ) {
					wrapper.insertBefore(pulse,wrapper.firstChild);
				} else {
					wrapper.insertBefore(pulse,field);
				}
			}
		}
	}

	window.tour = wp_tour;
	window.loadTour = function() {
		var color1 = '';
		var color2 = '';
		var styleElement = document.createElement( 'style' );
		let n = 0;
		var style;
		var startStep;

		document.head.appendChild( styleElement );
		style = styleElement.sheet;

		for ( const tourName in window.tour ) {
			n += 1;
			color1 = window.tour[ tourName ][ 0 ].color;
			color2 = window.tour[ tourName ][ 0 ].color + 'a0';
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

			style.insertRule( '.tour-' + n + '{' +
				'box-shadow: 0 0 0 ' + color2 + ';' +
				'background: ' + color1 + '80' + ';' +
				'-webkit-animation: animation-' + n + ' 2s infinite;' +
				'animation: animation-' + n + ' 2s infinite; }',
				style.cssRules.length );
			startStep = 0;
			if ( typeof wp_tour_settings.progress[tourName] !== 'undefined' ) {
				startStep = wp_tour_settings.progress[tourName] + 1;
			}
			addPulse( tourName, n, startStep + 1);
		}
	};
	window.loadTour();
}
);
