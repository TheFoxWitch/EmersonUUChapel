/**
 * Homepage re-commitment button: open Microsoft's form inside the dialog.
 */
(function () {
	'use strict';

	var dialog = document.getElementById('emerson-recommit');
	var frame = dialog ? dialog.querySelector('.emerson-recommit__frame') : null;
	if (!dialog || !frame || typeof dialog.showModal !== 'function') {
		return;
	}

	function reduceMotion() {
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function openDialog() {
		if (dialog.open || dialog.classList.contains('is-closing')) {
			return;
		}
		if (!frame.getAttribute('src')) {
			frame.setAttribute('src', frame.getAttribute('data-src'));
		}
		if (!reduceMotion()) {
			dialog.classList.add('is-opening');
		}
		dialog.showModal();
		if (!reduceMotion()) {
			void getComputedStyle(dialog).transform;
			dialog.classList.remove('is-opening');
		}
	}

	function closeDialog() {
		if (!dialog.open || dialog.classList.contains('is-closing')) {
			return;
		}
		if (reduceMotion()) {
			dialog.close();
			return;
		}
		dialog.classList.add('is-closing');
		var finished = false;
		function done(event) {
			if (event && (event.target !== dialog || event.propertyName !== 'transform')) {
				return;
			}
			if (finished) {
				return;
			}
			finished = true;
			dialog.removeEventListener('transitionend', done);
			dialog.classList.remove('is-closing');
			dialog.close();
		}
		dialog.addEventListener('transitionend', done);
		window.setTimeout(done, 500);
	}

	document.addEventListener('click', function (event) {
		var opener = event.target.closest ? event.target.closest('[data-emerson-recommit]') : null;
		if (!opener) {
			return;
		}
		event.preventDefault();
		openDialog();
	});
	dialog.addEventListener('click', function (event) {
		if (event.target === dialog) {
			closeDialog();
		}
	});
	dialog.addEventListener('cancel', function (event) {
		event.preventDefault();
		closeDialog();
	});
	dialog.querySelector('.emerson-recommit__close').addEventListener('click', closeDialog);
}());
