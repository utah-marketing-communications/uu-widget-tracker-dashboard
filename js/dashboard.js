(function () {
	'use strict';

	var config = typeof uuWidgetTrackerDashboard !== 'undefined' ? uuWidgetTrackerDashboard : {};
	var ajaxUrl = config.ajaxUrl || '';
	var nonce = config.nonce || '';
	var pageMode = config.pageMode || 'current';
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

	function postAjax(formData) {
		return fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' }).then(function (res) { return res.json(); });
	}

	function renderError(message, resultsEl, spinnerWrap) {
		if (spinnerWrap) spinnerWrap.classList.remove('is-active');
		resultsEl.innerHTML = '<p class="uu-widget-tracker-error">' + escHtml(message || i18n.error || 'Request failed.') + '</p>';
		resultsEl.classList.add('uu-widget-tracker-error');
	}

	function buildDebugHtml(debug) {
		if (!debug) return '';
		var parts = [];
		parts.push('<div class="uu-widget-tracker-debug" style="margin-bottom:16px; padding:12px; background:#f0f0f1; border:1px solid #c3c4c7; border-radius:4px;">');
		parts.push('<strong>Debug</strong>');
		parts.push('<ul style="margin:8px 0 0 0; padding-left:20px;">');
		if (debug.public_only != null) parts.push('<li>Content scope: ' + escHtml(debug.public_only ? (i18n.publishedOnly || 'Published content only') : 'Published + unpublished content') + '</li>');
		if (debug.total_sites != null) parts.push('<li>' + escHtml(i18n.remoteSiteUrls || 'Remote site URLs') + ': ' + escHtml(debug.total_sites) + '</li>');
		if (debug.processed != null) parts.push('<li>' + escHtml(i18n.remoteSitesProcessed || 'Remote sites processed') + ': ' + escHtml(debug.processed) + '</li>');
		if (debug.total_scanned_blogs != null) parts.push('<li>' + escHtml(i18n.multisiteBlogsScanned || 'Multisite blogs scanned') + ': ' + escHtml(debug.total_scanned_blogs) + '</li>');
		if (debug.sites_ok != null) parts.push('<li>Sites OK: ' + escHtml(debug.sites_ok) + '</li>');
		if (debug.sites_error != null) parts.push('<li>Sites error: ' + escHtml(debug.sites_error) + '</li>');
		if (debug.total_posts_found != null) parts.push('<li>Total posts found: ' + escHtml(debug.total_posts_found) + '</li>');
		if (debug.activation_status) parts.push('<li>' + escHtml(i18n.activationStatus || 'Plugin activation') + ': ' + escHtml(debug.activation_status) + '</li>');
		if (debug.active_blog_count != null) parts.push('<li>' + escHtml(i18n.activeBlogs || 'Blogs with plugin active') + ': ' + escHtml(debug.active_blog_count) + '</li>');
		if (debug.total_items != null) parts.push('<li>Tracked items audited: ' + escHtml(debug.total_items) + '</li>');
		if (debug.items_used != null) parts.push('<li>Tracked items in use: ' + escHtml(debug.items_used) + '</li>');
		if (debug.execution_time_seconds != null) parts.push('<li>Execution time: ' + escHtml(debug.execution_time_seconds) + ' s</li>');
		if (debug.total_time_seconds != null) parts.push('<li>Total time (all batches): ' + escHtml(debug.total_time_seconds) + ' s</li>');
		parts.push('</ul>');
		parts.push('</div>');
		return parts.join('');
	}

	function getTrackingDefinitionStatus(data) {
		return data && data.tracking_definition_status ? data.tracking_definition_status : 'Unknown';
	}

	function getTrackingDefinitionNote(data) {
		return data && data.tracking_definition_note ? data.tracking_definition_note : '';
	}

	function getActivationSummary(data) {
		return data && data.activation ? data.activation : null;
	}

	function getSignalLabel(signal) {
		var map = {
			siteorigin_class: 'SiteOrigin class',
			content_substring: 'content marker',
			page_slug: 'page slug',
			page_title: 'page title',
			page_template: 'page template',
			post_meta_key: 'meta key',
			post_meta_value: 'meta value',
			classic_widget: 'classic widget'
		};
		return map[signal] || signal || '';
	}

	function getUsageRowKey(row) {
		return [
			row.type || '',
			row.siteName || '',
			row.networkName || '',
			row.pageUrl || '',
			row.title || '',
			row.resultType || ''
		].join('|');
	}

	function getUsageDisplayRows(results, noPostsLabel) {
		var rows = [];
		var seen = Object.create(null);

		results.forEach(function (item) {
			var url = item.url;
			var data = item.data || {};
			var siteName = data.site_name || url;
			var networkName = data.network_name || (data.is_multisite && data.blog_id != null ? 'Blog ' + String(data.blog_id) : '—');
			var trackingDefinition = getTrackingDefinitionStatus(data);
			var trackingNote = getTrackingDefinitionNote(data);
			var activation = getActivationSummary(data);
			var posts = Array.isArray(data.posts) ? data.posts : [];

			if (data.error) {
				var errorRow = {
					type: 'error',
					siteName: url,
					networkName: networkName,
					trackingDefinition: trackingDefinition,
					trackingNote: trackingNote,
					activationStatus: activation && activation.status ? activation.status : '',
					title: data.error,
					resultType: 'Error',
					pageUrl: '',
					usageScope: '',
					usageNote: ''
				};
				var errorKey = getUsageRowKey(errorRow);
				if (!seen[errorKey]) {
					seen[errorKey] = true;
					rows.push(errorRow);
				}
				return;
			}

			if (!posts.length) {
				var emptyRow = {
					type: 'no-posts',
					siteName: siteName,
					networkName: networkName,
					trackingDefinition: trackingDefinition,
					trackingNote: trackingNote,
					activationStatus: activation && activation.status ? activation.status : '',
					title: noPostsLabel,
					resultType: '',
					pageUrl: '',
					usageScope: '',
					usageNote: ''
				};
				var emptyKey = getUsageRowKey(emptyRow);
				if (!seen[emptyKey]) {
					seen[emptyKey] = true;
					rows.push(emptyRow);
				}
				return;
			}

			posts.forEach(function (post) {
				var row = {
					type: 'post',
					siteName: post.site_name || siteName,
					networkName: post.network_name || networkName,
					trackingDefinition: trackingDefinition,
					trackingNote: trackingNote,
					activationStatus: activation && activation.status ? activation.status : '',
					title: post.title || '',
					resultType: post.post_type || '',
					pageUrl: post.permalink || '',
					usageScope: post.usage_scope || 'page',
					usageNote: post.usage_note || '',
					matchSources: Array.isArray(post.match_sources) ? post.match_sources : [],
					matchDetails: Array.isArray(post.match_details) ? post.match_details : []
				};
				var key = getUsageRowKey(row);
				if (!seen[key]) {
					seen[key] = true;
					rows.push(row);
				}
			});
		});

		return rows;
	}

	function buildResultsHtml(itemSlug, results, debug) {
		var usageRows = getUsageDisplayRows(results, i18n.noPosts || 'No matching uses found for this item.');
		var tableRows = [];
		var matchCount = usageRows.filter(function (row) { return row.type === 'post'; }).length;
		var summaryHtml = '';

		usageRows.forEach(function (row) {
			var resultType = row.resultType || '—';
			var title = row.title || '';
			var extras = [];
			if (row.usageScope === 'widget_area') {
				resultType = i18n.widgetAreaOnly || 'Widget area only';
				if (row.usageNote) extras.push(row.usageNote);
			}
			if (Array.isArray(row.matchSources) && row.matchSources.length) {
				extras.push((i18n.matchedBy || 'Matched by') + ': ' + row.matchSources.map(getSignalLabel).join(', '));
			}
			if (row.type === 'no-posts' && row.activationStatus) {
				extras.push((i18n.activationStatus || 'Plugin activation') + ': ' + row.activationStatus);
			}
			if (extras.length) title += ' — ' + extras.join(' | ');
			var trackingCell = '<td data-tracking-note="' + escAttr(row.trackingNote || '') + '">' + escHtml(row.trackingDefinition || 'Unknown') + '</td>';
			var viewCell = row.pageUrl ? '<a href="' + escAttr(row.pageUrl) + '" target="_blank" rel="noopener">' + escHtml(i18n.view || 'View') + '</a>' : '—';
			tableRows.push('<tr><td>' + escHtml(row.siteName) + '</td><td>' + escHtml(row.networkName) + '</td>' + trackingCell + '<td>' + escHtml(title) + '</td><td>' + escHtml(resultType) + '</td><td>' + viewCell + '</td></tr>');
		});

		if (matchCount > 0) {
			summaryHtml = '<p><strong>' + escHtml(String(matchCount)) + ' ' + escHtml(i18n.matchesFound || 'matching pages found') + '</strong></p>';
		} else {
			summaryHtml = '<p><strong>' + escHtml(i18n.noMatchesFound || 'No matching pages found.') + '</strong></p>';
		}

		return buildDebugHtml(debug) +
			'<h2>Lookup results' + (itemSlug ? ' — ' + escHtml(itemSlug) : '') + '</h2>' +
			summaryHtml +
			'<p><button type="button" class="button" id="uu-tracker-export-csv">Export CSV</button></p>' +
			'<table class="wp-list-table widefat fixed striped uu-widget-tracker-results-table">' +
			'<thead><tr><th class="uu-tracker-sortable" data-col="0">Site name</th><th class="uu-tracker-sortable" data-col="1">Multisite name</th><th class="uu-tracker-sortable" data-col="2">Tracking definition</th><th class="uu-tracker-sortable" data-col="3">Result title</th><th class="uu-tracker-sortable" data-col="4">Result type</th><th class="uu-tracker-sortable" data-col="5">View</th></tr></thead>' +
			'<tbody>' + tableRows.join('') + '</tbody></table>';
	}

	function attachTableSort(tableEl) {
		var tbody = tableEl.querySelector('tbody');
		if (!tbody) return;
		tableEl.querySelectorAll('thead th.uu-tracker-sortable').forEach(function (th) {
			th.style.cursor = 'pointer';
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
				rows.sort(function (a, b) {
					var av = a.cells[col] ? a.cells[col].textContent.trim().toLowerCase() : '';
					var bv = b.cells[col] ? b.cells[col].textContent.trim().toLowerCase() : '';
					if (av < bv) return dir === 'asc' ? -1 : 1;
					if (av > bv) return dir === 'asc' ? 1 : -1;
					return 0;
				});
				rows.forEach(function (tr) { tbody.appendChild(tr); });
			});
		});
	}

	function exportTableToCsv(tableEl, filename) {
		var headers = Array.prototype.slice.call(tableEl.querySelectorAll('thead th')).map(function (th) {
			return th.textContent.replace(/\s[\u2191\u2193]$/, '').trim() === 'View' ? 'Page URL' : th.textContent.replace(/\s[\u2191\u2193]$/, '').trim();
		});
		var lines = [ headers.map(csvEscape).join(',') ];
		tableEl.querySelectorAll('tbody tr').forEach(function (tr) {
			var cells = [];
			Array.prototype.slice.call(tr.cells).forEach(function (cell) {
				var link = cell.querySelector('a[href]');
				cells.push(csvEscape(link ? link.getAttribute('href') : cell.textContent.trim()));
			});
			lines.push(cells.join(','));
		});
		var blob = new Blob([ lines.join('\r\n') ], { type: 'text/csv;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = filename;
		a.click();
		URL.revokeObjectURL(a.href);
	}

	function getConfidence(kind) {
		if (kind === 'siteorigin_class' || kind === 'page_template' || kind === 'classic_widget') return i18n.confidenceHigh || 'High';
		if (kind === 'content_substring' || kind === 'post_meta_key' || kind === 'post_meta_value') return i18n.confidenceMedium || 'Medium';
		return i18n.confidenceLow || 'Low';
	}

	function summarizeAuditRow(siteUrl, item, result) {
		var data = result && result.data ? result.data : {};
		var posts = Array.isArray(data.posts) ? data.posts : [];
		var hasWidgetAreaUsage = posts.some(function (post) { return post && post.usage_scope === 'widget_area'; });
		var notes = data.error || '';
		if (!notes && hasWidgetAreaUsage) notes = 'Includes classic widget-area matches that do not resolve to exact public URLs.';
		return {
			site_url: siteUrl,
			network_name: data.network_name || '',
			slug: item.slug || '',
			label: item.label || item.slug || '',
			kind: item.kind || '',
			search_for: item.search_for || item.class || '',
			confidence: getConfidence(item.kind || ''),
			sites_scanned: Number(data.scanned_blog_count || 0),
			matches_found: posts.length,
			status: data.error ? (i18n.statusError || 'Error') : (posts.length ? (i18n.used || 'Used') : (i18n.noMatches || 'No matches')),
			sample_urls: uniqueStrings(posts.map(function (post) { return post.permalink || ''; })).slice(0, MAX_SAMPLE_URLS),
			notes: notes
		};
	}

	function buildAuditResultsHtml(rows, debug) {
		var parts = [ buildDebugHtml(debug), '<h2>' + escHtml(i18n.bulkAuditHeading || 'Audit summary') + '</h2>' ];
		parts.push('<p><button type="button" class="button" id="uu-tracker-export-audit-csv">' + escHtml(i18n.exportAuditCsv || 'Export Audit CSV') + '</button></p>');
		parts.push('<table class="wp-list-table widefat fixed striped uu-widget-tracker-audit-table"><thead><tr><th>Site URL</th><th>Network</th><th>Label</th><th>Slug</th><th>Kind</th><th>Confidence</th><th>Matches</th><th>Status</th><th>Sample URLs</th><th>Notes</th></tr></thead><tbody>');
		rows.forEach(function (row) {
			parts.push('<tr><td>' + escHtml(row.site_url) + '</td><td>' + escHtml(row.network_name || '—') + '</td><td>' + escHtml(row.label) + '</td><td>' + escHtml(row.slug) + '</td><td>' + escHtml(row.kind || '—') + '</td><td>' + escHtml(row.confidence) + '</td><td>' + escHtml(String(row.matches_found)) + '</td><td>' + escHtml(row.status) + '</td><td>' + escHtml((row.sample_urls || []).join(' | ') || '—') + '</td><td>' + escHtml(row.notes || '') + '</td></tr>');
		});
		parts.push('</tbody></table>');
		return parts.join('');
	}

	function exportAuditRowsToCsv(rows, filename) {
		var headers = [ 'site_url', 'network_name', 'slug', 'label', 'kind', 'search_for', 'confidence', 'sites_scanned', 'matches_found', 'status', 'sample_urls', 'notes' ];
		var lines = [ headers.join(',') ];
		rows.forEach(function (row) {
			lines.push([
				row.site_url, row.network_name, row.slug, row.label, row.kind, row.search_for,
				row.confidence, row.sites_scanned, row.matches_found, row.status,
				(row.sample_urls || []).join(' | '), row.notes
			].map(csvEscape).join(','));
		});
		var blob = new Blob([ lines.join('\r\n') ], { type: 'text/csv;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = filename;
		a.click();
		URL.revokeObjectURL(a.href);
	}

	function fetchWidgetCatalog(siteUrl) {
		var formData = new FormData();
		formData.append('action', 'uu_widget_tracker_dashboard_fetch_widget_catalog');
		formData.append('nonce', nonce);
		if (siteUrl) formData.append('site_url', siteUrl);
		return postAjax(formData).then(function (body) {
			if (!body.success || !body.data) throw new Error((body.data && body.data.message) || i18n.error || 'Request failed.');
			return body.data;
		});
	}

	function fetchUsageBatches(item, siteUrl, progressHooks) {
		progressHooks = progressHooks || {};
		return new Promise(function (resolve, reject) {
			var offset = 0;
			var allResults = [];
			var totalSites = 0;
			var allErrors = [];
			var totalTime = 0;

			function nextBatch() {
				var formData = new FormData();
				formData.append('action', 'uu_widget_tracker_dashboard_fetch');
				formData.append('nonce', nonce);
				formData.append('widget', item);
				formData.append('offset', String(offset));
				formData.append('batch_size', String(BATCH_SIZE));
				if (siteUrl) formData.append('site_url', siteUrl);

				postAjax(formData).then(function (body) {
					if (!body.success || !body.data) throw new Error((body.data && body.data.message) || i18n.error || 'Request failed.');
					var data = body.data;
					var debug = data.debug || {};
					var results = Array.isArray(data.results) ? data.results : [];
					if (!offset) {
						totalSites = Number(debug.total_sites || 0);
						if (progressHooks.onInit) progressHooks.onInit(totalSites, debug);
					}
					allResults = allResults.concat(results);
					totalTime += Number(debug.execution_time_seconds || 0);
					if (Array.isArray(debug.error_urls)) allErrors = allErrors.concat(debug.error_urls);
					if (progressHooks.onBatch) progressHooks.onBatch(offset + results.length, totalSites, results, debug);
					if (debug.has_more && debug.next_offset != null) {
						offset = Number(debug.next_offset);
						nextBatch();
						return;
					}
					resolve({
						results: allResults,
						debug: {
							public_only: debug.public_only,
							total_sites: totalSites,
							processed: allResults.length,
							total_scanned_blogs: allResults.reduce(function (sum, result) {
								var count = result && result.data ? Number(result.data.scanned_blog_count || 0) : 0;
								return sum + (isNaN(count) ? 0 : count);
							}, 0),
							sites_ok: allResults.length - allErrors.length,
							sites_error: allErrors.length,
							total_posts_found: allResults.reduce(function (sum, result) {
								return sum + ((result.data && Array.isArray(result.data.posts)) ? result.data.posts.length : 0);
							}, 0),
							activation_status: allResults.length === 1 && allResults[0].data && allResults[0].data.activation ? allResults[0].data.activation.status : '',
							active_blog_count: allResults.length === 1 && allResults[0].data && allResults[0].data.activation ? Number(allResults[0].data.activation.active_blog_count || 0) : null,
							total_time_seconds: Math.round(totalTime * 100) / 100,
							error_urls: allErrors
						}
					});
				}).catch(reject);
			}

			nextBatch();
		});
	}

	function buildDiscoveryHtml(siteUrl, discovery) {
		var cards = [];
		function buildList(title, data, key) {
			if (data.error) {
				return '<div class="uu-widget-tracker-discovery-card"><h3>' + escHtml(title) + '</h3><p class="uu-widget-tracker-error">' + escHtml(data.error) + '</p></div>';
			}
			var values = Array.isArray(data[key]) ? data[key] : [];
			return '<div class="uu-widget-tracker-discovery-card"><h3>' + escHtml(title) + '</h3><p><strong>' + escHtml(String(values.length)) + '</strong></p><div style="max-height:220px; overflow:auto; font-family:monospace; font-size:12px;">' + (values.length ? values.map(function (value) { return '<div>' + escHtml(value) + '</div>'; }).join('') : escHtml(i18n.discoveryEmpty || 'No candidate signals were returned.')) + '</div></div>';
		}
		cards.push(buildList(i18n.siteoriginClasses || 'SiteOrigin classes', discovery.siteorigin_classes || {}, 'classes'));
		cards.push(buildList(i18n.classicWidgetIds || 'Classic widget IDs', discovery.classic_widget_ids || {}, 'classic_widget_ids'));
		cards.push(buildList(i18n.contentMarkers || 'Content markers', discovery.content_markers || {}, 'content_markers'));
		return '<h2>' + escHtml(i18n.discoveryHeading || 'Discovery signals') + ' — ' + escHtml(siteUrl) + '</h2><div class="uu-widget-tracker-discovery-grid">' + cards.join('') + '</div>';
	}

	var form = document.getElementById('uu-widget-tracker-fetch-form');
	var lookupResultsEl = document.getElementById('uu-widget-tracker-lookup-results');
	var lookupSpinnerWrap = document.getElementById('uu-widget-tracker-lookup-spinner-wrap');
	var siteScopeSelect = document.getElementById('uu_widget_site_scope');
	var auditButton = document.getElementById('uu-widget-tracker-audit-button');
	var auditScopeSelect = document.getElementById('uu_widget_audit_scope');
	var auditResultsEl = document.getElementById('uu-widget-tracker-audit-results');
	var auditSpinnerWrap = document.getElementById('uu-widget-tracker-audit-spinner-wrap');
	var discoveryButton = document.getElementById('uu-widget-tracker-discovery-button');
	var discoveryScopeSelect = document.getElementById('uu_widget_discovery_scope');
	var discoveryResultsEl = document.getElementById('uu-widget-tracker-discovery-results');
	var discoverySpinnerWrap = document.getElementById('uu-widget-tracker-discovery-spinner-wrap');

	if (form && lookupResultsEl && lookupSpinnerWrap) {
		var scopeInputs = form.querySelectorAll('input[name="search_scope"]');
		var lookupMethodInputs = form.querySelectorAll('input[name="lookup_method"]');
		var lookupMethodPanels = document.querySelectorAll('[data-lookup-method-panel]');

		function getSelectedScope() {
			var value = 'all';
			Array.prototype.forEach.call(scopeInputs, function (input) {
				if (input.checked) value = input.value;
			});
			return value;
		}

		function getSelectedLookupMethod() {
			var value = 'known';
			Array.prototype.forEach.call(lookupMethodInputs, function (input) {
				if (input.checked) value = input.value;
			});
			return value;
		}

		function syncScopeUi() {
			if (!siteScopeSelect) return;
			var single = getSelectedScope() === 'single';
			siteScopeSelect.disabled = !single;
			if (!single) siteScopeSelect.value = '';
		}

		function syncLookupMethodUi() {
			var method = getSelectedLookupMethod();
			Array.prototype.forEach.call(lookupMethodPanels, function (panel) {
				panel.classList.toggle('is-active', panel.getAttribute('data-lookup-method-panel') === method);
			});
		}

		Array.prototype.forEach.call(scopeInputs, function (input) { input.addEventListener('change', syncScopeUi); });
		Array.prototype.forEach.call(lookupMethodInputs, function (input) { input.addEventListener('change', syncLookupMethodUi); });
		syncScopeUi();
		syncLookupMethodUi();

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var selectedScope = getSelectedScope();
			var selectedSiteUrl = selectedScope === 'single' && siteScopeSelect ? (siteScopeSelect.value || '').trim() : '';
			var knownField = document.getElementById('uu_widget_slug');
			var manualField = document.getElementById('uu_widget_slug_custom');
			var item = lookupMethodInputs.length && getSelectedLookupMethod() === 'manual'
				? (manualField ? (manualField.value || '').trim() : '')
				: (knownField ? (knownField.value || '').trim() : '');
			if (!item) return;
			if (selectedScope === 'single' && !selectedSiteUrl) return;

			lookupSpinnerWrap.classList.add('is-active');
			lookupResultsEl.innerHTML = '<div id="uu-widget-tracker-progress"><strong>' + escHtml(i18n.fetching || 'Fetching usage…') + '</strong></div><div id="uu-widget-tracker-log" style="max-height:220px; overflow:auto; font-family:monospace; font-size:12px; padding:8px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; margin-top:12px;"></div>';
			var progressDiv = lookupResultsEl.querySelector('#uu-widget-tracker-progress');
			var logDiv = lookupResultsEl.querySelector('#uu-widget-tracker-log');

			fetchUsageBatches(item, selectedSiteUrl, {
				onInit: function (totalSites) {
					progressDiv.innerHTML = '<strong>' + escHtml(i18n.fetching || 'Fetching usage…') + '</strong> 0 of ' + totalSites + ' sites';
				},
				onBatch: function (processed, totalSites, results) {
					progressDiv.innerHTML = '<strong>' + escHtml(i18n.fetching || 'Fetching usage…') + '</strong> ' + processed + ' of ' + totalSites + ' sites';
					results.forEach(function (result) {
						var data = result.data || {};
						var status = data.error ? ('Error: ' + data.error) : ((data.tracking_definition_status || 'OK') + (Array.isArray(data.posts) ? ' (' + data.posts.length + ' posts)' : ''));
						logDiv.innerHTML += '<div>' + escHtml(result.url) + ' — ' + escHtml(status) + '</div>';
					});
				}
			}).then(function (payload) {
				lookupSpinnerWrap.classList.remove('is-active');
				lookupResultsEl.innerHTML = buildResultsHtml(item, payload.results, payload.debug);
				var table = lookupResultsEl.querySelector('.uu-widget-tracker-results-table');
				if (table) {
					attachTableSort(table);
					var exportBtn = lookupResultsEl.querySelector('#uu-tracker-export-csv');
					if (exportBtn) {
						exportBtn.addEventListener('click', function () {
							exportTableToCsv(table, 'uu-usage-' + item + '-' + new Date().toISOString().slice(0, 10) + '.csv');
						});
					}
				}
			}).catch(function (error) {
				renderError(error && error.message ? error.message : '', lookupResultsEl, lookupSpinnerWrap);
			});
		});
	}

	if (auditButton && auditResultsEl && auditSpinnerWrap) {
		auditButton.addEventListener('click', function () {
			var scopedSiteUrl = pageMode === 'legacy' && auditScopeSelect ? (auditScopeSelect.value || '').trim() : '';
			if (pageMode === 'legacy' && !scopedSiteUrl) return;

			auditSpinnerWrap.classList.add('is-active');
			auditResultsEl.innerHTML = '<div id="uu-widget-tracker-progress"><strong>' + escHtml(i18n.fetchingCatalog || 'Fetching tracked item list…') + '</strong></div><div id="uu-widget-tracker-log" style="max-height:220px; overflow:auto; font-family:monospace; font-size:12px; padding:8px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; margin-top:12px;"></div>';
			var progressDiv = auditResultsEl.querySelector('#uu-widget-tracker-progress');
			var logDiv = auditResultsEl.querySelector('#uu-widget-tracker-log');

			fetchWidgetCatalog(scopedSiteUrl).then(function (catalogPayload) {
				var siteResults = Array.isArray(catalogPayload.results) ? catalogPayload.results : [];
				var jobs = [];
				siteResults.forEach(function (siteItem) {
					var data = siteItem.data || {};
					var items = Array.isArray(data.items) ? data.items : (Array.isArray(data.widgets) ? data.widgets : []);
					if (data.error) {
						logDiv.innerHTML += '<div>' + escHtml(siteItem.url) + ' — ' + escHtml(data.error) + '</div>';
						return;
					}
					items.forEach(function (item) {
						if (item && item.slug) jobs.push({ siteUrl: siteItem.url, item: item });
					});
					logDiv.innerHTML += '<div>' + escHtml(siteItem.url) + ' — ' + escHtml(String(items.length)) + ' tracked items</div>';
				});

				var auditRows = [];
				var index = 0;
				function nextJob() {
					if (index >= jobs.length) {
						auditSpinnerWrap.classList.remove('is-active');
						var debug = {
							total_sites: siteResults.length,
							processed: siteResults.length,
							total_items: jobs.length,
							items_used: auditRows.filter(function (row) { return row.status === (i18n.used || 'Used'); }).length
						};
						auditResultsEl.innerHTML = buildAuditResultsHtml(auditRows, debug);
						var exportAuditBtn = auditResultsEl.querySelector('#uu-tracker-export-audit-csv');
						if (exportAuditBtn) {
							exportAuditBtn.addEventListener('click', function () {
								exportAuditRowsToCsv(auditRows, 'uu-usage-audit-' + new Date().toISOString().slice(0, 10) + '.csv');
							});
						}
						return;
					}

					var job = jobs[index];
					progressDiv.innerHTML = '<strong>' + escHtml(i18n.auditProgress || 'Auditing item') + '</strong> ' + (index + 1) + ' of ' + jobs.length + ': ' + escHtml(job.siteUrl) + ' — ' + escHtml(job.item.slug);
					fetchUsageBatches(job.item.slug, job.siteUrl).then(function (payload) {
						var result = payload.results && payload.results[0] ? payload.results[0] : { url: job.siteUrl, data: { error: i18n.error || 'Request failed.' } };
						var row = summarizeAuditRow(job.siteUrl, job.item, result);
						auditRows.push(row);
						logDiv.innerHTML += '<div>' + escHtml(job.siteUrl) + ' — ' + escHtml(job.item.slug) + ' — ' + escHtml(row.status) + '</div>';
						index += 1;
						nextJob();
					}).catch(function (error) {
						auditRows.push({
							site_url: job.siteUrl,
							network_name: '',
							slug: job.item.slug || '',
							label: job.item.label || job.item.slug || '',
							kind: job.item.kind || '',
							search_for: job.item.search_for || '',
							confidence: getConfidence(job.item.kind || ''),
							sites_scanned: 0,
							matches_found: 0,
							status: i18n.statusError || 'Error',
							sample_urls: [],
							notes: error && error.message ? error.message : (i18n.error || 'Request failed.')
						});
						index += 1;
						nextJob();
					});
				}

				nextJob();
			}).catch(function (error) {
				renderError(error && error.message ? error.message : '', auditResultsEl, auditSpinnerWrap);
			});
		});
	}

	if (discoveryButton && discoveryResultsEl && discoverySpinnerWrap) {
		discoveryButton.addEventListener('click', function () {
			var siteUrl = discoveryScopeSelect ? (discoveryScopeSelect.value || '').trim() : '';
			if (!siteUrl) return;

			discoverySpinnerWrap.classList.add('is-active');
			discoveryResultsEl.innerHTML = '';
			var formData = new FormData();
			formData.append('action', 'uu_widget_tracker_dashboard_fetch_discovery');
			formData.append('nonce', nonce);
			formData.append('site_url', siteUrl);
			postAjax(formData).then(function (body) {
				if (!body.success || !body.data) throw new Error((body.data && body.data.message) || i18n.error || 'Request failed.');
				discoverySpinnerWrap.classList.remove('is-active');
				discoveryResultsEl.innerHTML = buildDiscoveryHtml(body.data.url, body.data.discovery || {});
			}).catch(function (error) {
				renderError(error && error.message ? error.message : '', discoveryResultsEl, discoverySpinnerWrap);
			});
		});
	}
})();
