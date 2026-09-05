(function () {
	'use strict';

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

				container.replaceWith(replacement);
			})
			.catch(function () {
				// Keep the server-rendered dashboard visible if the refresh endpoint is unavailable.
				container.setAttribute('data-hayfam-dashboard-refresh-state', 'failed');
			});
	}

	function initialise() {
		var dashboards = document.querySelectorAll('[data-hayfam-dashboard-live="1"]');
		Array.prototype.forEach.call(dashboards, refreshDashboard);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialise);
	} else {
		initialise();
	}
}());
