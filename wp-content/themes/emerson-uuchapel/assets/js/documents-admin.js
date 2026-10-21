/**
 * Documents screen: update a list in place, without jumping back to the top.
 */
(function () {
	'use strict';

	var settings = window.emersonDocumentsAdmin || {};

	function reduceMotion() {
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function statusLine(form) {
		return form.querySelector('.emerson-documents-admin__status');
	}

	function showStatus(form, message, isError) {
		var line = statusLine(form);
		if (!line) {
			return;
		}
		line.textContent = message;
		line.classList.toggle('is-error', !!isError);
	}

	function rowById(form, id) {
		return form.querySelector('.emerson-doc-row[data-doc-id="' + id + '"]');
	}

	function slideRows(row, neighbor, firstRow, firstNeighbor) {
		if (reduceMotion()) {
			return;
		}
		var nextRow = row.getBoundingClientRect();
		var nextNeighbor = neighbor.getBoundingClientRect();
		row.style.transform = 'translateY(' + (firstRow.top - nextRow.top) + 'px)';
		neighbor.style.transform = 'translateY(' + (firstNeighbor.top - nextNeighbor.top) + 'px)';
		window.requestAnimationFrame(function () {
			row.style.transition = 'transform 0.35s ease';
			neighbor.style.transition = 'transform 0.35s ease';
			row.style.transform = '';
			neighbor.style.transform = '';
			window.setTimeout(function () {
				row.style.transition = '';
				neighbor.style.transition = '';
			}, 400);
		});
	}

	function moveRow(form, id, direction) {
		var row = rowById(form, id);
		if (!row) {
			return false;
		}
		var neighbor = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
		if (!neighbor || !neighbor.classList.contains('emerson-doc-row')) {
			return false;
		}
		var firstRow = row.getBoundingClientRect();
		var firstNeighbor = neighbor.getBoundingClientRect();
		if (direction === 'up') {
			neighbor.before(row);
		} else {
			neighbor.after(row);
		}
		slideRows(row, neighbor, firstRow, firstNeighbor);
		return true;
	}

	function fadeOut(row) {
		return new Promise(function (resolve) {
			if (reduceMotion()) {
				row.remove();
				resolve();
				return;
			}
			row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
			row.style.opacity = '0';
			row.style.transform = 'translateY(-8px)';
			window.setTimeout(function () {
				row.remove();
				resolve();
			}, 300);
		});
	}

	function fadeIn(row) {
		if (reduceMotion()) {
			return;
		}
		row.style.opacity = '0';
		row.style.transform = 'translateY(-12px)';
		window.requestAnimationFrame(function () {
			row.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
			row.style.opacity = '1';
			row.style.transform = '';
		});
	}

	function chevron(direction) {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'emerson-documents-chevron emerson-documents-chevron--' + direction;
		button.setAttribute('aria-label', direction === 'up' ? 'Scroll to the top of this list' : 'Scroll to the bottom of this list');
		button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 14l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		return button;
	}

	function updateChevrons(rows) {
		var bar = rows.previousElementSibling;
		if (!bar || !bar.classList.contains('emerson-documents-chevrons')) {
			return;
		}
		var up = bar.querySelector('.emerson-documents-chevron--up');
		var down = bar.querySelector('.emerson-documents-chevron--down');
		if (!up || !down) {
			return;
		}
		var overflow = rows.classList.contains('is-scroll') && rows.scrollHeight > rows.clientHeight + 2;
		var atTop = rows.scrollTop <= 2;
		var atBottom = rows.scrollTop + rows.clientHeight >= rows.scrollHeight - 2;
		up.classList.toggle('is-visible', overflow && !atTop);
		down.classList.toggle('is-visible', overflow && !atBottom);
	}

	function fitLists() {
		document.querySelectorAll('.emerson-doc-rows').forEach(function (rows) {
			var items = rows.querySelectorAll('.emerson-doc-row');
			var place = rows.scrollTop;
			rows.style.maxHeight = '';
			rows.classList.remove('is-scroll');
			if (items.length > 10) {
				rows.scrollTop = 0;
				var height = 0;
				for (var i = 0; i < 10; i++) {
					var style = window.getComputedStyle(items[i]);
					height += items[i].offsetHeight + parseFloat(style.marginTop) + parseFloat(style.marginBottom);
				}
				rows.style.maxHeight = height + 'px';
				rows.classList.add('is-scroll');
				rows.scrollTop = place;
			}
			updateChevrons(rows);
		});
	}

	function showEmpty(form) {
		var rows = form.querySelector('.emerson-doc-rows');
		if (!rows || rows.querySelector('.emerson-doc-row')) {
			var empty = rows ? rows.querySelector('.emerson-doc-empty') : null;
			if (empty) {
				empty.remove();
			}
			return;
		}
		if (!rows.querySelector('.emerson-doc-empty')) {
			var note = document.createElement('p');
			note.className = 'emerson-doc-empty';
			note.textContent = 'Nothing is on this list yet.';
			rows.appendChild(note);
		}
	}

	document.querySelectorAll('.emerson-documents-admin__form').forEach(function (form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			if (form.dataset.busy === '1') {
				return;
			}
			var submitter = event.submitter;
			var data = new FormData(form);
			if (submitter && submitter.name) {
				data.set(submitter.name, submitter.value);
			}
			var move = submitter && submitter.name === 'doc_move' ? submitter.value : '';
			var moveId = '';
			if (move) {
				moveId = submitter.getAttribute('data-id') || '';
				data.set('doc_move_id', moveId);
				if (!moveRow(form, moveId, move)) {
					return;
				}
			}
			var removing = [];
			if (!move) {
				form.querySelectorAll('.emerson-doc-row input[type="checkbox"]:checked').forEach(function (box) {
					var row = box.closest('.emerson-doc-row');
					if (row) {
						removing.push(row);
					}
				});
			}
			form.dataset.busy = '1';
			showStatus(form, 'Saving…', false);
			window.fetch(settings.ajaxUrl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin'
			}).then(function (response) {
				return response.json();
			}).then(function (payload) {
				form.dataset.busy = '';
				if (!payload || !payload.success) {
					if (move) {
						moveRow(form, moveId, move === 'up' ? 'down' : 'up');
					}
					showStatus(form, (payload && payload.data && payload.data.message) || 'Could not save.', true);
					return;
				}
				if (payload.data && payload.data.files) {
					Object.keys(payload.data.files).forEach(function (id) {
						var fileLine = rowById(form, id);
						var slot = fileLine ? fileLine.querySelector('.emerson-doc-row__file') : null;
						if (slot) {
							slot.innerHTML = payload.data.files[id];
						}
						var input = form.querySelector('[name="replace_' + id + '"]');
						if (input) {
							input.value = '';
						}
					});
				}
				if (payload.data && payload.data.row) {
					var holder = form.querySelector('.emerson-doc-rows');
					var empty = holder.querySelector('.emerson-doc-empty');
					if (empty) {
						empty.remove();
					}
					holder.insertAdjacentHTML('afterbegin', payload.data.row);
					var added = holder.firstElementChild;
					holder.scrollTop = 0;
					fadeIn(added);
					fitLists();
					form.querySelector('[name="new_label"]').value = '';
					form.querySelector('[name="new_note"]').value = '';
					form.querySelector('[name="new_file"]').value = '';
				}
				if (removing.length) {
					Promise.all(removing.map(fadeOut)).then(function () {
						showEmpty(form);
						fitLists();
					});
				}
				if (move) {
					window.setTimeout(fitLists, 400);
				}
				fitLists();
				showStatus(form, payload.data.message || 'Saved.', false);
			}).catch(function () {
				form.dataset.busy = '';
				if (move) {
					moveRow(form, moveId, move === 'up' ? 'down' : 'up');
				}
				showStatus(form, 'Could not save.', true);
			});
		});
	});
	document.querySelectorAll('.emerson-doc-rows').forEach(function (rows) {
		var bar = document.createElement('div');
		bar.className = 'emerson-documents-chevrons';
		var up = chevron('up');
		var down = chevron('down');
		bar.appendChild(up);
		bar.appendChild(down);
		rows.parentElement.insertBefore(bar, rows);
		up.addEventListener('click', function () {
			rows.scrollTo({ top: 0, behavior: reduceMotion() ? 'auto' : 'smooth' });
		});
		down.addEventListener('click', function () {
			rows.scrollTo({ top: rows.scrollHeight, behavior: reduceMotion() ? 'auto' : 'smooth' });
		});
		rows.addEventListener('scroll', function () {
			updateChevrons(rows);
		});
		rows.addEventListener('wheel', function (event) {
			if (!rows.classList.contains('is-scroll') || event.deltaY === 0) {
				return;
			}
			var delta = event.deltaY;
			if (event.deltaMode === 1) {
				delta *= 16;
			} else if (event.deltaMode === 2) {
				delta *= rows.clientHeight;
			}
			var max = rows.scrollHeight - rows.clientHeight;
			var next = rows.scrollTop + delta;
			event.preventDefault();
			if (next < 0 || next > max) {
				rows.scrollTop = Math.max(0, Math.min(max, next));
				window.scrollBy(0, delta);
				return;
			}
			rows.scrollTop = next;
		}, { passive: false });
	});
	fitLists();
	var fitTimer = 0;
	window.addEventListener('resize', function () {
		window.clearTimeout(fitTimer);
		fitTimer = window.setTimeout(fitLists, 80);
	});
}());
