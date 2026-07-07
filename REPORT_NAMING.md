# Report Naming

This document defines the standard names for the audit reports so future runs are easy to request and easy to interpret.

The regular user-facing deliverables are matched URL reports. They answer the primary question:

> Which exact URLs display our managed widgets or standalone plugins?

Inventory, registry, summary, activation, and definition-gap reports remain available as support/debug artifacts, but they are not part of the default reporting workflow.

## Core Naming Rules

- `matched URL report` means one row per exact matched page URL and is the normal deliverable
- `inventory` means the raw discovered source list, used as support input
- `registry` means the normalized canonical list, used as support input
- `summary report` means one row per tracked item per environment, used for support/debug review
- `blog activation detail report` means one row per active blog for that item, used for support/debug review

## Terminology Rules

- use `widget` when the reporting unit is the widget itself
- use `standalone plugin` when the reporting unit is a plugin that is not primarily being tracked as a widget
- avoid `legacy` in the core naming, because the system should also support future plugins and widgets we build
- remember that widgets live inside plugins or widget bundles, but widgets are still their own reporting unit

## Common Commands

Use the matched URL wrapper for normal report runs:

```bash
php tools/export-managed-component-matched-urls-csv.php \
  --target=widgets \
  --widget-registry=/path/to/Widget_Audit.registry.v1.csv \
  --map=/path/to/site-map.json \
  --output-dir=/path/to/output-folder \
  --snapshot=/path/to/report-usage-snapshot.json
```

```bash
php tools/export-managed-component-matched-urls-csv.php \
  --target=plugins \
  --plugin-inventory=/path/to/Standalone_Plugin_Audit.inventory.v1.csv \
  --map=/path/to/site-map.json \
  --output-dir=/path/to/output-folder \
  --snapshot=/path/to/report-usage-snapshot.json
```

```bash
php tools/export-managed-component-matched-urls-csv.php \
  --target=all \
  --widget-registry=/path/to/Widget_Audit.registry.v1.csv \
  --plugin-inventory=/path/to/Standalone_Plugin_Audit.inventory.v1.csv \
  --map=/path/to/site-map.json \
  --output-dir=/path/to/output-folder \
  --snapshot=/path/to/report-usage-snapshot.json
```

Default outputs:

- `Widget_Audit.report-matched-urls.v1.csv`
- `Standalone_Plugin_Audit.report-matched-urls.v1.csv`
- `Component_Audit.report-matched-urls.v1.csv` when `--target=all`

## Widget Reports

### Widget matched URL report
Purpose:
- one row per exact matched page URL for a widget
- primary widget deliverable

Filename pattern:
- `Widget_Audit.report-matched-urls.v#.csv`

Current file:
- `Widget_Audit.report-matched-urls.v1.csv`

Primary command:
- `php tools/export-managed-component-matched-urls-csv.php --target=widgets ...`

### Widget inventory report
Support/debug purpose:
- raw discovered widget list across environments

Filename pattern:
- `Widget_Audit.inventory.v#.csv`

Current file:
- `Widget_Audit.inventory.v1.csv`

### Widget registry report
Support/debug purpose:
- canonical normalized widget list built from the inventory
- input to the widget matched URL workflow

Filename pattern:
- `Widget_Audit.registry.v#.csv`

Current file:
- `Widget_Audit.registry.v1.csv`

### Widget summary report
Support/debug purpose:
- one row per widget per environment
- includes activation, match count, confidence, and action

Filename pattern:
- `Widget_Audit.report-summary.v#.csv`

Current file:
- `Widget_Audit.report-summary.v1.csv`

### Widget blog activation detail report
Support/debug purpose:
- one row per active blog for a widget
- shows where widget-related plugin activation exists even when page matches are zero

Filename pattern:
- `Widget_Audit.report-blog-activation-details.v#.csv`

Current file:
- `Widget_Audit.report-blog-activation-details.v1.csv`

## Standalone Plugin Reports

### Standalone plugin matched URL report
Purpose:
- one row per exact matched page URL for a tracked plugin item
- primary standalone plugin deliverable

Filename pattern:
- `Standalone_Plugin_Audit.report-matched-urls.v#.csv`
- historical environment-specific names may use `AWS_Plugin_Audit.report-matched-urls.v#.csv` or `WP_Engine_Standalone_Plugin_Audit.report-matched-urls.v#.csv`

Current file:
- `Standalone_Plugin_Audit.report-matched-urls.v1.csv`

Primary command:
- `php tools/export-managed-component-matched-urls-csv.php --target=plugins ...`

### Standalone plugin inventory source
Support/debug purpose:
- source inventory used to build the plugin matched URL report

Filename pattern:
- `Standalone_Plugin_Audit.inventory.v#.csv`
- historical environment-specific names may use `AWS_Plugin_Audit.inventory.v#.csv` or `WP_Engine_Standalone_Plugin_Audit.inventory.v#.csv`

Current file:
- `Standalone_Plugin_Audit.inventory.v1.csv`

### Standalone plugin summary report
Support/debug purpose:
- one row per tracked plugin item per environment

Filename pattern:
- `Standalone_Plugin_Audit.report-summary.v#.csv`
- historical environment-specific names may use `AWS_Plugin_Audit.report-summary.v#.csv` or `WP_Engine_Standalone_Plugin_Audit.report-summary.v#.csv`

Current file:
- `Standalone_Plugin_Audit.report-summary.v1.csv`

### Standalone plugin blog activation detail report
Support/debug purpose:
- one row per active blog for a tracked plugin item

Filename pattern:
- `Standalone_Plugin_Audit.report-blog-activation-details.v#.csv`
- historical environment-specific names may use `AWS_Plugin_Audit.report-blog-activation-details.v#.csv` or `WP_Engine_Standalone_Plugin_Audit.report-blog-activation-details.v#.csv`

Current file:
- `Standalone_Plugin_Audit.report-blog-activation-details.v1.csv`

## Combined Component Reports

### Combined component matched URL report
Purpose:
- one row per exact matched page URL across widgets and standalone plugins
- primary combined deliverable

Filename pattern:
- `Component_Audit.report-matched-urls.v#.csv`

Current file:
- `Component_Audit.report-matched-urls.v1.csv`

Recommended default output folder:
- `Desktop/Component Audit - YYYY-MM-DD HH.MM.SS/`

Primary command:
- `php tools/export-managed-component-matched-urls-csv.php --target=all ...`

### Combined component summary report
Support/debug purpose:
- one row per tracked component per environment
- merges widgets and standalone plugins into a decision/debug sheet

Filename pattern:
- `Component_Audit.report-summary.v#.csv`

Current file:
- `Component_Audit.report-summary.v1.csv`

### Standalone-plugin-only matched URL report
Support/debug purpose:
- one row per exact matched page URL for standalone plugins only
- makes the plugin-only page footprint easy to review without widget rows overwhelming the file

Filename pattern:
- `Component_Audit.report-matched-urls.standalone-plugins-only.v#.csv`

Current file:
- `Component_Audit.report-matched-urls.standalone-plugins-only.v1.csv`

Recommended default output folder:
- `Desktop/Component Audit - YYYY-MM-DD HH.MM.SS/`

## Intermediate Runtime Artifacts

Purpose:
- stable non-Desktop storage for chunked or rerun source CSVs that feed the combined builder
- avoids reliance on `/private/tmp` for important intermediate inputs

Recommended runtime folder:
- `uu-widget-tracker-dashboard/reports/runtime/`

Recommended WP Engine standalone plugin runtime folder:
- `uu-widget-tracker-dashboard/reports/runtime/wpengine-standalone-plugin/`

## Recommended Request Language

Use these exact phrases when asking for a rerun:

- `run the widget matched URL report`
- `run the standalone plugin matched URL report`
- `run the combined component matched URL report`

Support/debug phrases remain available when needed:

- `run the widget inventory report`
- `run the widget registry report`
- `run the widget summary report`
- `run the widget blog activation detail report`
- `run the standalone plugin inventory report`
- `run the standalone plugin summary report`
- `run the standalone plugin blog activation detail report`
- `run the combined component summary report`

## Practical Interpretation

- `matched URL report` = exact pages and the regular deliverable
- `summary report` = support/debug decision sheet
- `blog activation detail report` = support/debug active footprint across blogs/sites
- `inventory` = support/debug discovery source
- `registry` = cleaned-up canonical source of truth and matched URL input
- `widget report` = the widget itself is the reporting unit
- `standalone plugin report` = the plugin is the reporting unit
