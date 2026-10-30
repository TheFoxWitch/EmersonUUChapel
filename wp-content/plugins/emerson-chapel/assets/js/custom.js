/**
 * Site-wide JavaScript for Emerson UU Chapel.
 */
(function () {
	'use strict';

	/**
	 * Brief bounce-down arrow when a form-maker dialog’s content overflows and
	 * more is still below. Shared by survey, PayPal fees, and custom CFF modals
	 * (not WPForms). Toggle: Emerson Chapel → Modals → Display.
	 */
	function modalOverflowArrowEnabled() {
		return !!(window.emersonModals && window.emersonModals.overflowArrow);
	}

	function findModalScrollRoot(dialog) {
		if (!dialog) {
			return null;
		}
		var candidates = [
			dialog.querySelector('.emerson-modal__form'),
			dialog.querySelector('form.cff-form'),
			dialog.querySelector('.emerson-recommit__fit'),
			dialog
		];
		var i;
		for (i = 0; i < candidates.length; i++) {
			var el = candidates[i];
			if (!el) {
				continue;
			}
			var style = window.getComputedStyle(el);
			var oy = style.overflowY;
			if (
				(oy === 'auto' || oy === 'scroll' || oy === 'overlay') &&
				el.scrollHeight > el.clientHeight + 2
			) {
				return el;
			}
		}
		return null;
	}

	function clearModalOverflowArrow(dialog) {
		if (!dialog) {
			return;
		}
		var hint = dialog.querySelector('.emerson-modal-scroll-hint');
		if (hint && hint.parentNode) {
			hint.parentNode.removeChild(hint);
		}
		if (dialog._emersonScrollHintCleanup) {
			dialog._emersonScrollHintCleanup();
			dialog._emersonScrollHintCleanup = null;
		}
	}

	function showModalOverflowArrow(dialog) {
		clearModalOverflowArrow(dialog);
		if (!dialog || !dialog.open || !modalOverflowArrowEnabled()) {
			return;
		}
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}
		var root = findModalScrollRoot(dialog);
		if (!root) {
			return;
		}
		/* Only cue “more below.” No up-arrow when reopened already scrolled down. */
		if (root.scrollTop + root.clientHeight >= root.scrollHeight - 4) {
			return;
		}
		var hint = document.createElement('div');
		hint.className = 'emerson-modal-scroll-hint';
		hint.setAttribute('aria-hidden', 'true');
		hint.innerHTML = '<span class="emerson-modal-scroll-hint__arrow"></span>';
		dialog.appendChild(hint);

		function dismiss() {
			clearModalOverflowArrow(dialog);
		}

		var arrow = hint.querySelector('.emerson-modal-scroll-hint__arrow');
		if (arrow) {
			arrow.addEventListener('animationend', dismiss);
		}
		root.addEventListener('scroll', dismiss, { passive: true });
		dialog.addEventListener('close', dismiss);

		dialog._emersonScrollHintCleanup = function () {
			root.removeEventListener('scroll', dismiss);
			dialog.removeEventListener('close', dismiss);
			if (arrow) {
				arrow.removeEventListener('animationend', dismiss);
			}
		};
	}

	window.emersonModalOverflowArrow = showModalOverflowArrow;

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
		openPaypalFees(dialog);
	});

	function revealPaypalFees(dialog) {
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduceMotion) {
			return;
		}
		void getComputedStyle(dialog).transform;
		dialog.classList.remove('is-opening');
	}

	function openPaypalFees(dialog) {
		if (dialog.open || dialog.classList.contains('is-closing')) {
			return;
		}
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (!reduceMotion) {
			dialog.classList.add('is-opening');
		}
		dialog.showModal();
		// The calculator prints a stored placeholder height, then draws the real
		// fields and drops that placeholder. Hold the dialog off-screen until the
		// fields are in place so the slide-in starts at the finished height.
		var list = dialog.querySelector('[id^="fieldlist_"]');
		if (!list || list.children.length || reduceMotion) {
			revealPaypalFees(dialog);
			window.requestAnimationFrame(function () {
				showModalOverflowArrow(dialog);
			});
			return;
		}
		var revealed = false;
		function reveal() {
			if (revealed) {
				return;
			}
			revealed = true;
			observer.disconnect();
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () {
					revealPaypalFees(dialog);
					showModalOverflowArrow(dialog);
				});
			});
		}
		var observer = new MutationObserver(function () {
			if (list.children.length) {
				reveal();
			}
		});
		observer.observe(list, { childList: true });
		window.setTimeout(reveal, 4000);
	}
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

	// Serving dates: positions appear when a date is ticked, and Mark all / Clear all follow.
	document.addEventListener('change', function (event) {
		var box = event.target;
		if (!box.matches || !box.matches('input[name="can_serve[]"]')) {
			return;
		}
		var group = box.closest ? box.closest('.church-admin-form-group') : null;
		var roles = group ? group.querySelector('.emerson-serve-roles') : null;
		if (!roles) {
			return;
		}
		roles.hidden = !box.checked;
		roles.querySelectorAll('input').forEach(function (input) {
			input.disabled = !box.checked;
		});
	});
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-emerson-serve-all]') : null;
		if (!button) {
			return;
		}
		var mark = button.getAttribute('data-emerson-serve-all') === 'mark';
		document.querySelectorAll('input[name="can_serve[]"]').forEach(function (box) {
			box.checked = mark;
			box.dispatchEvent(new Event('change', { bubbles: true }));
		});
		if (!mark) {
			document.querySelectorAll('.emerson-serve-roles input').forEach(function (input) {
				input.checked = false;
			});
		}
	});

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
			['title', 'holiday', 'speaker', 'location'].forEach(function (key) {
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
			var offered = (serving.offered && serving.offered[ymd]) || [];
			var mark = document.createElement('span');
			mark.className = 'emerson-ca-available';
			mark.textContent = offered.length ? 'You can serve: ' + offered.join(', ') : 'You can serve';
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
			names.forEach(function (entry) {
				var item = document.createElement('li');
				var person = typeof entry === 'string' ? entry : (entry.name || '');
				var roles = entry && entry.roles && entry.roles.length ? entry.roles.join(', ') : '';
				item.textContent = roles ? person + ': ' + roles : person;
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

		var ROTATING_LOCATION_LINE = 'Rotating location — check This Week at Emerson';

		function isRotatingLocation(location) {
			var text = String(location || '')
				.replace(/\u2014|\u2013/g, '-')
				.replace(/\s+/g, ' ')
				.trim()
				.toLowerCase();
			if (!text) {
				return false;
			}
			return (
				text.indexOf('rotating location') === 0 ||
				text === 'rotating' ||
				text.indexOf('check this week at emerson') !== -1
			);
		}

		function eventTimeSortKey(item) {
			return String((item && item.time) || '99:99:99');
		}

		function eventMark(item, showLocation) {
			var mark = document.createElement('span');
			mark.className = 'emerson-ca-event';
			var title = document.createElement('span');
			title.className = 'emerson-ca-event__title';
			title.textContent = (item && item.label) || (item && item.title) || '';
			mark.appendChild(title);
			if (showLocation !== false && item && item.location) {
				var loc = document.createElement('span');
				loc.className = 'emerson-ca-event__location';
				loc.textContent = item.location;
				mark.appendChild(loc);
			}
			return mark;
		}

		function sharedRotatingMark() {
			var mark = document.createElement('span');
			mark.className = 'emerson-ca-event emerson-ca-event--shared-place';
			var loc = document.createElement('span');
			loc.className = 'emerson-ca-event__location';
			loc.textContent = ROTATING_LOCATION_LINE;
			mark.appendChild(loc);
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
				var listed = extras.filter(function (item) {
					return item && item.title;
				});
				var rotatingCount = 0;
				listed.forEach(function (item) {
					if (isRotatingLocation(item.location)) {
						rotatingCount += 1;
					}
				});
				var collapseRotating = rotatingCount > 1;
				listed.sort(function (a, b) {
					return eventTimeSortKey(a).localeCompare(eventTimeSortKey(b));
				});
				listed.forEach(function (item) {
					var showLoc = !(collapseRotating && isRotatingLocation(item.location));
					day.appendChild(eventMark(item, showLoc));
				});
				if (collapseRotating) {
					day.appendChild(sharedRotatingMark());
				}
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

		/*
		 * Church Admin writes “No events today” when it has no calendar_date row.
		 * Emerson still lists planning-sheet / series events in that sidebar — clear
		 * the empty message so Dec 24 (etc.) does not contradict itself.
		 */
		function clearSidebarNoEventsMessage(list) {
			if (!list) {
				return;
			}
			Array.prototype.forEach.call(list.querySelectorAll(':scope > li'), function (li, index) {
				if (index === 0) {
					return;
				}
				var clone = li.cloneNode(true);
				clone.querySelectorAll('.emerson-ca-service, .emerson-ca-roster, .emerson-ca-event, .emerson-ca-serving, .emerson-ca-available').forEach(function (node) {
					node.remove();
				});
				var text = (clone.textContent || '').replace(/\s+/g, ' ').trim();
				if (/^no events today\.?$/i.test(text)) {
					li.textContent = '';
				}
			});
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
				window.requestAnimationFrame(function () {
					limitSidebarScroll(list);
				});
				return;
			}
			clearSidebarNoEventsMessage(list);
			var title = list.querySelector('strong');
			if (title && row && row.title && title.textContent.trim() === 'Sunday Service') {
				title.textContent = row.title;
			}
			var host = title ? title.parentElement : list.querySelector('li:nth-child(2)');
			if (!host) {
				host = document.createElement('li');
				list.appendChild(host);
			}
			if (host && row) {
				host.appendChild(
					detailHtml(
						{
							title: '',
							holiday: row.holiday,
							speaker: row.speaker,
							location: row.location || ''
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
			var places = [];
			if (row && row.location) {
				places.push(row.location);
			}
			extras.forEach(function (item) {
				if (item && item.location && places.indexOf(item.location) === -1) {
					places.push(item.location);
				}
			});
			shrinkPlaces(list, places);
			window.requestAnimationFrame(function () {
				limitSidebarScroll(list);
			});
		}

		/**
		 * Church Admin packs every event into one <li> as <p style="border-left…"> blocks
		 * (plus &nbsp; spacers). Cap at three of those; scroll + bounce chevron when more.
		 */
		function sidebarEventBlocks(list) {
			if (!list) {
				return [];
			}
			return Array.prototype.filter.call(list.querySelectorAll(':scope > li > p'), function (p) {
				var text = (p.textContent || '').replace(/\s+/g, ' ').trim();
				if (!text) {
					return false;
				}
				var style = p.getAttribute('style') || '';
				if (/border-left/i.test(style)) {
					return true;
				}
				return !!p.querySelector('strong');
			});
		}

		function clearSidebarFade(sidebar, list) {
			if (sidebar) {
				sidebar.classList.remove('emerson-ca-sidebar--scroll');
				sidebar.style.height = '';
				sidebar.style.maxHeight = '';
			}
			if (list) {
				list.classList.remove('emerson-ca-sidebar-scroll');
				list.style.maxHeight = '';
				list._emersonScrollKey = '';
				if (list._emersonFadeOnScroll) {
					list.removeEventListener('scroll', list._emersonFadeOnScroll);
					list._emersonFadeOnScroll = null;
				}
			}
			if (!sidebar) {
				return;
			}
			var fade = sidebar.querySelector('.emerson-ca-sidebar-fade');
			if (fade && fade.parentNode) {
				fade.parentNode.removeChild(fade);
			}
		}

		/**
		 * Height of the month grid alone (top bar + weeks). Do not use the stretched
		 * .ca-calendar-render box — flex stretch makes that match a tall sidebar.
		 */
		function calendarGridContentHeight(render) {
			if (!render) {
				return 0;
			}
			var h = 0;
			var top = render.querySelector('.ca-calendar-top-bar');
			if (top) {
				h += top.getBoundingClientRect().height;
			}
			Array.prototype.forEach.call(render.querySelectorAll('.ca-week'), function (week) {
				h += week.getBoundingClientRect().height;
			});
			return Math.round(h);
		}

		function updateSidebarFade(sidebar, list) {
			if (!sidebar || !list) {
				return;
			}
			var fade = sidebar.querySelector('.emerson-ca-sidebar-fade');
			if (!fade) {
				return;
			}
			var atBottom = list.scrollTop + list.clientHeight >= list.scrollHeight - 4;
			fade.classList.toggle('is-hidden', atBottom);
		}

		function ensureSidebarFade(sidebar, list) {
			if (!sidebar || !list) {
				return;
			}
			var fade = sidebar.querySelector('.emerson-ca-sidebar-fade');
			var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if (!fade) {
				fade = document.createElement('div');
				fade.className = 'emerson-ca-sidebar-fade';
				fade.setAttribute('aria-hidden', 'true');
				fade.innerHTML = '<span class="emerson-ca-sidebar-fade__arrow"></span>';
				sidebar.appendChild(fade);
				if (!reduced) {
					fade.classList.add('emerson-ca-sidebar-fade--bounce');
					var arrow = fade.querySelector('.emerson-ca-sidebar-fade__arrow');
					if (arrow) {
						arrow.addEventListener(
							'animationend',
							function () {
								fade.classList.remove('emerson-ca-sidebar-fade--bounce');
							},
							{ once: true }
						);
					}
				}
			}
			if (!list._emersonFadeOnScroll) {
				list._emersonFadeOnScroll = function () {
					updateSidebarFade(sidebar, list);
				};
				list.addEventListener('scroll', list._emersonFadeOnScroll, { passive: true });
			}
			updateSidebarFade(sidebar, list);
		}

		function limitSidebarScroll(list) {
			if (!list) {
				return;
			}
			var sidebar = list.closest('.ca-calendar-sidebar');
			var events = sidebarEventBlocks(list);
			if (events.length <= 3) {
				clearSidebarFade(sidebar, list);
				return;
			}

			var render = document.querySelector('.ca-calendar-render');
			var title = sidebar ? sidebar.querySelector('.ca-sidebar-title-area') : null;
			var titleH = title ? title.getBoundingClientRect().height : 0;
			var stacked = window.matchMedia('(max-width: 1000px)').matches;
			var gridH = calendarGridContentHeight(render);
			var listH;
			var shellH;

			if (stacked) {
				// Compact week grid is short; give the info pane enough room under Today/date.
				listH = Math.max(320, Math.min(Math.floor(window.innerHeight * 0.58), 480));
				shellH = 0;
			} else {
				if (gridH < 160) {
					clearSidebarFade(sidebar, list);
					return;
				}
				listH = Math.max(160, gridH - Math.ceil(titleH) - 8);
				shellH = gridH;
			}

			// Avoid tear-down/rebuild on MutationObserver passes (that was the scroll jitter).
			var key = String(events.length) + ':' + listH + ':' + (stacked ? 's' : 'd');
			if (list._emersonScrollKey === key && list.classList.contains('emerson-ca-sidebar-scroll')) {
				updateSidebarFade(sidebar, list);
				return;
			}
			list._emersonScrollKey = key;
			if (sidebar) {
				sidebar.classList.add('emerson-ca-sidebar--scroll');
				if (shellH > 0) {
					// Desktop: lock the column to the grid so event text cannot lengthen the row.
					sidebar.style.height = shellH + 'px';
					sidebar.style.maxHeight = shellH + 'px';
				} else {
					sidebar.style.height = '';
					sidebar.style.maxHeight = '';
				}
			}
			list.style.maxHeight = listH + 'px';
			list.classList.add('emerson-ca-sidebar-scroll');

			window.requestAnimationFrame(function () {
				if (!list.classList.contains('emerson-ca-sidebar-scroll')) {
					return;
				}
				if (list.scrollHeight <= list.clientHeight + 2) {
					clearSidebarFade(sidebar, list);
					return;
				}
				ensureSidebarFade(sidebar, list);
			});
		}

		function shrinkPlaces(root, places) {
			if (!root || !places.length) {
				return;
			}
			root.querySelectorAll('.emerson-ca-place').forEach(function (span) {
				var parent = span.parentNode;
				while (span.firstChild) {
					parent.insertBefore(span.firstChild, span);
				}
				parent.removeChild(span);
			});
			var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
			var hits = [];
			var node;
			while ((node = walker.nextNode())) {
				var text = node.textContent.replace(/\s+/g, ' ').trim();
				if (places.indexOf(text) === -1) {
					continue;
				}
				if (node.parentElement && node.parentElement.classList.contains('emerson-ca-service__location')) {
					continue;
				}
				hits.push(node);
			}
			hits.forEach(function (textNode) {
				var span = document.createElement('span');
				span.className = 'emerson-ca-place';
				textNode.parentNode.insertBefore(span, textNode);
				span.appendChild(textNode);
			});
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
				var dayStyle = window.getComputedStyle(day);
				var width =
					day.clientWidth -
					(parseFloat(dayStyle.paddingLeft) || 0) -
					(parseFloat(dayStyle.paddingRight) || 0) -
					2;
				if (width < 12) {
					return;
				}
				function painted(block) {
					return block.querySelector('.emerson-ca-requests__button') || block;
				}
				function widest(target) {
					var cs = window.getComputedStyle(target);
					probe.style.font = cs.font;
					probe.style.letterSpacing = cs.letterSpacing;
					var max = 0;
					var text = target.classList.contains('emerson-ca-requests__button')
						? (target.querySelector('span') || target).textContent
						: target.innerText || '';
					text
						.split(/\s+/)
						.filter(Boolean)
						.forEach(function (word) {
							probe.textContent = word;
							max = Math.max(max, probe.offsetWidth);
						});
					return max;
				}
				function room(block) {
					return width;
				}
				function fitRequests(button) {
					var label = button.querySelector('span');
					if (!label || button.clientWidth < 12) {
						return;
					}
					var count = button.querySelector('.emerson-ca-requests__count');
					var parentSize = parseFloat(window.getComputedStyle(button.parentElement || button).fontSize) || 16;
					var natural = window.matchMedia('(max-width: 1000px)').matches
						? parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16
						: parentSize * 0.75;
					var ref = 16;
					var buttonStyle = window.getComputedStyle(button);
					probe.style.font = buttonStyle.font;
					probe.style.letterSpacing = buttonStyle.letterSpacing;
					probe.style.fontWeight = buttonStyle.fontWeight;
					probe.style.fontSize = ref + 'px';
					var word = 0;
					label.textContent
						.split(/\s+/)
						.filter(Boolean)
						.forEach(function (part) {
							probe.textContent = part;
							word = Math.max(word, probe.offsetWidth);
						});
					var badge = 0;
					if (count) {
						probe.style.fontSize = ref * 0.85 + 'px';
						probe.textContent = count.textContent || '';
						badge = Math.max(probe.offsetWidth, ref * 0.85 * 1.4) + ref * 0.85 * 0.7;
					}
					var pad = (parseFloat(buttonStyle.paddingLeft) || 0) + (parseFloat(buttonStyle.paddingRight) || 0);
					var gap = parseFloat(buttonStyle.columnGap);
					if (isNaN(gap)) {
						gap = parseFloat(buttonStyle.gap) || 0;
					}
					var current = parseFloat(buttonStyle.fontSize) || natural;
					var padScales = Math.abs(pad - current * 0.8) < 2;
					var gapScales = Math.abs(gap - current * 0.35) < 1.25;
					var fixed = (padScales ? 0 : pad) + (gapScales ? 0 : gap);
					// Small sizes render a little wider than a straight scale, so the fit stays inside the count.
					var scaled = (word + badge) * 1.08 + (padScales ? ref * 0.8 : 0) + (gapScales ? ref * 0.35 : 0);
					if (scaled < 1) {
						return;
					}
					var size = Math.min(natural, Math.max(8, ((button.clientWidth - fixed) * ref) / scaled));
					if (size >= natural - 0.2) {
						button.style.fontSize = '';
						return;
					}
					button.style.fontSize = size.toFixed(2) + 'px';
				}
				Array.prototype.forEach.call(blocks, function (block) {
					var button = block.querySelector('.emerson-ca-requests__button');
					if (button) {
						fitRequests(button);
						return;
					}
					var target = painted(block);
					target.style.fontSize = '';
					var size = parseFloat(window.getComputedStyle(target).fontSize) || 11;
					var guard = 0;
					while (guard < 24 && size > 8 && widest(target) > room(block)) {
						size = Math.max(8, Math.round((size - 0.4) * 10) / 10);
						target.style.fontSize = size + 'px';
						guard += 1;
					}
				});
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

		// Site timezone (WP Settings / emersonChapel). Avoid UTC “tomorrow” after evening Central.
		function todayYmd() {
			if (window.emersonChapel && emersonChapel.today) {
				return String(emersonChapel.today);
			}
			var tz = (window.emersonChapel && emersonChapel.timezone) || 'America/Chicago';
			try {
				return new Intl.DateTimeFormat('en-CA', {
					timeZone: tz,
					year: 'numeric',
					month: '2-digit',
					day: '2-digit'
				}).format(new Date());
			} catch (error) {
				return formatYmd(new Date());
			}
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

		/*
		 * Church Admin pads the month grid with empty .ca-inactive cells and does
		 * not put adjacent-month dates on them. Compact week still needs Sun–Sat
		 * when a week crosses months (e.g. Sep 27–Oct 3), so we temporarily
		 * promote those padding cells for the visible week.
		 */
		function resetWeekSpillDays() {
			document.querySelectorAll('.ca-day.emerson-ca-week-spill').forEach(function (day) {
				if (day.classList.contains('emerson-ca-week-spill-host')) {
					day.remove();
					return;
				}
				day.classList.remove('emerson-ca-week-spill', 'is-compact-day', 'ca-current');
				day.classList.add('ca-inactive');
				day.removeAttribute('data-date');
				day.innerHTML = '';
			});
		}

		function markCompactDay(day, ymd) {
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
			if (!day.querySelector('.emerson-ca-daynum') && !day.querySelector('.ca-today')) {
				var num = document.createElement('span');
				num.className = 'emerson-ca-daynum';
				num.textContent = String(parseInt(ymd.slice(8), 10));
				day.appendChild(num);
			}
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
		}

		function fillMissingCompactDays(week) {
			var missing = week.filter(function (ymd) {
				return !hasDatedDay(ymd);
			});
			if (!missing.length) {
				return false;
			}
			var row =
				document.querySelector('.ca-week:has(.is-compact-day)') ||
				document.querySelector('.ca-week:has(.ca-day.ca-inactive)');
			if (!row) {
				return false;
			}
			// Padding cells in the same CA week row (not yet marked compact).
			var slots = Array.prototype.filter.call(row.querySelectorAll('.ca-day'), function (day) {
				return !day.classList.contains('is-compact-day');
			});
			var used = false;
			missing.forEach(function (ymd, index) {
				var day = slots[index];
				if (!day) {
					day = document.createElement('div');
					day.className = 'ca-day emerson-ca-week-spill-host';
					row.appendChild(day);
				}
				day.classList.remove('ca-inactive');
				day.classList.add('emerson-ca-week-spill');
				day.setAttribute('data-date', ymd);
				day.innerHTML = '';
				markCompactDay(day, ymd);
				used = true;
			});
			return used;
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

		/**
		 * “Previous week” is wider than “Next week”. Match both to the larger
		 * width so a centred date label also has even gaps (CSS alone cannot).
		 */
		function balanceWeekNavButtons(nav) {
			if (!nav || nav.hidden) {
				return;
			}
			var prev = nav.querySelector('.emerson-ca-week-nav__prev');
			var next = nav.querySelector('.emerson-ca-week-nav__next');
			if (!prev || !next) {
				return;
			}
			prev.style.width = '';
			next.style.width = '';
			var width = Math.ceil(
				Math.max(prev.getBoundingClientRect().width, next.getBoundingClientRect().width)
			);
			if (width > 0) {
				prev.style.width = width + 'px';
				next.style.width = width + 'px';
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
			balanceWeekNavButtons(nav);
		}

		function shiftCompactWeek(delta) {
			var start = weekStartYmd || sundayOf(todayYmd());
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
			resetWeekSpillDays();
			document.querySelectorAll('.ca-day[data-date], .ca-day.emerson-ca-week-spill-host').forEach(function (day) {
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
			var days = datedDays();
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
			var today = todayYmd();
			var current = document.querySelector('.ca-day.ca-current[data-date]:not(.ca-inactive)');
			if (!weekStartYmd) {
				if (hasDatedDay(today)) {
					weekStartYmd = sundayOf(today);
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
				markCompactDay(day, ymd);
			});
			var filled = fillMissingCompactDays(week);
			if (filled) {
				// Spill cells get data-date after the first decorateDays pass.
				decorateDays();
			}
			if (render) {
				ensureWeekNav(render);
			}
		}

		/*
		 * Church Admin only selects a day on click (sets .ca-current + day-render).
		 * Month load can leave today’s sidebar thin until that click — do it once
		 * when today is on the grid and nothing is selected yet.
		 */
		function ensureTodaySelected() {
			if (document.querySelector('.ca-day.ca-current[data-date]')) {
				return;
			}
			var today = todayYmd();
			var day = document.querySelector(
				'.ca-day[data-date="' + today + '"]:not(.ca-inactive)'
			);
			if (!day) {
				return;
			}
			day.click();
		}

		function decorate() {
			closeRequests();
			updateTheme();
			decorateDays();
			ensureTodaySelected();
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
					weekStartYmd = sundayOf(todayYmd());
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
		var fitFrame = 0;
		window.addEventListener('resize', function () {
			if (fitFrame) {
				window.cancelAnimationFrame(fitFrame);
			}
			fitFrame = window.requestAnimationFrame(function () {
				fitFrame = 0;
				fitDayCopy();
				if (requestsPanel) {
					placeRequests();
				}
			});
			if (fitTimer) {
				clearTimeout(fitTimer);
			}
			fitTimer = setTimeout(function () {
				applyCompactWeek();
				fitDayCopy();
				var sideList = document.querySelector('.ca-sidebar-list');
				if (sideList) {
					sideList._emersonScrollKey = '';
					limitSidebarScroll(sideList);
				}
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
						speaker: row.speaker || '',
						location: row.location || ''
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

	// Who Are We collage: nine tiles, each on its own timer. A tile will not flip while the one beside it is flipping.
	(function emersonWhoCollage() {
		var gallery = document.querySelector('.emerson-who-collage');
		if (!gallery) {
			return;
		}
		var all = Array.prototype.slice.call(gallery.querySelectorAll(':scope > .wp-block-image'));
		if (all.length < 10) {
			return;
		}
		function fileOf(src) {
			return String(src || '').split('/').pop().split('?')[0];
		}
		function picture(tile) {
			var image = tile.querySelector('img');
			if (!image) {
				return null;
			}
			return { src: image.getAttribute('src'), alt: image.alt || '', file: fileOf(image.getAttribute('src')) };
		}
		var deck = all.map(picture).filter(Boolean);
		var tiles = all.slice(0, 9);
		all.slice(9).forEach(function (tile) {
			tile.hidden = true;
		});
		var waiting = deck.slice(9);
		var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		function isFlipping(tile) {
			return tile.classList.contains('is-flipping');
		}

		function beside(index) {
			var mates = [];
			if (index % 3 !== 0) {
				mates.push(tiles[index - 1]);
			}
			if (index % 3 !== 2) {
				mates.push(tiles[index + 1]);
			}
			return mates;
		}

		function show(tile, next) {
			var image = tile.querySelector('img');
			if (!image) {
				return;
			}
			waiting.push({ src: image.getAttribute('src'), alt: image.alt || '', file: fileOf(image.getAttribute('src')) });
			image.removeAttribute('srcset');
			image.removeAttribute('sizes');
			image.src = next.src;
			image.alt = next.alt;
		}

		function flip(tile, next, direction) {
			if (reduced || typeof tile.animate !== 'function') {
				show(tile, next);
				return;
			}
			var image = tile.querySelector('img');
			if (!image) {
				return;
			}
			var incoming = new Image();
			incoming.src = next.src;
			var run = function () {
				var turned = direction === 'up' ? 1 : -1;
				var swapped = false;
				var finished = false;
				var duration = 1120;
				var timing = { duration: duration, easing: 'cubic-bezier(0.45, 0.05, 0.55, 0.95)', fill: 'both' };
				var swap = function () {
					if (swapped) {
						return;
					}
					swapped = true;
					show(tile, next);
				};
				var close = function () {
					if (finished) {
						return;
					}
					finished = true;
					swap();
					try {
						spin.cancel();
					} catch (error) {}
					try {
						counter.cancel();
					} catch (error) {}
					tile.style.willChange = '';
					image.style.willChange = '';
					tile.classList.remove('is-flipping');
				};
				tile.classList.add('is-flipping');
				tile.style.willChange = 'transform';
				image.style.willChange = 'transform';
				var spin = tile.animate([
					{ transform: 'perspective(2400px) rotateX(0deg)' },
					{ transform: 'perspective(2400px) rotateX(' + (turned * 180) + 'deg)' }
				], timing);
				var counter = image.animate([
					{ transform: 'rotateX(0deg)', offset: 0 },
					{ transform: 'rotateX(0deg)', offset: 0.5 },
					{ transform: 'rotateX(180deg)', offset: 0.5 },
					{ transform: 'rotateX(180deg)', offset: 1 }
				], timing);
				var watch = function () {
					if (swapped || finished) {
						return;
					}
					if (spin.currentTime >= duration * 0.5) {
						swap();
						return;
					}
					window.requestAnimationFrame(watch);
				};
				window.requestAnimationFrame(watch);
				spin.onfinish = close;
				counter.onfinish = close;
			};
			if (typeof incoming.decode === 'function') {
				incoming.decode().then(run, run);
			} else {
				run();
			}
		}

		function turn(index) {
			if (document.hidden) {
				return false;
			}
			if (isFlipping(tiles[index]) || beside(index).some(isFlipping)) {
				return false;
			}
			var visible = tiles.map(function (tile) {
				var image = tile.querySelector('img');
				return image ? fileOf(image.getAttribute('src')) : '';
			});
			var choices = waiting.filter(function (item) {
				return visible.indexOf(item.file) === -1;
			});
			if (!choices.length) {
				return false;
			}
			var next = choices[Math.floor(Math.random() * choices.length)];
			waiting.splice(waiting.indexOf(next), 1);
			flip(tiles[index], next, Math.random() < 0.5 ? 'up' : 'down');
			return true;
		}

		function wave() {
			if (document.hidden) {
				return;
			}
			var picks = [0, 1, 2].map(function (row) {
				var columns = [0, 1, 2].filter(function (column) {
					var index = row * 3 + column;
					return !isFlipping(tiles[index]) && !beside(index).some(isFlipping);
				});
				if (!columns.length) {
					return -1;
				}
				return row * 3 + columns[Math.floor(Math.random() * columns.length)];
			}).filter(function (index) {
				return index >= 0;
			});
			picks.forEach(function (index, order) {
				window.setTimeout(function () {
					turn(index);
				}, order * (280 + Math.random() * 220));
			});
		}

		window.setTimeout(wave, 800);
		window.setInterval(wave, 5000);
	})();

	(function emersonMobileNavAnimate() {
		var openBtn = document.querySelector('header .wp-block-navigation__responsive-container-open');
		var panel = document.querySelector('header .wp-block-navigation__responsive-container');
		if (!openBtn || !panel) {
			return;
		}
		var closeBtn = panel.querySelector('.wp-block-navigation__responsive-container-close');
		var burgerMarkup =
			'<span class="emerson-nav-burger__box" aria-hidden="true">' +
			'<span></span><span></span><span></span>' +
			'</span>';

		function paintBurger(btn) {
			if (!btn) {
				return;
			}
			btn.classList.add('emerson-nav-burger');
			btn.innerHTML = burgerMarkup;
		}

		paintBurger(openBtn);
		paintBurger(closeBtn);

		var mobileQuery = window.matchMedia('(max-width: 599px)');
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
		var wasOpen = panel.classList.contains('is-menu-open');
		var openLayout = null;

		function setIconOpen(isOpen) {
			openBtn.classList.toggle('is-x', isOpen);
			if (closeBtn) {
				closeBtn.classList.toggle('is-x', isOpen);
			}
		}

		function clearStuckModalLock() {
			if (panel.classList.contains('is-menu-open')) {
				return;
			}
			document.documentElement.classList.remove('has-modal-open');
			document.body.classList.remove('has-modal-open');
		}

		function cacheOpenLayout() {
			var dialog = panel.querySelector('.wp-block-navigation__responsive-dialog');
			var content = panel.querySelector('.wp-block-navigation__responsive-container-content');
			var closeWrap = panel.querySelector('.wp-block-navigation__responsive-close');
			openLayout = {
				dialogMarginTop: dialog ? window.getComputedStyle(dialog).marginTop : '',
				contentPaddingTop: content ? window.getComputedStyle(content).paddingTop : '',
				closePaddingTop: closeWrap ? window.getComputedStyle(closeWrap).paddingTop : '',
				panelPadding:
					window.getComputedStyle(panel).paddingTop +
					' ' +
					window.getComputedStyle(panel).paddingRight +
					' ' +
					window.getComputedStyle(panel).paddingBottom +
					' ' +
					window.getComputedStyle(panel).paddingLeft
			};
		}

		function playExitSlide() {
			if (!mobileQuery.matches || reduceMotion.matches) {
				document.documentElement.classList.remove('emerson-nav-exiting');
				return;
			}
			document.querySelectorAll('.emerson-nav-exit-ghost').forEach(function (node) {
				node.remove();
			});
			var ghost = panel.cloneNode(true);
			var exitDone = false;
			ghost.removeAttribute('id');
			// Keep is-menu-open so WordPress overlay layout (column links) still applies.
			ghost.classList.remove('has-modal-open');
			ghost.classList.add('is-menu-open', 'emerson-nav-exit-ghost');
			ghost.setAttribute('aria-hidden', 'true');
			ghost.querySelectorAll('[id]').forEach(function (node) {
				node.removeAttribute('id');
			});
			// Apply spacing captured while open so Chrome’s first paint doesn’t jump
			// after html.has-modal-open (admin-bar offset) is already gone.
			if (openLayout) {
				ghost.style.padding = openLayout.panelPadding;
				var ghostDialog = ghost.querySelector('.wp-block-navigation__responsive-dialog');
				var ghostContent = ghost.querySelector('.wp-block-navigation__responsive-container-content');
				var ghostClose = ghost.querySelector('.wp-block-navigation__responsive-close');
				if (ghostDialog && openLayout.dialogMarginTop) {
					ghostDialog.style.marginTop = openLayout.dialogMarginTop;
				}
				if (ghostContent && openLayout.contentPaddingTop) {
					ghostContent.style.paddingTop = openLayout.contentPaddingTop;
				}
				if (ghostClose && openLayout.closePaddingTop) {
					ghostClose.style.paddingTop = openLayout.closePaddingTop;
				}
			}
			function endExitSlide() {
				if (exitDone) {
					return;
				}
				exitDone = true;
				if (ghost.parentNode) {
					ghost.remove();
				}
				// Reveal the real burger only after the ghost (and X→☰ morph) is gone.
				document.documentElement.classList.remove('emerson-nav-exiting');
			}
			// Cover immediately — never append hidden (that flashes the page underneath).
			document.body.appendChild(ghost);
			void ghost.offsetHeight;
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () {
					ghost.classList.add('is-leaving');
					// Fallback only after the 0.4s slide can finish (started on this frame).
					window.setTimeout(endExitSlide, 500);
				});
			});
			ghost.addEventListener('transitionend', function (event) {
				if (event.target !== ghost || event.propertyName !== 'transform') {
					return;
				}
				endExitSlide();
			});
		}

		new MutationObserver(function () {
			var isOpen = panel.classList.contains('is-menu-open');
			if (!wasOpen && isOpen) {
				document.documentElement.classList.remove('emerson-nav-exiting');
				// Paint ☰ first, then morph to X with the slide (avoids instant X).
				setIconOpen(false);
				window.requestAnimationFrame(function () {
					setIconOpen(true);
				});
				cacheOpenLayout();
				window.requestAnimationFrame(cacheOpenLayout);
			}
			if (wasOpen && !isOpen) {
				// Hold the “menu open” cover before WP’s closed panel can flash the page.
				document.documentElement.classList.add('emerson-nav-exiting');
				playExitSlide();
				setIconOpen(false);
				clearStuckModalLock();
				openLayout = null;
			}
			wasOpen = isOpen;
		}).observe(panel, { attributes: true, attributeFilter: ['class'] });

		setIconOpen(wasOpen);
		if (wasOpen) {
			cacheOpenLayout();
		}
		clearStuckModalLock();
		window.addEventListener('resize', clearStuckModalLock);
	})();

	var foilButton = document.querySelector('.emerson-foil-toggle');
	var foilCanvas = document.querySelector('.emerson-foil__canvas');
	var foilHideTimer = 0;
	var foilSeq = 0;
	var foilOrder = null;
	var foilReveal = null;
	var foilHide = null;
	/* At most one in-flight animation + one pending toggle (latest click wins). */
	var foilBusy = false;
	var foilPending = null;

	function foilRoot() {
		return document.documentElement;
	}
	function foilSheetEl() {
		return document.querySelector('.emerson-foil__sheet');
	}
	function foilLayerEl() {
		return document.querySelector('.emerson-foil-layer');
	}
	function foilShineSeconds() {
		var raw = parseFloat(
			window.getComputedStyle(foilRoot()).getPropertyValue('--emerson-foil-shine-seconds')
		);
		return raw > 0 ? raw : 3.2;
	}
	/** Show/hide wipe duration — independent of ambient shine pass. */
	function foilWipeSeconds() {
		var raw = parseFloat(
			window.getComputedStyle(foilRoot()).getPropertyValue('--emerson-foil-wipe-seconds')
		);
		return raw > 0 ? raw : 4;
	}
	function foilShineAngleDeg() {
		var raw = parseFloat(
			window.getComputedStyle(foilRoot()).getPropertyValue('--emerson-foil-shine-angle')
		);
		return window.isFinite(raw) ? raw : 0;
	}
	/**
	 * Wipe mask-position must travel along the shine gradient, not always on X.
	 * Shine 90° → gradient 180° (vertical); X-only animation used to flash the wash.
	 */
	function foilSyncWipeAxis() {
		var root = foilRoot();
		var theta = ((90 + foilShineAngleDeg()) * Math.PI) / 180;
		var sin = Math.sin(theta);
		var cos = Math.cos(theta);
		var closed =
			(50 + 50 * sin).toFixed(3) + '% ' + (50 - 50 * cos).toFixed(3) + '%';
		var open =
			(50 - 50 * sin).toFixed(3) + '% ' + (50 + 50 * cos).toFixed(3) + '%';
		root.style.setProperty('--emerson-foil-wipe-closed', closed);
		root.style.setProperty('--emerson-foil-wipe-open', open);
		return { closed: closed, open: open };
	}
	function foilReduceMotion() {
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}
	function foilRandomizeOn() {
		return !!(foilCanvas && foilCanvas.getAttribute('data-randomize') === '1');
	}
	function foilBumpSeq() {
		foilSeq += 1;
		return foilSeq;
	}
	function foilStill(seq) {
		return seq === foilSeq;
	}
	function foilClearMotionClasses() {
		foilRoot().classList.remove(
			'emerson-foil-ready',
			'emerson-foil-wiping-in',
			'emerson-foil-wiping-out',
			'emerson-foil-drifting',
			'emerson-foil-shining',
			'emerson-foil-fading-out',
			'emerson-foil-hiding',
			'emerson-foil-safari-static'
		);
	}
	/**
	 * Safari / iOS WebKit (not Chrome/Edge/Firefox). Foil’s fixed overlay is a
	 * sibling of the page — not a parent of the YouTube iframe — so this is not a
	 * z-index fight. WebKit still blacks out HTML5/YouTube video when a
	 * full-viewport fixed layer keeps translateZ + continuous transform
	 * animations (drift sheet + shine) in the same compositor as the player.
	 *
	 * Homepage sermon is click-to-load. Ambient motion pauses only while that
	 * video is actually playing (YouTube API); pause/end resumes drift+shine.
	 * Other pages’ YouTube iframes still count as risk while present.
	 */
	var foilLiveVideoPlaying = false;
	function foilIsSafariWebKit() {
		var ua = navigator.userAgent || '';
		if (/CriOS|FxiOS|EdgiOS|Chrome|Chromium|Edg\/|OPR\//i.test(ua)) {
			return false;
		}
		return /Safari/i.test(ua) || (/AppleWebKit/i.test(ua) && /Mobile/i.test(ua));
	}
	/** Gallery/other embeds, HTML5 video, or homepage sermon currently playing. */
	function foilPageHasWebKitVideoRisk() {
		if (foilLiveVideoPlaying) {
			return true;
		}
		var iframes = document.querySelectorAll(
			'iframe[src*="youtube.com"], iframe[src*="youtube-nocookie.com"], iframe[src*="youtu.be"]'
		);
		var i;
		for (i = 0; i < iframes.length; i++) {
			if (!iframes[i].closest('.emerson-latest-sermon')) {
				return true;
			}
		}
		return !!document.querySelector('video');
	}
	function foilNeedsSafariVideoSafe() {
		return foilIsSafariWebKit() && foilPageHasWebKitVideoRisk();
	}
	/**
	 * Pause ambient motion in place (CSS animation-play-state) so resume does not
	 * restart drift/shine at 0% and so the GPU stops advancing frames.
	 */
	function foilApplySafariVideoSafe() {
		var root = foilRoot();
		var layer = foilLayerEl();
		var sheet = foilSheetEl();
		root.classList.add('emerson-foil-safari-static');
		foilClearMaskHold(layer);
		foilClearMaskHold(sheet);
		if (layer) {
			layer.style.setProperty('transform', 'none');
		}
		if (sheet) {
			sheet.style.setProperty('will-change', 'auto');
		}
	}
	function foilClearSafariVideoSafe() {
		var root = foilRoot();
		var layer = foilLayerEl();
		var sheet = foilSheetEl();
		root.classList.remove('emerson-foil-safari-static');
		if (layer) {
			layer.style.removeProperty('transform');
		}
		if (sheet) {
			sheet.style.removeProperty('will-change');
		}
	}
	function foilEffectiveOn() {
		var root = foilRoot();
		return (
			root.classList.contains('emerson-foil') &&
			!root.classList.contains('emerson-foil-wiping-out')
		);
	}
	function foilSyncTabHidden() {
		foilRoot().classList.toggle('emerson-foil-tab-hidden', !!document.hidden);
	}
	document.addEventListener('visibilitychange', foilSyncTabHidden);
	foilSyncTabHidden();
	/** Resume drift+shine when Safari video risk clears (e.g. sermon paused). */
	function foilResumeAmbient() {
		var root = foilRoot();
		var sheet = foilSheetEl();
		if (!foilEffectiveOn() || !root.classList.contains('emerson-foil-ready')) {
			return;
		}
		if (
			root.classList.contains('emerson-foil-wiping-in') ||
			root.classList.contains('emerson-foil-wiping-out')
		) {
			return;
		}
		if (foilNeedsSafariVideoSafe()) {
			foilApplySafariVideoSafe();
			return;
		}
		foilClearSafariVideoSafe();
		/* Keep existing drifting/shining classes — only un-pause play-state. */
		if (!root.classList.contains('emerson-foil-drifting')) {
			root.classList.add('emerson-foil-drifting');
		}
		if (!root.classList.contains('emerson-foil-shining')) {
			root.classList.add('emerson-foil-shining');
		}
		window.requestAnimationFrame(function () {
			if (!sheet || !foilEffectiveOn()) {
				return;
			}
			sheet.style.removeProperty('transform');
		});
	}
	/** After wipe-in: drift+shine, unless Safari has active video risk. */
	function foilEnterAmbient(seq) {
		var root = foilRoot();
		var sheet = foilSheetEl();
		if (!foilStill(seq) || !root.classList.contains('emerson-foil-ready')) {
			return;
		}
		root.classList.add('emerson-foil-drifting');
		root.classList.add('emerson-foil-shining');
		if (foilNeedsSafariVideoSafe()) {
			foilApplySafariVideoSafe();
			return;
		}
		foilClearSafariVideoSafe();
		window.requestAnimationFrame(function () {
			if (!foilStill(seq) || !sheet) {
				return;
			}
			sheet.style.removeProperty('transform');
		});
	}
	/**
	 * Homepage YouTube API (detail.playing) or late inject: pause/resume ambient
	 * in Safari for the current play state.
	 */
	function foilOnLiveVideoEvent(event) {
		var detail = event && event.detail ? event.detail : {};
		foilLiveVideoPlaying = !!detail.playing;
		if (!foilIsSafariWebKit() || !foilEffectiveOn()) {
			return;
		}
		if (foilLiveVideoPlaying) {
			foilApplySafariVideoSafe();
		} else {
			foilResumeAmbient();
		}
	}
	document.addEventListener('emerson-live-video', foilOnLiveVideoEvent);
	/** Same gradient the CSS wipe uses — keep holds visually identical to the animation. */
	function foilWipeMaskImage() {
		return (
			'linear-gradient(calc(90deg + var(--emerson-foil-shine-angle, 0deg)),' +
			'#000 0%,#000 42%,transparent 58%,transparent 100%)'
		);
	}
	function foilApplyWipeMaskHold(el, position) {
		if (!el) {
			return;
		}
		var img = foilWipeMaskImage();
		el.style.setProperty('-webkit-mask-image', img);
		el.style.setProperty('mask-image', img);
		el.style.setProperty('-webkit-mask-size', '300% 300%');
		el.style.setProperty('mask-size', '300% 300%');
		el.style.setProperty('-webkit-mask-repeat', 'no-repeat');
		el.style.setProperty('mask-repeat', 'no-repeat');
		el.style.setProperty('-webkit-mask-position', position);
		el.style.setProperty('mask-position', position);
		el.style.setProperty('animation', 'none');
	}
	/** Hold mask fully closed (wipe-out end / wipe-in start). */
	function foilHoldMaskClosed(el) {
		foilApplyWipeMaskHold(el, foilSyncWipeAxis().closed);
	}
	/** Hold mask fully open before dropping wiping-in. */
	function foilHoldMaskOpen(el) {
		foilApplyWipeMaskHold(el, foilSyncWipeAxis().open);
	}
	function foilClearMaskHold(el) {
		if (!el) {
			return;
		}
		el.style.removeProperty('animation');
		el.style.removeProperty('-webkit-mask-position');
		el.style.removeProperty('mask-position');
		el.style.removeProperty('-webkit-mask-image');
		el.style.removeProperty('mask-image');
		el.style.removeProperty('-webkit-mask-size');
		el.style.removeProperty('mask-size');
		el.style.removeProperty('-webkit-mask-repeat');
		el.style.removeProperty('mask-repeat');
	}
	/** Hide layer with no opacity transition — call while wipe mask is still shut. */
	function foilSnapLayerHidden(layer) {
		if (!layer) {
			return;
		}
		layer.style.setProperty('transition', 'none');
		layer.style.setProperty('opacity', '0');
		layer.style.setProperty('visibility', 'hidden');
		void layer.offsetWidth;
	}
	function foilClearLayerSnap(layer) {
		if (!layer) {
			return;
		}
		layer.style.removeProperty('transition');
		layer.style.removeProperty('opacity');
		layer.style.removeProperty('visibility');
	}
	/** Full reset used before a fresh paint, not mid-hide. */
	function foilForceMaskClosed() {
		var sheet = foilSheetEl();
		var layer = foilLayerEl();
		/* Hide first — clearing wiping-out used to flash a full unmasked frame. */
		foilSnapLayerHidden(layer);
		foilClearMotionClasses();
		foilClearSheetTransform(sheet);
		foilHoldMaskClosed(sheet);
		foilHoldMaskClosed(layer);
		if (sheet) {
			void sheet.offsetWidth;
		}
		if (layer) {
			void layer.offsetWidth;
		}
		foilClearMaskHold(sheet);
		foilClearMaskHold(layer);
		/* Leave layer snapped shut; wipe-in clears the snap when it starts. */
		if (sheet) {
			void sheet.offsetWidth;
		}
	}
	function foilFreezeSheetTransform(sheet) {
		if (!sheet) {
			return;
		}
		var transform = window.getComputedStyle(sheet).transform;
		sheet.style.transform = transform && transform !== 'none' ? transform : 'translate3d(0, 0, 0)';
	}
	function foilClearSheetTransform(sheet) {
		if (!sheet) {
			return;
		}
		sheet.style.removeProperty('transform');
	}
	function foilShuffle(symbols) {
		var list = symbols.slice();
		var i;
		var j;
		var swap;
		for (i = list.length - 1; i > 0; i--) {
			j = Math.floor(Math.random() * (i + 1));
			swap = list[i];
			list[i] = list[j];
			list[j] = swap;
		}
		return list;
	}
	function foilApplyShineMask(shine) {
		/*
		 * Shine is a viewport-sized sibling of the sheet (not masked to the canvas).
		 * The old canvas PNG mask made the band effectively invisible.
		 */
		if (!shine) {
			return;
		}
		shine.style.removeProperty('width');
		shine.style.removeProperty('height');
		shine.style.removeProperty('mask-image');
		shine.style.removeProperty('-webkit-mask-image');
		shine.style.removeProperty('mask-size');
		shine.style.removeProperty('-webkit-mask-size');
		shine.style.removeProperty('mask-repeat');
		shine.style.removeProperty('-webkit-mask-repeat');
	}
	/**
	 * Draw icons only (no toDataURL). Must run while the wipe mask is shut.
	 * reshuffle=true picks a new order when Randomize is on; otherwise reuse foilOrder.
	 * Returns {width,height} for a later shine-mask build, or null.
	 */
	function foilPaint(symbolsSource, reshuffle) {
		if (!foilCanvas || !symbolsSource.length) {
			return null;
		}
		if (reshuffle || !foilOrder || foilOrder.length !== symbolsSource.length) {
			foilOrder = foilRandomizeOn() ? foilShuffle(symbolsSource) : symbolsSource.slice();
		}
		var symbols = foilOrder;
		/*
		 * Stamp each glyph into a cell-sized bitmap, then drawImage onto the field.
		 * iOS Color Emoji ignores ctx.clip(), and the purple religious “plate” symbols
		 * (peace, dharma, star of David, …) paint larger than font-size on iPhone while
		 * fitting fine on desktop — so we shrink per unique symbol until ink stays
		 * inside a padded box.
		 */
		var cols = 30;
		var rows = 20;
		var cell = 120;
		var gap = 16;
		var glyph = 52;
		var width = cols * cell + (cols - 1) * gap;
		var height = rows * cell + (rows - 1) * gap;
		var layer = foilCanvas.closest('.emerson-foil-layer');
		if (layer) {
			layer.style.position = 'fixed';
			layer.style.inset = '0';
			layer.style.overflow = 'hidden';
			layer.style.pointerEvents = 'none';
			layer.style.zIndex = '0';
		}
		foilCanvas.width = width;
		foilCanvas.height = height;
		var ctx = foilCanvas.getContext('2d');
		var fontStack = '"Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif';
		var pad = Math.max(8, Math.round(cell * 0.12));
		var stampCache = Object.create(null);

		/*
		 * Purple “plate” religious symbols (peace, dharma, om, …). On iPhone these
		 * paint as solid squares larger than font-size; colorful emoji do not.
		 */
		function foilIsPlateSymbol(sym) {
			var cp = sym.codePointAt(0);
			return (
				cp === 0x262f ||
				cp === 0x2638 ||
				cp === 0x262e ||
				cp === 0x262a ||
				cp === 0x271d ||
				cp === 0x2721 ||
				cp === 0x1f549 ||
				cp === 0x267b ||
				cp === 0x2626
			);
		}

		function foilInkHitsPad(sctx) {
			var img = sctx.getImageData(0, 0, cell, cell).data;
			var x;
			var y;
			var row;
			function alpha(px, py) {
				return img[(py * cell + px) * 4 + 3];
			}
			for (y = 0; y < pad; y++) {
				row = y;
				for (x = 0; x < cell; x++) {
					if (alpha(x, row) > 10 || alpha(x, cell - 1 - row) > 10) {
						return true;
					}
				}
			}
			for (x = 0; x < pad; x++) {
				for (y = pad; y < cell - pad; y++) {
					if (alpha(x, y) > 10 || alpha(cell - 1 - x, y) > 10) {
						return true;
					}
				}
			}
			return false;
		}

		function foilStampFor(sym) {
			if (stampCache[sym]) {
				return stampCache[sym];
			}
			var stamp = document.createElement('canvas');
			stamp.width = cell;
			stamp.height = cell;
			var sctx = stamp.getContext('2d');
			/* Plates start smaller — iOS still grows them past font-size. */
			var size = foilIsPlateSymbol(sym) ? Math.floor(glyph * 0.72) : glyph;
			sctx.textAlign = 'center';
			sctx.textBaseline = 'middle';
			do {
				sctx.clearRect(0, 0, cell, cell);
				sctx.font = size + 'px ' + fontStack;
				sctx.fillText(sym, cell / 2, cell / 2);
				if (!foilInkHitsPad(sctx)) {
					break;
				}
				size = Math.floor(size * 0.82);
			} while (size >= 22);
			stampCache[sym] = stamp;
			return stamp;
		}

		var index = 0;
		var row;
		var col;
		for (row = 0; row < rows; row++) {
			for (col = 0; col < cols; col++) {
				ctx.drawImage(
					foilStampFor(symbols[index % symbols.length]),
					col * (cell + gap),
					row * (cell + gap)
				);
				index++;
			}
		}
		foilCanvas.dataset.emersonDrawn = '1';
		var root = foilRoot();
		/*
		 * Drift must start at translate(0,0). A negative phase delay used to jump the
		 * whole field at wipe-in end — looked like a new random arrangement.
		 * Shine delay parks the band off-screen after the reveal pass.
		 */
		root.style.setProperty('--emerson-foil-drift-delay', '0s');
		/* Shine starts with drift; tiny settle so it does not pop on the first drift frame. */
		root.style.setProperty('--emerson-foil-shine-delay', '0.2s');
		foilSyncWipeAxis();
		return { width: width, height: height };
	}
	/**
	 * Build the shine mask, then start wipe-in.
	 * Applying the mask mid-wipe flashed a full band at the end of reveal.
	 */
	function foilShineThenWipeIn(seq, size, done) {
		function startIn() {
			if (!foilStill(seq)) {
				return;
			}
			foilStartWipeIn(seq, done);
		}
		if (!size || !foilCanvas) {
			startIn();
			return;
		}
		window.requestAnimationFrame(function () {
			if (!foilStill(seq) || !foilCanvas) {
				return;
			}
			foilApplyShineMask(document.querySelector('.emerson-foil__shine'));
			startIn();
		});
	}
	function foilWaitWipe(el, seq, done) {
		var finished = false;
		var ms = foilReduceMotion() ? 0 : Math.round(foilWipeSeconds() * 1000) + 100;
		function finish() {
			if (finished || !foilStill(seq)) {
				return;
			}
			finished = true;
			if (el) {
				el.removeEventListener('animationend', onEnd);
			}
			window.clearTimeout(timer);
			done();
		}
		function onEnd(event) {
			if (!el || event.target !== el) {
				return;
			}
			if (String(event.animationName || '').indexOf('emerson-foil-wipe') === -1) {
				return;
			}
			finish();
		}
		if (el) {
			el.addEventListener('animationend', onEnd);
		}
		var timer = window.setTimeout(finish, ms);
	}
	function foilStartWipeIn(seq, done) {
		var root = foilRoot();
		var sheet = foilSheetEl();
		var layer = foilLayerEl();
		foilClearMotionClasses();
		/* Clear leftover freeze only while hidden — not mid-visible (that jumped). */
		foilClearSheetTransform(sheet);
		if (foilReduceMotion() || !layer) {
			foilClearLayerSnap(layer);
			root.classList.add('emerson-foil-ready');
			foilEnterAmbient(seq);
			if (typeof done === 'function') {
				done();
			}
			return;
		}
		/*
		 * Mask shut BEFORE the layer is shown. Clearing snap then adding wiping-in
		 * used to paint one full wash frame before the animation-fill kicked in.
		 */
		foilSyncWipeAxis();
		foilHoldMaskClosed(layer);
		root.classList.add('emerson-foil-wiping-in');
		foilClearLayerSnap(layer);
		void layer.offsetWidth;
		window.requestAnimationFrame(function () {
			if (!foilStill(seq)) {
				return;
			}
			foilClearMaskHold(layer);
			foilWaitWipe(layer, seq, function () {
				if (!foilStill(seq)) {
					return;
				}
				foilHoldMaskOpen(layer);
				/* Ready first — drift waits so scroll does not kick on the wipe handoff. */
				root.classList.add('emerson-foil-ready');
				root.classList.remove('emerson-foil-wiping-in');
				foilClearSheetTransform(sheet);
				if (sheet && !foilNeedsSafariVideoSafe()) {
					sheet.style.transform = 'translate3d(0, 0, 0)';
				}
				window.requestAnimationFrame(function () {
					if (!foilStill(seq)) {
						return;
					}
					foilClearMaskHold(layer);
					if (typeof done === 'function') {
						done();
					}
					window.setTimeout(function () {
						foilEnterAmbient(seq);
					}, 280);
				});
			});
		});
	}
	function foilStartWipeOut(seq, done) {
		var root = foilRoot();
		var sheet = foilSheetEl();
		var layer = foilLayerEl();
		if (foilReduceMotion() || !layer) {
			foilHoldMaskClosed(layer || sheet);
			foilClearSafariVideoSafe();
			root.classList.remove(
				'emerson-foil-ready',
				'emerson-foil-wiping-in',
				'emerson-foil-wiping-out',
				'emerson-foil-drifting',
				'emerson-foil-shining',
				'emerson-foil-safari-static'
			);
			if (typeof done === 'function') {
				done();
			}
			return;
		}
		function afterOut() {
			if (!foilStill(seq)) {
				return;
			}
			foilHoldMaskClosed(layer);
			if (typeof done === 'function') {
				done();
			}
		}
		if (root.classList.contains('emerson-foil-wiping-out')) {
			foilWaitWipe(layer, seq, afterOut);
			return;
		}
		/* Freeze drift; hold open → wiping-out → drop ready (same open mask). */
		foilClearSafariVideoSafe();
		foilSyncWipeAxis();
		foilFreezeSheetTransform(sheet);
		foilHoldMaskOpen(layer);
		root.classList.add('emerson-foil-wiping-out');
		root.classList.remove(
			'emerson-foil-ready',
			'emerson-foil-drifting',
			'emerson-foil-shining',
			'emerson-foil-wiping-in',
			'emerson-foil-safari-static'
		);
		window.requestAnimationFrame(function () {
			if (!foilStill(seq)) {
				return;
			}
			foilClearMaskHold(layer);
			foilWaitWipe(layer, seq, afterOut);
		});
	}
	/**
	 * Strict order (one flight at a time via foilSeq):
	 * 1) if icons are visible / mid-wipe → wipe out to shut
	 * 2) force mask closed
	 * 3) paint exactly once (optional reshuffle)
	 * 4) shine mask
	 * 5) wipe in
	 * 6) hold mask open → ready (no shut-frame flash)
	 */
	foilReveal = function (options) {
		options = options || {};
		var symbols = options.symbols || [];
		var reshuffle = !!options.reshuffle;
		var onComplete = typeof options.onComplete === 'function' ? options.onComplete : null;
		var seq = foilBumpSeq();
		var root = foilRoot();
		window.clearTimeout(foilHideTimer);
		root.classList.remove('emerson-foil-fading-out');

		function finishReveal() {
			if (!foilStill(seq)) {
				return;
			}
			if (onComplete) {
				onComplete();
			}
		}

		function paintThenIn() {
			if (!foilStill(seq)) {
				return;
			}
			foilForceMaskClosed();
			var size = foilPaint(symbols, reshuffle);
			foilShineThenWipeIn(seq, size, finishReveal);
		}

		if (!symbols.length) {
			finishReveal();
			return;
		}
		/*
		 * ready → animated wipe out, then paint.
		 * wiping-out → wait for shut, then paint.
		 * else → already shut; paint.
		 */
		if (root.classList.contains('emerson-foil-ready')) {
			foilStartWipeOut(seq, paintThenIn);
		} else if (root.classList.contains('emerson-foil-wiping-out')) {
			foilStartWipeOut(seq, paintThenIn);
		} else {
			paintThenIn();
		}
	};
	foilHide = function (done) {
		var seq = foilBumpSeq();
		var finished = false;
		window.clearTimeout(foilHideTimer);

		function finishOff() {
			if (finished || !foilStill(seq)) {
				return;
			}
			finished = true;
			window.clearTimeout(foilHideTimer);
			var root = foilRoot();
			var layer = foilLayerEl();
			/* Mask still shut from afterOut; ensure opacity is 0 before unmasking. */
			foilHoldMaskClosed(layer);
			foilSnapLayerHidden(layer);
			foilClearSafariVideoSafe();
			root.classList.remove(
				'emerson-foil',
				'emerson-foil-fading-out',
				'emerson-foil-ready',
				'emerson-foil-wiping-in',
				'emerson-foil-wiping-out',
				'emerson-foil-drifting',
				'emerson-foil-shining',
				'emerson-foil-safari-static'
			);
			/* Clear mask/holds on the next frame while layer stays opacity 0. */
			window.requestAnimationFrame(function () {
				if (!foilStill(seq)) {
					return;
				}
				foilForceMaskClosed();
				if (typeof done === 'function') {
					done();
				}
			});
		}

		foilStartWipeOut(seq, finishOff);
		foilHideTimer = window.setTimeout(
			finishOff,
			Math.round(foilWipeSeconds() * 1000) + 200
		);
	};

	function foilFlightDone() {
		foilBusy = false;
		if (foilPending === null) {
			return;
		}
		var wantOn = foilPending;
		foilPending = null;
		if (wantOn === foilEffectiveOn()) {
			if (foilButton) {
				foilButton.setAttribute('aria-pressed', wantOn ? 'true' : 'false');
			}
			return;
		}
		foilStartToward(wantOn);
	}

	function foilStartToward(wantOn) {
		foilBusy = true;
		foilPending = null;
		var root = foilRoot();
		if (foilButton) {
			foilButton.setAttribute('aria-pressed', wantOn ? 'true' : 'false');
		}
		try {
			localStorage.setItem('emerson-foil', wantOn ? '1' : '0');
		} catch (error) {}

		if (wantOn) {
			root.classList.add('emerson-foil');
			if (typeof foilReveal === 'function' && foilCanvas) {
				var symbols = [];
				try {
					symbols = JSON.parse(foilCanvas.getAttribute('data-symbols') || '[]');
				} catch (error) {}
				if (symbols.length) {
					foilReveal({
						symbols: symbols,
						reshuffle: foilRandomizeOn(),
						onComplete: foilFlightDone
					});
					return;
				}
			}
			foilForceMaskClosed();
			root.classList.add('emerson-foil-ready');
			if (foilNeedsSafariVideoSafe()) {
				foilApplySafariVideoSafe();
			} else {
				root.classList.add('emerson-foil-drifting', 'emerson-foil-shining');
			}
			foilFlightDone();
			return;
		}

		if (typeof foilHide === 'function') {
			foilHide(foilFlightDone);
			return;
		}
		foilForceMaskClosed();
		foilClearSafariVideoSafe();
		root.classList.remove('emerson-foil', 'emerson-foil-safari-static');
		foilFlightDone();
	}

	if (foilCanvas) {
		var foilSymbols = [];
		try {
			foilSymbols = JSON.parse(foilCanvas.getAttribute('data-symbols') || '[]');
		} catch (error) {}
		if (foilSymbols.length) {
			try {
				sessionStorage.removeItem('emersonFoilOrder');
			} catch (error) {}
			/*
			 * Lazy paint: skip the big canvas draw on cold loads when foil is off.
			 * First lion click (or a saved-on session) paints then reveals.
			 */
			if (foilRoot().classList.contains('emerson-foil')) {
				window.requestAnimationFrame(function () {
					if (!foilRoot().classList.contains('emerson-foil')) {
						return;
					}
					foilBusy = true;
					foilReveal({
						symbols: foilSymbols,
						reshuffle: true,
						onComplete: foilFlightDone
					});
				});
			}
		}
	}

	if (foilButton) {
		foilButton.setAttribute(
			'aria-pressed',
			foilRoot().classList.contains('emerson-foil') ? 'true' : 'false'
		);
		foilButton.addEventListener('click', function () {
			var wantOn = foilButton.getAttribute('aria-pressed') !== 'true';
			foilButton.setAttribute('aria-pressed', wantOn ? 'true' : 'false');
			try {
				localStorage.setItem('emerson-foil', wantOn ? '1' : '0');
			} catch (error) {}
			if (foilBusy) {
				/* One pending only — latest click replaces it; no stack. */
				foilPending = wantOn;
				return;
			}
			foilStartToward(wantOn);
		});
	}
})();

/**
 * Homepage sermon: poster until click, then inject YouTube iframe (autoplay).
 * YouTube IFrame API reports play/pause so Safari foil can freeze only while
 * playing and resume ambient motion when the visitor pauses or the video ends.
 */
(function () {
	var ytApiLoading = false;
	var ytApiWaiters = [];

	function notifyFoilPlaying(playing) {
		try {
			document.dispatchEvent(
				new CustomEvent('emerson-live-video', { detail: { playing: !!playing } })
			);
		} catch (error) {}
	}

	function whenYtApiReady(done) {
		if (window.YT && window.YT.Player) {
			done();
			return;
		}
		ytApiWaiters.push(done);
		if (ytApiLoading) {
			return;
		}
		ytApiLoading = true;
		var previous = window.onYouTubeIframeAPIReady;
		window.onYouTubeIframeAPIReady = function () {
			if (typeof previous === 'function') {
				previous();
			}
			var waiters = ytApiWaiters.slice();
			ytApiWaiters = [];
			waiters.forEach(function (fn) {
				fn();
			});
		};
		if (!document.querySelector('script[src*="youtube.com/iframe_api"]')) {
			var script = document.createElement('script');
			script.src = 'https://www.youtube.com/iframe_api';
			document.head.appendChild(script);
		}
	}

	function bindPlayer(iframe) {
		whenYtApiReady(function () {
			if (!iframe || !iframe.parentNode) {
				return;
			}
			try {
				new window.YT.Player(iframe, {
					events: {
						onStateChange: function (event) {
							var state = event && typeof event.data === 'number' ? event.data : null;
							/* 1 = playing; 2 = paused; 0 = ended; 3 = buffering stays “on”. */
							if (state === 1 || state === 3) {
								notifyFoilPlaying(true);
							} else if (state === 0 || state === 2 || state === 5) {
								notifyFoilPlaying(false);
							}
						}
					}
				});
			} catch (error) {
				notifyFoilPlaying(true);
			}
		});
	}

	function loadSermon(figure) {
		if (!figure || figure.classList.contains('is-playing')) {
			return;
		}
		var src = figure.getAttribute('data-embed-src') || '';
		var title = figure.getAttribute('data-embed-title') || 'Sunday service';
		if (!src) {
			return;
		}
		var iframe = document.createElement('iframe');
		iframe.id = 'emerson-latest-sermon-player';
		iframe.title = title;
		iframe.src = src;
		iframe.setAttribute(
			'allow',
			'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
		);
		iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		iframe.setAttribute('allowfullscreen', '');
		iframe.setAttribute('playsinline', '');
		figure.classList.add('is-playing');
		while (figure.firstChild) {
			figure.removeChild(figure.firstChild);
		}
		figure.appendChild(iframe);
		/* Optimistic freeze until the API confirms; pause/end clears it. */
		notifyFoilPlaying(true);
		bindPlayer(iframe);
	}

	document.querySelectorAll('.emerson-latest-sermon').forEach(function (figure) {
		var play = figure.querySelector('.emerson-latest-sermon__play');
		if (!play) {
			return;
		}
		play.addEventListener('click', function () {
			loadSermon(figure);
		});
	});
})();

(function () {
	var boxes = document.querySelectorAll('.emerson-documents-scroll');
	if (!boxes.length) {
		return;
	}
	function chevron(direction) {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'emerson-documents-chevron emerson-documents-chevron--' + direction;
		button.setAttribute('aria-label', direction === 'up' ? 'Scroll to the top of this list' : 'Scroll to the bottom of this list');
		button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 14l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		return button;
	}
	function updateChevrons(box) {
		var column = box.parentElement;
		var up = column.querySelector('.emerson-documents-chevron--up');
		var down = column.querySelector('.emerson-documents-chevron--down');
		if (!up || !down) {
			return;
		}
		var overflow = box.scrollHeight > box.clientHeight + 2;
		var atTop = box.scrollTop <= 2;
		var atBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 2;
		up.classList.toggle('is-visible', overflow && !atTop);
		down.classList.toggle('is-visible', overflow && !atBottom);
	}
	function fitDocumentLists() {
		boxes.forEach(function (box) {
			var items = box.querySelectorAll('.emerson-documents > li');
			var place = box.scrollTop;
			box.style.maxHeight = '';
			if (items.length > 10) {
				box.scrollTop = 0;
				var limit = items[9].getBoundingClientRect().bottom - box.getBoundingClientRect().top;
				box.style.maxHeight = Math.ceil(limit) + 'px';
				box.scrollTop = place;
			}
			updateChevrons(box);
		});
	}
	boxes.forEach(function (box) {
		var up = chevron('up');
		var down = chevron('down');
		box.parentElement.insertBefore(up, box);
		box.insertAdjacentElement('afterend', down);
		up.addEventListener('click', function () {
			box.scrollTo({ top: 0, behavior: 'smooth' });
		});
		down.addEventListener('click', function () {
			box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
		});
		box.addEventListener('scroll', function () {
			updateChevrons(box);
		});
	});
	fitDocumentLists();
	var fitTimer = 0;
	window.addEventListener('resize', function () {
		window.clearTimeout(fitTimer);
		fitTimer = window.setTimeout(fitDocumentLists, 80);
	});
})();
