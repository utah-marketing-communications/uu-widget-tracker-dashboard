(function () {
	'use strict';

	var config = typeof uuWidgetTrackerDashboard !== 'undefined' ? uuWidgetTrackerDashboard : {};
	var ajaxUrl = config.ajaxUrl || '';
	var nonce = config.nonce || '';
	var i18n = config.i18n || {};
	var BATCH_SIZE = 20;
	var MAX_SAMPLE_URLS = 5;

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

	function csvEscape(str) {
		if (str == null) return '';
		str = String(str);
		if (/[",\n\r]/.test(str)) {
			return '"' + str.replace(/"/g, '""') + '"';
		}
		return str;
	}

	function uniqueStrings(values) {
		var out = [];
		(values || []).forEach(function (value) {
			if (value == null) return;
			value = String(value).trim();
			if (!value) return;
			if (out.indexOf(value) === -1) out.push(value);
		});
		return out;
	}

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
		if (debug.total_posts_found != null) parts.push('<li>Total posts found: ' + escHtml(debug.total_posts_found) + '</li>');
		if (debug.total_items != null) parts.push('<li>Tracked items audited: ' + escHtml(debug.total_items) + '</li>');
		if (debug.items_used != null) parts.push('<li>Tracked items in use: ' + escHtml(debug.items_used) + '</li>');
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
			if (debug.error_urls.length > 50) parts.push('<li>… and ' + (debug.error_urls.length - 50) + ' more</li>');
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

	function noPostsMessage(data, defaultLabel) {
		if (data && data.is_multisite && Number(data.scanned_blog_count || 0) > 1) {
			return 'No posts using this item were found across the scanned multisite network.';
		}
		return defaultLabel;
	}

	function buildResultsHtml(widgetSlug, results, debug) {
		var viewLabel = i18n.view || 'View';
		var noPostsLabel = i18n.noPosts || 'No matching uses found for this item.';
		var scanSummary = '';
		var rows = [];
		results.forEach(function (item) {
			var data = item.data || {};
			if (data.is_multisite && Number(data.scanned_blog_count || 0) > 1) {
				scanSummary = '<p><em>Scanned ' + escHtml(String(data.scanned_blog_count)) + ' sites in this multisite network.</em></p>';
			}
		});
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
				rows.push('<tr><td>' + escHtml(siteName) + '</td><td>' + multisiteCell + '</td><td colspan="3">' + escHtml(noPostsMessage(data, noPostsLabel)) + '</td></tr>');
				return;
			}
			posts.forEach(function (post) {
				var rowSiteName = post.site_name || data.site_name || url;
				var rowNetworkName = post.network_name || multisiteCell;
				var title = post.title || '';
				var postType = post.post_type || '';
				var permalink = post.permalink || '';
				var viewCell = permalink ? '<a href="' + escAttr(permalink) + '" target="_blank" rel="noopener">' + escHtml(viewLabel) + '</a>' : '—';
				rows.push('<tr><td>' + escHtml(rowSiteName) + '</td><td>' + escHtml(rowNetworkName) + '</td><td>' + escHtml(title) + '</td><td>' + escHtml(postType) + '</td><td>' + viewCell + '</td></tr>');
			});
		});
		var heading = 'Results' + (widgetSlug ? ' — ' + escHtml(widgetSlug) : '');
		return buildDebugHtml(debug) + '<h2>' + heading + '</h2>' +
			scanSummary +
			'<p><button type="button" class="button" id="uu-tracker-export-csv" data-widget="' + escAttr(widgetSlug || '') + '">Export CSV</button></p>' +
			'<table class="wp-list-table widefat fixed striped uu-widget-tracker-results-table">' +
			'<thead><tr><th class="uu-tracker-sortable" data-col="0">Site name</th><th class="uu-tracker-sortable" data-col="1">Multisite name</th><th class="uu-tracker-sortable" data-col="2">Result title</th><th class="uu-tracker-sortable" data-col="3">Result type</th><th class="uu-tracker-sortable" data-col="4">' + escHtml(viewLabel) + '</th></tr></thead>' +
			'<tbody>' + rows.join('') + '</tbody></table>';
	}

	function attachTableSort(tableEl) {
		var thead = tableEl.querySelector('thead th.uu-tracker-sortable');
		if (!thead) return;
		var tbody = tableEl.querySelector('tbody');
		if (!tbody) return;
		var expectedCellCount = tableEl.querySelectorAll('thead th.uu-tracker-sortable').length;
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
				var dataRows = rows.filter(function (tr) { return tr.cells.length === expectedCellCount; });
				var errorRows = rows.filter(function (tr) { return tr.cells.length !== expectedCellCount; });
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
		downloadCsv(lines.join('\r\n'), filename || 'uu-widget-usage-export.csv');
	}

	function downloadCsv(csv, filename) {
		var blob = new Blob([ csv ], { type: 'text/csv;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = filename || 'uu-widget-usage-export.csv';
		a.click();
		URL.revokeObjectURL(a.href);
	}

	function postAjax(formData) {
		return fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' }).then(function (res) { return res.json(); });
	}

	function getConfidence(kind) {
		if (kind === 'siteorigin_class' || kind === 'page_template' || kind === 'classic_widget') {
			return i18n.confidenceHigh || 'High';
		}
		if (kind === 'content_substring' || kind === 'post_meta_key' || kind === 'post_meta_value') {
			return i18n.confidenceMedium || 'Medium';
		}
		return i18n.confidenceLow || 'Low';
	}

	function getAuditNotes(kind, status, sitesScanned) {
		var prefix = '';
		if (kind === 'siteorigin_class') prefix = 'High confidence: matched a SiteOrigin widget class stored in panels_data.';
		else if (kind === 'page_template') prefix = 'High confidence: matched a saved page-template assignment.';
		else if (kind === 'classic_widget') prefix = 'High confidence: matched an active classic widget id in widget-area options.';
		else if (kind === 'content_substring') prefix = 'Medium confidence: matched a saved content marker in post_content.';
		else if (kind === 'post_meta_key') prefix = 'Medium confidence: matched a saved post meta key.';
		else if (kind === 'post_meta_value') prefix = 'Medium confidence: matched a saved post meta value.';
		else prefix = 'Confidence is based on the saved placement signal type.';

		if (status === (i18n.noMatches || 'No matches') && sitesScanned > 1) {
			prefix += ' No matches were found across the scanned multisite network.';
		}
		return prefix;
	}

	function summarizeAuditRow(siteUrl, item, result) {
		var data = result && result.data ? result.data : {};
		var posts = Array.isArray(data.posts) ? data.posts : [];
		var sampleUrls = uniqueStrings(posts.map(function (post) { return post.permalink || ''; })).slice(0, MAX_SAMPLE_URLS);
		var kind = item.kind || '';
		var status = data.error
			? (i18n.statusError || 'Error')
			: (posts.length > 0 ? (i18n.used || 'Used') : (i18n.noMatches || 'No matches'));

		return {
			site_url: siteUrl,
			network_name: data.network_name || '',
			slug: item.slug || '',
			label: item.label || item.slug || '',
			kind: kind,
			search_for: item.search_for || item.class || '',
			confidence: getConfidence(kind),
			sites_scanned: Number(data.scanned_blog_count || 0),
			matches_found: posts.length,
			status: status,
			sample_urls: sampleUrls,
			notes: data.error ? data.error : getAuditNotes(kind, status, Number(data.scanned_blog_count || 0))
		};
	}

	function buildAuditResultsHtml(rows, debug) {
		var heading = i18n.bulkAuditHeading || 'Audit summary';
		var parts = [ buildDebugHtml(debug), '<h2>' + escHtml(heading) + '</h2>' ];
		parts.push('<p><button type="button" class="button" id="uu-tracker-export-audit-csv">' + escHtml(i18n.exportAuditCsv || 'Export Audit CSV') + '</button></p>');
		parts.push('<table class="wp-list-table widefat fixed striped uu-widget-tracker-audit-table">');
		parts.push('<thead><tr>' +
			'<th class="uu-tracker-sortable" data-col="0">Site URL</th>' +
			'<th class="uu-tracker-sortable" data-col="1">Network</th>' +
			'<th class="uu-tracker-sortable" data-col="2">Label</th>' +
			'<th class="uu-tracker-sortable" data-col="3">Slug</th>' +
			'<th class="uu-tracker-sortable" data-col="4">Kind</th>' +
			'<th class="uu-tracker-sortable" data-col="5">Confidence</th>' +
			'<th class="uu-tracker-sortable" data-col="6">Matches</th>' +
			'<th class="uu-tracker-sortable" data-col="7">Status</th>' +
			'<th class="uu-tracker-sortable" data-col="8">Sample URLs</th>' +
			'<th class="uu-tracker-sortable" data-col="9">Notes</th>' +
			'</tr></thead>');
		parts.push('<tbody>');
		rows.forEach(function (row) {
			parts.push('<tr>' +
				'<td>' + escHtml(row.site_url) + '</td>' +
				'<td>' + escHtml(row.network_name || '—') + '</td>' +
				'<td>' + escHtml(row.label) + '</td>' +
				'<td>' + escHtml(row.slug) + '</td>' +
				'<td>' + escHtml(row.kind || '—') + '</td>' +
				'<td>' + escHtml(row.confidence) + '</td>' +
				'<td>' + escHtml(String(row.matches_found)) + '</td>' +
				'<td>' + escHtml(row.status) + '</td>' +
				'<td>' + escHtml((row.sample_urls || []).join(' | ') || '—') + '</td>' +
				'<td>' + escHtml(row.notes || '') + '</td>' +
				'</tr>');
		});
		parts.push('</tbody></table>');
		return parts.join('');
	}

	function exportAuditRowsToCsv(rows, filename) {
		var headers = [
			'site_url',
			'network_name',
			'slug',
			'label',
			'kind',
			'search_for',
			'confidence',
			'sites_scanned',
			'matches_found',
			'status',
			'sample_urls',
			'notes'
		];
		var lines = [ headers.join(',') ];
		rows.forEach(function (row) {
			lines.push([
				row.site_url,
				row.network_name,
				row.slug,
				row.label,
				row.kind,
				row.search_for,
				row.confidence,
				row.sites_scanned,
				row.matches_found,
				row.status,
				(row.sample_urls || []).join(' | '),
				row.notes
			].map(csvEscape).join(','));
		});
		downloadCsv(lines.join('\r\n'), filename || 'uu-usage-audit-export.csv');
	}

	function fetchWidgetCatalog() {
		var formData = new FormData();
		formData.append('action', 'uu_widget_tracker_dashboard_fetch_widget_catalog');
		formData.append('nonce', nonce);
		return postAjax(formData).then(function (body) {
			if (!body.success || !body.data) {
				throw new Error((body.data && body.data.message) || i18n.error || 'Request failed.');
			}
			return body.data;
		});
	}

	function fetchUsageBatches(widget, siteUrl, progressHooks) {
		progressHooks = progressHooks || {};

		return new Promise(function (resolve, reject) {
			var offset = 0;
			var totalSites = 0;
			var allResults = [];
			var totalPostsFound = 0;
			var totalSitesOk = 0;
			var totalSitesError = 0;
			var allErrorUrls = [];
			var totalTimeSeconds = 0;

			function nextBatch() {
				var formData = new FormData();
				formData.append('action', 'uu_widget_tracker_dashboard_fetch');
				formData.append('nonce', nonce);
				formData.append('widget', widget);
				formData.append('offset', String(offset));
				formData.append('batch_size', String(BATCH_SIZE));
				if (siteUrl) formData.append('site_url', siteUrl);

				postAjax(formData).then(function (body) {
					if (!body.success || !body.data) {
						reject(new Error((body.data && body.data.message) || i18n.error || 'Request failed.'));
						return;
					}

					var data = body.data;
					var results = Array.isArray(data.results) ? data.results : [];
					var debug = data.debug || {};

					if (offset === 0) {
						totalSites = Number(debug.total_sites || 0);
						if (progressHooks.onInit) progressHooks.onInit(totalSites, debug);
					}

					allResults = allResults.concat(results);
					totalPostsFound += Number(debug.total_posts_found || 0);
					totalSitesOk += Number(debug.sites_ok || 0);
					totalSitesError += Number(debug.sites_error || 0);
					if (Array.isArray(debug.error_urls) && debug.error_urls.length) {
						allErrorUrls = allErrorUrls.concat(debug.error_urls);
					}
					totalTimeSeconds += Number(debug.execution_time_seconds || 0);

					if (progressHooks.onBatch) {
						progressHooks.onBatch(offset + results.length, totalSites, results, debug);
					}

					if (debug.has_more === true && debug.next_offset != null) {
						offset = Number(debug.next_offset);
						nextBatch();
						return;
					}

					resolve({
						widget: widget,
						results: allResults,
						debug: {
							total_sites: totalSites,
							processed: allResults.length,
							sites_ok: totalSitesOk,
							sites_error: totalSitesError,
							total_posts_found: totalPostsFound,
							total_time_seconds: Math.round(totalTimeSeconds * 100) / 100,
							error_urls: allErrorUrls,
							php_time_limit_set: debug.php_time_limit_set
						}
					});
				}).catch(function (error) {
					reject(error);
				});
			}

			nextBatch();
		});
	}

	function renderError(message, resultsEl, spinnerWrap) {
		spinnerWrap.classList.remove('is-active');
		resultsEl.innerHTML = '<p class="uu-widget-tracker-error">' + escHtml(message || i18n.error || 'Request failed.') + '</p>';
		resultsEl.classList.add('uu-widget-tracker-error');
	}

	var form = document.getElementById('uu-widget-tracker-fetch-form');
	var auditButton = document.getElementById('uu-widget-tracker-audit-button');
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
		resultsEl.innerHTML =
			'<div id="uu-widget-tracker-progress" style="margin-bottom:12px;"><strong>' + escHtml(i18n.fetching || 'Fetching usage…') + '</strong></div>' +
			'<div id="uu-widget-tracker-log" style="max-height:240px; overflow-y:auto; font-family:monospace; font-size:12px; padding:8px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; margin-bottom:16px;"></div>';

		var progressDiv = document.getElementById('uu-widget-tracker-progress');
		var logDiv = document.getElementById('uu-widget-tracker-log');

		fetchUsageBatches(widget, '', {
			onInit: function (totalSites) {
				progressDiv.innerHTML = '<strong>' + escHtml(i18n.fetching || 'Fetching usage…') + '</strong> 0 of ' + totalSites + ' sites (0%)';
			},
			onBatch: function (processed, totalSites, results) {
				var pct = totalSites ? Math.round((processed / totalSites) * 100) : 0;
				progressDiv.innerHTML = '<strong>' + escHtml(i18n.fetching || 'Fetching usage…') + '</strong> ' + processed + ' of ' + totalSites + ' sites (' + pct + '%)';
				results.forEach(function (item) {
					logDiv.innerHTML += '<div style="margin:2px 0;">' + siteStatusLine(item) + '</div>';
				});
				logDiv.scrollTop = logDiv.scrollHeight;
			}
		}).then(function (payload) {
			spinnerWrap.classList.remove('is-active');
			resultsEl.innerHTML = buildResultsHtml(widget, payload.results, payload.debug);
			var table = resultsEl.querySelector('.uu-widget-tracker-results-table');
			if (table) attachTableSort(table);
			var exportBtn = resultsEl.querySelector('#uu-tracker-export-csv');
			if (exportBtn && table) {
				exportBtn.addEventListener('click', function () {
					var date = new Date().toISOString().slice(0, 10);
					exportTableToCsv(table, 'uu-widget-usage-' + widget + '-' + date + '.csv');
				});
			}
		}).catch(function (error) {
			renderError(error && error.message ? error.message : '', resultsEl, spinnerWrap);
		});
	});

	if (auditButton) {
		auditButton.addEventListener('click', function () {
			spinnerWrap.classList.add('is-active');
			resultsEl.classList.remove('uu-widget-tracker-error');
			resultsEl.innerHTML =
				'<div id="uu-widget-tracker-progress" style="margin-bottom:12px;"><strong>' + escHtml(i18n.fetchingCatalog || 'Fetching tracked item list…') + '</strong></div>' +
				'<div id="uu-widget-tracker-log" style="max-height:240px; overflow-y:auto; font-family:monospace; font-size:12px; padding:8px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; margin-bottom:16px;"></div>';

			var progressDiv = document.getElementById('uu-widget-tracker-progress');
			var logDiv = document.getElementById('uu-widget-tracker-log');

			fetchWidgetCatalog().then(function (catalogPayload) {
				var siteResults = Array.isArray(catalogPayload.results) ? catalogPayload.results : [];
				var jobs = [];

				siteResults.forEach(function (siteItem) {
					if (siteItem.data && !siteItem.data.error && Array.isArray(siteItem.data.widgets)) {
						siteItem.data.widgets.forEach(function (widgetItem) {
							if (widgetItem && widgetItem.slug) {
								jobs.push({
									siteUrl: siteItem.url,
									item: widgetItem
								});
							}
						});
						logDiv.innerHTML += '<div style="margin:2px 0;">' + escHtml(siteItem.url) + ' <span style="color:#00a32a;">— ' + escHtml(String(siteItem.data.widgets.length)) + ' ' + escHtml(i18n.trackedItemsFound || 'Tracked items discovered') + '</span></div>';
					} else {
						logDiv.innerHTML += '<div style="margin:2px 0;">' + escHtml(siteItem.url) + ' <span style="color:#d63638;">— Error: ' + escHtml(siteItem.data && siteItem.data.error ? siteItem.data.error : (i18n.error || 'Request failed.')) + '</span></div>';
					}
				});

				logDiv.scrollTop = logDiv.scrollHeight;

				if (!jobs.length) {
					throw new Error('No tracked items were returned by the saved site URLs.');
				}

				var auditRows = [];
				var auditErrors = Array.isArray(catalogPayload.debug && catalogPayload.debug.error_urls) ? catalogPayload.debug.error_urls.slice() : [];
				var totalTimeSeconds = Number(catalogPayload.debug && catalogPayload.debug.execution_time_seconds || 0);
				var usedCount = 0;
				var index = 0;

				function nextJob() {
					if (index >= jobs.length) {
						spinnerWrap.classList.remove('is-active');
						var debug = {
							total_sites: siteResults.length,
							processed: siteResults.length,
							sites_ok: Number(catalogPayload.debug && catalogPayload.debug.sites_ok || 0),
							sites_error: Number(catalogPayload.debug && catalogPayload.debug.sites_error || 0),
							total_items: jobs.length,
							items_used: usedCount,
							total_time_seconds: Math.round(totalTimeSeconds * 100) / 100,
							error_urls: auditErrors
						};
						resultsEl.innerHTML = buildAuditResultsHtml(auditRows, debug);
						var auditTable = resultsEl.querySelector('.uu-widget-tracker-audit-table');
						if (auditTable) attachTableSort(auditTable);
						var exportAuditBtn = resultsEl.querySelector('#uu-tracker-export-audit-csv');
						if (exportAuditBtn) {
							exportAuditBtn.addEventListener('click', function () {
								var date = new Date().toISOString().slice(0, 10);
								exportAuditRowsToCsv(auditRows, 'uu-usage-audit-' + date + '.csv');
							});
						}
						return;
					}

					var job = jobs[index];
					progressDiv.innerHTML = '<strong>' + escHtml(i18n.auditProgress || 'Auditing item') + '</strong> ' + (index + 1) + ' of ' + jobs.length + ': ' + escHtml(job.siteUrl) + ' — ' + escHtml(job.item.slug);

					fetchUsageBatches(job.item.slug, job.siteUrl).then(function (payload) {
						var result = payload.results && payload.results[0] ? payload.results[0] : { url: job.siteUrl, data: { error: i18n.error || 'Request failed.' } };
						var row = summarizeAuditRow(job.siteUrl, job.item, result);
						if (row.status === (i18n.used || 'Used')) usedCount++;
						auditRows.push(row);
						totalTimeSeconds += Number(payload.debug && payload.debug.total_time_seconds || 0);
						if (payload.debug && Array.isArray(payload.debug.error_urls) && payload.debug.error_urls.length) {
							auditErrors = auditErrors.concat(payload.debug.error_urls);
						}
						logDiv.innerHTML += '<div style="margin:2px 0;">' + escHtml(job.siteUrl) + ' — ' + escHtml(job.item.slug) + ' <span style="' + (row.status === (i18n.used || 'Used') ? 'color:#00a32a;' : (row.status === (i18n.statusError || 'Error') ? 'color:#d63638;' : 'color:#50575e;')) + '">' + escHtml(row.status) + (row.matches_found ? ' (' + escHtml(String(row.matches_found)) + ')' : '') + '</span></div>';
						logDiv.scrollTop = logDiv.scrollHeight;
						index += 1;
						nextJob();
					}).catch(function (error) {
						auditRows.push({
							site_url: job.siteUrl,
							network_name: '',
							slug: job.item.slug || '',
							label: job.item.label || job.item.slug || '',
							kind: job.item.kind || '',
							search_for: job.item.search_for || job.item.class || '',
							confidence: getConfidence(job.item.kind || ''),
							sites_scanned: 0,
							matches_found: 0,
							status: i18n.statusError || 'Error',
							sample_urls: [],
							notes: error && error.message ? error.message : (i18n.error || 'Request failed.')
						});
						auditErrors.push({ url: job.siteUrl + ' — ' + job.item.slug, message: error && error.message ? error.message : (i18n.error || 'Request failed.') });
						logDiv.innerHTML += '<div style="margin:2px 0;">' + escHtml(job.siteUrl) + ' — ' + escHtml(job.item.slug) + ' <span style="color:#d63638;">' + escHtml(i18n.statusError || 'Error') + '</span></div>';
						logDiv.scrollTop = logDiv.scrollHeight;
						index += 1;
						nextJob();
					});
				}

				nextJob();
			}).catch(function (error) {
				renderError(error && error.message ? error.message : '', resultsEl, spinnerWrap);
			});
		});
	}
})();
