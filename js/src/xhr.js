import { eraseCookie } from './cookies';

/**
 * Decode function for HTML entities
 *
 * @param {string} input
 * @returns 
 */
const htmlDecode = input => {
	const doc = new DOMParser().parseFromString(input, "text/html");
	return doc.documentElement.textContent;
}

/**
 * Test if valid JSON
 *
 * @param {string} input
 * @returns 
 */
const isJsonString = str => {
	try {
			JSON.parse(str);
	} catch (e) {
			return false;
	}
	return true;
};

/**
 * Shows overlay element with a message from the ajax response
 *
 * @param {string} message
 */
export const showMessage = (message, delay = 3000) => {
	let overlayDiv = document.querySelector('.nine3v__overlay');
	if (overlayDiv) {
		overlayDiv.innerHTML = message;
	} else {
		overlayDiv = document.createElement('div');
		const overlayContent = document.createTextNode(message);
		overlayDiv.setAttribute('class', 'nine3v__overlay');
		overlayDiv.appendChild(overlayContent);
		document.body.appendChild(overlayDiv);
	}

	// Destroy element after 3 seconds.
	setTimeout(() => {
		overlayDiv.remove();
	}, delay);
};

/**
 * Destroys message element
 *
 * @param {string} message
 */
export const destroyMessage = () => {
	const overlayDiv = document.querySelector('.nine3v__overlay');
	if (overlayDiv) {
		overlayDiv.remove();
	}
};

/**
 * Calls PHP script to do stuff...
 *
 * @param {string} action name of the action to call
 * @param {int} pageId page ID to post to php script
 * @param {object} data object of data to send to PHP
 */
 export const triggerXHR = (action, pageId, data = {}, delay = 3000) => {
	const xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function () {
		if (this.readyState == 4) {
			// Test if json.
			let response;
			if ( isJsonString(xhttp.response) ) {
				response = JSON.parse(xhttp.response);
			} else {
				console.log(xhttp.response);
				return;
			}

			delay = response.delay ?? delay;
			document.body.classList.remove('nine3v--xhr');
			
			if (response.offset && response.total) {
				showMessage('Adding post ' + response.offset + ' of ' + response.total, delay);
			}

			if (response.message) {
				showMessage(response.message, delay);
			}

			if (!response.success) {
				showMessage('Something went wrong, please check the logs.');
				console.log(response);
			}
			
			switch (action) {
				case 'nine3v-edit':
				case 'nine3v-add':
					if (response.target) {
						window.location.href = htmlDecode(response.target);
					}
					break;
				case 'nine3v-view':
					if (response.rows) {
						const listTable = document.querySelector('#the-list');
						listTable.innerHTML = response.rows;
						const event = new CustomEvent('XHRTrigger', { "detail": listTable });
						document.dispatchEvent(event);
					}
					if ( response.column_headers ) {
						const listHeaders = document.querySelectorAll('thead tr, tfoot tr');
						listHeaders.forEach(element => {
							element.innerHTML = response.column_headers;
						});
					}
					if ( response.pagination.bottom ) {
						const paginationBottom = document.querySelector('.tablenav.top .tablenav-pages');
						paginationBottom.innerHTML = response.pagination.bottom;
					}
					if ( response.pagination.top ) {
						const paginationTop = document.querySelector('.tablenav.bottom .tablenav-pages');
						paginationTop.innerHTML = response.pagination.top;
					}
					break;
				case 'nine3v-clone':
					if (response.offset) {
						triggerXHR(action, pageId, response, (1000*60*5));
					} else if (response.complete) {
						window.location.reload();
					}
					break;
				case 'nine3v-move':
				case 'nine3v-clone-page':
				case 'nine3v-publish':
				case 'nine3v-replace':
					window.location.reload();
					break;
				case 'nine3v-delete':
				case 'nine3v-clear':
					eraseCookie('_nine3v_pageId');
					eraseCookie('_nine3v_pageTitle');
					window.location.reload();
					break;
			}
		}
	};

	xhttp.open('GET', `${nine3v.ajaxurl}?action=${action}&nonce=${nine3v.nonce}&pageId=${pageId}&data=${JSON.stringify(data)}`);
	xhttp.send();
};