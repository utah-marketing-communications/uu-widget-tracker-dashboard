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

	var BATCH_SIZE = 20;

	function buildDebugHtml(debug) {
		if (!debug) return '';
		var parts = [];
		parts.push('<div class="uu-widget-tracker-debug" style="margin-bottom:16px; padding:12px; background:#f0f0f1; border:1px solid #c3c4c7; border-radius:4px;">');
		parts.push('<strong>Debug</strong>');
		parts.push('<ul style="margin:8px 0 0 0; padding-left:20px;">');
		parts.push('<li>Total site URLs: ' + escHtml(debug.total_sites) + '</li>');
		parts.push('<li>Sites processed: ' + escHtml(debug.processed) + '</li>');
		parts.push('<li>Sites OK: ' + escHtml(debug.sites_ok) + '</li>');
		parts.push('<li>Sites error: ' + escHtml(debug.sites_error) + '</li>');
		parts.push('<li>Total posts found: ' + escHtml(debug.total_posts_found) + '</li>');
		if (debug.execution_time_seconds != null) parts.push('<li>Execution time: ' + escHtml(debug.execution_time_seconds) + ' s</li>');
		if (debug.total_time_seconds != null) parts.push('<li>Total time (all batches): ' + escHtml(debug.total_time_seconds) + ' s</li>');
		if (debug.php_time_limit_set != null) parts.push('<li>PHP time limit per batch: ' + (debug.php_time_limit_set === 0 ? 'unlimited' : debug.php_time_limit_set + ' s') + '</li>');
		parts.push('</ul>');
		if (debug.error_urls && debug.error_urls.length > 0) {
			parts.push('<p style="margin:8px 0 0 0;"><strong>Sites that returned errors (' + debug.error_urls.length + '):</strong></p>');
			parts.push('<ul style="margin:4px 0 0 0; padding-left:20px; max-height:200px; overflow-y:auto;">');
			debug.error_urls.slice(0, 50).forEach(function (e) {
				parts.push('<li><code>' + escHtml(e.url) + '</code> — ' + escHtml(e.message) + '</li>');
			});
			if (debug.error_urls.length > 50) parts.push('<li>… and ' + (debug.error_urls.length - 50) + ' more (see table below)</li>');
			parts.push('</ul>');
		}
		parts.push('</div>');
		return parts.join('');
	}

	function siteStatusLine(item) {
		var url = item.url;
		var data = item.data;
		if (data.error) return escHtml(url) + ' <span style="color:#d63638;">— Error: ' + escHtml(data.error) + '</span>';
		var n = Array.isArray(data.posts) ? data.posts.length : 0;
		return escHtml(url) + ' <span style="color:#00a32a;">— OK' + (n ? ' (' + n + ' posts)' : '') + '</span>';
	}

	function multisiteDisplay(data) {
		if (data.network_name) return escHtml(data.network_name);
		if (data.is_multisite && data.blog_id != null) return 'Blog ' + escHtml(String(data.blog_id));
		return '—';
	}

	function buildResultsHtml(widgetSlug, results, debug) {
		var viewLabel = i18n.view || 'View';
		var noPostsLabel = i18n.noPosts || 'No posts using this widget.';
		var rows = [];
		results.forEach(function (item) {
			var url = item.url;
			var data = item.data;
			if (data.error) {
				rows.push('<tr class="uu-tracker-error-row"><td colspan="5"><strong>' + escHtml(url) + '</strong> — ' + escHtml(data.error) + '</td></tr>');
				return;
			}
			var siteName = data.site_name || url;
			var multisiteCell = multisiteDisplay(data);
			var posts = Array.isArray(data.posts) ? data.posts : [];
			if (posts.length === 0) {
				rows.push('<tr><td>' + escHtml(siteName) + '</td><td>' + multisiteCell + '</td><td colspan="3">' + escHtml(noPostsLabel) + '</td></tr>');
				return;
			}
			posts.forEach(function (post) {
				var title = post.title || '';
				var postType = post.post_type || '';
				var permalink = post.permalink || '';
				var viewCell = permalink ? '<a href="' + escAttr(permalink) + '" target="_blank" rel="noopener">' + escHtml(viewLabel) + '</a>' : '—';
				rows.push('<tr><td>' + escHtml(siteName) + '</td><td>' + multisiteCell + '</td><td>' + escHtml(title) + '</td><td>' + escHtml(postType) + '</td><td>' + viewCell + '</td></tr>');
			});
		});
		var heading = 'Results' + (widgetSlug ? ' — ' + escHtml(widgetSlug) : '');
		var html = buildDebugHtml(debug) + '<h2>' + heading + '</h2>' +
			'<p><button type="button" class="button" id="uu-tracker-export-csv" data-widget="' + escAttr(widgetSlug || '') + '">Export CSV</button></p>' +
			'<table class="wp-list-table widefat fixed striped uu-widget-tracker-results-table">' +
			'<thead><tr><th class="uu-tracker-sortable" data-col="0">Site name</th><th class="uu-tracker-sortable" data-col="1">Multisite name</th><th class="uu-tracker-sortable" data-col="2">Post title</th><th class="uu-tracker-sortable" data-col="3">Post type</th><th class="uu-tracker-sortable" data-col="4">' + escHtml(viewLabel) + '</th></tr></thead>' +
			'<tbody>' + rows.join('') + '</tbody></table>';
		return html;
	}

	function attachTableSort(tableEl) {
		var thead = tableEl.querySelector('thead th.uu-tracker-sortable');
		if (!thead) return;
		var tbody = tableEl.querySelector('tbody');
		if (!tbody) return;
		tableEl.querySelectorAll('thead th.uu-tracker-sortable').forEach(function (th) {
			th.style.cursor = 'pointer';
			th.title = 'Click to sort';
			th.addEventListener('click', function () {
				var col = parseInt(th.getAttribute('data-col'), 10);
				var dir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
				tableEl.querySelectorAll('thead th.uu-tracker-sortable').forEach(function (h) {
					h.removeAttribute('data-sort-dir');
					h.textContent = h.textContent.replace(/\s[\u2191\u2193]$/, '').trim();
				});
				th.setAttribute('data-sort-dir', dir);
				th.textContent = th.textContent.trim() + (dir === 'asc' ? ' \u2191' : ' \u2193');
				var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
				var dataRows = rows.filter(function (tr) { return tr.cells.length === 5; });
				var errorRows = rows.filter(function (tr) { return tr.cells.length !== 5; });
				function sortKey(tr) {
					var cell = tr.cells[col];
					if (!cell) return '';
					var link = cell.querySelector('a');
					return (link ? (link.getAttribute('href') || link.textContent) : cell.textContent).trim().toLowerCase();
				}
				dataRows.sort(function (a, b) {
					var ka = sortKey(a);
					var kb = sortKey(b);
					if (ka < kb) return dir === 'asc' ? -1 : 1;
					if (ka > kb) return dir === 'asc' ? 1 : -1;
					return 0;
				});
				dataRows.forEach(function (tr) { tbody.appendChild(tr); });
				errorRows.forEach(function (tr) { tbody.appendChild(tr); });
			});
		});
	}

	function csvEscape(str) {
		if (str == null) return '';
		str = String(str);
		if (/[",\n\r]/.test(str)) {
			return '"' + str.replace(/"/g, '""') + '"';
		}
		return str;
	}

	function exportTableToCsv(tableEl, filename) {
		var ths = tableEl.querySelectorAll('thead th.uu-tracker-sortable');
		if (!ths.length) return;
		var headers = [];
		for (var i = 0; i < ths.length; i++) {
			var label = ths[i].textContent.replace(/\s[\u2191\u2193]$/, '').trim();
			headers.push(label === 'View' ? 'Page URL' : label);
		}
		var lines = [ headers.map(csvEscape).join(',') ];
		var rows = tableEl.querySelectorAll('tbody tr');
		for (var r = 0; r < rows.length; r++) {
			var tr = rows[r];
			if (tr.cells.length !== 5) continue;
			var cells = [];
			for (var c = 0; c < tr.cells.length; c++) {
				var cell = tr.cells[c];
				var link = cell.querySelector('a[href]');
				var val = link ? link.getAttribute('href') : cell.textContent.trim();
				cells.push(csvEscape(val));
			}
			lines.push(cells.join(','));
		}
		var csv = lines.join('\r\n');
		var blob = new Blob([ csv ], { type: 'text/csv;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = filename || 'uu-widget-usage-export.csv';
		a.click();
		URL.revokeObjectURL(a.href);
	}

	var form = document.getElementById('uu-widget-tracker-fetch-form');
	var resultsEl = document.getElementById('uu-widget-tracker-results');
	var spinnerWrap = document.getElementById('uu-widget-tracker-spinner-wrap');

	if (!form || !resultsEl || !spinnerWrap) return;

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var slugField = document.getElementById('uu_widget_slug');
		var customSlugField = document.getElementById('uu_widget_slug_custom');
		var widget = customSlugField && (customSlugField.value || '').trim()
			? (customSlugField.value || '').trim()
			: (slugField ? (slugField.value || '').trim() : '');
		if (!widget) return;

		spinnerWrap.classList.add('is-active');
		resultsEl.classList.remove('uu-widget-tracker-error');
		resultsEl.innerHTML = '';

		var allResults = [];
		var totalSites = 0;
		var totalPostsFound = 0;
		var totalSitesOk = 0;
		var totalSitesError = 0;
		var allErrorUrls = [];
		var totalTimeSeconds = 0;
		var progressDiv = null;
		var logDiv = null;
		var widgetSlugForResults = widget;

		function updateProgress(processed, total, batchLogHtml) {
			if (!progressDiv) return;
			var pct = total ? Math.round((processed / total) * 100) : 0;
			progressDiv.innerHTML = '<strong>Fetching…</strong> ' + processed + ' of ' + total + ' sites (' + pct + '%)';
			if (logDiv && batchLogHtml) {
				logDiv.innerHTML += batchLogHtml;
				logDiv.scrollTop = logDiv.scrollHeight;
			}
		}

		function doOneBatch(offset) {
			var formData = new FormData();
			formData.append('action', 'uu_widget_tracker_dashboard_fetch');
			formData.append('nonce', nonce);
			formData.append('widget', widget);
			formData.append('offset', String(offset));
			formData.append('batch_size', String(BATCH_SIZE));
			return fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' }).then(function (res) { return res.json(); });
		}

		(function runBatches() {
			var offset = 0;
			function nextBatch() {
				doOneBatch(offset).then(function (body) {
					if (!body.success || !body.data) {
						spinnerWrap.classList.remove('is-active');
						resultsEl.innerHTML = '<p class="uu-widget-tracker-error">' + escHtml((body.data && body.data.message) || i18n.error || 'Request failed.') + '</p>';
						resultsEl.classList.add('uu-widget-tracker-error');
						return;
					}
					var data = body.data;
					var results = data.results || [];
					var debug = data.debug || {};
					if (offset === 0) {
						totalSites = debug.total_sites || 0;
						resultsEl.innerHTML =
							'<div id="uu-widget-tracker-progress" style="margin-bottom:12px;"><strong>Fetching…</strong> 0 of ' + totalSites + ' sites (0%)</div>' +
							'<div id="uu-widget-tracker-log" style="max-height:240px; overflow-y:auto; font-family:monospace; font-size:12px; padding:8px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; margin-bottom:16px;"></div>';
						progressDiv = document.getElementById('uu-widget-tracker-progress');
						logDiv = document.getElementById('uu-widget-tracker-log');
					}
					allResults = allResults.concat(results);
					totalPostsFound += debug.total_posts_found || 0;
					totalSitesOk += debug.sites_ok || 0;
					totalSitesError += debug.sites_error || 0;
					if (debug.error_urls && debug.error_urls.length) allErrorUrls = allErrorUrls.concat(debug.error_urls);
					totalTimeSeconds += debug.execution_time_seconds || 0;
					var batchLog = '';
					results.forEach(function (item) { batchLog += '<div style="margin:2px 0;">' + siteStatusLine(item) + '</div>'; });
					updateProgress(offset + results.length, totalSites, batchLog);

					var hasMore = debug.has_more === true && debug.next_offset != null;
					if (hasMore) {
						offset = Number(debug.next_offset);
						nextBatch();
					} else {
						spinnerWrap.classList.remove('is-active');
						var combinedDebug = {
							total_sites: totalSites,
							processed: allResults.length,
							sites_ok: totalSitesOk,
							sites_error: totalSitesError,
							total_posts_found: totalPostsFound,
							total_time_seconds: Math.round(totalTimeSeconds * 100) / 100,
							error_urls: allErrorUrls,
							php_time_limit_set: debug.php_time_limit_set
						};
						resultsEl.innerHTML = buildResultsHtml(widgetSlugForResults, allResults, combinedDebug);
						var table = resultsEl.querySelector('.uu-widget-tracker-results-table');
						if (table) attachTableSort(table);
						var exportBtn = resultsEl.querySelector('#uu-tracker-export-csv');
						if (exportBtn) {
							exportBtn.addEventListener('click', function () {
								var widget = exportBtn.getAttribute('data-widget') || 'widget';
								var date = new Date().toISOString().slice(0, 10);
								exportTableToCsv(table, 'uu-widget-usage-' + widget + '-' + date + '.csv');
							});
						}
					}
				}).catch(function () {
					spinnerWrap.classList.remove('is-active');
					resultsEl.innerHTML = '<p class="uu-widget-tracker-error">' + escHtml(i18n.error || 'Request failed.') + '</p>';
					resultsEl.classList.add('uu-widget-tracker-error');
				});
			}
			nextBatch();
		})();
	});
})();
