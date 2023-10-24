/* global , document, window, wp_tour */
/* eslint camelcase: "off" */

document.addEventListener('DOMContentLoaded', function() {
	document.addEventListener('click', function( event ) {
		if ( ! event.target.matches( '.pulse' ) ) {
			return;
		}
		const driver = window.driver.js.driver;
		const tourName = event.target.dataset.tourName;
		var tourSteps = window.tour[ tourName ].slice(1);
		tourSteps[0].element = event.target.closest('.pulse-wrapper');
		var driverObj = driver( {
			showProgress: true,
			steps: tourSteps,
		} );
		driverObj.drive();
	} );

	function addPulse(tourName,n) {
		const selector = window.tour[tourName][1].element;
		const fields = document.querySelectorAll(selector);
		for (let i = 0; i < fields.length; i++) {
			let field = fields[i];
			let wrapper = field.closest('.pulse-wrapper');
			if (!wrapper) {
				wrapper = document.createElement('div');
				wrapper.classList.add("pulse-wrapper");
				field.parentNode.insertBefore(wrapper, field);
				wrapper.appendChild(field);
			}
			if ( ! wrapper.querySelector('.pulse') ) {
				const pulse = document.createElement('div');
				pulse.classList.add("pulse");
				pulse.classList.add("tour-" + n);
				pulse.dataset.tourName = tourName;
				wrapper.insertBefore(pulse,field);
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
			addPulse( tourName, n );
		}
	};
	window.loadTour();
}
);
