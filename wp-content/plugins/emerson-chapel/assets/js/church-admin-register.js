/**
 * Front-end Church Admin register helpers (Emerson overlays).
 * - Show wedding anniversary when marital status is Married (on load + change).
 * - Sync typed dates into the hidden Y-m-d field and datepicker.
 * - Existing-email register response: open dialog, no second login form.
 */
(function ($) {
	'use strict';

	var cfg = window.emersonCaRegister || {};
	var marriedId = String(cfg.marriedStatusId || '3');

	function focusMembersLogin(email) {
		var login =
			document.querySelector('#user_login') ||
			document.querySelector('input[name="log"]') ||
			document.querySelector('.wppb-form-field input[type="text"]') ||
			document.querySelector('.wppb-user-forms input[type="text"]');
		var heading = document.querySelector('#member-login, .wppb-login-form, .wppb-user-forms');
		if (heading && typeof heading.scrollIntoView === 'function') {
			heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		if (login) {
			if (email && !login.value) {
				login.value = email;
			}
			try {
				login.focus({ preventScroll: true });
			} catch (e) {
				login.focus();
			}
		}
	}

	function bindAlreadyRegisteredDialog() {
		var dialog = document.getElementById('emerson-ca-already-registered');
		if (!dialog || typeof dialog.showModal !== 'function') {
			return;
		}
		var email = dialog.getAttribute('data-emerson-login-email') || '';
		var scrollLogin = dialog.getAttribute('data-emerson-scroll-login') === '1';

		function closeDialog() {
			if (dialog.open) {
				dialog.close();
			}
		}

		dialog.querySelectorAll('.emerson-nl-dialog__close').forEach(function (btn) {
			btn.addEventListener('click', closeDialog);
		});
		dialog.querySelectorAll('.emerson-ca-already-dialog__ok').forEach(function (btn) {
			if (btn.tagName === 'A') {
				return;
			}
			btn.addEventListener('click', function () {
				closeDialog();
				if (scrollLogin) {
					focusMembersLogin(email);
				}
			});
		});
		dialog.addEventListener('click', function (event) {
			if (event.target === dialog) {
				closeDialog();
			}
		});

		dialog.showModal();
		if (scrollLogin) {
			// Prefill quietly so “Go to login” / close still lands on a ready field.
			window.setTimeout(function () {
				var login =
					document.querySelector('#user_login') ||
					document.querySelector('input[name="log"]') ||
					document.querySelector('.wppb-form-field input[type="text"]');
				if (login && email && !login.value) {
					login.value = email;
				}
			}, 0);
		}
	}

	function toggleWeddingAnniversary() {
		var $wrap = $('#wedding-anniversary');
		if (!$wrap.length) {
			return;
		}
		if (String($('#marital_status').val()) === marriedId) {
			$wrap.show();
		} else {
			$wrap.hide();
		}
	}

	function parseTypedDate(raw) {
		var s = $.trim(raw || '');
		if (!s) {
			return null;
		}

		var iso = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(s);
		var y;
		var mo;
		var d;
		var dt;

		if (iso) {
			y = +iso[1];
			mo = +iso[2];
			d = +iso[3];
			dt = new Date(y, mo - 1, d);
			if (dt.getFullYear() !== y) {
				return null;
			}
			if (dt.getMonth() !== mo - 1) {
				return null;
			}
			if (dt.getDate() !== d) {
				return null;
			}
			return dt;
		}

		var us = /^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2}|\d{4})$/.exec(s);
		if (!us) {
			return null;
		}

		mo = +us[1];
		d = +us[2];
		y = +us[3];
		if (y < 100) {
			y += y <= 69 ? 2000 : 1900;
		}
		dt = new Date(y, mo - 1, d);
		if (dt.getFullYear() !== y) {
			return null;
		}
		if (dt.getMonth() !== mo - 1) {
			return null;
		}
		if (dt.getDate() !== d) {
			return null;
		}
		return dt;
	}

	function toYmd(dt) {
		return (
			dt.getFullYear() +
			'-' +
			('0' + (dt.getMonth() + 1)).slice(-2) +
			'-' +
			('0' + dt.getDate()).slice(-2)
		);
	}

	function syncWaFromTyped() {
		var $vis = $('#wedding_anniversaryx');
		var $hid = $('#wedding_anniversary');
		if (!$vis.length || !$hid.length) {
			return;
		}

		var dt = parseTypedDate($vis.val());
		if (!dt) {
			return;
		}

		$hid.val(toYmd(dt));

		try {
			if ($vis.hasClass('hasDatepicker')) {
				$vis.datepicker('setDate', dt);
			} else if ($.fn.datepicker) {
				$vis.datepicker({
					altFormat: 'yy-mm-dd',
					altField: '#wedding_anniversary',
					dateFormat:
						typeof caJSDateFormat !== 'undefined'
							? caJSDateFormat
							: 'mm/dd/yy',
					changeYear: true,
					yearRange:
						($vis.attr('data-start') || '1910') +
						':' +
						($vis.attr('data-end') || '2036'),
				});
				$vis.datepicker('setDate', dt);
			}
		} catch (e) {
			// Datepicker optional; hidden Y-m-d is enough for save.
		}

		$hid.trigger('change');
	}

	function boot() {
		$(document).on('change', '#marital_status', toggleWeddingAnniversary);
		$(document).on('blur change', '#wedding_anniversaryx', syncWaFromTyped);
		$('form').on('submit', syncWaFromTyped);
		toggleWeddingAnniversary();
		bindAlreadyRegisteredDialog();
	}

	$(boot);
})(jQuery);
