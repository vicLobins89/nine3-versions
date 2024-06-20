import { getCookie } from './cookies';
import { triggerXHR } from './xhr';

(() => {
	/**
	 * Hides set of elements and recurses if there are children/gchildren
	 *
	 * @param {HTMLElement} parent 
	 * @param {HTMLElement} child 
	 */
	const recursiveHide = (parent, child) => {
		parent.classList.remove('is-active');
		child.style.display = 'none';
		const childId = parseInt(child.dataset.id);
		const gchildren = document.querySelectorAll(`[data-parent="${childId}"]`);
		gchildren.forEach(gchild => {
			recursiveHide(child, gchild);
		});
	};

	/**
	 * Allows to trigger clone-page from quick links bar
	 *
	 * @param {HTMLElement} listTable 
	 */
	const setupQuickClonePage = listTable => {
		// Quick link clone-page button.
		const cloneButtons = listTable.querySelectorAll('.row-actions .clone-page a');
		cloneButtons.forEach(element => {
			element.addEventListener('click', (e) => {
				e.preventDefault();
				const row = element.closest('tr');
				const pageId = row.id.substring(5);

				document.body.classList.add('nine3v--xhr');
				triggerXHR('nine3v-clone-page', pageId);
			});
		});
	};

	/**
	 * Allows to trigger clone from quick links bar
	 *
	 * @param {HTMLElement} listTable 
	 */
	const setupQuickClone = listTable => {
		// Quick link clone button.
		const cloneButtons = listTable.querySelectorAll('.row-actions .clone a');
		cloneButtons.forEach(element => {
			element.addEventListener('click', (e) => {
				e.preventDefault();
				const row = element.closest('tr');
				const pageId = row.id.substring(5);
				let data = {};
				
				// Get title.
				const tempTitle = row.querySelector('.post_title').innerHTML;

				let pageTitle = prompt('Please enter the page title', tempTitle);
				if (pageTitle !== null) {
					data.title = pageTitle;
				} else {
					return;
				}

				document.body.classList.add('nine3v--xhr');
				triggerXHR('nine3v-clone', pageId, data);
			});
		});
	};

	/**
	 * Attaches event listener and handles the menu order change
	 *
	 * @param {NodeList} rows
	 */
	const initOrder = rows => {
		rows.forEach(row => {
			const input = row.querySelector('.nine3v__order');
			if (!input) {
				return;
			}

			const pageId = parseInt(row.dataset.id);
			input.addEventListener('change', () => {
				const data = { order: input.value }
				triggerXHR('nine3v-order', pageId, data, 500);
			});
		});
	};

	/**
	 * Attaches event listener and handles the expand/collapse event
	 *
	 * @param {NodeList} rows
	 */
	const initClick = rows => {
		rows.forEach(row => {
			const button = row.querySelector('.nine3v__collapse');
			if (!button) {
				return;
			}

			button.addEventListener('click', (e) => {
				if (e.target != button) {
					return;
				}
				e.preventDefault();
				
				const postId = parseInt(row.dataset.id);
				const children = document.querySelectorAll(`[data-parent="${postId}"]`);
				children.forEach(child => {
					if (child.style.display === 'none') {
						row.classList.add('is-active');
						child.style.display = 'table-row';
					} else {
						recursiveHide(row, child);
					}
				});
			});
		});
	};

	/**
	 * Adds expand/collapse icon for each row and initialises click event
	 *
	 * @param {NodeList} rows
	 */
	const initRows = rows => {
		// Ignore if trash.
		if (window.location.href.indexOf('post_status=trash') > -1) {
			return;
		}

		// Parent rows.
		let parents = [];

		rows.forEach(row => {
			const parent = parseInt(row.dataset.parent);

			// Add attr to items with children.
			const parentRow = document.querySelector(`[data-id="${parent}"]`);
			if (parentRow) {
				let expandCollapse = parentRow.querySelector('.nine3v__collapse');
				if (!expandCollapse) {
					const title = parentRow.querySelector('.row-title');
					expandCollapse = document.createElement('span');
					expandCollapse.setAttribute('class', 'nine3v__collapse');
					if (title) {
						title.prepend(expandCollapse);
					}
				}

				parentRow.before(row);
				row.style.display = 'none';

				if (parents.indexOf(parent) === -1) {
					parents.push(parent);
				}
			}
		});

		// Re-order parents on top of their children.
		parents = parents.reverse();
		parents.forEach(parent => {
			const parentRow = document.querySelector(`[data-id="${parent}"]`);
			const firstChild = document.querySelector(`[data-parent="${parent}"]`);
			if (parentRow && firstChild) {
				firstChild.before(parentRow);
			}
		});

		initClick(rows);
		initOrder(rows);
	};

	/**
	 * Initialises the expand/collapse all buttons
	 *
	 * @param {HTMLElement} element parent of inserted buttons
	 * @param {NodeList} rows 
	 */
	const initButtons = (element, rows) => {
		const expand = element.querySelector('.nine3v__expand-all');
		const collapse = element.querySelector('.nine3v__collapse-all');

		expand.addEventListener('click', () => {
			rows.forEach(row => {
				row.classList.add('is-active');
				row.style.display = 'table-row';
			});
		});

		collapse.addEventListener('click', () => {
			rows.forEach(row => {
				const parent = parseInt(row.dataset.parent);
				const parentRow = document.querySelector(`[data-id="${parent}"]`);
				if (parentRow) {
					row.style.display = 'none';
				}
				row.classList.remove('is-active');
			});
		});
	};

	/**
	 * Add attributes to rows
	 *
	 * @param {HTMLElement} table 
	 * @returns 
	 */
	const setupRowAtts = table => {
		const rows = table.querySelectorAll('tr.iedit');
		rows.forEach(row => {
			const parent = row.querySelector('.post_parent').innerHTML;
			const postId = row.id.substring(5);

			row.setAttribute('data-id', postId);
			row.setAttribute('data-parent', parent);
		});

		return rows;
	};

	/**
	 * Create expand/collapse buttons dynamically
	 *
	 * @param {HTMLElement} form 
	 * @param {NodeList} rows 
	 */
	const setupExpandCollapse = (form, rows) => {
		// Expand button
		const expandButton = document.createElement('a');
		const expandContent = document.createTextNode('Expand all');
		expandButton.appendChild(expandContent);
		expandButton.setAttribute('class', 'nine3v__expand-all');
		form.appendChild(expandButton);

		// Collapse button
		const collapseButton = document.createElement('a');
		const collapseContent = document.createTextNode('Collapse all');
		collapseButton.appendChild(collapseContent);
		collapseButton.setAttribute('class', 'nine3v__collapse-all');
		form.appendChild(collapseButton);

		// Init buttons.
		initButtons(form, rows);
	};

	// Get posts list and continue to init.
	const listTable = document.querySelector('#the-list');
	if (!listTable) {
		return;
	}

	// Set data attrs for each row.
	let rows = setupRowAtts(listTable);

	// Initialise row actions.
	initRows(rows);
	setupQuickClonePage(listTable);
	setupQuickClone(listTable);

	// Add expand/collapse all buttons.
	const postsForm = document.querySelector('#posts-filter');
	if (postsForm) {
		setupExpandCollapse(postsForm, rows);
	}

	// Listen for XHR and refresh list.
	document.addEventListener('XHRTrigger', function(e) {
		if (e.detail) {
			rows = setupRowAtts(e.detail);
			initRows(rows);
			initButtons(postsForm, rows);
			setupQuickClonePage(e.detail);
			setupQuickClone(e.detail);

			// Expand top level button if set.
			const currentPage = getCookie('_nine3v_pageId');
			if (currentPage) {
				const row = document.querySelector(`[data-id="${currentPage}"]`);
				const children = document.querySelectorAll(`[data-parent="${currentPage}"]`);
				children.forEach(child => {
					if (child.style.display === 'none') {
						row.classList.add('is-active');
						child.style.display = 'table-row';
					}
				});
			}
		}
	});
})();