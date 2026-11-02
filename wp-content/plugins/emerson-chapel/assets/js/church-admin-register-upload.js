/**
 * Front-end register photo upload (Emerson overlay).
 *
 * CA's draganddrop.js is often not enqueued for the register block, and the
 * markup has no #file input for click-to-browse — drops then open the image
 * in a new tab. This wires click + drag/drop to CA's people/household AJAX.
 *
 * Preview: show a local object-URL immediately (same fixed box as CSS), then
 * swap to the server URL only after that image has loaded — avoids the flash
 * / resize when the attachment response replaces a differently sized source.
 */
(function ($) {
	'use strict';

	var ajax =
		typeof ajaxurl !== 'undefined'
			? ajaxurl
			: '/wp-admin/admin-ajax.php';

	function resetDragMessage($area, text) {
		$area.find('.drag-message').text(
			text || 'Drag and drop image into this box to replace'
		);
	}

	function defaultAvatarUrl() {
		return (
			(typeof emersonCaRegisterUpload !== 'undefined' &&
				emersonCaRegisterUpload.defaultAvatar) ||
			''
		);
	}

	function allowDirectoryPhoto(peopleId) {
		var cfg =
			typeof emersonCaRegisterUpload !== 'undefined' &&
			emersonCaRegisterUpload.allowDirectoryPhoto;
		peopleId = parseInt(peopleId, 10) || 0;
		if (!cfg || !cfg.action || !cfg.nonce || peopleId < 1) {
			return;
		}
		$.post(ajax, {
			action: cfg.action,
			nonce: cfg.nonce,
			people_id: peopleId,
		});
	}

	/**
	 * CA sometimes stores a 1×1 test pixel as the person photo; that looks like
	 * an empty dashed box. Swap in the default silhouette so the drop target
	 * is obvious.
	 */
	function ensureVisiblePlaceholder($area) {
		var fallback = defaultAvatarUrl();
		if (!fallback) {
			return;
		}
		$area.find('img').each(function () {
			var img = this;
			function maybeFix() {
				if (img.naturalWidth > 0 && img.naturalWidth < 8) {
					img.src = fallback;
					$area.removeClass('emerson-ca-upload--has-photo');
				} else if (img.naturalWidth >= 8) {
					$area.addClass('emerson-ca-upload--has-photo');
				}
			}
			if (img.complete) {
				maybeFix();
			} else {
				$(img).one('load error', maybeFix);
			}
		});
	}

	/**
	 * Put src into the preview img only after it has decoded, so the box does
	 * not flash empty or jump while the new file loads.
	 */
	function setPreview($area, src, onDone) {
		var $img = $area.find('img').first();
		if (!$img.length || !src) {
			if (onDone) {
				onDone();
			}
			return;
		}
		var img = $img[0];
		if (img.getAttribute('src') === src && img.complete && img.naturalWidth > 0) {
			$area.addClass('emerson-ca-upload--has-photo');
			if (onDone) {
				onDone();
			}
			return;
		}

		$area.addClass('emerson-ca-upload--busy');
		var pre = new Image();
		pre.onload = function () {
			img.src = src;
			$area
				.addClass('emerson-ca-upload--has-photo')
				.removeClass('emerson-ca-upload--busy');
			if (onDone) {
				onDone();
			}
		};
		pre.onerror = function () {
			$area.removeClass('emerson-ca-upload--busy');
			if (onDone) {
				onDone();
			}
		};
		pre.src = src;
	}

	function upload($area, file) {
		if (!file) {
			return;
		}
		if (file.type && file.type.indexOf('image/') !== 0) {
			resetDragMessage($area, 'Please choose an image file');
			return;
		}

		var which = String($area.data('which') || '');
		var id = $area.data('id');
		var nonce = $area.data('nonce');
		if (!which || !nonce) {
			return;
		}

		var objectUrl = URL.createObjectURL(file);
		var probe = new Image();
		probe.onload = function () {
			if (probe.naturalWidth < 8 || probe.naturalHeight < 8) {
				URL.revokeObjectURL(objectUrl);
				resetDragMessage($area, 'Please choose a larger photo');
				return;
			}
			// Instant local preview in the fixed box; server URL replaces it later.
			setPreview($area, objectUrl);
			sendUpload($area, file, which, id, nonce, objectUrl);
		};
		probe.onerror = function () {
			URL.revokeObjectURL(objectUrl);
			resetDragMessage($area, 'Please choose an image file');
		};
		probe.src = objectUrl;
	}

	function sendUpload($area, file, which, id, nonce, localUrl) {
		var fd = new FormData();
		fd.append('action', 'church_admin');
		fd.append('method', which + '-upload');
		fd.append('nonce', nonce);
		if (id !== undefined && id !== null && String(id) !== '') {
			fd.append('id', id);
		}
		fd.append('userImage', file);

		resetDragMessage($area, 'Uploading…');

		$.ajax({
			url: ajax,
			type: 'post',
			data: fd,
			contentType: false,
			processData: false,
			dataType: 'json',
			success: function (response) {
				if (!response || !response.src) {
					resetDragMessage($area, 'Upload failed');
					if (localUrl) {
						URL.revokeObjectURL(localUrl);
					}
					return;
				}

				$area
					.find('input.attachment_id, input[name="people_attachment_id"]')
					.val(response.attachment_id);
				if (response.id) {
					$('#' + response.id).val(response.attachment_id);
				}

				// Directory cards only show portraits when photo_permission is on.
				var $form = $area.closest('form');
				$form.find('input[name="photo_permission"]').prop('checked', true);
				var peopleId =
					response.people_id ||
					$area.data('id') ||
					$form.find('input[name="people_id"]').val();
				allowDirectoryPhoto(peopleId);

				var serverSrc = response.src;
				setPreview($area, serverSrc, function () {
					if (localUrl) {
						URL.revokeObjectURL(localUrl);
					}
				});
				showRemoveButton($area, true);
				var $form = $area.closest('form');
				$form.find('input[name="emerson_ca_clear_people_photo"]').remove();

				resetDragMessage($area, 'Uploaded');
				window.setTimeout(function () {
					resetDragMessage($area);
				}, 1600);
			},
			error: function () {
				resetDragMessage($area, 'Upload failed');
				if (localUrl) {
					URL.revokeObjectURL(localUrl);
				}
			},
		});
	}

	function clearPhotoCfg() {
		return (
			(typeof emersonCaRegisterUpload !== 'undefined' &&
				emersonCaRegisterUpload.clearPhoto) ||
			null
		);
	}

	function showRemoveButton($area, show) {
		var $btn = $area.find('.emerson-ca-remove-photo');
		if (!$btn.length) {
			return;
		}
		$btn.prop('hidden', !show);
	}

	function resetToPlaceholder($area) {
		var fallback = defaultAvatarUrl();
		var $img = $area.find('img').first();
		if ($img.length && fallback) {
			$img.attr('src', fallback).removeAttr('srcset').removeAttr('sizes');
		}
		$area
			.find(
				'input.attachment_id, input[name="people_attachment_id"], input[name="household_attachment_id"]'
			)
			.val('');
		$area.removeClass('emerson-ca-upload--has-photo');
		showRemoveButton($area, false);
		resetDragMessage(
			$area,
			$area.hasClass('emerson-ca-person-photo')
				? 'Drag a photo here, or click to choose'
				: 'Drag and drop image into this box to replace'
		);
	}

	function clearPhoto($area) {
		var cfg = clearPhotoCfg();
		var which = String($area.data('which') || '');
		var id = $area.data('id');
		if (!cfg || !cfg.action || !cfg.nonce || !which || !id) {
			resetToPlaceholder($area);
			return;
		}

		resetDragMessage($area, 'Removing…');
		$.post(ajax, {
			action: cfg.action,
			nonce: cfg.nonce,
			which: which,
			id: id,
		})
			.done(function (response) {
				if (!response || !response.success) {
					resetDragMessage($area, 'Could not remove photo');
					return;
				}
				resetToPlaceholder($area);
				var $form = $area.closest('form');
				if ($form.length && which === 'people') {
					var $flag = $form.find('input[name="emerson_ca_clear_people_photo"]');
					if (!$flag.length) {
						$form.append(
							'<input type="hidden" name="emerson_ca_clear_people_photo" value="1" />'
						);
					} else {
						$flag.val('1');
					}
				}
			})
			.fail(function () {
				resetDragMessage($area, 'Could not remove photo');
			});
	}

	function bindArea(area) {
		var $area = $(area);
		if ($area.data('emersonUploadBound')) {
			return;
		}
		$area.data('emersonUploadBound', true);

		ensureVisiblePlaceholder($area);

		if (!$area.find('.emerson-ca-remove-photo').length) {
			var $remove = $(
				'<button type="button" class="emerson-ca-remove-photo">Remove photo</button>'
			);
			$area.append($remove);
			showRemoveButton(
				$area,
				$area.hasClass('emerson-ca-upload--has-photo') ||
					($area.find('img').first().attr('src') || '').indexOf('default-avatar') ===
						-1 &&
						($area.find('img').first().attr('src') || '').indexOf('.svg') === -1
			);
			// Refine after images load.
			$area.find('img').each(function () {
				var img = this;
				function sync() {
					var real =
						img.naturalWidth >= 8 &&
						(img.src || '').indexOf('default-avatar') === -1 &&
						(img.src || '').indexOf('man.svg') === -1 &&
						(img.src || '').indexOf('woman.svg') === -1;
					if (real) {
						$area.addClass('emerson-ca-upload--has-photo');
					}
					showRemoveButton($area, !!real);
				}
				if (img.complete) {
					sync();
				} else {
					$(img).one('load error', sync);
				}
			});
			$remove.on('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				clearPhoto($area);
			});
		}

		var $file = $(
			'<input type="file" accept="image/*" class="emerson-ca-upload-file" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;" tabindex="-1" aria-hidden="true" />'
		);
		$area.css('position', 'relative').append($file);

		$area.on('click', function (e) {
			if (
				$(e.target).closest(
					'a, button, input:not(.emerson-ca-upload-file), .emerson-ca-remove-photo'
				).length
			) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			$file.trigger('click');
		});

		$file.on('click', function (e) {
			e.stopPropagation();
		});

		$file.on('change', function () {
			if (this.files && this.files[0]) {
				upload($area, this.files[0]);
			}
			this.value = '';
		});

		$area.on('dragenter dragover', function (e) {
			e.preventDefault();
			e.stopPropagation();
			$area.find('.drag-message').text('Drop to upload');
		});

		$area.on('dragleave', function (e) {
			e.preventDefault();
			$area
				.find('.drag-message')
				.text('Drag and drop image into this box to replace');
		});

		$area.on('drop', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var dt = e.originalEvent && e.originalEvent.dataTransfer;
			var files = dt && dt.files;
			if (files && files[0]) {
				upload($area, files[0]);
			}
		});
	}

	$(function () {
		$('.ca-upload-area, .emerson-ca-person-photo').each(function () {
			bindArea(this);
		});
	});
})(jQuery);
