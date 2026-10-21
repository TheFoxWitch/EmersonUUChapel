/**
 * Cookie notice. "Okay" is remembered in localStorage so the card does not return.
 * The Cookies page can clear that note and the newsletter invitation note.
 */
(function () {
	'use strict';

	var NOTICE_KEY = 'emersonCookieNotice';
	var NEWSLETTER_KEY = 'emersonNewsletter';

	function storageWorks() {
		try {
			localStorage.setItem(NOTICE_KEY + 'Test', '1');
			localStorage.removeItem(NOTICE_KEY + 'Test');
			return true;
		} catch (e) {
			return false;
		}
	}

	var notice = document.querySelector('.emerson-cookie-notice');
	if (notice) {
		var accepted = false;
		try {
			accepted = localStorage.getItem(NOTICE_KEY) === 'ok';
		} catch (e) {
			accepted = false;
		}
		if (!accepted) {
			notice.hidden = false;
		}
		var okay = notice.querySelector('.emerson-cookie-notice__ok');
		if (okay) {
			okay.addEventListener('click', function () {
				try {
					localStorage.setItem(NOTICE_KEY, 'ok');
				} catch (e) {}
				notice.hidden = true;
			});
		}
	}

	function say(button, text) {
		var note = button.parentElement.querySelector('.emerson-cookie-pref__status');
		if (note) {
			note.textContent = text;
		}
	}

	document.querySelectorAll('[data-emerson-clear-newsletter]').forEach(function (button) {
		button.addEventListener('click', function () {
			if (!storageWorks()) {
				say(button, 'This browser is blocking site storage, so there is nothing to clear.');
				return;
			}
			localStorage.removeItem(NEWSLETTER_KEY);
			say(button, 'Cleared. The newsletter invitation can show again.');
		});
	});

	document.querySelectorAll('[data-emerson-reset-notice]').forEach(function (button) {
		button.addEventListener('click', function () {
			if (!storageWorks()) {
				say(button, 'This browser is blocking site storage, so there is nothing to clear.');
				return;
			}
			localStorage.removeItem(NOTICE_KEY);
			say(button, 'Cleared. The cookie notice will show on the next page.');
		});
	});
})();
