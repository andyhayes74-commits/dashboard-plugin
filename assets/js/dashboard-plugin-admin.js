(function () {
	'use strict';

	var form = document.getElementById('hayfam-dashboard-settings-form');
	var output = document.getElementById('hayfam-dashboard-preview-output');
	var widget = output ? output.querySelector('.hayfam-dashboard-metric') : null;

	if (!form || !output || !widget) {
		return;
	}

	var before = widget.querySelector('.hayfam-dashboard-metric__before');
	var value = widget.querySelector('.hayfam-dashboard-metric__value');
	var after = widget.querySelector('.hayfam-dashboard-metric__after');
	var fontFamilies = {
		inherit: 'inherit',
		system: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
		arial: 'Arial, Helvetica, sans-serif',
		georgia: 'Georgia, "Times New Roman", serif',
		courier: '"Courier New", Courier, monospace',
		trebuchet: '"Trebuchet MS", Arial, sans-serif',
		verdana: 'Verdana, Arial, sans-serif'
	};
	var classGroups = {
		preset: 'hayfam-dashboard-widget--preset-',
		border: 'hayfam-dashboard-widget--border-',
		background: 'hayfam-dashboard-widget--background-',
		graphic: 'hayfam-dashboard-widget--graphic-'
	};
	var fieldNames = {
		before: 'before',
		after: 'after',
		prefix: 'prefix',
		suffix: 'suffix',
		override: 'override',
		fontFamily: 'font_family',
		fontSize: 'font_size',
		valueFontSize: 'value_font_size',
		fontWeight: 'font_weight',
		valueFontWeight: 'value_font_weight',
		lineHeight: 'line_height',
		textAlign: 'text_align',
		beforeColor: 'before_color',
		valueColor: 'value_color',
		afterColor: 'after_color',
		backgroundColor: 'background_color',
		gap: 'gap',
		padding: 'padding',
		borderRadius: 'border_radius',
		preset: 'widget_preset',
		border: 'widget_border',
		background: 'widget_background',
		graphic: 'widget_graphic',
		animatedGraphic: 'animated_graphic',
		graphicMax: 'graphic_max'
	};
	var initialPrefix = getValue(fieldNames.prefix);
	var initialSuffix = getValue(fieldNames.suffix);
	var sourceValue = stripAffixes(value ? value.textContent : '', initialPrefix, initialSuffix);

	function getField(name) {
		return form.querySelector('[name="dashboard[' + name + ']"]');
	}

	function getValue(name) {
		var field = getField(name);
		return field ? field.value : '';
	}

	function stripAffixes(text, prefix, suffix) {
		var result = text || '';
		if (prefix && result.indexOf(prefix) === 0) {
			result = result.slice(prefix.length);
		}
		if (suffix && result.slice(-suffix.length) === suffix) {
			result = result.slice(0, -suffix.length);
		}
		return result;
	}

	function setText(element, text) {
		if (!element) {
			return;
		}
		element.textContent = '';
		String(text || '').split(/\r?\n/).forEach(function (line, index) {
			if (index) {
				element.appendChild(document.createElement('br'));
			}
			element.appendChild(document.createTextNode(line));
		});
	}

	function setStyle(element, property, value) {
		if (!element) {
			return;
		}
		if (value) {
			element.style.setProperty(property, value, 'important');
		} else {
			element.style.removeProperty(property);
		}
	}

	function setVariable(property, value) {
		if (value) {
			widget.style.setProperty(property, value);
		} else {
			widget.style.removeProperty(property);
		}
	}

	function setWidgetClass(group, value) {
		var prefix = classGroups[group];
		Array.prototype.slice.call(widget.classList).forEach(function (className) {
			if (className.indexOf(prefix) === 0) {
				widget.classList.remove(className);
			}
		});
		if (value) {
			widget.classList.add(prefix + value.replace(/_/g, '-'));
		}
	}

	function normaliseLength(value) {
		var trimmed = String(value || '').trim();
		if (!trimmed) {
			return '';
		}
		return /^\d+(?:\.\d+)?$/.test(trimmed) ? trimmed + 'px' : trimmed;
	}

	function graphicPercent(rawValue, rawMaximum) {
		var number = parseFloat(String(rawValue || '').replace(/,/g, '').replace(/[^0-9.\-]/g, ''));
		var maximum = parseFloat(String(rawMaximum || '').replace(/,/g, ''));
		if (isNaN(number)) {
			number = 0;
		}
		if (isNaN(maximum) || maximum <= 0) {
			maximum = 100;
		}
		return Math.max(0, Math.min(100, (number / maximum) * 100));
	}

	function createAnimatedGraphic(type) {
		var graphic = document.createElement('div');
		graphic.className = 'hayfam-dashboard-animated hayfam-dashboard-animated--' + type.replace(/_/g, '-');
		graphic.setAttribute('data-hayfam-animated-graphic', type);
		graphic.setAttribute('role', 'img');

		if (type === 'progress_bar') {
			var track = document.createElement('span');
			track.className = 'hayfam-dashboard-animated__track';
			var fill = document.createElement('span');
			fill.className = 'hayfam-dashboard-animated__fill';
			track.appendChild(fill);
			graphic.appendChild(track);
		} else if (type === 'progress_arc') {
			var arc = document.createElement('span');
			arc.className = 'hayfam-dashboard-animated__arc';
			var arcLabel = document.createElement('span');
			arcLabel.className = 'hayfam-dashboard-animated__arc-label';
			arc.appendChild(arcLabel);
			graphic.appendChild(arc);
		} else if (type === 'battery') {
			var battery = document.createElement('span');
			battery.className = 'hayfam-dashboard-animated__battery';
			var level = document.createElement('span');
			level.className = 'hayfam-dashboard-animated__battery-level';
			var terminal = document.createElement('span');
			terminal.className = 'hayfam-dashboard-animated__battery-terminal';
			battery.appendChild(level);
			battery.appendChild(terminal);
			graphic.appendChild(battery);
		} else if (type === 'pulse') {
			var pulse = document.createElement('span');
			pulse.className = 'hayfam-dashboard-animated__pulse';
			graphic.appendChild(pulse);
		} else if (type === 'bars') {
			var bars = document.createElement('span');
			bars.className = 'hayfam-dashboard-animated__bars';
			[42, 68, 54, 86, 100].forEach(function () {
				var bar = document.createElement('span');
				bars.appendChild(bar);
			});
			graphic.appendChild(bars);
		}

		return graphic;
	}

	function updateAnimatedGraphic(displayValue) {
		var type = getValue(fieldNames.animatedGraphic);
		var existing = widget.querySelector('.hayfam-dashboard-animated');
		if (!type || type === 'none') {
			if (existing) {
				existing.remove();
			}
			return;
		}

		if (!existing || existing.getAttribute('data-hayfam-animated-graphic') !== type) {
			if (existing) {
				existing.remove();
			}
			existing = createAnimatedGraphic(type);
			widget.insertBefore(existing, before);
		}

		var percent = graphicPercent(displayValue, getValue(fieldNames.graphicMax));
		existing.style.setProperty('--hayfam-dashboard-graphic-percent', percent + '%');
		existing.setAttribute('aria-label', 'Progress ' + Math.round(percent) + ' percent');
		var label = existing.querySelector('.hayfam-dashboard-animated__arc-label');
		if (label) {
			label.textContent = Math.round(percent) + '%';
		}
		if (type === 'bars') {
			[42, 68, 54, 86, 100].forEach(function (height, index) {
				var bar = existing.querySelectorAll('.hayfam-dashboard-animated__bars > span')[index];
				if (bar) {
					bar.style.setProperty('--hayfam-dashboard-bar-height', Math.max(12, Math.min(100, height * percent / 100)) + '%');
				}
			});
		}
	}

	function update() {
		var prefix = getValue(fieldNames.prefix);
		var suffix = getValue(fieldNames.suffix);
		var override = getValue(fieldNames.override).trim();
		var displayValue = override || sourceValue;

		setText(before, getValue(fieldNames.before));
		setText(value, prefix + displayValue + suffix);
		setText(after, getValue(fieldNames.after));
		updateAnimatedGraphic(displayValue);

		setWidgetClass('preset', getValue(fieldNames.preset));
		setWidgetClass('border', getValue(fieldNames.border));
		setWidgetClass('background', getValue(fieldNames.background));
		setWidgetClass('graphic', getValue(fieldNames.graphic));

		var family = fontFamilies[getValue(fieldNames.fontFamily)] || '';
		setStyle(widget, 'font-family', family);
		setStyle(before, 'font-family', family);
		setStyle(value, 'font-family', family);
		setStyle(after, 'font-family', family);
		setVariable('--hayfam-dashboard-font-family', family);

		var textSize = normaliseLength(getValue(fieldNames.fontSize));
		setStyle(widget, 'font-size', textSize);
		setStyle(before, 'font-size', textSize);
		setStyle(after, 'font-size', textSize);
		setVariable('--hayfam-dashboard-font-size', textSize);

		var valueSize = normaliseLength(getValue(fieldNames.valueFontSize));
		setStyle(value, 'font-size', valueSize);
		setStyle(widget, 'font-weight', getValue(fieldNames.fontWeight));
		setStyle(value, 'font-weight', getValue(fieldNames.valueFontWeight));
		setVariable('--hayfam-dashboard-value-font-size', valueSize);
		setVariable('--hayfam-dashboard-font-weight', getValue(fieldNames.fontWeight));
		setVariable('--hayfam-dashboard-value-font-weight', getValue(fieldNames.valueFontWeight));

		var lineHeight = getValue(fieldNames.lineHeight);
		if (lineHeight && parseFloat(lineHeight) >= 1) {
			setStyle(widget, 'line-height', lineHeight);
			setStyle(before, 'line-height', lineHeight);
			setStyle(value, 'line-height', lineHeight);
			setStyle(after, 'line-height', lineHeight);
			setVariable('--hayfam-dashboard-line-height', lineHeight);
		} else {
			setStyle(widget, 'line-height', '1.25');
			setStyle(before, 'line-height', '1.25');
			setStyle(value, 'line-height', '1.15');
			setStyle(after, 'line-height', '1.25');
			setVariable('--hayfam-dashboard-line-height', '');
		}

		var textAlign = getValue(fieldNames.textAlign);
		var backgroundColor = getValue(fieldNames.backgroundColor);
		var gap = normaliseLength(getValue(fieldNames.gap));
		var padding = normaliseLength(getValue(fieldNames.padding));
		var borderRadius = normaliseLength(getValue(fieldNames.borderRadius));
		setStyle(widget, 'text-align', textAlign);
		setStyle(widget, 'background-color', backgroundColor);
		setStyle(widget, 'gap', gap);
		setStyle(widget, 'padding', padding);
		setStyle(widget, 'border-radius', borderRadius);
		setVariable('--hayfam-dashboard-text-align', textAlign);
		setVariable('--hayfam-dashboard-background-color', backgroundColor);
		setVariable('--hayfam-dashboard-gap', gap);
		setVariable('--hayfam-dashboard-padding', padding);
		setVariable('--hayfam-dashboard-border-radius', borderRadius);

		var beforeColor = getValue(fieldNames.beforeColor);
		var valueColor = getValue(fieldNames.valueColor);
		var afterColor = getValue(fieldNames.afterColor);
		setStyle(before, 'color', beforeColor);
		setStyle(value, 'color', valueColor);
		setStyle(after, 'color', afterColor);
		setVariable('--hayfam-dashboard-before-color', beforeColor);
		setVariable('--hayfam-dashboard-value-color', valueColor);
		setVariable('--hayfam-dashboard-after-color', afterColor);

		var dark = getValue(fieldNames.preset) === 'dark_card' || getValue(fieldNames.background) === 'dark';
		setStyle(widget, 'color', dark ? '#ffffff' : '#1f2937');
	}

	form.addEventListener('input', update);
	form.addEventListener('change', update);
	update();
}());
