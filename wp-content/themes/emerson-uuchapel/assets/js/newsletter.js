/**
 * Newsletter sign-up: homepage pop-up timing and in-page form submission.
 * The browser remembers "subscribed" (never show again) or "dismissed" (show again after 30 days)
 * in localStorage under emersonNewsletter. No name or email is stored.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'emersonNewsletter';
	var RETURN_AFTER_DAYS = 30;
	var OPEN_DELAY_MS = 5000;
	var OPEN_AT_SCROLL = 0.4;
	var FADE_MS = 300; // Match the transition on .emerson-nl-dialog in custom.css.
	var CLOSE_AFTER_SUCCESS_MS = 4000;
	var SLOW_NOTICE_MS = 2000;

	function storageWorks() {
		try {
			localStorage.setItem(STORAGE_KEY + 'Test', '1');
			localStorage.removeItem(STORAGE_KEY + 'Test');
			return true;
		} catch (e) {
			return false;
		}
	}

	function readState() {
		try {
			return JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
		} catch (e) {
			return null;
		}
	}

	function writeState(state) {
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify({ state: state, at: Date.now() }));
		} catch (e) {}
	}

	function showMessage(form, text, kind) {
		var box = form.querySelector('.emerson-nl-form__message');
		box.textContent = text;
		box.classList.remove('is-success', 'is-error', 'is-info');
		box.classList.add('is-' + kind);
	}

	function clearMessage(form) {
		var box = form.querySelector('.emerson-nl-form__message');
		box.textContent = '';
		box.classList.remove('is-success', 'is-error', 'is-info');
	}

	function setupForm(form) {
		var button = form.querySelector('.emerson-nl-form__submit');
		var started = form.querySelector('input[name="ts"]');
		started.value = Math.floor(Date.now() / 1000);

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			if (!form.checkValidity()) {
				form.reportValidity();
				return;
			}

			var data = new FormData(form);
			data.append('ajax', '1');
			button.disabled = true;
			button.classList.add('is-busy');
			button.textContent = 'Sending…';
			clearMessage(form);
			var slowNotice = setTimeout(function () {
				showMessage(form, 'Still working on it. This can take a few seconds…', 'info');
			}, SLOW_NOTICE_MS);

			// form.action would return the hidden input named "action", not the URL.
			fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (response) {
					if (!(response.headers.get('content-type') || '').includes('application/json')) {
						throw new Error('Unexpected response');
					}
					return response.json();
				})
				.then(function (result) {
					showMessage(form, result.message, result.ok ? 'success' : 'error');
					if (result.ok) {
						writeState('subscribed');
						form.classList.add('is-done');
						var dialog = form.closest('dialog');
						var decline = dialog && dialog.querySelector('.emerson-nl-dialog__no');
						if (decline) {
							decline.textContent = 'Close';
						}
						form.dispatchEvent(new CustomEvent('emerson-nl-subscribed', { bubbles: true }));
					}
				})
				.catch(function () {
					showMessage(form, 'Something went wrong. Please check your connection and try again.', 'error');
				})
				.then(function () {
					clearTimeout(slowNotice);
					button.disabled = false;
					button.classList.remove('is-busy');
					button.textContent = 'Subscribe';
				});
		});
	}

	function setupPopup(dialog) {
		if (typeof dialog.showModal !== 'function' || !storageWorks()) {
			return;
		}

		var state = readState();
		if (state && state.state === 'subscribed') {
			return;
		}
		if (state && state.state === 'dismissed' && Date.now() - state.at < RETURN_AFTER_DAYS * 86400000) {
			return;
		}

		var opened = false;
		var timer;

		function onScroll() {
			var scrollable = document.documentElement.scrollHeight - window.innerHeight;
			if (scrollable > 0 && window.scrollY / scrollable >= OPEN_AT_SCROLL) {
				open();
			}
		}

		function open() {
			if (opened) {
				return;
			}
			opened = true;
			clearTimeout(timer);
			window.removeEventListener('scroll', onScroll);
			dialog.querySelector('input[name="ts"]').value = Math.floor(Date.now() / 1000);
			dialog.classList.add('is-opening');
			dialog.showModal();
			// The hidden starting style has to be applied before it's removed, or there's nothing to fade from.
			void getComputedStyle(dialog).opacity;
			dialog.classList.remove('is-opening');
			dialog.querySelector('input[name="first_name"]').focus();
		}

		var closing = false;

		function dismiss() {
			if (!dialog.open || closing) {
				return;
			}
			closing = true;
			var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			// Without a settled starting style the browser skips the fade and jumps to the end.
			void getComputedStyle(dialog).opacity;
			dialog.classList.add('is-closing');
			setTimeout(function () {
				dialog.close();
				dialog.classList.remove('is-closing');
				closing = false;
			}, reduceMotion ? 0 : FADE_MS);
		}

		// Every way of closing (buttons, Esc, backdrop, browser) ends here.
		dialog.addEventListener('close', function () {
			var current = readState();
			if (!current || current.state !== 'subscribed') {
				writeState('dismissed');
			}
		});

		dialog.querySelectorAll('[data-emerson-nl-close]').forEach(function (button) {
			button.addEventListener('click', dismiss);
		});
		dialog.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				dismiss();
			}
		});
		dialog.addEventListener('cancel', function (event) {
			event.preventDefault();
			dismiss();
		});
		dialog.addEventListener('emerson-nl-subscribed', function () {
			setTimeout(dismiss, CLOSE_AFTER_SUCCESS_MS);
		});
		dialog.addEventListener('click', function (event) {
			if (event.target === dialog) {
				dismiss();
			}
		});

		timer = setTimeout(open, OPEN_DELAY_MS);
		window.addEventListener('scroll', onScroll, { passive: true });
	}

	function setupToast(toast) {
		if (toast.getAttribute('data-emerson-nl-result') === 'confirmed') {
			writeState('subscribed');
		}
		toast.querySelector('.emerson-nl-toast__close').addEventListener('click', function () {
			toast.remove();
		});
	}

	document.querySelectorAll('.emerson-nl-form').forEach(setupForm);

	var toast = document.querySelector('.emerson-nl-toast');
	if (toast) {
		setupToast(toast);
	}

	var popup = document.getElementById('emerson-newsletter-popup');
	if (popup && !toast) {
		setupPopup(popup);
	}
})();
