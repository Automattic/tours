/* global , document, window, wp_tour */
/* eslint camelcase: "off" */

document.addEventListener('DOMContentLoaded', function() {
	var dismissTour;
	document.addEventListener('click', function( event ) {
		if ( ! event.target.matches( '.pulse' ) ) {
			return;
		}
		event.preventDefault();
		const driver = window.driver.js.driver;
		const tourId = event.target.dataset.tourId;
		var startStep = 0;
		if ( typeof tour.progress[ tourId ] !== 'undefined' ) {
			startStep = tour.progress[ tourId ] + 1;
		}
		var tourSteps = tour.tours[ tourId ].slice(1);
		if ( ! tourSteps.length ) {
			return;
		}

		dismissTour = function() {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', tour.rest_url + 'tour/v1/save-progress');
			xhr.setRequestHeader('Content-Type', 'application/json');
			xhr.setRequestHeader('X-WP-Nonce', tour.nonce);
			xhr.send(JSON.stringify({
				tour: tourId,
				step: tour.tours[ tourId ].length - 1
			}));

			driverObj.destroy();
		}
		tourSteps[startStep].element = event.target.closest('.pulse-wrapper');
		var driverObj = driver( {
			showProgress: true,
			steps: tourSteps,
			onHighlightStarted: function( element, step, options )  {
				step.popover.description += '<br><a href="" class="dismiss-tour">Dismiss the tour';
			},
			onHighlighted: function( element, step, options )  {
				var xhr = new XMLHttpRequest();
				xhr.open('POST', tour.rest_url + 'tour/v1/save-progress');
				xhr.setRequestHeader('Content-Type', 'application/json');
				xhr.setRequestHeader('X-WP-Nonce', tour.nonce);
				xhr.send(JSON.stringify({
					tour: tourId,
					step: options.state.activeIndex - 1
				}));
			},
			onDestroyStarted: function( element, step, options ) {
				if ( driverObj.hasNextStep() ) {
					addPulse( tourId, tourId, options.state.activeIndex + 1 );
				} else {
					var xhr = new XMLHttpRequest();
					xhr.open('POST', tour.rest_url + 'tour/v1/save-progress');
					xhr.setRequestHeader('Content-Type', 'application/json');
					xhr.setRequestHeader('X-WP-Nonce', tour.nonce);
					xhr.send(JSON.stringify({
						tour: tourId,
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

	document.addEventListener('click', function( event ) {
		if ( ! event.target.matches( '.dismiss-tour' ) ) {
			return;
		}
		event.preventDefault();
		if ( dismissTour ) {
			dismissTour();
		}
	} );


	function addPulse( tourId, startStep) {
		let fields;
		if ( tour.tours[ tourId ].length <= startStep ) {
			return;
		}
		const selector = tour.tours[ tourId ][startStep].element;
		console.log( 'selector',selector );
		if ( typeof selector === 'string' ) {
			try {
				fields = document.querySelectorAll(selector);
			} catch {
				fields = [];
			}
		} else {
			fields = [ selector ];
		}
				console.log( fields );

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
				pulse.classList.add("tour-" + tourId);
				pulse.dataset.tourId = tourId;
				pulse.dataset.tourId = tourId;
				if ( field.hasChildNodes() ) {
					wrapper.insertBefore(pulse,wrapper.firstChild);
				} else {
					wrapper.insertBefore(pulse,field);
				}
			}
		}
	}

	const loadTour = function() {
		var color1 = '';
		var color2 = '';
		var styleElement = document.createElement( 'style' );
		var style;
		var startStep;

		document.head.appendChild( styleElement );
		style = styleElement.sheet;

		for ( const tourId in tour.tours ) {
			color1 = tour.tours[ tourId ][ 0 ].color;
			color2 = tour.tours[ tourId ][ 0 ].color + 'a0';
			style.insertRule( '@keyframes animation-' + tourId + ' {' +
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

			style.insertRule( '.tour-' + tourId + '{' +
				'box-shadow: 0 0 0 ' + color2 + ';' +
				'background: ' + color1 + '80' + ';' +
				'-webkit-animation: animation-' + tourId + ' 2s infinite;' +
				'animation: animation-' + tourId + ' 2s infinite; }',
				style.cssRules.length );
			startStep = 0;
			if ( typeof tour.progress[ tourId ] !== 'undefined' ) {
				startStep = tour.progress[ tourId ] + 1;
			}
			addPulse( tourId, startStep + 1);
		}
	};
	loadTour();
}
);
