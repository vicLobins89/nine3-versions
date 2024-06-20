(() => {
	/**
	 * Observes dom and changes popup button text
	 */
	const observer = new MutationObserver(function (mutations) {
		const correctURL = nine3v.page;
		const popupContainer = document.querySelector('.components-popover__content');
		if (document.contains(popupContainer)) {
			const linkButton = popupContainer.querySelector('.editor-post-url__link');
			if (linkButton) {
				const prefix = linkButton.querySelector('.editor-post-url__link-prefix');
				const slug = linkButton.querySelector('.editor-post-url__link-slug');
				const suffix = linkButton.querySelector('.editor-post-url__link-suffix');
				prefix.innerText = correctURL;
				slug.remove();
				suffix.remove();
			}
			observer.disconnect();
		}
	});

	/**
	 * Changes summary URLs in the editor to correct permalinks
	 */
	const initialiseUrlChange = () => {
		const correctURL = nine3v.page;
		const correctURLnoHttp = correctURL.replace(/(^\w+:|^)\/\//, '');

		// Change View Page toolbar URL.
		const viewPageButton = document.querySelector('.edit-post-header__settings > a[aria-label="View Page"]');
		if (viewPageButton) {
			viewPageButton.setAttribute('href', correctURL);
		}

		// Change summary URL.
		let summaryButton = document.querySelector('.editor-post-url__panel-toggle');
		if (!summaryButton) {
			summaryButton = document.querySelector('.edit-post-post-url__toggle');
		}
		if (summaryButton) {
			summaryButton.innerText = correctURLnoHttp;

			// Observe popup and change URLs inside.
			summaryButton.addEventListener('click', () => {
				observer.observe(document, { attributes: false, childList: true, characterData: false, subtree: true });
			});
		}
	};

	// Run when page is fully loaded.
	window.onload = () => {
		initialiseUrlChange();
	};
})();