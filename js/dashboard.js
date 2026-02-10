(function () {
	'use strict';

	var config = typeof uuWidgetTrackerDashboard !== 'undefined' ? uuWidgetTrackerDashboard : {};
	var ajaxUrl = config.ajaxUrl || '';
	var nonce = config.nonce || '';
	var i18n = config.i18n || {};

	function escHtml(str) {
		if (str == null) return '';
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	function escAttr(str) {
		if (str == null) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function buildResultsHtml(widgetSlug, results) {
		var viewLabel = i18n.view || 'View';
		var noPostsLabel = i18n.noPosts || 'No posts using this widget.';
		var rows = [];
		results.forEach(function (item) {
			var url = item.url;
			var data = item.data;
			if (data.error) {
				rows.push('<tr><td colspan="4"><strong>' + escHtml(url) + '</strong> — ' + escHtml(data.error) + '</td></tr>');
				return;
			}
			var siteName = data.site_name || url;
			var posts = Array.isArray(data.posts) ? data.posts : [];
			if (posts.length === 0) {
				rows.push('<tr><td>' + escHtml(siteName) + ' <small>(' + escHtml(url) + ')</small></td><td colspan="3">' + escHtml(noPostsLabel) + '</td></tr>');
				return;
			}
			posts.forEach(function (post, i) {
				var title = post.title || '';
				var postType = post.post_type || '';
				var permalink = post.permalink || '';
				var siteCell = i === 0 ? escHtml(siteName) + ' <small>(' + escHtml(url) + ')</small>' : '';
				var viewCell = permalink ? '<a href="' + escAttr(permalink) + '" target="_blank" rel="noopener">' + escHtml(viewLabel) + '</a>' : '—';
				rows.push('<tr><td>' + siteCell + '</td><td>' + escHtml(title) + '</td><td>' + escHtml(postType) + '</td><td>' + viewCell + '</td></tr>');
			});
		});
		var heading = 'Results' + (widgetSlug ? ' — ' + escHtml(widgetSlug) : '');
		return '<h2>' + heading + '</h2>' +
			'<table class="wp-list-table widefat fixed striped">' +
			'<thead><tr><th>Site</th><th>Post title</th><th>Post type</th><th>' + escHtml(viewLabel) + '</th></tr></thead>' +
			'<tbody>' + rows.join('') + '</tbody></table>';
	}

	var form = document.getElementById('uu-widget-tracker-fetch-form');
	var resultsEl = document.getElementById('uu-widget-tracker-results');
	var spinnerWrap = document.getElementById('uu-widget-tracker-spinner-wrap');

	if (!form || !resultsEl || !spinnerWrap) return;

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var slugField = document.getElementById('uu_widget_slug');
		var widget = slugField ? (slugField.value || '').trim() : '';
		if (!widget) return;

		spinnerWrap.classList.add('is-active');
		resultsEl.innerHTML = '';
		resultsEl.classList.remove('uu-widget-tracker-error');

		var formData = new FormData();
		formData.append('action', 'uu_widget_tracker_dashboard_fetch');
		formData.append('nonce', nonce);
		formData.append('widget', widget);

		fetch(ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function (res) { return res.json(); })
			.then(function (body) {
				spinnerWrap.classList.remove('is-active');
				if (body.success && body.data && body.data.results) {
					resultsEl.innerHTML = buildResultsHtml(body.data.widget, body.data.results);
				} else {
					resultsEl.innerHTML = '<p class="uu-widget-tracker-error">' + escHtml((body.data && body.data.message) || i18n.error || 'Request failed.') + '</p>';
					resultsEl.classList.add('uu-widget-tracker-error');
				}
			})
			.catch(function () {
				spinnerWrap.classList.remove('is-active');
				resultsEl.innerHTML = '<p class="uu-widget-tracker-error">' + escHtml(i18n.error || 'Request failed.') + '</p>';
				resultsEl.classList.add('uu-widget-tracker-error');
			});
	});
})();
