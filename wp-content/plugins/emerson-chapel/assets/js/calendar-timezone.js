/**
 * Church Admin month calendar: first load must use the site calendar day, not UTC.
 * Stock jQueryCalendar.js uses new Date().toJSON().slice(0,10). Init is the only
 * calendar-render call that includes cat_id / fac_ids; prev/next omit them.
 */
(function ($) {
	'use strict';

	if (!$ || typeof $.getJSON !== 'function') {
		return;
	}

	function siteToday() {
		if (window.emersonCalendarTz && emersonCalendarTz.today) {
			return String(emersonCalendarTz.today);
		}
		var tz =
			(window.emersonCalendarTz && emersonCalendarTz.timezone) || 'America/Chicago';
		try {
			return new Intl.DateTimeFormat('en-CA', {
				timeZone: tz,
				year: 'numeric',
				month: '2-digit',
				day: '2-digit'
			}).format(new Date());
		} catch (err) {
			var n = new Date();
			return (
				n.getFullYear() +
				'-' +
				('0' + (n.getMonth() + 1)).slice(-2) +
				'-' +
				('0' + n.getDate()).slice(-2)
			);
		}
	}

	var original = $.getJSON;
	$.getJSON = function (url, data, success) {
		if (
			data &&
			typeof data === 'object' &&
			data.method === 'calendar-render' &&
			(Object.prototype.hasOwnProperty.call(data, 'cat_id') ||
				Object.prototype.hasOwnProperty.call(data, 'fac_ids'))
		) {
			data.date = siteToday();
		}
		return original.apply(this, arguments);
	};
})(jQuery);
