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
		/* Scroll instead of transform:scale — scaling broke signature pointer coordinates. */
		form.style.transform = '';
		form.style.maxHeight = '';
		form.style.overflowY = '';
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
		form.style.maxHeight = available + 'px';
		form.style.overflowY = 'auto';
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
		block.innerHTML = '<p class="emerson-recommit__sign">Signed<label class="screen-reader-text" for="emerson-recommit-pad">Draw your signature</label><canvas class="emerson-recommit__pad" id="emerson-recommit-pad"></canvas></p><div class="emerson-recommit__sign-actions"><button type="button" class="emerson-recommit__clear">Clear signature</button><div class="emerson-recommit__date-line"></div></div><p class="emerson-recommit__status" role="status"></p>';
		anchor.parentNode.insertBefore(block, anchor);
		var dateLine = block.querySelector('.emerson-recommit__date-line');
		var dateField = dialog.querySelector('.fields[class*="fieldname7_"]');
		if (dateField && dateLine) {
			dateLine.appendChild(dateField);
			var dateInput = dateField.querySelector('input.date-component, input[id$="_date"], input[type="text"], input');
			if (dateInput) {
				/* CFF Date signed uses mm/dd/yyyy — placeholder is the format explainer. */
				dateInput.setAttribute('placeholder', 'mm/dd/yyyy');
				if (!dateInput.getAttribute('aria-label')) {
					dateInput.setAttribute('aria-label', 'Date signed, mm/dd/yyyy');
				}
			}
		}
		var canvas = block.querySelector('.emerson-recommit__pad');
		var status = block.querySelector('.emerson-recommit__status');
		var ctx = canvas.getContext('2d');
		var drawing = false;
		var ink = false;

		function signatureInput() {
			var input = form.querySelector('[name="emerson_signature"]');
			if (!input) {
				input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'emerson_signature';
				form.appendChild(input);
			}
			return input;
		}

		/** Keep the field filled — Calculated Fields submits via nativeSubmit(), which skips the submit event. */
		function syncSignatureField() {
			signatureInput().value = ink ? canvas.toDataURL('image/png') : '';
		}

		function sizeCanvas() {
			/* Use layout size (client*), not getBoundingClientRect — that includes parent transforms. */
			var cssW = Math.max(1, canvas.clientWidth);
			var cssH = Math.max(1, canvas.clientHeight);
			var ratio = window.devicePixelRatio || 1;
			var previous = ink ? canvas.toDataURL('image/png') : '';
			canvas.width = Math.max(1, Math.round(cssW * ratio));
			canvas.height = Math.max(1, Math.round(cssH * ratio));
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			ctx.lineWidth = 2.25;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			ctx.strokeStyle = '#1e1e1e';
			ctx.fillStyle = '#fff';
			ctx.fillRect(0, 0, cssW, cssH);
			if (previous) {
				var image = new Image();
				image.onload = function () {
					ctx.drawImage(image, 0, 0, cssW, cssH);
					syncSignatureField();
				};
				image.src = previous;
			} else {
				ink = false;
				syncSignatureField();
			}
		}

		function point(event) {
			var rect = canvas.getBoundingClientRect();
			var scaleX = canvas.clientWidth / Math.max(1, rect.width);
			var scaleY = canvas.clientHeight / Math.max(1, rect.height);
			return {
				x: (event.clientX - rect.left) * scaleX,
				y: (event.clientY - rect.top) * scaleY
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
			syncSignatureField();
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
			syncSignatureField();
			status.textContent = '';
			status.classList.remove('is-error');
		}, true);
		if (window.jQuery && window.jQuery.fbuilder) {
			window.jQuery.fbuilder.extra_validations = window.jQuery.fbuilder.extra_validations || {};
			window.jQuery.fbuilder.extra_validations.emersonSignature = function (cffForm) {
				var el = cffForm && cffForm.jquery ? cffForm[0] : cffForm;
				if (!el || !dialog.contains(el)) {
					return true;
				}
				if (!ink) {
					status.textContent = 'Draw your signature.';
					status.classList.add('is-error');
					return false;
				}
				syncSignatureField();
				status.textContent = '';
				status.classList.remove('is-error');
				return true;
			};
		}
		canvas.emersonResize = sizeCanvas;
		window.addEventListener('resize', sizeCanvas);
		window.requestAnimationFrame(function () {
			fitSurveyForm(dialog);
			sizeCanvas();
		});
	}

	function digitsOnlyPhone(value) {
		return String(value || '').replace(/\D/g, '').slice(0, 10);
	}

	/** Display as (123)456-7891 from up to 10 digits. */
	function formatSurveyPhone(digits) {
		digits = digitsOnlyPhone(digits);
		if (digits.length === 0) {
			return '';
		}
		if (digits.length <= 3) {
			return '(' + digits;
		}
		if (digits.length <= 6) {
			return '(' + digits.slice(0, 3) + ')' + digits.slice(3);
		}
		return '(' + digits.slice(0, 3) + ')' + digits.slice(3, 6) + '-' + digits.slice(6);
	}

	function installSurveyPhone(dialog) {
		if (!dialog || dialog.id !== 'emerson-recommit') {
			return;
		}
		var input = dialog.querySelector(
			'.emerson-survey-phone input[type="text"], .fields[class*="fieldname5_"] input[type="text"], input[name^="fieldname5"]'
		);
		if (!input || input.dataset.emersonPhone === '1') {
			return;
		}
		input.dataset.emersonPhone = '1';
		input.setAttribute('inputmode', 'numeric');
		input.setAttribute('autocomplete', 'tel');
		input.setAttribute('maxlength', '13');
		input.setAttribute('placeholder', '(123)456-7891');
		if (input.value) {
			input.value = formatSurveyPhone(input.value);
		}
		input.addEventListener('input', function () {
			var start = input.selectionStart;
			var before = input.value.length;
			input.value = formatSurveyPhone(input.value);
			var after = input.value.length;
			if (typeof start === 'number') {
				input.setSelectionRange(Math.max(0, start + (after - before)), Math.max(0, start + (after - before)));
			}
		});
		input.addEventListener('blur', function () {
			input.value = formatSurveyPhone(input.value);
		});
		var form = dialog.querySelector('form');
		if (form) {
			form.addEventListener(
				'submit',
				function (event) {
					var digits = digitsOnlyPhone(input.value);
					input.value = formatSurveyPhone(digits);
					if (digits.length !== 10) {
						event.preventDefault();
						event.stopPropagation();
						input.focus();
						if (typeof input.reportValidity === 'function') {
							input.setCustomValidity('Enter a 10-digit phone number.');
							input.reportValidity();
							input.setCustomValidity('');
						}
					}
				},
				true
			);
		}
	}

	function finishSurveyLayout(dialog) {
		installSurveySignature(dialog);
		installSurveyPhone(dialog);
		window.requestAnimationFrame(function () {
			fitSurveyForm(dialog);
			var pad = dialog.querySelector('.emerson-recommit__pad');
			if (pad && typeof pad.emersonResize === 'function') {
				pad.emersonResize();
			}
			if (typeof window.emersonModalOverflowArrow === 'function') {
				window.emersonModalOverflowArrow(dialog);
			}
		});
	}

	function clearSurveyValidation(dialog) {
		if (!dialog) {
			return;
		}
		/*
		 * Calculated Fields puts `cpefb_error` on the inputs themselves and uses
		 * `.cpefb_error.message` for the bubbles. Removing every `.cpefb_error`
		 * deleted the fields after a failed submit — reopen showed only signature.
		 */
		dialog.querySelectorAll('.cpefb_error.message').forEach(function (node) {
			node.remove();
		});
		dialog.querySelectorAll('.cpefb_error').forEach(function (node) {
			node.classList.remove('cpefb_error');
		});
		dialog.querySelectorAll('.error, .cff-error').forEach(function (node) {
			node.classList.remove('error', 'cff-error');
		});
		dialog.querySelectorAll('[aria-invalid="true"]').forEach(function (node) {
			node.removeAttribute('aria-invalid');
		});
		dialog.querySelectorAll('.cff-error-dlg').forEach(function (node) {
			node.remove();
		});
		var form = dialog.querySelector('form.cff-form');
		if (form) {
			form.style.transform = '';
			form.style.maxHeight = '';
			form.style.overflowY = '';
		}
	}

	/**
	 * Calculated Fields error summary (.cff-error-dlg): fade away after 4s
	 * instead of waiting for a click on the X / document.
	 */
	function watchSurveyErrorDlg(dialog) {
		if (!dialog || dialog.dataset.emersonErrorDlgWatch === '1') {
			return;
		}
		dialog.dataset.emersonErrorDlgWatch = '1';

		function dismissBox(box) {
			if (!box || !box.parentNode) {
				return;
			}
			box.classList.add('emerson-cff-error-dlg--fade');
			window.setTimeout(function () {
				if (window.jQuery && window.jQuery.fbuilder && typeof window.jQuery.fbuilder.closeErrorDlg === 'function') {
					window.jQuery.fbuilder.closeErrorDlg();
					return;
				}
				if (box.parentNode) {
					box.remove();
				}
			}, 450);
		}

		function armBox(box) {
			if (!box || box.dataset.emersonAutoFade === '1') {
				return;
			}
			box.dataset.emersonAutoFade = '1';
			window.setTimeout(function () {
				dismissBox(box);
			}, 4000);
		}

		function scan() {
			dialog.querySelectorAll('.cff-error-dlg').forEach(armBox);
		}

		var watch = new MutationObserver(scan);
		watch.observe(dialog, { childList: true, subtree: true });
		scan();
	}

	/**
	 * After a real Calculated Fields success: thanks for 2s (no X), then close the modal at 4s.
	 */
	function watchSurveyThanks(dialog, closeDialog) {
		if (!dialog || dialog.dataset.emersonThanksWatch === '1') {
			return;
		}
		dialog.dataset.emersonThanksWatch = '1';
		var thanksTimer = null;
		var closeTimer = null;

		function onSuccess() {
			clearSurveyValidation(dialog);
			dialog.classList.add('emerson-recommit--sent');
			var thanks = dialog.querySelector('.cff-thanks-message');
			if (thanks) {
				thanks.style.display = 'block';
			}
			window.clearTimeout(thanksTimer);
			window.clearTimeout(closeTimer);
			thanksTimer = window.setTimeout(function () {
				if (thanks) {
					thanks.style.display = 'none';
				}
			}, 2000);
			closeTimer = window.setTimeout(function () {
				closeDialog();
			}, 4000);
		}

		if (window.jQuery) {
			window.jQuery(document).on('cff-form-submitted.emersonSurvey', function (_event, form) {
				var el = form && form.jquery ? form[0] : form;
				if (el && el.nodeType === 1 && !dialog.contains(el)) {
					return;
				}
				onSuccess();
			});
		}

		var thanksNode = dialog.querySelector('.cff-thanks-message');
		if (thanksNode) {
			var watch = new MutationObserver(function () {
				var shown = window.getComputedStyle(thanksNode).display !== 'none';
				if (shown && !dialog.classList.contains('emerson-recommit--sent')) {
					onSuccess();
				}
			});
			watch.observe(thanksNode, { attributes: true, attributeFilter: ['style', 'class'] });
		}
	}

	function setFrameLoading(dialog, on) {
		var fit = dialog ? dialog.querySelector('[data-emerson-frame-fit]') : null;
		if (!fit) {
			return;
		}
		fit.classList.toggle('is-loading', !!on);
		var label = fit.querySelector('.emerson-recommit__loading');
		if (label) {
			label.hidden = !on;
		}
	}

	/* Our spinner is for Google only — Microsoft Forms already shows its own. */
	function isGoogleFormUrl(url) {
		if (!url) {
			return false;
		}
		try {
			var host = new URL(url, window.location.href).hostname.toLowerCase();
			return host === 'forms.gle' || host === 'docs.google.com';
		} catch (err) {
			return /forms\.gle|docs\.google\.com/i.test(url);
		}
	}

	function bindIframeDialog(dialog, openerSelector) {
		if (!dialog || typeof dialog.showModal !== 'function') {
			return;
		}
		if (dialog.dataset.emersonBound === '1') {
			return;
		}
		dialog.dataset.emersonBound = '1';
		var frame = dialog.querySelector('.emerson-recommit__frame');

		function revealDialog() {
			if (reduceMotion()) {
				return;
			}
			void getComputedStyle(dialog).transform;
			dialog.classList.remove('is-opening');
		}

		function afterOpenReady() {
			revealDialog();
			finishSurveyLayout(dialog);
		}

		function openDialog() {
			if (dialog.open || dialog.classList.contains('is-closing')) {
				return;
			}
			dialog.classList.remove('emerson-recommit--sent');
			clearSurveyValidation(dialog);
			if (!reduceMotion()) {
				dialog.classList.add('is-opening');
			}
			dialog.showModal();

			var list = dialog.querySelector('[id^="fieldlist_"]');
			if (list && !list.children.length && !reduceMotion()) {
				var revealed = false;
				function reveal() {
					if (revealed) {
						return;
					}
					revealed = true;
					observer.disconnect();
					window.requestAnimationFrame(function () {
						window.requestAnimationFrame(afterOpenReady);
					});
				}
				var observer = new MutationObserver(function () {
					if (list.children.length) {
						reveal();
					}
				});
				observer.observe(list, { childList: true });
				window.setTimeout(reveal, 4000);
				return;
			}

			if (frame) {
				var src = frame.getAttribute('src');
				var dataSrc = frame.getAttribute('data-src');
				if (!src && dataSrc) {
					var showGoogleLoader = isGoogleFormUrl(dataSrc);
					if (showGoogleLoader) {
						setFrameLoading(dialog, true);
					}
					function onFrameLoad() {
						frame.removeEventListener('load', onFrameLoad);
						setFrameLoading(dialog, false);
						afterOpenReady();
					}
					frame.addEventListener('load', onFrameLoad);
					frame.setAttribute('src', dataSrc);
					/* Show the shell while the form loads (spinner only for Google). */
					revealDialog();
					if (showGoogleLoader) {
						window.setTimeout(function () {
							if (fitStillLoading(dialog)) {
								setFrameLoading(dialog, false);
								afterOpenReady();
							}
						}, 12000);
					}
					return;
				}
			}

			afterOpenReady();
		}

		function fitStillLoading(dlg) {
			var fit = dlg.querySelector('[data-emerson-frame-fit]');
			return !!(fit && fit.classList.contains('is-loading'));
		}

		function closeDialog() {
			if (!dialog.open || dialog.classList.contains('is-closing')) {
				return;
			}
			setFrameLoading(dialog, false);
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

		if (dialog.id === 'emerson-recommit' && dialog.classList.contains('emerson-recommit--form')) {
			watchSurveyThanks(dialog, closeDialog);
			watchSurveyErrorDlg(dialog);
		}

		document.addEventListener('click', function (event) {
			var opener = event.target.closest ? event.target.closest(openerSelector) : null;
			if (!opener) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
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
	/* One listener per modal id (getElementById) so a duplicated <dialog> cannot open twice. */
	var boundModalIds = {};
	document.querySelectorAll('[data-emerson-modal]').forEach(function (opener) {
		var modalId = opener.getAttribute('data-emerson-modal');
		if (!modalId || boundModalIds[modalId]) {
			return;
		}
		boundModalIds[modalId] = true;
		var modalDialog = document.getElementById('emerson-modal-' + modalId);
		if (!modalDialog) {
			return;
		}
		bindIframeDialog(modalDialog, '[data-emerson-modal="' + modalId + '"]');
	});
	window.addEventListener('resize', function () {
		fitSurveyForm(document.getElementById('emerson-recommit'));
	});
}());
