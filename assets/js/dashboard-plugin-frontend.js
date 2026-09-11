(function () {
	'use strict';

	function customProperty(element, name) {
		return element.style.getPropertyValue(name).trim() || window.getComputedStyle(element).getPropertyValue(name).trim();
	}

	function setResponsiveStyle(element, property, value, removeWhenEmpty) {
		if (!element) {
			return;
		}
		if (value) {
			element.style.setProperty(property, value, 'important');
		} else if (removeWhenEmpty) {
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
		setResponsiveStyle(container, 'max-width', mobileWidth, true);

		var graphicHeight = isMobile ? customProperty(container, '--hayfam-dashboard-mobile-graphic-height') : '';
		setResponsiveStyle(container.querySelector('.hayfam-dashboard-animated__bars'), 'height', graphicHeight, true);
		setResponsiveStyle(container.querySelector('.hayfam-dashboard-animated__fundraising-layout'), 'height', graphicHeight, true);
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

				replaceDashboard(container, data.html);
			})
			.catch(function () {
				showDashboardFallback(container);
			});
	}

	function showDashboardFallback(container) {
		var value = container.querySelector('.hayfam-dashboard-metric__value');
		var fallback = 'Data currently unavailable';
		var attributes = container.getAttribute('data-hayfam-dashboard-attributes') || '{}';

		try {
			attributes = JSON.parse(attributes);
			if (attributes.fallback) {
				fallback = String(attributes.fallback);
			}
		} catch (error) {
			// Use the standard fallback when the serialized attributes cannot be read.
		}

		if (value) {
			value.textContent = fallback;
		}
		container.setAttribute('data-hayfam-dashboard-loading', '0');
		container.setAttribute('aria-busy', 'false');
		container.setAttribute('data-hayfam-dashboard-refresh-state', 'failed');
	}

	function replaceDashboard(container, html) {
		var template = document.createElement('template');
		template.innerHTML = String(html || '').trim();
		var replacement = template.content.firstElementChild;
		if (!replacement) {
			throw new Error('Dashboard refresh returned invalid markup');
		}

		applyResponsiveStyles(replacement);
		container.replaceWith(replacement);
	}

	function refreshDashboards(containers) {
		if (!containers.length) {
			return;
		}

		var batchEndpoint = containers[0].getAttribute('data-hayfam-dashboard-refresh-batch-url');
		if (!batchEndpoint) {
			Array.prototype.forEach.call(containers, refreshDashboard);
			return;
		}

		var dashboards = Array.prototype.map.call(containers, function (container) {
			var attributes = container.getAttribute('data-hayfam-dashboard-attributes') || '{}';
			try {
				attributes = JSON.parse(attributes);
			} catch (error) {
				attributes = {};
			}

			return {
				id: container.getAttribute('data-hayfam-dashboard-id') || '',
				attributes: attributes
			};
		});

		fetch(batchEndpoint, {
			method: 'POST',
			cache: 'no-store',
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({ dashboards: dashboards, _: Date.now() })
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Dashboard batch refresh failed');
				}
				return response.json();
			})
			.then(function (data) {
				if (!Array.isArray(data.dashboards) || data.dashboards.length !== containers.length) {
					throw new Error('Dashboard batch refresh returned invalid data');
				}

				Array.prototype.forEach.call(containers, function (container, index) {
					replaceDashboard(container, data.dashboards[index].html);
				});
			})
			.catch(function () {
				Array.prototype.forEach.call(containers, function (container) {
					showDashboardFallback(container);
				});
			});
	}

	function initialise() {
		var dashboards = document.querySelectorAll('[data-hayfam-dashboard-live="1"]');
		Array.prototype.forEach.call(dashboards, function (dashboard) {
			applyResponsiveStyles(dashboard);
		});
		refreshDashboards(dashboards);
		var resizeFrame = null;
		var scheduleResize = window.requestAnimationFrame || function (callback) {
			return window.setTimeout(callback, 16);
		};
		window.addEventListener('resize', function () {
			if (null !== resizeFrame) {
				return;
			}

			resizeFrame = scheduleResize(function () {
				resizeFrame = null;
				var currentDashboards = document.querySelectorAll('[data-hayfam-dashboard-live="1"]');
				Array.prototype.forEach.call(currentDashboards, applyResponsiveStyles);
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialise);
	} else {
		initialise();
	}
}());
