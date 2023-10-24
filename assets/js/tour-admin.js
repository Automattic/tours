if ( typeof wp_tour !== 'undefined' ) {
	window.tour = wp_tour;
}
var tourSelectorActive = false;
var tourSteps = [];
var dialogOpen = false;

var addMoreSteps = function( tour_title, tour_color ) {
}

var setTourCookie = function( tour_title, tour_color ) {
	document.cookie = 'tour=' + escape( tour_title ) + ';path=/';
	document.cookie = 'tour_color=' + escape( tour_color ) + ';path=/';
	enableTourCreation();
}

var deleteTourCookie = function() {
	document.cookie = 'tour=;path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
	document.cookie = 'tour_color=;path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
	enableTourCreation();
}

document.addEventListener('click', function( event ) {
	if ( ! event.target.dataset.addMoreStepsText || ! event.target.dataset.tourTitle ) {
		return;
	}

	event.preventDefault();
	if ( event.target.textContent === event.target.dataset.finishTourCreationText ) {
		event.target.textContent = event.target.dataset.addMoreStepsText;
		deleteTourCookie();
		return;
	}
	setTourCookie( event.target.dataset.tourTitle, event.target.dataset.tourColor );
	event.target.textContent = event.target.dataset.finishTourCreationText;
} );

function enableTourCreation() {
	var tour_name = document.cookie.indexOf('tour=') > -1 ? unescape(document.cookie.split('tour=')[1].split(';')[0]) : '';
	var tour_color = document.cookie.indexOf('tour_color=') > -1 ? unescape(document.cookie.split('tour_color=')[1].split(';')[0]) : '';
	if ( tour_name && document.querySelector('#tour-launcher') ) {
		document.querySelector('#tour-launcher').style.display = 'block';
		document.querySelector('#tour-title').textContent = tour_name;
		if ( typeof window.tour !== 'undefined' && typeof window.tour[tour_name] !== 'undefined' ) {
			tourSteps = window.tour[tour_name];
			for ( var i = 1; i < tourSteps.length; i++ ) {
				el = document.querySelector(tourSteps[i].selector);
				if ( el ) {
					el.style.outline = '1px dashed ' + tourSteps[0].color;
				} else {
					reportMissingSelector( tour_name, i, tourSteps[i].selector );
				}
			}
		} else {
			tourSteps = [
			{
				title: tour_name,
				color: tour_color,
			}];
		}
	} else if ( document.querySelector('#tour-launcher') ) {
		document.querySelector('#tour-launcher').style.display = 'none';
	}

}
enableTourCreation();

function reportMissingSelector( tour_name, step, selector ) {
	var xhr = new XMLHttpRequest();
	xhr.open('POST', wp_tour_settings.rest_url + 'tour/v1/report-missing');
	xhr.setRequestHeader('Content-Type', 'application/json');
	xhr.setRequestHeader('X-WP-Nonce', wp_tour_settings.nonce);
	xhr.send(JSON.stringify({
		tour: tour_name,
		selector: selector,
		step: step,
		url: location.href,
	}));
}

function toggleTourSelector( event ) {
	event.stopPropagation();
	tourSelectorActive = ! tourSelectorActive;

	document.querySelector('#tour-launcher').style.color = tourSelectorActive ? tourSteps[0].color : '';
	return false;
}

document.querySelector('#tour-launcher').addEventListener('click', toggleTourSelector);
var clearHighlight = function( event ) {
	for ( var i = 1; i < tourSteps.length; i++ ) {
		if ( event.target.matches(tourSteps[i].selector) ) {
			document.querySelector(tourSteps[i].selector).style.outline = '1px dashed ' + tourSteps[0].color;
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
	target.style.outline = '2px solid ' + tourSteps[0].color;
	target.style.cursor = 'pointer';
};


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
				if ( [ 'wp-first-item', 'current' ].includes( c ) ) {
					return;
				}
				classes.push( c );
			})
				console.log( classes );

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
	var stepName = prompt( 'Enter description for step ' + tourSteps.length );
	if ( ! stepName ) {
		event.target.style.outline = '';
		return false;
	}

	tourSteps.push({
		element: selectors.join(' '),
		popover: {
			title: tourSteps[0].title,
			description: stepName,
		},
	});

	event.target.style.outline = '1px dashed ' + tourSteps[0].color;

	if ( tourSteps.length > 1 ) {
		window.tour[tourSteps[0].title] = tourSteps;

		// store the tours on the server
		var xhr = new XMLHttpRequest();
		xhr.open('POST', wp_tour_settings.rest_url + 'tour/v1/save');
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.setRequestHeader('X-WP-Nonce', wp_tour_settings.nonce);
		xhr.send(JSON.stringify({
			tour: JSON.stringify(tourSteps),
		}));

		document.querySelector('#tour-title').textContent = 'Saved!';

		window.loadTour();
		setTimeout(function() {
			document.querySelector('#tour-title').textContent = tourSteps[0].title;
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
		document.querySelector('#tour-launcher').style.color = '';
	}
});
document.addEventListener('mouseover', tourStepHighlighter);
document.addEventListener('mouseout', clearHighlight);
document.addEventListener('click', tourStepSelector);
