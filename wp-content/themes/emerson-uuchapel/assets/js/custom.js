/**
 * Site-wide JavaScript for Emerson UU Chapel.
 */
(function () {
	'use strict';

	// Church Admin's month grid writes "This month's PDF" as /ca_download=monthly-calendar-pdf&start_date=…,
	// a page address that doesn't exist. The grid is redrawn by Ajax, so fix the address when it's clicked.
	document.addEventListener(
		'click',
		function (event) {
			var link = event.target.closest ? event.target.closest('a[href*="/ca_download=monthly-calendar-pdf"]') : null;
			if (link) {
				link.href = link.href.replace('/ca_download=', '/?church_admin_download=');
			}
		},
		true
	);

	// Homepage and giving page: open the PayPal fee calculator here. The standalone page stays, with no link to it.
	document.addEventListener('click', function (event) {
		var link = event.target.closest ? event.target.closest('[data-emerson-paypal-fees]') : null;
		var dialog = document.getElementById('emerson-paypal-fees');
		if (!link || !dialog || typeof dialog.showModal !== 'function') {
			return;
		}
		event.preventDefault();
		if (dialog.open || dialog.classList.contains('is-closing')) {
			return;
		}
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (!reduceMotion) {
			dialog.classList.add('is-opening');
		}
		dialog.showModal();
		if (!reduceMotion) {
			// Apply the off-screen start before removing it, or the slide has nothing to move from.
			void getComputedStyle(dialog).transform;
			dialog.classList.remove('is-opening');
		}
	});
	var paypalFees = document.getElementById('emerson-paypal-fees');
	if (paypalFees) {
		function closePaypalFees() {
			if (!paypalFees.open || paypalFees.classList.contains('is-closing')) {
				return;
			}
			var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if (reduceMotion) {
				paypalFees.close();
				return;
			}
			paypalFees.classList.add('is-closing');
			var finished = false;
			function done(event) {
				if (event && (event.target !== paypalFees || event.propertyName !== 'transform')) {
					return;
				}
				if (finished) {
					return;
				}
				finished = true;
				paypalFees.removeEventListener('transitionend', done);
				paypalFees.classList.remove('is-closing');
				paypalFees.close();
			}
			paypalFees.addEventListener('transitionend', done);
			window.setTimeout(done, 500);
		}

		paypalFees.addEventListener('click', function (event) {
			if (event.target === paypalFees) {
				closePaypalFees();
			}
		});
		paypalFees.addEventListener('cancel', function (event) {
			event.preventDefault();
			closePaypalFees();
		});
		var paypalFeesClose = paypalFees.querySelector('.emerson-paypal-fees__close');
		if (paypalFeesClose) {
			paypalFeesClose.addEventListener('click', function () {
				closePaypalFees();
			});
		}
	}

	// Yearly Planner dropdown: Church Admin names every copy "guideform", so document.guideform
	// can be a list and picking 2027/2028 goes nowhere. Use this select's own value.
	document.querySelectorAll('form[name="guideform"] select[name="guidelinks"]').forEach(function (select) {
		select.onchange = function () {
			if (this.value) {
				window.location.href = this.value;
			}
		};
	});

	function emersonLookingForOther(form) {
		var field = form.querySelector('.emerson-looking-for');
		var detail = form.querySelector('.emerson-looking-for-other');
		if (!field || !detail) {
			return;
		}

		var boxes = field.querySelectorAll('input[type="checkbox"]');
		var other = null;
		for (var i = 0; i < boxes.length; i++) {
			var box = boxes[i];
			var wrap = box.closest('li') || box.parentElement;
			var label = wrap ? wrap.textContent : box.value;
			if (/^\s*other\s*$/i.test(label) || /^other$/i.test(box.value)) {
				other = box;
				break;
			}
		}
		if (!other) {
			return;
		}

		var area = detail.querySelector('textarea');

		function sync() {
			if (other.checked) {
				detail.classList.add('is-open');
				return;
			}
			detail.classList.remove('is-open');
			if (area) {
				area.value = '';
			}
		}

		other.addEventListener('change', sync);
		sync();
	}

	document.querySelectorAll('form.wpforms-form').forEach(emersonLookingForOther);

	// Sunday Morning Schedule sheet: monthly theme + holiday / title / speaker on Church Admin's Ajax grid.
	(function emersonScheduleSheetCalendar() {
		var data = window.emersonScheduleSheet;
		if (!data || !data.themes || !data.sundays) {
			return;
		}

		var monthNames = {
			Jan: '01',
			Feb: '02',
			Mar: '03',
			Apr: '04',
			May: '05',
			Jun: '06',
			Jul: '07',
			Aug: '08',
			Sep: '09',
			Oct: '10',
			Nov: '11',
			Dec: '12'
		};

		function monthFromHeading() {
			var heading = document.getElementById('ca-date');
			if (!heading) {
				return '';
			}
			var match = heading.textContent.match(/\b([A-Z][a-z]{2})\s+(\d{4})\b/);
			if (!match || !monthNames[match[1]]) {
				return '';
			}
			return match[2] + '-' + monthNames[match[1]];
		}

		function fillTheme(line, month) {
			if (!line) {
				return;
			}
			var theme = (month && data.themes[month]) || '';
			line.setAttribute('data-month', month || '');
			line.classList.toggle('is-empty', !theme);
			line.innerHTML = theme
				? "This month's theme: <strong></strong>"
				: '';
			if (theme) {
				line.querySelector('strong').textContent = theme;
			}
		}

		function updateTheme() {
			var month = monthFromHeading();
			var pdf = document.querySelector('#ca-date a.monthly-calendar-pdf');
			if (pdf) {
				var slot = pdf.closest('p') || pdf.parentElement;
				slot.classList.add('emerson-month-theme');
				fillTheme(slot, month);
			}
			document.querySelectorAll('.emerson-schedule .emerson-month-theme').forEach(function (line) {
				if (!line.getAttribute('data-month') && month) {
					fillTheme(line, month);
				}
			});
		}

		function detailHtml(row, extraClass) {
			var wrap = document.createElement('span');
			wrap.className = extraClass || 'emerson-ca-service';
			['title', 'holiday', 'speaker'].forEach(function (key) {
				if (!row[key]) {
					return;
				}
				var part = document.createElement('span');
				part.className = wrap.className + '__' + key;
				part.textContent = key === 'holiday' ? String(row[key]).replace(/\//g, '/\u200b') : row[key];
				wrap.appendChild(part);
			});
			return wrap;
		}

		function rosterHtml(row) {
			if (!data.canViewRoster || !row || !row.roster || !data.labels) {
				return null;
			}
			var list = document.createElement('dl');
			list.className = 'emerson-ca-roster';
			Object.keys(data.labels).forEach(function (key) {
				if (!row.roster[key]) {
					return;
				}
				var dt = document.createElement('dt');
				dt.textContent = data.labels[key];
				var dd = document.createElement('dd');
				dd.textContent = row.roster[key];
				list.appendChild(dt);
				list.appendChild(dd);
			});
			return list.childNodes.length ? list : null;
		}

		function formatDay(ymd) {
			var parts = String(ymd).split('-');
			if (parts.length !== 3) {
				return ymd;
			}
			var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
			if (isNaN(date.getTime())) {
				return ymd;
			}
			return date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
		}

		function servingMark(ymd) {
			var serving = data.serving || {};
			var jobs = (serving.jobs && serving.jobs[ymd]) || [];
			var available = serving.available && serving.available[ymd];
			if (jobs.length) {
				var assigned = document.createElement('span');
				assigned.className = 'emerson-ca-serving';
				assigned.textContent = "You're serving: " + jobs.join(', ');
				return assigned;
			}
			if (data.canViewRoster || !available) {
				return null;
			}
			var mark = document.createElement('span');
			mark.className = 'emerson-ca-available';
			mark.textContent = 'You can serve';
			return mark;
		}

		var requestsPanel = null;
		var openRequestsButton = null;

		function closeRequests() {
			if (requestsPanel) {
				requestsPanel.remove();
				requestsPanel = null;
			}
			if (openRequestsButton) {
				openRequestsButton.setAttribute('aria-expanded', 'false');
				openRequestsButton = null;
			}
		}

		function placeRequests() {
			if (!requestsPanel || !openRequestsButton || !openRequestsButton.isConnected) {
				closeRequests();
				return;
			}
			var rect = openRequestsButton.getBoundingClientRect();
			var margin = 8;
			var width = Math.min(280, window.innerWidth - margin * 2);
			requestsPanel.style.width = width + 'px';
			requestsPanel.hidden = false;
			var panelHeight = requestsPanel.offsetHeight;
			var top = rect.bottom + 6;
			if (top + panelHeight > window.innerHeight - margin) {
				top = rect.top - panelHeight - 6;
			}
			if (top < margin) {
				top = margin;
			}
			if (top + panelHeight > window.innerHeight - margin) {
				top = Math.max(margin, window.innerHeight - panelHeight - margin);
			}
			var left = rect.left;
			if (left + width > window.innerWidth - margin) {
				left = window.innerWidth - width - margin;
			}
			if (left < margin) {
				left = margin;
			}
			requestsPanel.style.top = Math.round(top) + 'px';
			requestsPanel.style.left = Math.round(left) + 'px';
		}

		function openRequests(button, names, ymd) {
			closeRequests();
			var panel = document.createElement('div');
			panel.className = 'emerson-ca-requests__panel';
			panel.id = 'emerson-service-requests';
			panel.setAttribute('role', 'dialog');
			panel.setAttribute('aria-label', 'Service requests for ' + formatDay(ymd));
			panel.tabIndex = -1;
			var body = document.createElement('div');
			body.className = 'emerson-ca-requests__body';
			var heading = document.createElement('p');
			heading.className = 'emerson-ca-requests__heading';
			heading.textContent = formatDay(ymd);
			body.appendChild(heading);
			var note = document.createElement('p');
			note.className = 'emerson-ca-requests__note';
			note.textContent = 'Marked available to serve';
			body.appendChild(note);
			var list = document.createElement('ul');
			names.forEach(function (name) {
				var item = document.createElement('li');
				item.textContent = name;
				list.appendChild(item);
			});
			body.appendChild(list);
			panel.appendChild(body);
			var close = document.createElement('button');
			close.type = 'button';
			close.className = 'emerson-ca-requests__close';
			close.textContent = 'Close';
			close.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				closeRequests();
				if (button.isConnected) {
					button.focus();
				}
			});
			panel.appendChild(close);
			panel.addEventListener('pointerdown', function (event) {
				event.stopPropagation();
			});
			document.body.appendChild(panel);
			requestsPanel = panel;
			openRequestsButton = button;
			button.setAttribute('aria-expanded', 'true');
			button.setAttribute('aria-controls', panel.id);
			placeRequests();
			close.focus();
		}

		function requestsControl(ymd) {
			if (!data.canViewRoster) {
				return null;
			}
			var names = (data.serviceRequests && data.serviceRequests[ymd]) || [];
			if (!names.length) {
				return null;
			}
			var wrap = document.createElement('span');
			wrap.className = 'emerson-ca-requests';
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'emerson-ca-requests__button';
			button.setAttribute('aria-expanded', 'false');
			button.setAttribute('aria-haspopup', 'dialog');
			var label = document.createElement('span');
			label.textContent = 'Service Requests';
			var count = document.createElement('span');
			count.className = 'emerson-ca-requests__count';
			count.textContent = String(names.length);
			button.appendChild(label);
			button.appendChild(count);
			button.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				if (openRequestsButton === button) {
					closeRequests();
					return;
				}
				openRequests(button, names, ymd);
			});
			button.addEventListener('pointerdown', function (event) {
				event.stopPropagation();
			});
			wrap.appendChild(button);
			return wrap;
		}

		function otherEvents(ymd) {
			return (data.events && data.events[ymd]) || [];
		}

		function eventMark(item) {
			var mark = document.createElement('span');
			mark.className = 'emerson-ca-event';
			var title = document.createElement('span');
			title.className = 'emerson-ca-event__title';
			title.textContent = (item && item.label) || (item && item.title) || '';
			mark.appendChild(title);
			if (item && item.location) {
				var loc = document.createElement('span');
				loc.className = 'emerson-ca-event__location';
				loc.textContent = item.location;
				mark.appendChild(loc);
			}
			return mark;
		}

		function decorateDays() {
			document.querySelectorAll('.ca-day[data-date]').forEach(function (day) {
				day.querySelectorAll('.emerson-ca-service, .emerson-ca-event, .emerson-ca-serving, .emerson-ca-available, .emerson-ca-requests').forEach(function (node) {
					node.remove();
				});
				var ymd = day.getAttribute('data-date');
				var row = data.sundays[ymd] || {};
				var extras = otherEvents(ymd);
				var mark = servingMark(ymd);
				var requests = requestsControl(ymd);
				if (!row.title && !row.holiday && !row.speaker && !extras.length && !mark && !requests) {
					return;
				}
				var count = day.querySelector('.ca-events');
				if (count && (row.title || row.holiday || row.speaker || extras.length)) {
					count.hidden = true;
				}
				if (row.title || row.holiday || row.speaker) {
					day.appendChild(
						detailHtml({
							title: row.title || row.holiday,
							holiday: '',
							speaker: row.speaker
						})
					);
				}
				extras.forEach(function (item) {
					if (item && item.title) {
						day.appendChild(eventMark(item));
					}
				});
				if (mark) {
					day.appendChild(mark);
				}
				if (requests) {
					day.appendChild(requests);
				}
			});
		}

		function parseListDate(text) {
			var match = text.replace(/\s+/g, ' ').match(
				/\b((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4})\b/
			);
			if (!match) {
				return '';
			}
			var date = new Date(match[1]);
			if (isNaN(date.getTime())) {
				return '';
			}
			var month = String(date.getMonth() + 1).padStart(2, '0');
			var day = String(date.getDate()).padStart(2, '0');
			return date.getFullYear() + '-' + month + '-' + day;
		}

		function selectedDay() {
			var current = document.querySelector('.ca-day.ca-current[data-date]');
			return current ? current.getAttribute('data-date') : '';
		}

		function decorateSidebar() {
			var list = document.querySelector('.ca-sidebar-list');
			if (!list) {
				return;
			}
			list.querySelectorAll('.emerson-ca-service, .emerson-ca-roster, .emerson-ca-event, .emerson-ca-serving, .emerson-ca-available').forEach(function (node) {
				node.remove();
			});
			var ymd = parseListDate(list.textContent || '') || selectedDay();
			var row = ymd ? data.sundays[ymd] : null;
			var extras = ymd ? otherEvents(ymd) : [];
			var mark = ymd ? servingMark(ymd) : null;
			if (!row && !extras.length && !mark) {
				return;
			}
			var title = list.querySelector('strong');
			if (title && row && row.title && title.textContent.trim() === 'Sunday Service') {
				title.textContent = row.title;
			}
			var host = title ? title.parentElement : list.querySelector('li:nth-child(2)') || list;
			if (host && row) {
				host.appendChild(
					detailHtml(
						{
							title: '',
							holiday: row.holiday,
							speaker: row.speaker
						}
					)
				);
				var roster = rosterHtml(row);
				if (roster) {
					host.appendChild(roster);
				}
			}
			var extraHost = host || list;
			var listed = list.textContent || '';
			extras.forEach(function (item) {
				if (item && item.title && listed.indexOf(item.title) === -1) {
					extraHost.appendChild(eventMark(item));
				}
			});
			if (mark) {
				extraHost.appendChild(mark);
			}
		}

		function fitDayCopy() {
			var probe = document.createElement('span');
			probe.setAttribute('aria-hidden', 'true');
			probe.style.cssText = 'position:absolute;left:-9999px;top:0;white-space:nowrap;visibility:hidden;';
			document.body.appendChild(probe);
			document.querySelectorAll('.ca-day[data-date]').forEach(function (day) {
				var blocks = day.querySelectorAll(
					'.emerson-ca-service, .emerson-ca-event, .emerson-ca-serving, .emerson-ca-available, .emerson-ca-requests'
				);
				if (!blocks.length) {
					return;
				}
				blocks.forEach(function (block) {
					block.style.fontSize = '';
				});
				var dayStyle = window.getComputedStyle(day);
				var width =
					day.clientWidth -
					(parseFloat(dayStyle.paddingLeft) || 0) -
					(parseFloat(dayStyle.paddingRight) || 0) -
					2;
				if (width < 12) {
					return;
				}
				function longestWord() {
					var max = 0;
					blocks.forEach(function (block) {
						var cs = window.getComputedStyle(block);
						probe.style.font = cs.font;
						probe.style.letterSpacing = cs.letterSpacing;
						(block.innerText || '')
							.split(/\s+/)
							.filter(Boolean)
							.forEach(function (word) {
								probe.textContent = word;
								max = Math.max(max, probe.offsetWidth);
							});
					});
					return max;
				}
				if (longestWord() <= width) {
					return;
				}
				var size = parseFloat(window.getComputedStyle(blocks[0]).fontSize) || 11;
				while (size > 6 && longestWord() > width) {
					size = Math.max(6, Math.floor((size - 0.3) * 10) / 10);
					blocks.forEach(function (block) {
						block.style.fontSize = size + 'px';
					});
				}
			});
			probe.remove();
		}

		var compactQuery = window.matchMedia('(max-width: 1000px)');
		var weekStartYmd = '';
		var pendingWeekStart = '';
		var lastHeadingMonth = '';

		function padDatePart(value) {
			return String(value).padStart(2, '0');
		}

		function formatYmd(date) {
			return date.getFullYear() + '-' + padDatePart(date.getMonth() + 1) + '-' + padDatePart(date.getDate());
		}

		function parseYmd(ymd) {
			var parts = String(ymd).split('-');
			return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
		}

		function addDays(ymd, count) {
			var date = parseYmd(ymd);
			date.setDate(date.getDate() + count);
			return formatYmd(date);
		}

		function sundayOf(ymd) {
			var date = parseYmd(ymd);
			if (isNaN(date.getTime())) {
				return '';
			}
			date.setDate(date.getDate() - date.getDay());
			return formatYmd(date);
		}

		function datedDays() {
			return Array.prototype.filter.call(document.querySelectorAll('.ca-day[data-date]'), function (day) {
				var ymd = day.getAttribute('data-date') || '';
				return /^\d{4}-\d{2}-\d{2}$/.test(ymd) && !day.classList.contains('ca-inactive');
			});
		}

		function hasDatedDay(ymd) {
			return !!document.querySelector('.ca-day[data-date="' + ymd + '"]:not(.ca-inactive)');
		}

		function weekRangeLabel(start) {
			var first = parseYmd(start);
			var last = parseYmd(addDays(start, 6));
			var opts = { month: 'short', day: 'numeric' };
			return first.toLocaleDateString(undefined, opts) + ' – ' + last.toLocaleDateString(undefined, opts);
		}

		function clickMonthNav(direction) {
			var buttons = document.querySelectorAll('#ca-date button.ca-calendar-nav.btn-info');
			var button = direction < 0 ? buttons[0] : buttons[1];
			if (button) {
				button.click();
			}
		}

		function ensureWeekNav(render) {
			var nav = render.querySelector('.emerson-ca-week-nav');
			if (!nav) {
				nav = document.createElement('div');
				nav.className = 'emerson-ca-week-nav';
				nav.innerHTML =
					'<button type="button" class="emerson-ca-week-nav__prev">Previous week</button>' +
					'<p class="emerson-ca-week-nav__label"></p>' +
					'<button type="button" class="emerson-ca-week-nav__next">Next week</button>';
				render.insertBefore(nav, render.firstChild);
				nav.querySelector('.emerson-ca-week-nav__prev').addEventListener('click', function () {
					shiftCompactWeek(-1);
				});
				nav.querySelector('.emerson-ca-week-nav__next').addEventListener('click', function () {
					shiftCompactWeek(1);
				});
			}
			nav.hidden = false;
			nav.querySelector('.emerson-ca-week-nav__label').textContent = weekStartYmd ? weekRangeLabel(weekStartYmd) : '';
		}

		function shiftCompactWeek(delta) {
			var start = weekStartYmd || sundayOf(formatYmd(new Date()));
			var next = addDays(start, delta * 7);
			var found = false;
			for (var i = 0; i < 7; i++) {
				if (hasDatedDay(addDays(next, i))) {
					found = true;
					break;
				}
			}
			if (found) {
				weekStartYmd = next;
				applyCompactWeek();
				fitDayCopy();
				return;
			}
			pendingWeekStart = next;
			clickMonthNav(delta);
		}

		function applyCompactWeek() {
			var root = document.querySelector('.church-admin-calendar, .ca-calendar');
			if (!root) {
				return;
			}
			var compact = compactQuery.matches;
			root.classList.toggle('emerson-ca-compact', compact);
			var days = datedDays();
			days.forEach(function (day) {
				day.classList.remove('is-compact-day');
				var label = day.querySelector('.emerson-ca-weekday');
				if (!compact && label) {
					label.remove();
				}
			});
			var render = document.querySelector('.ca-calendar-render');
			var nav = render ? render.querySelector('.emerson-ca-week-nav') : null;
			if (!compact) {
				if (nav) {
					nav.hidden = true;
				}
				weekStartYmd = '';
				return;
			}
			if (!days.length) {
				return;
			}
			var headingMonth = monthFromHeading();
			if (pendingWeekStart) {
				weekStartYmd = pendingWeekStart;
				pendingWeekStart = '';
			} else if (headingMonth && lastHeadingMonth && headingMonth !== lastHeadingMonth) {
				weekStartYmd = '';
			}
			lastHeadingMonth = headingMonth;
			var todayYmd = formatYmd(new Date());
			var current = document.querySelector('.ca-day.ca-current[data-date]:not(.ca-inactive)');
			if (!weekStartYmd) {
				if (hasDatedDay(todayYmd)) {
					weekStartYmd = sundayOf(todayYmd);
				} else if (current) {
					weekStartYmd = sundayOf(current.getAttribute('data-date'));
				} else {
					weekStartYmd = sundayOf(days[0].getAttribute('data-date'));
				}
			}
			var week = [];
			for (var i = 0; i < 7; i++) {
				week.push(addDays(weekStartYmd, i));
			}
			days.forEach(function (day) {
				var ymd = day.getAttribute('data-date');
				if (week.indexOf(ymd) === -1) {
					return;
				}
				day.classList.add('is-compact-day');
				Array.prototype.forEach.call(day.childNodes, function (node) {
					if (node.nodeType === 3 && /^\s*\d{1,2}\s*$/.test(node.textContent || '')) {
						var wrap = document.createElement('span');
						wrap.className = 'emerson-ca-daynum';
						wrap.textContent = node.textContent.trim();
						day.replaceChild(wrap, node);
					} else if (node.nodeType === 1 && node.classList.contains('ca-today')) {
						node.classList.add('emerson-ca-daynum');
					}
				});
				var label = day.querySelector('.emerson-ca-weekday');
				if (!label) {
					label = document.createElement('span');
					label.className = 'emerson-ca-weekday';
					day.insertBefore(label, day.firstChild);
				}
				label.textContent = parseYmd(ymd).toLocaleDateString(undefined, {
					weekday: 'long',
					month: 'short',
					day: 'numeric'
				});
			});
			if (render) {
				ensureWeekNav(render);
			}
		}

		function decorate() {
			closeRequests();
			updateTheme();
			decorateDays();
			decorateSidebar();
			applyCompactWeek();
			fitDayCopy();
		}

		document.addEventListener('click', function (event) {
			if (!requestsPanel) {
				return;
			}
			if (requestsPanel.contains(event.target) || (openRequestsButton && openRequestsButton.contains(event.target))) {
				return;
			}
			closeRequests();
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeRequests();
			}
		});
		window.addEventListener(
			'scroll',
			function () {
				if (requestsPanel) {
					placeRequests();
				}
			},
			true
		);

		document.addEventListener(
			'click',
			function (event) {
				var button = event.target.closest('button.ca-calendar-nav');
				if (!button || button.closest('.emerson-ca-week-nav')) {
					return;
				}
				if (button.classList.contains('btn-warning') || /today/i.test(button.textContent || '')) {
					weekStartYmd = sundayOf(formatYmd(new Date()));
					pendingWeekStart = weekStartYmd;
					return;
				}
				if (!pendingWeekStart) {
					weekStartYmd = '';
				}
			},
			true
		);

		var fitTimer = null;
		window.addEventListener('resize', function () {
			if (fitTimer) {
				clearTimeout(fitTimer);
			}
			fitTimer = setTimeout(function () {
				applyCompactWeek();
				fitDayCopy();
				if (requestsPanel) {
					placeRequests();
				}
			}, 80);
		});

		decorate();

		(function bindScheduleRoster() {
			if (!data.canViewRoster) {
				return;
			}
			var grid = document.querySelector('.emerson-schedule__grid');
			if (!grid) {
				return;
			}
			var panel = document.querySelector('.emerson-schedule-day');
			if (!panel) {
				panel = document.createElement('div');
				panel.className = 'emerson-schedule-day';
				panel.hidden = true;
				panel.setAttribute('tabindex', '-1');
				grid.parentElement.appendChild(panel);
			}

			function closePanel() {
				panel.hidden = true;
				panel.innerHTML = '';
			}

			function openPanel(ymd) {
				var row = data.sundays[ymd] || {};
				panel.innerHTML = '';
				var heading = document.createElement('h3');
				heading.textContent = formatDay(ymd);
				panel.appendChild(heading);
				panel.appendChild(
					detailHtml({
						title: row.title || 'Sunday Service',
						holiday: row.holiday || '',
						speaker: row.speaker || ''
					})
				);
				var roster = rosterHtml(row);
				if (roster) {
					panel.appendChild(roster);
				} else {
					var empty = document.createElement('p');
					empty.textContent = 'The sheet has no serving names for this Sunday yet.';
					panel.appendChild(empty);
				}
				var close = document.createElement('button');
				close.type = 'button';
				close.className = 'emerson-schedule-day__close';
				close.textContent = 'Close';
				close.addEventListener('click', closePanel);
				panel.appendChild(close);
				panel.hidden = false;
				panel.focus();
			}

			grid.addEventListener('click', function (event) {
				var cell = event.target.closest('td.is-roster[data-date]');
				if (!cell) {
					return;
				}
				openPanel(cell.getAttribute('data-date'));
			});
			grid.addEventListener('keydown', function (event) {
				if (event.key !== 'Enter' && event.key !== ' ') {
					return;
				}
				var cell = event.target.closest('td.is-roster[data-date]');
				if (!cell) {
					return;
				}
				event.preventDefault();
				openPanel(cell.getAttribute('data-date'));
			});
		})();

		var root = document.querySelector('.church-admin-calendar, .ca-calendar');
		if (!root || typeof MutationObserver === 'undefined') {
			return;
		}
		var timer = null;
		var observer = new MutationObserver(function () {
			if (timer) {
				clearTimeout(timer);
			}
			timer = setTimeout(function () {
				observer.disconnect();
				decorate();
				observer.observe(root, { childList: true, subtree: true });
			}, 50);
		});
		observer.observe(root, { childList: true, subtree: true });
	})();
})();
