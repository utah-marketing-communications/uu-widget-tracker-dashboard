# Project Handoff Summary

## Goal

The current goal is to produce a reliable page-level inventory of University of Utah-created components across both hosting environments:

- AWS multisite networks
- WP Engine single-site production installs

Specifically, the target outcome is:

- a URL for every page where each custom widget is displayed
- a URL for every page where each custom standalone plugin is displayed, when page-level detection is possible
- enough supporting inventory and summary data to distinguish confirmed usage from review-only/discovery-only rows

## Current Status

The project is in a strong widget-audit state and a partial standalone-plugin state.

### What is working well

- `uu-usage-tracker` has been deployed and activated across all WP Engine production sites.
- The local reporting workflow in `uu-widget-tracker-dashboard` is working for both AWS and WP Engine.
- Widget reporting is now available across:
  - AWS multisite environments
  - all WP Engine production sites
- Page-level matched URL reporting is working and is currently the highest-confidence source of truth.

### What is complete enough to use now

- AWS widget and plugin audit workflows
- WP Engine widget inventory workflow
- WP Engine widget registry workflow
- WP Engine widget summary workflow
- WP Engine widget matched URL workflow
- WP Engine standalone plugin seed inventory workflow
- WP Engine standalone plugin definition-gap summary workflow

### What is not fully complete yet

- Standalone plugin page-level reporting across WP Engine is not yet as complete as widget reporting.
- The latest WP Engine standalone plugin pass found no defined/matched standalone plugin rows; most rows are now explicitly marked `Tracked item is not defined by remote tracker`.
- WP Engine widget activation-detail reporting is currently weak because widget rows typically report `Plugin Activation = Unknown`.
- Some summary and inventory rows are still discovery-only or review-only and should not be treated as confirmed page usage.

## Accuracy Guidance

The current data should be interpreted in layers:

- `matched URL` reports:
  highest confidence; these are the best source for exact page-level usage
- `summary` reports:
  useful for triage and prioritization, but confidence varies by row
- `inventory` and `registry` reports:
  useful for discovery, normalization, and coverage tracking
- `blog activation detail` reports on WP Engine:
  currently not very informative because activation attribution is mostly unknown for widget rows

## Working Files

### Main repository

- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard`

### Core planning / reference docs

- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/TRACKER_REQUIREMENTS.md`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/TRACKER_ARCHITECTURE_AUDIT.md`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/WIDGET_AUDIT_IMPLEMENTATION_PLAN.md`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/REPORT_NAMING.md`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/COMPONENT_ONBOARDING.md`

### Main report scripts

- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/export-widget-inventory-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/build-widget-registry-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/fill-widget-summary-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/export-widget-matched-urls-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/export-widget-blog-activation-details-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/build-wpengine-standalone-plugin-inventory-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/fill-audit-summary-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/export-audit-url-details-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/export-audit-blog-activation-details-csv.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/export-wpengine-production-map.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/run-wpengine-activation-batches.php`

### Shared helper layer

- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/report-runtime.php`
- `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/tools/audit-cli-common.php`

### WP Engine map files

- full production map:
  `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/maps/wpengine-production-sites.v1.json`
- first 5-site pilot map:
  `/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/maps/wpengine-production-sites.batch-5.v1.json`

## Latest Full WP Engine Output Files

These are the outputs from the latest completed full WP Engine widget process:

- inventory:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Widget_Audit.inventory.v1.csv`
- registry:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Widget_Audit.registry.v1.csv`
- summary:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Widget_Audit.report-summary.v1.csv`
- matched URLs:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Widget_Audit.report-matched-urls.v1.csv`
- blog activation details:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Widget_Audit.report-blog-activation-details.v1.csv`

### Full WP Engine output counts

- inventory rows: `4,691`
- registry rows: `147`
- summary rows: `4,576`
- matched URL rows: `1,757`
- blog activation detail rows: `0`

## Latest Full WP Engine Standalone Plugin Output Files

These outputs are the current WP Engine standalone plugin gap report:

- inventory:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Standalone_Plugin_Audit.inventory.v1.csv`
- summary:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Standalone_Plugin_Audit.report-summary.v1.csv`
- matched URLs:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Standalone_Plugin_Audit.report-matched-urls.v1.csv`
- blog activation details:
  `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Standalone_Plugin_Audit.report-blog-activation-details.v1.csv`

### Full WP Engine standalone plugin output counts

- inventory rows: `1,162`
- summary rows: `1,162`
- matched URL rows: `0`
- blog activation detail rows: `0`

### Full WP Engine standalone plugin high-level read

- The standalone plugin inventory was built from 14 standalone or standalone/classic tracked items expanded across 83 WP Engine production environments.
- Summary rows currently show:
  - `1,078` rows with `Tracked item is not defined by remote tracker`
  - `84` rows with `Unable to fetch JSON from remote tracker`
  - `0` defined rows with page matches
- The `Unable to fetch JSON` rows are concentrated in 6 environments:
  `academicsen.wpenginepowered.com`, `intranet.advancement.utah.edu`, `sandbox.umc.utah.edu`, `students.utah.edu`, `umcdigitalsand.wpenginepowered.com`, and `uutraining.wpenginepowered.com`.
- This confirms the main WP Engine standalone-plugin gap is remote tracker definition coverage. The local matched URL and activation detail exporters are ready, but the WP Engine remote tracker does not currently expose these standalone plugin slugs.

## Latest Full WP Engine High-Level Read

- Widget reporting across WP Engine is working at full-fleet scale.
- The matched URL report is the best current source for exact widget page locations.
- Most matched URL usage is concentrated in the `uu-so-widgets` family.
- The WP Engine summary contains many `Review` rows because a large portion of the fleet is still represented by discovery/core/review-only rows rather than strong page-level matches.

## Best Current Source of Truth

If the question is:

> “What exact pages are our custom widgets currently appearing on?”

the best file to use right now is:

- `/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Widget_Audit.report-matched-urls.v1.csv`

For AWS, the equivalent matched URL outputs should be used the same way.

## Recommended Next Phase

The next project phase should focus on closing the remaining gaps in page-level coverage:

1. Treat widget matched URL reports as the current source of truth.
2. Review AWS and WP Engine matched URL outputs together.
3. Move or otherwise enable relevant standalone plugin definitions for WP Engine production sites.
4. Rerun the WP Engine standalone plugin summary, matched URL, and activation detail reports.
5. Identify remaining standalone plugins that need stronger page-level signals or plugin-focused reporting.
6. Expand or refine standalone plugin detection so the same page-level confidence we now have for widgets can be reached for non-widget custom plugins wherever possible.

## Suggested Fresh-Thread Starting Point

If continuing in a new thread, use a prompt like:

> We have completed full WP Engine widget reporting and already have AWS reporting in place. Our goal is to produce a page-level URL inventory for every custom University of Utah widget and standalone plugin across AWS multisites and WP Engine single-site installs. Please use `PROJECT_HANDOFF_SUMMARY.md` in the `uu-widget-tracker-dashboard` repo as the starting context and help us close the remaining gaps, especially for standalone plugins and any rows that are still review-only or discovery-only.
