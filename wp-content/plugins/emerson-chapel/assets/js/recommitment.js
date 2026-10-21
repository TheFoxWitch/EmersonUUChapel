/**
 * Emerson Community Survey opens the chapel commitment form.
 * The pledge button opens its own Microsoft or Google form.
 */
(function () {
	'use strict';

	function reduceMotion() {
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function fitSurveyForm(dialog) {
		if (!dialog || !dialog.classList.contains('emerson-recommit--form')) {
			return;
		}
		var form = dialog.querySelector('form.cff-form');
		if (!form) {
			return;
		}
		form.style.transform = '';
		var bar = dialog.querySelector('.emerson-recommit__bar');
		var dialogStyle = window.getComputedStyle(dialog);
		var barStyle = bar ? window.getComputedStyle(bar) : null;
		var available = dialog.clientHeight
			- parseFloat(dialogStyle.paddingTop)
			- parseFloat(dialogStyle.paddingBottom)
			- (bar ? bar.offsetHeight : 0)
			- (barStyle ? parseFloat(barStyle.marginBottom) : 0);
		var needed = form.scrollHeight;
		if (available <= 0 || needed <= available + 1) {
			return;
		}
		var scale = available / needed;
		form.style.transformOrigin = 'top center';
		form.style.transform = 'scale(' + scale + ')';
	}

	function installSurveySignature(dialog) {
		if (!dialog || dialog.id !== 'emerson-recommit' || !window.emersonRecommit || !window.emersonRecommit.signature) {
			return;
		}
		if (dialog.querySelector('.emerson-recommit__pad')) {
			return;
		}
		var form = dialog.querySelector('form');
		var anchor = dialog.querySelector('.fields[class*="fieldname7_"]') || dialog.querySelector('.pbSubmit') || dialog.querySelector('.cp_subbtn') || dialog.querySelector('[type="submit"]');
		if (!form || !anchor) {
			if (dialog.dataset.emersonSignatureWait) {
				return;
			}
			dialog.dataset.emersonSignatureWait = '1';
			var wait = new MutationObserver(function () {
				if (dialog.querySelector('.fields[class*="fieldname7_"]') || dialog.querySelector('.pbSubmit') || dialog.querySelector('.cp_subbtn') || dialog.querySelector('[type="submit"]')) {
					wait.disconnect();
					installSurveySignature(dialog);
				}
			});
			wait.observe(dialog, { childList: true, subtree: true });
			window.setTimeout(function () {
				wait.disconnect();
				installSurveySignature(dialog);
			}, 4000);
			return;
		}
		var block = document.createElement('div');
		block.className = 'emerson-recommit__signature';
		block.innerHTML = '<p class="emerson-recommit__sign">Signed<label class="screen-reader-text" for="emerson-recommit-pad">Draw your signature</label><canvas class="emerson-recommit__pad" id="emerson-recommit-pad"></canvas></p><p class="emerson-recommit__sign-actions"><button type="button" class="emerson-recommit__clear">Clear signature</button><span class="emerson-recommit__and-date">and date</span></p><p class="emerson-recommit__status" role="status"></p>';
		anchor.parentNode.insertBefore(block, anchor);
		var actions = block.querySelector('.emerson-recommit__sign-actions');
		var dateField = dialog.querySelector('.fields[class*="fieldname7_"]');
		if (dateField && actions) {
			actions.appendChild(dateField);
		}
		var canvas = block.querySelector('.emerson-recommit__pad');
		var status = block.querySelector('.emerson-recommit__status');
		var ctx = canvas.getContext('2d');
		var drawing = false;
		var ink = false;

		function sizeCanvas() {
			var rect = canvas.getBoundingClientRect();
			var ratio = window.devicePixelRatio || 1;
			var previous = ink ? canvas.toDataURL('image/png') : '';
			canvas.width = Math.max(1, Math.round(rect.width * ratio));
			canvas.height = Math.max(1, Math.round(rect.height * ratio));
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			ctx.lineWidth = 2.25;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			ctx.strokeStyle = '#1e1e1e';
			ctx.fillStyle = '#fff';
			ctx.fillRect(0, 0, rect.width, rect.height);
			if (previous) {
				var image = new Image();
				image.onload = function () {
					ctx.drawImage(image, 0, 0, rect.width, rect.height);
				};
				image.src = previous;
			} else {
				ink = false;
			}
		}

		function point(event) {
			var rect = canvas.getBoundingClientRect();
			return {
				x: event.clientX - rect.left,
				y: event.clientY - rect.top
			};
		}

		canvas.addEventListener('pointerdown', function (event) {
			drawing = true;
			canvas.setPointerCapture(event.pointerId);
			var at = point(event);
			ctx.beginPath();
			ctx.moveTo(at.x, at.y);
			ctx.lineTo(at.x + 0.01, at.y);
			ctx.stroke();
			ink = true;
			event.preventDefault();
		});
		canvas.addEventListener('pointermove', function (event) {
			if (!drawing) {
				return;
			}
			var at = point(event);
			ctx.lineTo(at.x, at.y);
			ctx.stroke();
			ink = true;
			event.preventDefault();
		});
		function stopDraw() {
			drawing = false;
		}
		canvas.addEventListener('pointerup', stopDraw);
		canvas.addEventListener('pointercancel', stopDraw);
		block.querySelector('.emerson-recommit__clear').addEventListener('click', function () {
			ink = false;
			sizeCanvas();
			status.textContent = '';
		});
		form.addEventListener('submit', function (event) {
			if (!ink) {
				event.preventDefault();
				event.stopPropagation();
				status.textContent = 'Draw your signature.';
				status.classList.add('is-error');
				return;
			}
			var input = form.querySelector('[name="emerson_signature"]');
			if (!input) {
				input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'emerson_signature';
				form.appendChild(input);
			}
			input.value = canvas.toDataURL('image/png');
			status.textContent = '';
			status.classList.remove('is-error');
		}, true);
		window.requestAnimationFrame(function () {
			sizeCanvas();
			fitSurveyForm(dialog);
		});
	}

	function finishSurveyLayout(dialog) {
		installSurveySignature(dialog);
		window.requestAnimationFrame(function () {
			fitSurveyForm(dialog);
		});
	}

	function bindIframeDialog(dialog, openerSelector) {
		if (!dialog || typeof dialog.showModal !== 'function') {
			return;
		}
		var frame = dialog.querySelector('.emerson-recommit__frame');

		function revealDialog() {
			if (reduceMotion()) {
				return;
			}
			void getComputedStyle(dialog).transform;
			dialog.classList.remove('is-opening');
		}

		function openDialog() {
			if (dialog.open || dialog.classList.contains('is-closing')) {
				return;
			}
			if (frame && !frame.getAttribute('src')) {
				frame.setAttribute('src', frame.getAttribute('data-src'));
			}
			if (!reduceMotion()) {
				dialog.classList.add('is-opening');
			}
			dialog.showModal();
			var list = dialog.querySelector('[id^="fieldlist_"]');
			if (!list || list.children.length || reduceMotion()) {
				revealDialog();
				finishSurveyLayout(dialog);
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
						revealDialog();
						finishSurveyLayout(dialog);
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

		function closeDialog() {
			if (!dialog.open || dialog.classList.contains('is-closing')) {
				return;
			}
			if (reduceMotion()) {
				dialog.close();
				return;
			}
			dialog.classList.add('is-closing');
			var finished = false;
			function done(event) {
				if (event && (event.target !== dialog || event.propertyName !== 'transform')) {
					return;
				}
				if (finished) {
					return;
				}
				finished = true;
				dialog.removeEventListener('transitionend', done);
				dialog.classList.remove('is-closing');
				dialog.close();
			}
			dialog.addEventListener('transitionend', done);
			window.setTimeout(done, 500);
		}

		document.addEventListener('click', function (event) {
			var opener = event.target.closest ? event.target.closest(openerSelector) : null;
			if (!opener) {
				return;
			}
			event.preventDefault();
			openDialog();
		});
		dialog.addEventListener('click', function (event) {
			if (event.target === dialog) {
				closeDialog();
			}
		});
		dialog.addEventListener('cancel', function (event) {
			event.preventDefault();
			closeDialog();
		});
		var close = dialog.querySelector('.emerson-recommit__close');
		if (close) {
			close.addEventListener('click', closeDialog);
		}
	}

	var dialog = document.getElementById('emerson-recommit');
	var form = dialog ? dialog.querySelector('.emerson-recommit__form') : null;
	if (dialog && form && typeof dialog.showModal === 'function' && window.emersonRecommit) {
		var canvas = dialog.querySelector('.emerson-recommit__pad');
		var status = dialog.querySelector('.emerson-recommit__status');
		var submit = dialog.querySelector('.emerson-recommit__submit');
		var pledge = form.querySelector('[name="pledge"]');
		var ctx = canvas.getContext('2d');
		var drawing = false;
		var ink = false;

		function todayValue() {
			var now = new Date();
			var month = String(now.getMonth() + 1);
			var day = String(now.getDate());
			if (month.length < 2) {
				month = '0' + month;
			}
			if (day.length < 2) {
				day = '0' + day;
			}
			return now.getFullYear() + '-' + month + '-' + day;
		}

		function setToday() {
			var today = todayValue();
			form.querySelector('[name="continue_on"]').value = today;
			form.querySelector('[name="signed_on"]').value = today;
		}

		function sizeCanvas() {
			var rect = canvas.getBoundingClientRect();
			var ratio = window.devicePixelRatio || 1;
			canvas.width = Math.max(1, Math.round(rect.width * ratio));
			canvas.height = Math.max(1, Math.round(rect.height * ratio));
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			ctx.lineWidth = 2.25;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			ctx.strokeStyle = '#1e1e1e';
			ctx.fillStyle = '#fff';
			ctx.fillRect(0, 0, rect.width, rect.height);
			ink = false;
		}

		function point(event) {
			var rect = canvas.getBoundingClientRect();
			return {
				x: event.clientX - rect.left,
				y: event.clientY - rect.top
			};
		}

		canvas.addEventListener('pointerdown', function (event) {
			drawing = true;
			canvas.setPointerCapture(event.pointerId);
			var at = point(event);
			ctx.beginPath();
			ctx.moveTo(at.x, at.y);
			ctx.lineTo(at.x + 0.01, at.y);
			ctx.stroke();
			ink = true;
			event.preventDefault();
		});
		canvas.addEventListener('pointermove', function (event) {
			if (!drawing) {
				return;
			}
			var at = point(event);
			ctx.lineTo(at.x, at.y);
			ctx.stroke();
			ink = true;
			event.preventDefault();
		});
		function stopDraw() {
			drawing = false;
		}
		canvas.addEventListener('pointerup', stopDraw);
		canvas.addEventListener('pointercancel', stopDraw);

		dialog.querySelector('.emerson-recommit__clear').addEventListener('click', function () {
			sizeCanvas();
		});

		function openForm() {
			if (dialog.open || dialog.classList.contains('is-closing')) {
				return;
			}
			if (!reduceMotion()) {
				dialog.classList.add('is-opening');
			}
			dialog.showModal();
			setToday();
			window.requestAnimationFrame(function () {
				sizeCanvas();
			});
			if (!reduceMotion()) {
				void getComputedStyle(dialog).transform;
				dialog.classList.remove('is-opening');
			}
			status.textContent = '';
			status.classList.remove('is-error');
			form.querySelector('[name="name"]').focus();
		}

		function showError(message) {
			status.textContent = message;
			status.classList.add('is-error');
		}

		function closeForm() {
			if (!dialog.open || dialog.classList.contains('is-closing')) {
				return;
			}
			if (reduceMotion()) {
				dialog.close();
				return;
			}
			dialog.classList.add('is-closing');
			var finished = false;
			function done(event) {
				if (event && (event.target !== dialog || event.propertyName !== 'transform')) {
					return;
				}
				if (finished) {
					return;
				}
				finished = true;
				dialog.removeEventListener('transitionend', done);
				dialog.classList.remove('is-closing');
				dialog.close();
			}
			dialog.addEventListener('transitionend', done);
			window.setTimeout(done, 500);
		}

		document.addEventListener('click', function (event) {
			var opener = event.target.closest ? event.target.closest('[data-emerson-recommit]') : null;
			if (!opener) {
				return;
			}
			event.preventDefault();
			openForm();
		});
		dialog.addEventListener('click', function (event) {
			if (event.target === dialog) {
				closeForm();
			}
		});
		dialog.addEventListener('cancel', function (event) {
			event.preventDefault();
			closeForm();
		});
		dialog.querySelector('.emerson-recommit__close').addEventListener('click', closeForm);

		pledge.addEventListener('input', function () {
			var value = pledge.value.replace(/[^\d.]/g, '');
			var parts = value.split('.');
			var dollars = parts[0].slice(0, 6);
			var next = dollars;
			if (parts.length > 1) {
				next += '.' + parts.slice(1).join('').slice(0, 2);
			}
			pledge.value = next;
		});

		function pledgeAmount() {
			var cleaned = pledge.value.replace(/[$,\s]/g, '');
			if (!/^\d{1,6}(\.\d{0,2})?$/.test(cleaned) || cleaned === '') {
				return '';
			}
			var number = Number(cleaned);
			if (!isFinite(number)) {
				return '';
			}
			return number.toFixed(2);
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			status.textContent = '';
			status.classList.remove('is-error');
			var name = form.querySelector('[name="name"]').value.trim();
			var amount = pledgeAmount();
			var frequency = form.querySelector('[name="frequency"]').value;
			var continueOn = form.querySelector('[name="continue_on"]').value;
			var signedOn = form.querySelector('[name="signed_on"]').value;
			if (!name) {
				showError('Enter your name.');
				return;
			}
			if (!amount) {
				showError('Enter your pledge as a dollar amount with cents, like 7.77 or 5.00.');
				return;
			}
			if (!frequency) {
				showError('Choose monthly, quarterly, or annually.');
				return;
			}
			if (!continueOn) {
				showError('Choose the date payments continue.');
				return;
			}
			var phone = form.querySelector('[name="phone"]').value.trim();
			var email = form.querySelector('[name="email"]').value.trim();
			var phoneDigits = phone.replace(/\D/g, '');
			if (phoneDigits.length < 10 || phoneDigits.length > 15) {
				showError('Enter a valid phone number.');
				return;
			}
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
				showError('Enter a valid email address.');
				return;
			}
			if (!ink) {
				showError('Draw your signature.');
				return;
			}
			if (!signedOn) {
				showError('Choose the date you signed.');
				return;
			}

			var body = new FormData(form);
			body.set('action', 'emerson_recommit');
			body.set('nonce', window.emersonRecommit.nonce);
			body.set('pledge', amount);
			body.set('signature', canvas.toDataURL('image/png'));
			submit.disabled = true;
			status.textContent = 'Sending…';
			status.classList.remove('is-error');

			window.fetch(window.emersonRecommit.ajaxUrl, {
				method: 'POST',
				body: body,
				credentials: 'same-origin'
			}).then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			}).then(function (result) {
				var message = result.data && result.data.data && result.data.data.message;
				if (result.ok && result.data && result.data.success) {
					status.textContent = message || 'Sent. Thank you for your re-commitment.';
					status.classList.remove('is-error');
					form.reset();
					setToday();
					sizeCanvas();
					window.setTimeout(closeForm, 3000);
					return;
				}
				showError(message || 'The form could not be sent. Please try again, or email the office.');
			}).catch(function () {
				showError('The form could not be sent. Please try again, or email the office.');
			}).then(function () {
				submit.disabled = false;
			});
		});
	}

	if (!(dialog && form)) {
		bindIframeDialog(dialog, '[data-emerson-recommit]');
	}
	bindIframeDialog(document.getElementById('emerson-pledge'), '[data-emerson-pledge]');
	document.querySelectorAll('dialog.emerson-modal[data-emerson-modal-id]').forEach(function (modalDialog) {
		var modalId = modalDialog.getAttribute('data-emerson-modal-id');
		if (!modalId) {
			return;
		}
		bindIframeDialog(modalDialog, '[data-emerson-modal="' + modalId + '"]');
	});
	window.addEventListener('resize', function () {
		fitSurveyForm(document.getElementById('emerson-recommit'));
	});
}());
