/**
 * Documents screen: update a list in place, without jumping back to the top.
 */
(function () {
	'use strict';

	var settings = window.emersonDocumentsAdmin || {};
	var topButton = document.querySelector('.emerson-docs-top');
	if (topButton) {
		function scrollProgress() {
			var scrolling = document.documentElement.scrollHeight - window.innerHeight;
			return scrolling > 0 ? window.scrollY / scrolling : 0;
		}
		function updateTopButton() {
			var show = scrollProgress() >= 0.25;
			if (show) {
				topButton.hidden = false;
				window.requestAnimationFrame(function () {
					topButton.classList.add('is-visible');
				});
				return;
			}
			topButton.classList.remove('is-visible');
			window.setTimeout(function () {
				if (!topButton.classList.contains('is-visible')) {
					topButton.hidden = true;
				}
			}, 250);
		}
		window.addEventListener('scroll', updateTopButton, { passive: true });
		window.addEventListener('resize', updateTopButton);
		updateTopButton();
		topButton.addEventListener('click', function () {
			var behavior = reduceMotion() ? 'auto' : 'smooth';
			window.scrollTo({ top: 0, behavior: behavior });
		});
	}
	if (!settings.ajaxUrl) {
		return;
	}

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

	function fitLists() {
		document.querySelectorAll('.emerson-doc-rows').forEach(function (rows) {
			var items = rows.querySelectorAll('.emerson-doc-row');
			if (items.length <= 5) {
				rows.style.maxHeight = '';
				rows.classList.remove('is-scroll');
				return;
			}
			var height = 0;
			var count = 5;
			for (var i = 0; i < count; i++) {
				var style = window.getComputedStyle(items[i]);
				height += items[i].offsetHeight + parseFloat(style.marginTop) + parseFloat(style.marginBottom);
			}
			rows.style.maxHeight = height + 'px';
			rows.classList.add('is-scroll');
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
	fitLists();
}());
