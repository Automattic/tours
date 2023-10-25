if ( typeof wp_tour !== 'undefined' ) {
	window.tour = wp_tour;
}
var tourSelectorActive = false;
var tourId;
var dialogOpen = false;

var setTourCookie = function( tourId ) {
	document.cookie = 'tour=' + escape( tourId ) + ';path=/';
	enableTourCreation();
}

var deleteTourCookie = function() {
	document.cookie = 'tour=;path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
	enableTourCreation();
}

document.addEventListener('click', function( event ) {
	if ( ! event.target.dataset.addMoreStepsText || ! event.target.dataset.tourId ) {
		return;
	}

	event.preventDefault();
	if ( event.target.textContent === event.target.dataset.finishTourCreationText ) {
		event.target.textContent = event.target.dataset.addMoreStepsText;
		deleteTourCookie();
		return;
	}
	setTourCookie( event.target.dataset.tourId );
	event.target.textContent = event.target.dataset.finishTourCreationText;
} );

function enableTourCreation() {
	tourId = document.cookie.indexOf('tour=') > -1 ? unescape(document.cookie.split('tour=')[1].split(';')[0]) : '';
	if ( tourId && document.getElementById('tour-launcher') ) {
	console.log( tour, tourId );
		if ( typeof tour !== 'undefined' && typeof tour.tours[ tourId ] !== 'undefined' ) {
			document.getElementById('tour-launcher').style.display = 'block';
			document.getElementById('tour-title').textContent = tour.tours[ tourId ][0].title;
			for ( var i = 1; i < tour.tours[ tourId ].length; i++ ) {
				el = document.querySelector(tour.tours[ tourId ][i].selector);
				if ( el ) {
					el.style.outline = '1px dashed ' + tour.tours[ tourId ][0].color;
				} else {
					reportMissingSelector( tour.tours[ tourId ][0].title, i, tour.tours[ tourId ][i].selector );
				}
			}
		}
	} else if ( document.getElementById('tour-launcher') ) {
		document.getElementById('tour-launcher').style.display = 'none';
	}

}
enableTourCreation();

function reportMissingSelector( tourTitle, step, selector ) {
	var xhr = new XMLHttpRequest();
	xhr.open('POST', tour.rest_url + 'tour/v1/report-missing');
	xhr.setRequestHeader('Content-Type', 'application/json');
	xhr.setRequestHeader('X-WP-Nonce', tour.nonce);
	xhr.send(JSON.stringify({
		tour: tourId,
		selector: selector,
		step: step,
		url: location.href,
	}));
}

function toggleTourSelector( event ) {
	event.stopPropagation();
	tourSelectorActive = ! tourSelectorActive;

	document.getElementById('tour-launcher').style.color = tourSelectorActive ? tour.tours[ tourId ][0].color : '';
	return false;
}

document.getElementById('tour-launcher').addEventListener('click', toggleTourSelector);
var clearHighlight = function( event ) {
	for ( var i = 1; i < tour.tours[ tourId ].length; i++ ) {
		if ( event.target.matches(tour.tours[ tourId ][i].selector) ) {
			document.querySelector(tour.tours[ tourId ][i].selector).style.outline = '1px dashed ' + tour.tours[ tourId ][0].color;
			return;
		}
	}
	event.target.style.outline = '';
	event.target.style.cursor = '';
}

var tourStepHighlighter = function(event) {
	var target = event.target;
	if ( ! tourSelectorActive || target.closest('#tour-launcher') ) {
		clearHighlight( event );
		return;
	}
	// Highlight the element on hover
	target.style.outline = '2px solid ' + tour.tours[ tourId ][0].color;
	target.style.cursor = 'pointer';
};

var filter_selectors = function( c ) {
	return c.indexOf( 'wp-' ) > -1
	|| c.indexOf( 'page' ) > -1
	|| c.indexOf( 'post' ) > -1
	|| c.indexOf( 'column' ) > -1;
}

var tourStepSelector = function(event) {
	if ( ! tourSelectorActive ) {
		return;
	}

	function getSelectors(elem) {
		var selectors = [];

		while ( elem.parentElement ) {
			var currentElement = elem.parentElement;
			var tagName = elem.tagName.toLowerCase();
			var classes = [];

			if ( elem.id ) {
				selectors.push( tagName + '#' + elem.id );
				break;
			}

			elem.classList.forEach( function( c ) {
				if ( ! filter_selectors( c ) ) {
					return;
				}
				classes.push( c );
			})

			if ( classes.length ) {
				selectors.push( tagName + '.' + classes.join( '.') );
			} else {
				var index = Array.prototype.indexOf.call(currentElement.children, elem) + 1;
				selectors.push(tagName + ':nth-child(' + index + ')');
			}

			elem = currentElement;
		}

		return selectors.reverse();
	}


	event.preventDefault();

	var selectors = getSelectors(event.target);
	console.log( selectors );

	dialogOpen = true;
	var stepName = prompt( 'Enter description for step ' + tour.tours[ tourId ].length );
	if ( ! stepName ) {
		event.target.style.outline = '';
		return false;
	}

	tour.tours[ tourId ].push({
		element: selectors.join(' '),
		popover: {
			title: tour.tours[ tourId ][0].title,
			description: stepName,
		},
	});

	event.target.style.outline = '1px dashed ' + tour.tours[ tourId ][0].color;

	if ( tour.tours[ tourId ].length > 1 ) {
		console.log( tour.tours[ tourId ] );

		// store the tours on the server
		var xhr = new XMLHttpRequest();
		xhr.open('POST', tour.rest_url + 'tour/v1/save');
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.setRequestHeader('X-WP-Nonce', tour.nonce);
		xhr.send(JSON.stringify({
			tour: tourId,
			steps: JSON.stringify(tour.tours[ tourId ]),
		}));

		document.getElementById('tour-title').textContent = 'Saved!';

		window.loadTour();
		setTimeout(function() {
			document.getElementById('tour-title').textContent = tour.tours[ tourId ][0].title;
		}, 1000);
		return false;
	}

	return false;
};

document.addEventListener('keyup', function(event) {
	if ( event.keyCode === 27 ) {
		if ( dialogOpen ) {
			dialogOpen = false;
			return;
		}
		tourSelectorActive = false;
		document.getElementById('tour-launcher').style.color = '';
	}
});
document.addEventListener('mouseover', tourStepHighlighter);
document.addEventListener('mouseout', clearHighlight);
document.addEventListener('click', tourStepSelector);
