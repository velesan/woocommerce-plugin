(function ($) {
	'use strict';

	var cfg = window.globkurierCustomsCfg || { nonces: {}, i18n: {} };
	var i18n = cfg.i18n || {};

	var state = {
		required: false,
		schema: null,
		senderCountry: null,
		receiverCountry: null,
		carrierName: null,
		declaration: null
	};

	var SEP = ' ;; ';
	
	// Licznik zapytan o konfiguracje - spozniona odpowiedz dla poprzedniego przewoznika jest ignorowana.
	var configRequestId = 0;
	
	// Wartosc do selektora atrybutu: nazwy pol i kody HS przychodza z API i moga zawierac cudzyslow.
	function attrEsc(value) {
		return String(value === undefined || value === null ? '' : value).replace(/["\\]/g, '\\$&');
	}

	window.globkurierCustoms = {
		confirmCarrierChange: confirmCarrierChange,
		onCarrierSelected: onCarrierSelected,
		getPayload: getPayload,
		getCustomsNotice: getCustomsNotice
	};

	function getCustomsNotice() {
		if (state.required && state.declaration) {
			return i18n.afterOrderInfo || '';
		}
		return '';
	}

	/**
	 * Wolane PRZED wyborem przewoznika - false = uzytkownik anulowal, wybor nie powinien sie zmienic.
	 */
	function confirmCarrierChange(newCarrierName) {
		if (state.declaration && newCarrierName && newCarrierName !== state.carrierName) {
			return window.confirm(i18n.resetConfirm || 'Zmiana wyczyści deklarację. Kontynuować?');
		}
		return true;
	}

	function onCarrierSelected(carrierData, senderCountry, receiverCountry) {
		var newCarrier = (carrierData && carrierData.carrierName) ? carrierData.carrierName : '';

		// Ponowny wybor tego samego przewoznika (te same kraje) nie kasuje wypelnionej deklaracji.
		if (state.declaration && newCarrier === state.carrierName
			&& senderCountry === state.senderCountry && receiverCountry === state.receiverCountry) {
			return;
		}

		resetDeclaration();
		state.senderCountry = senderCountry;
		state.receiverCountry = receiverCountry;
		state.carrierName = newCarrier;

		fetchConfig();
	}

	function getPayload() {
		if (state.required && state.declaration) {
			return { customs: state.declaration };
		}
		return {};
	}

	function post(action, nonce, data, onSuccess, onFail) {
		data = data || {};
		data.nonce = nonce;

		$.post(cfg.ajaxUrl, { action: action, data: data })
			.done(function (response) {
				var res = response;
				if (typeof response === 'string') {
					try { res = JSON.parse(response); } catch (e) { res = null; }
				}
				onSuccess(res);
			})
			.fail(function () {
				if (onFail) { onFail(); }
				if ($('#globkurier-customs-modal-overlay').is(':visible')) {
					showModalError(i18n.requestError || 'Błąd komunikacji.');
				}
			});
	}

	function fetchConfig() {
		if (!state.carrierName || !state.receiverCountry) {
			hideSection();
			return;
		}

		var requestId = ++configRequestId;
		hideConfigError();

		post('globkurierGetCustomsConfig', cfg.nonces.config, {
			senderCountry: state.senderCountry,
			receiverCountry: state.receiverCountry,
			carrierName: state.carrierName
		}, function (res) {
			if (requestId !== configRequestId) {
				return;
			}
			if (res && res.success && res.data && res.data.error) {
				// Nie wiadomo, czy deklaracja jest wymagana - nie udajemy, ze nie jest.
				resetDeclaration();
				showConfigError();
				return;
			}
			if (res && res.success && res.data && res.data.required && res.data.schema) {
				state.required = true;
				state.schema = res.data.schema;
				renderModalBody();
				showSection();
				updateRequiredMsg();
			} else {
				resetDeclaration();
			}
		}, function () {
			if (requestId === configRequestId) {
				resetDeclaration();
				showConfigError();
			}
		});
	}

	function showConfigError() {
		showSection();
		$('#globkurier-customs-section .globkurier-customs-section__row').hide();
		$('#globkurier-customs-config-error').show();
	}
	function hideConfigError() {
		$('#globkurier-customs-config-error').hide();
		$('#globkurier-customs-section .globkurier-customs-section__row').show();
	}

	function showSection() { $('#globkurier-customs-section').show(); }
	function hideSection() { $('#globkurier-customs-section').hide(); }

	function resetDeclaration() {
		state.required = false;
		state.schema = null;
		state.declaration = null;
		$('#globkurier-customs-data').val('');
		$('#globkurier-customs-modal-body').empty();
		setFilled(false);
		updateRequiredMsg();
		hideSection();
	}

	function setFilled(filled) {
		$('#globkurier-customs-edit-btn').toggleClass('is-filled', !!filled);
	}

	function updateRequiredMsg() {
		var show = state.required && !state.declaration;
		$('#globkurier-customs-required-msg').toggle(show);
	}

	function openModal() {
		$('#globkurier-customs-modal-overlay').css('display', 'flex');
	}
	function closeModal() { $('#globkurier-customs-modal-overlay').hide(); }
	function showModalError(msg) { $('#globkurier-customs-modal-error').text(msg).show(); }
	function clearModalError() { $('#globkurier-customs-modal-error').hide().text(''); }

	function renderModalBody() {
		var $body = $('#globkurier-customs-modal-body').empty();
		if (!state.schema || !Array.isArray(state.schema.fields)) {
			return;
		}

		state.schema.fields.forEach(function (field) {
			if (field.type === 'collection' && !field.compound) {
				$body.append(renderCommodities(field));
			} else if (field.compound) {
				$body.append(renderCompound(field));
			} else {
				$body.append(wrapStandalone(field));
			}
		});

		recalcAll();

		autoImportItems();
	}

	function autoImportItems() {
		var $group = $('#globkurier-customs-modal-body [data-collection="commodities"]');
		if ($group.length) {
			importItems($group.find('.globkurier-customs-commodity-rows'));
		}
	}

	function renderCompound(field) {
		var $group = $('<div class="globkurier-customs-fieldgroup" data-compound="1">').attr('data-name', field.name);
		if (field.label) {
			$group.append($('<h3 class="globkurier-customs-fieldgroup__title">').text(field.label));
		}
		$group.append(compoundRow(field));
		return $group;
	}

	function compoundRow(field) {
		var $row = $('<div class="globkurier-customs-row">');
		(field.fields || []).forEach(function (sub) {
			$row.append(createField(sub, field.name));
		});
		return $row;
	}

	function wrapStandalone(field) {
		var $row = $('<div class="globkurier-customs-row">');
		$row.append(createField(field, null));
		return $row;
	}

	function renderCommodities(collectionField) {
		var $group = $('<div class="globkurier-customs-fieldgroup" data-collection="commodities">');
		$group.append($('<h3 class="globkurier-customs-fieldgroup__title">').text(collectionField.label || 'Zawartość'));

		var $rows = $('<div class="globkurier-customs-commodity-rows">');
		$group.append($rows);

		$group.data('template', collectionField.fields || []);

		var $actions = $('<div class="globkurier-customs-actions">');
		var $importBtn = $('<button type="button" class="button globkurier-customs-import">').text(i18n.importItems || 'Pobierz produkty');
		var $addBtn = $('<button type="button" class="button button-secondary globkurier-customs-add">').text(i18n.addRow || 'Dodaj pozycję');
		$actions.append($importBtn, $addBtn);
		$group.append($actions);

		addCommodityRow($rows, collectionField.fields || []);

		return $group;
	}

	function addCommodityRow($rows, templateFields, values) {
		var index = $rows.children('.globkurier-customs-commodity').length + 1;
		var $row = $('<div class="globkurier-customs-commodity">');
		$row.append($('<span class="globkurier-customs-commodity__index">').text(index + '.'));

		var $main = $('<div class="globkurier-customs-commodity__main">');
		var $lineA = $('<div class="globkurier-customs-commodity__line globkurier-customs-commodity__line--desc">');
		var $lineB = $('<div class="globkurier-customs-commodity__line">');
		var cFields = [];

		templateFields.forEach(function (sub) {
			var val = values ? values[sub.name] : undefined;
			var $field = createField(sub, 'commodities', val);
			var cls = classifyCommodityField(sub);
			if (cls === 'A') { $lineA.append($field); }
			else if (cls === 'B') { $lineB.append($field); }
			else { cFields.push($field); }
		});

		if ($lineA.children().length) { $main.append($lineA); }
		if ($lineB.children().length) { $main.append($lineB); }

		for (var ci = 0; ci < cFields.length; ci += 3) {
			var $lineC = $('<div class="globkurier-customs-commodity__line">');
			cFields.slice(ci, ci + 3).forEach(function ($f) { $lineC.append($f); });
			$main.append($lineC);
		}
		$row.append($main);

		var $remove = $('<button type="button" class="button-link globkurier-customs-commodity__remove">')
			.attr('title', i18n.removeRow || 'Usuń pozycję')
			.attr('aria-label', i18n.removeRow || 'Usuń pozycję')
			.append('<span class="dashicons dashicons-no-alt"></span>');
		$row.append($remove);

		$rows.append($row);
		reindexRows($rows);
		return $row;
	}

	function classifyCommodityField(field) {
		var ft = field.frontType || field.type || 'text';
		if (ft === 'dual_description') { return 'A'; }
		if (field.name === 'mid' || field.name === 'hsCode' || field.name === 'countryOfOrigin') { return 'B'; }
		return 'C';
	}

	function reindexRows($rows) {
		$rows.children('.globkurier-customs-commodity').each(function (i) {
			$(this).children('.globkurier-customs-commodity__index').text((i + 1) + '.');
		});
	}

	function createField(field, section, presetValue) {
		var ft = field.frontType || field.type || 'text';

		if (ft === 'dual_description') {
			return createDualField(field, section, presetValue);
		}

		var $wrap = $('<div class="globkurier-customs-field">');
		$wrap.attr('data-section', section || field.section || '');
		applyWidth($wrap, field);

		$wrap.append(labelFor(field));

		var $input;
		if (ft === 'select' || ft === 'country') {
			$input = $('<select>');
			(field.options || []).forEach(function (opt) {
				var $opt = $('<option>').val(opt.value).text(opt.name);
				if ((presetValue !== undefined && String(presetValue) === String(opt.value)) ||
					(presetValue === undefined && opt.selected)) {
					$opt.prop('selected', true);
				}
				$input.append($opt);
			});
		} else if (ft === 'number') {
			$input = $('<input type="number">');
			$input.attr('step', 'any');
			if (field.min != null) { $input.attr('min', field.min); }
			if (presetValue !== undefined) { $input.val(presetValue); }
			else if (field.value != null) { $input.val(field.value); }
		} else if (ft === 'autocomplete' || field.name === 'hsCode') {
			$input = $('<select class="globkurier-customs-hs-select">');
			$input.append($('<option value="" disabled selected>').text(field.placeholder || 'kod HS'));
			if (presetValue !== undefined && presetValue !== '' && presetValue !== null) {
				$input.append($('<option>').val(presetValue).text(presetValue).prop('selected', true));
			}
		} else if (ft === 'date') {
			$input = $('<input type="date">');
			if (presetValue !== undefined) { $input.val(presetValue); }
			else if (field.value != null) { $input.val(field.value); }
		} else {
			$input = $('<input type="text">');
			if (presetValue !== undefined) { $input.val(presetValue); }
			else if (field.value != null) { $input.val(field.value); }
		}

		$input.attr('data-name', field.name);
		$input.data('field', field);
		if (field.disabled) { $input.prop('disabled', true); }
		if (field.required) { $input.attr('data-required', '1'); }

		if (field.suffix) {
			var $group = $('<div class="globkurier-customs-inputgroup">');
			$group.append($input);
			$group.append($('<span class="globkurier-customs-suffix">').text(field.suffix));
			$wrap.append($group);
		} else {
			$wrap.append($input);
		}

		$wrap.append($('<div class="field-error" style="display:none;">'));

		var helpText = field.tooltip || field.help || '';
		if (helpText) {
			$wrap.append($('<p class="globkurier-customs-help">').text(helpText));
		}

		return $wrap;
	}

	function applyWidth($wrap, field) {
		var w = field.style && field.style.width ? field.style.width : null;
		if (w) { $wrap.css('flex', '1 1 ' + w); }
	}

	function createDualField(field, section, presetValue) {
		var labels = (field.label || '').split(SEP);
		var plLabel = labels[0] || field.label || '';
		var enLabel = labels[1] || (plLabel + ' (EN)');
		var max = field.max ? Math.floor((field.max - SEP.length) / 2) : null;

		var pre = (presetValue !== undefined && presetValue !== null) ? String(presetValue).split(SEP) : [];

		var $wrap = $('<div class="globkurier-customs-field globkurier-customs-field--dual">');
		$wrap.attr('data-name', field.name).attr('data-dual', '1');
		$wrap.attr('data-section', section || field.section || '');
		$wrap.data('field', field);
		applyWidth($wrap, field);

		[{ lng: 'pl', lbl: plLabel, v: pre[0] }, { lng: 'en', lbl: enLabel, v: pre[1] }].forEach(function (part) {
			var $sub = $('<div class="globkurier-customs-field__dual-part">');
			var $lab = $('<label>').text(part.lbl);
			if (field.required) { $lab.append($('<span class="required-star"> *</span>')); }
			$sub.append($lab);
			var $in = $('<input type="text" class="dual-input">').attr('data-lng', part.lng);
			if (part.v != null) { $in.val(part.v); }
			if (field.required) { $in.attr('data-required', '1'); }
			if (part.lng === 'pl') {
				var $inWrap = $('<div class="globkurier-customs-translate-wrap">');
				var $translateBtn = $('<button type="button" class="globkurier-customs-translate-btn">')
					.attr('title', i18n.translate || 'Przetłumacz na angielski')
					.attr('aria-label', i18n.translate || 'Przetłumacz na angielski')
					.append('<span class="dashicons dashicons-translation"></span>');
				$inWrap.append($in, $translateBtn);
				$sub.append($inWrap);
			} else {
				$sub.append($in);
			}
			if (max) {
				var $cnt = $('<div class="char-counter">').text((part.v ? part.v.length : 0) + '/' + max);
				$in.attr('maxlength', max);
				$sub.append($cnt);
			}
			$wrap.append($sub);
		});

		$wrap.append($('<div class="field-error" style="display:none;">'));
		return $wrap;
	}

	function labelFor(field) {
		var $lab = $('<label>').text(field.label || field.placeholder || field.name);
		if (field.required) { $lab.append($('<span class="required-star"> *</span>')); }
		return $lab;
	}

	function recalcAll() {
		$('#globkurier-customs-modal-body .globkurier-customs-commodity').each(function () {
			recalcRow($(this));
		});
		recalcTotals();
	}

	function commodityTemplate() {
		return $('#globkurier-customs-modal-body [data-collection="commodities"]').data('template') || [];
	}

	function customFn(field, formula) {
		var fns = (field && field.customFunctions) || [];
		for (var i = 0; i < fns.length; i++) {
			if (fns[i] && fns[i].formula === formula) { return fns[i]; }
		}
		return null;
	}

	function defaultPrecision(name) {
		return /value|charges/i.test(String(name || '')) ? 2 : 3;
	}

	function recalcRow($row) {
		commodityTemplate().forEach(function (field) {
			var fn = customFn(field, 'multiple');
			if (!fn || !Array.isArray(fn.args)) { return; }
			var product = 1;
			fn.args.forEach(function (n) { product *= num(valByName($row, n)); });
			var $out = $row.find('[data-name="' + attrEsc(field.name) + '"]');
			if ($out.length) { $out.val(product.toFixed(precisionOf($out, defaultPrecision(field.name)))); }
		});
	}

	function recalcTotals() {
		if (!state.schema || !Array.isArray(state.schema.fields)) { return; }
		state.schema.fields.forEach(function (field) {
			var fn = customFn(field, 'sum-list');
			if (!fn || !Array.isArray(fn.args) || fn.args.length < 3) { return; }
			var src = fn.args[1], dest = fn.args[2];
			if (!Array.isArray(src) || !Array.isArray(dest) || src.length !== dest.length + 1) { return; }

			var units = [], sumsByUnit = {};
			$('#globkurier-customs-modal-body .globkurier-customs-commodity').each(function () {
				var $row = $(this);
				var unit = valByName($row, src[0]) || '';
				var mult = num(valByName($row, src[1]));
				if (!sumsByUnit[unit]) {
					sumsByUnit[unit] = [];
					for (var k = 1; k < dest.length; k++) { sumsByUnit[unit][k] = 0; }
					units.push(unit);
				}
				for (var m = 1; m < dest.length; m++) {
					sumsByUnit[unit][m] += mult * num(valByName($row, src[m + 1]));
				}
			});

			var $tw = $('#globkurier-customs-modal-body .globkurier-customs-fieldgroup[data-name="' + attrEsc(field.name) + '"]').first();
			if (!$tw.length) { return; }

			var wanted = Math.max(units.length, 1);
			var $totalRows = $tw.children('.globkurier-customs-row');
			while ($totalRows.length > wanted) {
				$totalRows.last().remove();
				$totalRows = $tw.children('.globkurier-customs-row');
			}
			while ($totalRows.length < wanted) {
				$tw.append(compoundRow(field));
				$totalRows = $tw.children('.globkurier-customs-row');
			}

			units.forEach(function (unit, i) {
				var $r = $totalRows.eq(i);
				setByName($r, dest[0], unit);
				for (var j = 1; j < dest.length; j++) {
					setByName($r, dest[j], sumsByUnit[unit][j].toFixed(precisionOf($r.find('[data-name="' + attrEsc(dest[j]) + '"]'), defaultPrecision(dest[j]))));
				}
			});
		});

		applySumValidators();
	}

	function orderWeightLimit() {
		var $w = $('#globkurier-weight');
		if (!$w.length) { return null; }
		var w = num($w.val());
		if (w <= 0) { return null; }
		var q = num($('#globkurier-quantity').val());
		return w * (q > 0 ? q : 1);
	}

	function sumValidatorError(field) {
		var fn = customFn(field, 'sum-validator');
		if (!fn || !Array.isArray(fn.args) || fn.args.length < 2 || !Array.isArray(fn.args[1])) { return null; }

		var limit = orderWeightLimit();
		if (limit == null) { return null; }

		var names = fn.args[1];
		var total = 0;
		$('#globkurier-customs-modal-body .globkurier-customs-commodity').each(function () {
			var $row = $(this);
			var product = 1;
			names.forEach(function (n) { product *= num(valByName($row, n)); });
			total += product;
		});

		if (total > limit + 0.001) {
			return i18n.weightExceeds || 'Waga całkowita w deklaracji celnej nie może być większa niż waga podana w zamówieniu.';
		}
		return null;
	}

	function applySumValidators() {
		if (!state.schema || !Array.isArray(state.schema.fields)) { return true; }
		var ok = true;

		state.schema.fields.forEach(function (field) {
			var $group = $('#globkurier-customs-modal-body .globkurier-customs-fieldgroup[data-name="' + attrEsc(field.name) + '"]').first();
			if (!$group.length) { return; }

			var msg = sumValidatorError(field);
			var $err = $group.children('.globkurier-customs-sum-error');

			if (msg) {
				if (!$err.length) { $err = $('<p class="globkurier-customs-sum-error">').appendTo($group); }
				$err.text(msg);
				$group.addClass('has-sum-error');
				ok = false;
			} else {
				$err.remove();
				$group.removeClass('has-sum-error');
			}
		});

		return ok;
	}

	function valByName($scope, name) {
		return $scope.find('[data-name="' + attrEsc(name) + '"]').val();
	}
	function setByName($scope, name, val) {
		$scope.find('[data-name="' + attrEsc(name) + '"]').val(val);
	}
	function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
	function precisionOf($el, def) {
		var f = $el.data('field');
		return (f && f.precision != null) ? Math.min(+f.precision, def) : def;
	}

	function dualPlValue($row, name) {
		var $dual = $row.find('[data-name="' + attrEsc(name) + '"][data-dual="1"]');
		if ($dual.length) { return $dual.find('input[data-lng="pl"]').val() || ''; }
		return $row.find('[data-name="' + attrEsc(name) + '"]').val() || '';
	}

	function hsSourceFields() {
		var tpl = commodityTemplate();
		for (var i = 0; i < tpl.length; i++) {
			var fn = customFn(tpl[i], 'fetch-hs');
			if (fn && Array.isArray(fn.args)) { return fn.args; }
		}
		return tpl.filter(function (f) {
			return (f.frontType || f.type) === 'dual_description';
		}).map(function (f) { return f.name; });
	}

	function autoFetchHs($row) {
		if (!state.receiverCountry) { return; }

		var srcNames = hsSourceFields();
		if (!srcNames.length) { return; }

		var parts = srcNames
			.map(function (n) { return dualPlValue($row, n); })
			.filter(Boolean).join(' ').trim();

		if (parts.length < 2) { return; }

		var $sel = $row.find('select.globkurier-customs-hs-select');
		if (!$sel.length) { return; }

		clearTimeout($row.data('hsTimer'));
		$row.data('hsTimer', setTimeout(function () {
			post('globkurierFetchHsCode', cfg.nonces.hsCode, {
				receiverCountryId: state.receiverCountry,
				itemDescription: parts
			}, function (res) {
				if (!res || !res.success || !res.data || !res.data.code) { return; }
				var code = res.data.code;
				var label = code + ' - ' + (res.data.description || parts);
				$sel.find('option[data-auto="1"]').filter(function () { return this.value !== code; }).remove();
				var $opt = $sel.find('option[value="' + attrEsc(code) + '"]');
				if (!$opt.length) {
					$sel.append($(new Option(label, code, true, true)).attr('data-auto', '1'));
				} else {
					$opt.text(label);
					$sel.val(code);
				}
				$sel.trigger('change');
			});
		}, 600));
	}

	function importItems($rows) {
		var orderId = $('#globkurier_create_order_order_id').val() || $('#post_ID').val();
		if (!orderId) { return; }

		post('globkurierImportCustomsItems', cfg.nonces.import, { orderId: orderId }, function (res) {
			if (!res || !res.success || !res.data || !Array.isArray(res.data.commodities)) { return; }
			var template = $rows.closest('[data-collection="commodities"]').data('template') || [];
			$rows.empty();
			res.data.commodities.forEach(function (c) {
				var $row = addCommodityRow($rows, template, c);
				if (!c.hsCode) { autoFetchHs($row); }
			});
			if (!$rows.children().length) {
				addCommodityRow($rows, template);
			}
			recalcAll();
			autoTranslateAll($rows);
		});
	}

	function translateDual($dual, onDone) {
		var $btn = $dual.find('.globkurier-customs-translate-btn');
		var $pl = $dual.find('input[data-lng="pl"]');
		var $en = $dual.find('input[data-lng="en"]');
		var text = String($pl.val() || '').trim();

		if (!text || $dual.data('translating')) {
			if (onDone) { onDone(); }
			return;
		}

		$dual.data('translating', true);
		$btn.addClass('is-loading').prop('disabled', true);
		var restore = function () {
			$dual.removeData('translating');
			$btn.removeClass('is-loading').prop('disabled', false);
			if (onDone) { onDone(); }
		};

		post('globkurierTranslateCustoms', cfg.nonces.translate, {
			text: text,
			sourceLanguage: 'pl',
			targetLanguage: 'en'
		}, function (res) {
			restore();
			if (!res || !res.success || !res.data || !res.data.text) {
				var msg = (res && res.data && res.data.message) || i18n.translateError || 'Tłumaczenie nie powiodło się.';
				failField($en, msg);
				return;
			}
			var val = String(res.data.text);
			var max = $en.attr('maxlength');
			if (max) { val = val.slice(0, +max); }
			$en.val(val).removeClass('has-error').trigger('input');
			$dual.find('.field-error').first().hide().text('');
		}, restore);
	}

	function autoTranslateAll($scope) {
		var $duals = $scope.find('.globkurier-customs-field--dual').filter(function () {
			var $d = $(this);
			return String($d.find('input[data-lng="pl"]').val() || '').trim() !== '' &&
				String($d.find('input[data-lng="en"]').val() || '').trim() === '';
		});

		$duals.find('.globkurier-customs-translate-btn').addClass('is-loading').prop('disabled', true);

		var i = 0;
		(function next() {
			if (i >= $duals.length) { return; }
			translateDual($duals.eq(i++), next);
		})();
	}

	function constraintClass(c) {
		var parts = String((c && c.class) || '').split('\\');
		return parts[parts.length - 1];
	}

	function valMsg(key, fallback, val) {
		return String(i18n[key] || fallback).replace(/%[sd]/, val != null ? val : '');
	}

	function checkConstraints(field, val) {
		var list = (field && field.constraints) || [];
		for (var i = 0; i < list.length; i++) {
			var cls = constraintClass(list[i]);
			var a = list[i].arguments || {};
			if (cls === 'Length') {
				if (a.min != null && val.length < +a.min) { return valMsg('valMinLength', 'Min. %d znaków', a.min); }
				if (a.max != null && val.length > +a.max) { return valMsg('valMaxLength', 'Maks. %d znaków', a.max); }
			} else if (cls === 'GreaterThan') {
				if (num(val) <= +a.value) { return valMsg('valGreater', 'Wartość musi być większa niż %s', a.value); }
			} else if (cls === 'GreaterThanOrEqual') {
				if (num(val) < +a.value) { return valMsg('valGreaterEq', 'Wartość nie może być mniejsza niż %s', a.value); }
			} else if (cls === 'LessThanOrEqual') {
				if (num(val) > +a.value) { return valMsg('valLessEq', 'Wartość nie może być większa niż %s', a.value); }
			} else if (cls === 'Date') {
				if (!/^\d{4}-\d{2}-\d{2}$/.test(val) || isNaN(Date.parse(val))) { return valMsg('valDate', 'Nieprawidłowa data (RRRR-MM-DD)'); }
			} else if (cls === 'Number') {
				if (isNaN(parseFloat(val))) { return valMsg('valNumber', 'Wartość musi być liczbą'); }
				if (a.precision != null && (val.split('.')[1] || '').length > +a.precision) {
					return valMsg('valPrecision', 'Maks. %d miejsc po przecinku', a.precision);
				}
			}
		}
		return null;
	}

	function failField($in, msg) {
		$in.addClass('has-error');
		$in.closest('.globkurier-customs-field').find('.field-error').first().text(msg || '').show();
	}

	function validateAndBuild() {
		clearModalError();
		var ok = true;
		var $body = $('#globkurier-customs-modal-body');

		$body.find('.field-error').hide().text('');
		$body.find('.has-error').removeClass('has-error');

		$body.find('[data-required="1"]').each(function () {
			var $in = $(this);
			if ($in.is(':disabled')) { return; }
			if (!String($in.val() || '').trim()) {
				failField($in, i18n.required || 'Pole jest wymagane');
				ok = false;
			}
		});

		$body.find('input[data-name], select[data-name]').each(function () {
			var $in = $(this);
			if ($in.is(':disabled') || $in.hasClass('has-error')) { return; }
			var field = $in.data('field');
			var val = String($in.val() || '').trim();
			if (!val || !field) { return; }
			var msg = checkConstraints(field, val);
			if (msg) { failField($in, msg); ok = false; }
		});

		if (!applySumValidators()) { ok = false; }

		if (!ok) {
			showModalError(i18n.fixFields || 'Popraw zaznaczone pola deklaracji celnej.');
			return null;
		}

		return buildDeclaration();
	}

	function buildDeclaration() {
		var decl = {};
		var $body = $('#globkurier-customs-modal-body');

		state.schema.fields.forEach(function (field) {
			if (field.type === 'collection') {
				decl[field.name] = field.compound ? collectCompoundList(field) : collectCommodities();
			} else if (field.compound) {
				decl[field.name] = collectCompound(field);
			} else {
				decl[field.name] = readField($body, field);
			}
		});

		return decl;
	}

	function collectCompound(field) {
		var obj = {};
		var $group = $('#globkurier-customs-modal-body .globkurier-customs-fieldgroup[data-name="' + attrEsc(field.name) + '"]').first();
		(field.fields || []).forEach(function (sub) {
			obj[sub.name] = readFieldByName($group, sub);
		});
		return obj;
	}

	function collectCompoundList(field) {
		var $group = $('#globkurier-customs-modal-body .globkurier-customs-fieldgroup[data-name="' + attrEsc(field.name) + '"]').first();
		var list = [];
		$group.children('.globkurier-customs-row').each(function () {
			var $row = $(this);
			var obj = {};
			(field.fields || []).forEach(function (sub) {
				obj[sub.name] = readFieldByName($row, sub);
			});
			list.push(obj);
		});
		return list;
	}

	function collectCommodities() {
		var template = $('#globkurier-customs-modal-body [data-collection="commodities"]').data('template') || [];
		var list = [];
		$('#globkurier-customs-modal-body .globkurier-customs-commodity').each(function () {
			var $row = $(this);
			var item = {};
			template.forEach(function (sub) {
				item[sub.name] = readFieldByName($row, sub);
			});
			list.push(item);
		});
		return list;
	}

	function readField($scope, field) {
		if (field.type === 'collection') {
			return field.compound ? collectCompoundList(field) : collectCommodities();
		}
		if (field.compound) { return collectCompound(field); }
		return readFieldByName($scope, field);
	}

	function readFieldByName($scope, field) {
		var ft = field.frontType || field.type || 'text';
		if (ft === 'dual_description') {
			var $dual = $scope.find('[data-name="' + attrEsc(field.name) + '"][data-dual="1"]').first();
			var pl = $dual.find('input[data-lng="pl"]').val() || '';
			var en = $dual.find('input[data-lng="en"]').val() || '';
			return pl + SEP + en;
		}
		var $in = $scope.find('[data-name="' + attrEsc(field.name) + '"]').first();
		var val = $in.val();
		if (ft === 'number') { return val === '' ? null : Number(val); }
		return val;
	}

	$(document)
		.on('click', '#globkurier-customs-config-retry', function (e) {
			e.preventDefault();
			fetchConfig();
		})
		.on('click', '#globkurier-customs-edit-btn', function (e) {
			e.preventDefault();
			openModal();
		})
		.on('click', '#globkurier-customs-modal-close', function () { closeModal(); })
		.on('click', '#globkurier-customs-modal-overlay', function (e) {
			if (e.target === this) { closeModal(); }
		})
		.on('click', '.globkurier-customs-add', function () {
			var $group = $(this).closest('[data-collection="commodities"]');
			addCommodityRow($group.find('.globkurier-customs-commodity-rows'), $group.data('template') || []);
		})
		.on('click', '.globkurier-customs-import', function () {
			var $group = $(this).closest('[data-collection="commodities"]');
			importItems($group.find('.globkurier-customs-commodity-rows'));
		})
		.on('click', '.globkurier-customs-translate-btn', function () {
			translateDual($(this).closest('.globkurier-customs-field--dual'));
		})
		.on('click', '.globkurier-customs-commodity__remove', function () {
			var $rows = $(this).closest('.globkurier-customs-commodity-rows');
			$(this).closest('.globkurier-customs-commodity').remove();
			reindexRows($rows);
			recalcTotals();
		})
		.on('input change', '#globkurier-customs-modal-body input[type="number"]', function () {
			var $row = $(this).closest('.globkurier-customs-commodity');
			if ($row.length) { recalcRow($row); }
			recalcTotals();
		})
		.on('change', '#globkurier-customs-modal-body .globkurier-customs-commodity select[data-name="unitOfMeasurement"]', function () {
			recalcTotals();
		})
		.on('input change', '#globkurier-weight, #globkurier-quantity', function () {
			applySumValidators();
		})
		.on('input', '#globkurier-customs-modal-body .dual-input[maxlength]', function () {
			var max = $(this).attr('maxlength');
			$(this).closest('.globkurier-customs-field__dual-part').find('.char-counter')
				.text(($(this).val() || '').length + '/' + max);
		})
		.on('change', '#globkurier-customs-modal-body .globkurier-customs-commodity .dual-input', function () {
			var name = $(this).closest('.globkurier-customs-field--dual').attr('data-name');
			if (hsSourceFields().indexOf(name) !== -1) {
				autoFetchHs($(this).closest('.globkurier-customs-commodity'));
			}
		})
		.on('click', '#globkurier-customs-modal-save', function () {
			var decl = validateAndBuild();
			if (!decl) { return; }
			state.declaration = decl;
			$('#globkurier-customs-data').val(JSON.stringify(decl));
			setFilled(true);
			updateRequiredMsg();
			closeModal();
		});

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.udi-save-order') : null;
		if (!btn) { return; }
		if (state.required && !state.declaration) {
			e.preventDefault();
			e.stopImmediatePropagation();
			updateRequiredMsg();
			$('html, body').animate({ scrollTop: $('#globkurier-customs-section').offset().top - 80 }, 300);
		}
	}, true);

})(jQuery);
