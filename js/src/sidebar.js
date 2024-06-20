import { eraseCookie, getCookie, setCookie } from './cookies';
import { triggerXHR, showMessage } from './xhr';

(() => {
	const sidebar = document.querySelector('.nine3v-page-sidebar');
	if (!sidebar) {
		return;
	}

	// Setup page vars by checking cookies.
	let currentPage = getCookie('_nine3v_pageId');
	let currentTitle = getCookie('_nine3v_pageTitle');

	// Add body class.
	document.body.classList.add('nine3v');

	/**
	 * Toggle 1 element on and all others off.
	 *
	 * @param {HTMLElement} elementOn element to turn on
	 * @param {NodeList} elementsOff elements to turn off
	 */
	const togglePages = (elementOn, elementsOff) => {
		// All off.
		elementsOff.forEach(element => {
			element.classList.remove('is-active');
		});
		
		elementOn.classList.add('is-active');

		const toolbar = document.querySelector('.nine3v-page-sidebar__toolbar-wrap');
		currentPage = elementOn.getAttribute('data-page-id');
		currentTitle = elementOn.querySelector('.page-title').innerHTML;
		toolbar.classList.add('is-active');
		setCookie('_nine3v_pageId', currentPage);
		setCookie('_nine3v_pageTitle', currentTitle);
	};

	/**
	 * Toggles the page tree
	 *
	 * @param {HTMLElement} pageToggle
	 * @param {HTMLElement} pageItemParent 
	 * @param {string} type whether to add remove or toggle.
	 * @param {bool} recursive do we go up the DOM and toggle all other page items.
	 */
	const togglePageTree = (pageToggle, pageItemParent, type = 'toggle', recursive = false) => {
		if (pageToggle) {
			pageToggle.classList[type]('is-active');
		}

		const nestedElement = pageItemParent.querySelector('.nested');
		if (nestedElement) {
			nestedElement.classList[type]('is-active');
		}
		
		if (recursive) {
			const parentPageId = pageItemParent.querySelector('.page-item').getAttribute('data-parent-id');
			const parentPageItem = document.querySelector(`[data-page-id="${parentPageId}"]`);
			if (parentPageItem) {
				const parentPageItemParent = parentPageItem.parentElement;
				const parentPageItemToggle = parentPageItemParent.querySelector('.page-toggle');
				togglePageTree(parentPageItemToggle, parentPageItemParent, 'add', true);
			}
		}
	};

	/**
	 * Highlights the sidebar in order to select the new target parent element
	 */
	const getNewParentId = () => {
		const sidebarPages = document.querySelector('.nine3v-page-sidebar__pages-wrap');
		if (!sidebarPages) {
			return false;
		}
		
		// Clone sidebar to remove event listeners.
		const clonedPages = sidebarPages.cloneNode(true);
		sidebarPages.parentNode.appendChild(clonedPages);

		const buttonsWrap = clonedPages.querySelector('.nine3v-page-sidebar__buttons');
		const cancelBtn = clonedPages.querySelector('.nine3v-page-sidebar__cancel');
		const acceptBtn = clonedPages.querySelector('.nine3v-page-sidebar__accept');
		const rootBtn = clonedPages.querySelector('.nine3v-page-sidebar__root');
		sidebar.classList.add('highlight');
		clonedPages.classList.add('highlight');
		buttonsWrap.classList.add('show');
		let pageItemId = false;

		// Toggle page tree.
		const toggles = clonedPages.querySelectorAll('.page-toggle');
		toggles.forEach(element => {
			element.addEventListener('click', () => {
				togglePageTree(element, element.parentElement);
			});
		});

		// Toggle page items.
		const pageItems = clonedPages.querySelectorAll('.page-item');
		pageItems.forEach(pageItem => {
			pageItem.addEventListener('click', () => {
				pageItems.forEach(element => {
					element.classList.remove('select-parent');
				});
				pageItem.classList.add('select-parent');
				pageItemId = pageItem.getAttribute('data-page-id');
				acceptBtn.removeAttribute('disabled');
			});
		});

		// Accept.
		acceptBtn.addEventListener('click', () => {
			if (pageItemId) {
				const data = {
					parentId: pageItemId
				};
				document.body.classList.add('nine3v--xhr');
				triggerXHR('nine3v-move', currentPage, data);
			}
		});

		// Root.
		rootBtn.addEventListener('click', () => {
			const data = {
				parentId: 0
			};
			document.body.classList.add('nine3v--xhr');
			triggerXHR('nine3v-move', currentPage, data);
		});

		// Cancel.
		cancelBtn.addEventListener('click', () => {
			sidebar.classList.remove('highlight');
			clonedPages.remove();
		});
	};

	// Toggle page tree.
	const toggles = document.querySelectorAll('.page-toggle');
	toggles.forEach(element => {
		element.addEventListener('click', () => {
			togglePageTree(element, element.parentElement);
		});
	});

	// Toggle page item.
	const pageItems = document.querySelectorAll('.page-item');
	pageItems.forEach(element => {
		element.addEventListener('click', () => {
			togglePages(element, pageItems);
			
			const pageItemParent = element.parentElement;
			const pageToggle = pageItemParent.querySelector('.page-toggle');
			if (pageItemParent && pageToggle) {
				togglePageTree(pageToggle, pageItemParent, 'add');
			}

			if (window.location.href.indexOf('post_status=trash') < 0) {
				showMessage('Loading Pages...', 100000);
				triggerXHR('nine3v-view', currentPage);
			}
		});
	});

	// If cookie selected, set page.
	if (currentPage) {
		const pageItem = document.querySelector(`[data-page-id="${currentPage}"]`);
		if (!pageItem) {
			return;
		}

		togglePages(pageItem, pageItems);

		const pageItemParent = pageItem.parentElement;
		const pageToggle = pageItemParent.querySelector('.page-toggle');
		togglePageTree(pageToggle, pageItemParent, 'add', true);

		// Get current URL params and pass to functions.
		const allowedUrlParams = [ 'post_status', 'author', 'orderby', 'order', 's' ];
		const queryString = window.location.search;
		const urlParams = new URLSearchParams(queryString);
		
		const data = {};
		for (let i = 0; i < allowedUrlParams.length; i++) {
			if (urlParams.has(allowedUrlParams[i])) {
				data[allowedUrlParams[i]] = urlParams.get(allowedUrlParams[i]);
			}
		}
		
		// Ignore request if trash.
		if (window.location.href.indexOf('post_status=trash') < 0) {
			showMessage('Loading Pages...', 100000);
			triggerXHR('nine3v-view', currentPage, data);
		}

	}

	// Perform page action.
	const pageActions = document.querySelectorAll('.page-action');
	pageActions.forEach(element => {
		element.addEventListener('click', () => {
			const action = element.getAttribute('data-page-action');
			if (action && currentPage) {
				let data = {};

				switch (action) {
					case 'nine3v-add':
					case 'nine3v-clone':
						let tempTitle = (action === 'nine3v-add') ? 'New post draft' : currentTitle;
						let postTitle = prompt('Please enter the page title', tempTitle);
						if (postTitle !== null) {
							data.title = postTitle;
						} else {
							return;
						}
						break;
					case 'nine3v-move':
						getNewParentId();
						return;
					case 'nine3v-delete':
						let deleteString = prompt('This will delete the entire page tree, please type "DELETE" to confirm', '');
						if (deleteString !== 'DELETE') {
							return;
						}
						break;
					case 'nine3v-replace':
						data.from = prompt('Search for', '');
						if (!data.from) {
							return;
						}
						data.to = prompt('Replace with', '');
						if (!data.to) {
							return;
						}
						let confirmReplace = prompt(`This will replace all instances of "${data.from}" to "${data.to}" in the selected page tree, please type "CONFIRM" to proceed`, '');
						if (confirmReplace !== 'CONFIRM') {
							return;
						}
						break;
					case 'nine3v-publish':
						let pubString = prompt('This will publish the entire page tree, please type "CONFIRM" to confirm', '');
						if (pubString !== 'CONFIRM') {
							return;
						}
						break;
				}

				document.body.classList.add('nine3v--xhr');
				triggerXHR(action, currentPage, data);
			}
		});
	});
})();