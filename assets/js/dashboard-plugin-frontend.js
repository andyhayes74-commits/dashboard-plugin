(function () {
	'use strict';

	function customProperty(element, name) {
		return window.getComputedStyle(element).getPropertyValue(name).trim();
	}

	function setResponsiveStyle(element, property, value) {
		if (!element) {
			return;
		}
		if (value) {
			element.style.setProperty(property, value, 'important');
		} else {
			element.style.removeProperty(property);
		}
	}

	function applyResponsiveStyles(container) {
		if (!container) {
			return;
		}

		var isMobile = window.matchMedia('(max-width: 767px)').matches;
		var before = container.querySelector('.hayfam-dashboard-metric__before');
		var value = container.querySelector('.hayfam-dashboard-metric__value');
		var after = container.querySelector('.hayfam-dashboard-metric__after');
		var desktopFontSize = customProperty(container, '--hayfam-dashboard-font-size');
		var desktopValueFontSize = customProperty(container, '--hayfam-dashboard-value-font-size');
		var desktopGap = customProperty(container, '--hayfam-dashboard-gap');
		var desktopPadding = customProperty(container, '--hayfam-dashboard-padding');
		var fontSize = isMobile ? customProperty(container, '--hayfam-dashboard-mobile-font-size') || desktopFontSize : desktopFontSize;
		var valueFontSize = isMobile ? customProperty(container, '--hayfam-dashboard-mobile-value-font-size') || desktopValueFontSize : desktopValueFontSize;
		var gap = isMobile ? customProperty(container, '--hayfam-dashboard-mobile-gap') || desktopGap : desktopGap;
		var padding = isMobile ? customProperty(container, '--hayfam-dashboard-mobile-padding') || desktopPadding : desktopPadding;

		setResponsiveStyle(container, 'font-size', fontSize);
		setResponsiveStyle(before, 'font-size', fontSize);
		setResponsiveStyle(value, 'font-size', valueFontSize);
		setResponsiveStyle(after, 'font-size', fontSize);
		setResponsiveStyle(container, 'gap', gap);
		setResponsiveStyle(container, 'padding', padding);

		var mobileWidth = isMobile ? customProperty(container, '--hayfam-dashboard-mobile-width') : '';
		setResponsiveStyle(container, 'max-width', mobileWidth);

		var graphicHeight = isMobile ? customProperty(container, '--hayfam-dashboard-mobile-graphic-height') : '';
		setResponsiveStyle(container.querySelector('.hayfam-dashboard-animated__bars'), 'height', graphicHeight);
		setResponsiveStyle(container.querySelector('.hayfam-dashboard-animated__fundraising-layout'), 'height', graphicHeight);
	}

	function refreshDashboard(container) {
		var endpoint = container.getAttribute('data-hayfam-dashboard-refresh-url');
		var dashboardId = container.getAttribute('data-hayfam-dashboard-id');
		var attributes = container.getAttribute('data-hayfam-dashboard-attributes') || '{}';

		if (!endpoint || !dashboardId) {
			return;
		}

		var params = new URLSearchParams();
		params.set('id', dashboardId);
		params.set('attributes', attributes);
		params.set('_', String(Date.now()));

		fetch(endpoint + '?' + params.toString(), {
			cache: 'no-store',
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json' }
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Dashboard refresh failed');
				}
				return response.json();
			})
			.then(function (data) {
				if (!data.html) {
					throw new Error('Dashboard refresh returned no markup');
				}

				var template = document.createElement('template');
				template.innerHTML = data.html.trim();
				var replacement = template.content.firstElementChild;
				if (!replacement) {
					throw new Error('Dashboard refresh returned invalid markup');
				}

				applyResponsiveStyles(replacement);
				container.replaceWith(replacement);
			})
			.catch(function () {
				// Keep the server-rendered dashboard visible if the refresh endpoint is unavailable.
				container.setAttribute('data-hayfam-dashboard-refresh-state', 'failed');
			});
	}

	function initialise() {
		var dashboards = document.querySelectorAll('[data-hayfam-dashboard-live="1"]');
		Array.prototype.forEach.call(dashboards, function (dashboard) {
			applyResponsiveStyles(dashboard);
			refreshDashboard(dashboard);
		});
		window.addEventListener('resize', function () {
			var currentDashboards = document.querySelectorAll('[data-hayfam-dashboard-live="1"]');
			Array.prototype.forEach.call(currentDashboards, applyResponsiveStyles);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialise);
	} else {
		initialise();
	}
}());
