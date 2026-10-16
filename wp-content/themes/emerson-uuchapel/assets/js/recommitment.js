/**
 * Homepage re-commitment form: open the dialog, capture a signature, and send it.
 */
(function () {
	'use strict';

	var dialog = document.getElementById('emerson-recommit');
	var form = dialog ? dialog.querySelector('.emerson-recommit__form') : null;
	if (!dialog || !form || typeof dialog.showModal !== 'function' || !window.emersonRecommit) {
		return;
	}

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

	function reduceMotion() {
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function openDialog() {
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
		var opener = event.target.closest ? event.target.closest('[data-emerson-recommit]') : null;
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
	dialog.querySelector('.emerson-recommit__close').addEventListener('click', closeDialog);

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
				window.setTimeout(closeDialog, 3000);
				return;
			}
			showError(message || 'The form could not be sent. Please try again, or email the office.');
		}).catch(function () {
			showError('The form could not be sent. Please try again, or email the office.');
		}).then(function () {
			submit.disabled = false;
		});
	});
})();
