/**
 * Front-end Church Admin register helpers (Emerson overlays).
 * - Show wedding anniversary when marital status is Married (on load + change).
 * - Sync typed dates into the hidden Y-m-d field on every CA date picker.
 * - Use m/d/yy so birthday, anniversary, and custom dates match.
 * - Type in the field; open the calendar only from the calendar icon.
 * - Existing-email register response: open dialog, no second login form.
 */
(function ($) {
	'use strict';

	var cfg = window.emersonCaRegister || {};
	var marriedId = String(cfg.marriedStatusId || '3');
	var DATE_FORMAT = 'm/d/yy';

	var MONTHS = {
		january: 0,
		jan: 0,
		february: 1,
		feb: 1,
		march: 2,
		mar: 2,
		april: 3,
		apr: 3,
		may: 4,
		june: 5,
		jun: 5,
		july: 6,
		jul: 6,
		august: 7,
		aug: 7,
		september: 8,
		sep: 8,
		sept: 8,
		october: 9,
		oct: 9,
		november: 10,
		nov: 10,
		december: 11,
		dec: 11,
	};

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

	function validYmd(y, mo, d) {
		var dt = new Date(y, mo - 1, d);
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

	function parseTypedDate(raw) {
		var s = $.trim(raw || '');
		if (!s) {
			return null;
		}

		var iso = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(s);
		if (iso) {
			return validYmd(+iso[1], +iso[2], +iso[3]);
		}

		var us = /^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2}|\d{4})$/.exec(s);
		if (us) {
			var y = +us[3];
			if (y < 100) {
				y += y <= 69 ? 2000 : 1900;
			}
			return validYmd(y, +us[1], +us[2]);
		}

		var named = /^([A-Za-z]+)\s+(\d{1,2}),?\s+(\d{4})$/.exec(s);
		if (named) {
			var moName = MONTHS[named[1].toLowerCase()];
			if (typeof moName === 'number') {
				return validYmd(+named[3], moName + 1, +named[2]);
			}
		}

		return null;
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

	function hiddenForVisible($vis) {
		var id = $vis.attr('id') || '';
		if (id && id.slice(-1) === 'x') {
			var $byId = $('#' + id.slice(0, -1));
			if ($byId.length) {
				return $byId;
			}
		}
		return $vis.siblings('.clonableHiddenDatePicker').first();
	}

	function syncTypedDatePair($vis, $hid, opts) {
		opts = opts || {};
		if (!$vis.length || !$hid.length) {
			return;
		}

		var raw = String($vis.val() || '').trim();
		if (raw === '') {
			$hid.val('');
			$hid.trigger('change');
			return;
		}

		var dt = parseTypedDate(raw);
		if (!dt) {
			return;
		}

		$hid.val(toYmd(dt));

		// setDate during blur fights calendar day clicks (input blurs first).
		if (!opts.skipSetDate) {
			try {
				ensureDatePicker($vis);
				if ($vis.hasClass('hasDatepicker')) {
					$vis.datepicker('setDate', dt);
				}
			} catch (e) {
				// Datepicker optional; hidden Y-m-d is enough for save.
			}
		}

		$hid.trigger('change');
	}

	function syncAllTypedDates() {
		$('.clonableDatePicker').each(function () {
			var $vis = $(this);
			syncTypedDatePair($vis, hiddenForVisible($vis), { skipSetDate: true });
		});
	}

	function datepickerIsShowing() {
		return !!(
			$.datepicker &&
			($.datepicker._datepickerShowing || $('#ui-datepicker-div').is(':visible'))
		);
	}

	function unbindChurchAdminFocusPicker() {
		// CA opens the calendar on focus via delegated body handlers.
		$('body').off('focus', '.clonableDatePicker');
		$('body').off('focusin', '.clonableDatePicker');
	}

	function ensureDatePicker($vis) {
		if (!$vis.length || !$.fn.datepicker) {
			return;
		}

		var $hid = hiddenForVisible($vis);
		if (!$hid.length) {
			return;
		}

		var hidSel = '#' + $hid.attr('id');
		var yearRange =
			($vis.attr('data-start') || '1910') + ':' + ($vis.attr('data-end') || '2036');

		if ($vis.data('emersonPickerReady') && $vis.hasClass('hasDatepicker')) {
			// Don't poke datepicker('option') here — it can dismiss an open popup.
			$vis.off('keyup');
			$vis.siblings('button.ui-datepicker-trigger').hide();
			return;
		}

		if ($vis.hasClass('hasDatepicker')) {
			try {
				$vis.datepicker('destroy');
			} catch (e) {
				// ignore
			}
		}

		// showOn: button — typing/focus does not open the popup. Icon calls show().
		$vis.datepicker({
			altFormat: 'yy-mm-dd',
			altField: hidSel,
			dateFormat: DATE_FORMAT,
			changeYear: true,
			yearRange: yearRange,
			showOn: 'button',
			buttonText: '',
			onSelect: function (dateText) {
				// Input already has dateText; only refresh hidden Y-m-d (no setDate).
				var dt = parseTypedDate(dateText);
				if (dt) {
					$hid.val(toYmd(dt));
					$hid.trigger('change');
				}
			},
		});
		$vis.off('keyup');
		$vis.siblings('button.ui-datepicker-trigger').hide().attr({
			tabindex: '-1',
			'aria-hidden': 'true',
		});
		$vis.data('emersonPickerReady', true);
	}

	function placeCalendarIcons() {
		$('.church-admin-form-group').each(function () {
			var $group = $(this);
			var $vis = $group.find('> .clonableDatePicker').first();
			if (!$vis.length) {
				$vis = $group.find('.clonableDatePicker').first();
			}
			if (!$vis.length) {
				return;
			}

			var $label = $group.children('label').first();
			var $icon = $group
				.children('.ca-dashicons.dashicons-calendar-alt')
				.add($group.find('> .ca-dashicons.dashicons-calendar-alt'))
				.first();
			if (!$icon.length) {
				$icon = $vis.prevAll('.ca-dashicons.dashicons-calendar-alt').first();
			}
			if (!$icon.length || !$label.length) {
				return;
			}

			// ui-datepicker-trigger: jQuery UI won't treat icon clicks as "outside".
			$icon
				.addClass('emerson-ca-datepicker-trigger ui-datepicker-trigger')
				.attr({
					role: 'button',
					tabindex: '0',
					title: 'Open calendar',
					'aria-label': 'Open calendar',
				});
			if (!$label.find('.emerson-ca-datepicker-trigger').length) {
				$label.append($icon);
			}
			// Point label at the visible field so icon clicks don't focus the hidden Y-m-d.
			var visId = $vis.attr('id');
			if (visId) {
				$label.attr('for', visId);
			}
			ensureDatePicker($vis);
		});
	}

	function visibleForIcon($icon) {
		var $group = $icon.closest('.church-admin-form-group');
		var $vis = $group.find('.clonableDatePicker').first();
		if ($vis.length) {
			return $vis;
		}
		return $icon.nextAll('.clonableDatePicker').first();
	}

	function openPickerFromIcon($icon) {
		var $vis = visibleForIcon($icon);
		if (!$vis.length) {
			return;
		}
		ensureDatePicker($vis);
		// Apply typed value so the popup highlights the right day.
		syncTypedDatePair($vis, hiddenForVisible($vis));
		// Defer past this click so jQuery UI's external-click handler doesn't
		// close the popup in the same event (flash then disappear).
		window.setTimeout(function () {
			try {
				$vis.datepicker('show');
			} catch (e) {
				// ignore
			}
		}, 0);
	}

	function boot() {
		window.caJSDateFormat = DATE_FORMAT;
		unbindChurchAdminFocusPicker();
		window.setTimeout(unbindChurchAdminFocusPicker, 0);

		$(document).on('change', '#marital_status', toggleWeddingAnniversary);

		// mousedown + stopPropagation: keep UI from closing on the same gesture.
		$(document).on(
			'mousedown',
			'.emerson-ca-datepicker-trigger, .church-admin-form-group > .ca-dashicons.dashicons-calendar-alt',
			function (e) {
				e.preventDefault();
				e.stopPropagation();
			}
		);

		$(document).on(
			'click',
			'.emerson-ca-datepicker-trigger, .church-admin-form-group > .ca-dashicons.dashicons-calendar-alt',
			function (e) {
				e.preventDefault();
				e.stopPropagation();
				openPickerFromIcon($(this));
			}
		);

		$(document).on(
			'keydown',
			'.emerson-ca-datepicker-trigger',
			function (e) {
				if (e.key !== 'Enter' && e.key !== ' ') {
					return;
				}
				e.preventDefault();
				openPickerFromIcon($(this));
			}
		);

		// Keep typing from opening the popup (showOn: 'button').
		$(document).on('focus', '.clonableDatePicker', function () {
			unbindChurchAdminFocusPicker();
			ensureDatePicker($(this));
		});

		$(document).on('change', '.clonableDatePicker', function () {
			var $vis = $(this);
			syncTypedDatePair($vis, hiddenForVisible($vis), { skipSetDate: true });
		});

		$(document).on('blur', '.clonableDatePicker', function () {
			var $vis = $(this);
			// Day clicks blur the input before onSelect — wait, and skip if still open.
			window.setTimeout(function () {
				if (datepickerIsShowing()) {
					return;
				}
				syncTypedDatePair($vis, hiddenForVisible($vis), { skipSetDate: true });
			}, 0);
		});

		$('form').on('submit', syncAllTypedDates);
		toggleWeddingAnniversary();
		placeCalendarIcons();
		bindAlreadyRegisteredDialog();
	}

	$(boot);
})(jQuery);
