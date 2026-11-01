/**
 * Front-end Church Admin register helpers (Emerson overlays).
 * - Show wedding anniversary when marital status is Married (on load + change).
 * - Sync typed dates into the hidden Y-m-d field and datepicker.
 */
(function ($) {
	'use strict';

	var cfg = window.emersonCaRegister || {};
	var marriedId = String(cfg.marriedStatusId || '3');

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
	}

	$(boot);
})(jQuery);
