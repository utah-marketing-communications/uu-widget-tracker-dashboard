# Report Naming

This document defines the standard names for the audit reports so future runs are easy to request and easy to interpret.

## Core Naming Rules

- `inventory` means the raw discovered source list
- `registry` means the normalized canonical list
- `summary report` means one row per tracked item per environment
- `matched URL report` means one row per exact matched page URL
- `blog activation detail report` means one row per active blog for that item

## Terminology Rules

- use `widget` when the reporting unit is the widget itself
- use `standalone plugin` when the reporting unit is a plugin that is not primarily being tracked as a widget
- avoid `legacy` in the core naming, because the system should also support future plugins and widgets we build
- remember that widgets live inside plugins or widget bundles, but widgets are still their own reporting unit

## Widget Reports

### Widget inventory report
Purpose:
- raw discovered widget list across environments

Filename pattern:
- `Widget_Audit.inventory.v#.csv`

Current file:
- `Widget_Audit.inventory.v1.csv`

### Widget registry report
Purpose:
- canonical normalized widget list built from the inventory

Filename pattern:
- `Widget_Audit.registry.v#.csv`

Current file:
- `Widget_Audit.registry.v1.csv`

### Widget summary report
Purpose:
- one row per widget per environment
- includes activation, match count, confidence, and action

Filename pattern:
- `Widget_Audit.report-summary.v#.csv`

Current file:
- `Widget_Audit.report-summary.v1.csv`

### Widget matched URL report
Purpose:
- one row per exact matched page URL for a widget

Filename pattern:
- `Widget_Audit.report-matched-urls.v#.csv`

Current file:
- `Widget_Audit.report-matched-urls.v1.csv`

### Widget blog activation detail report
Purpose:
- one row per active blog for a widget
- shows where widget-related plugin activation exists even when page matches are zero

Filename pattern:
- `Widget_Audit.report-blog-activation-details.v#.csv`

Current file:
- `Widget_Audit.report-blog-activation-details.v1.csv`

## Standalone Plugin Reports

### Standalone plugin inventory source
Purpose:
- source inventory used to build the plugin reports

Filename pattern:
- `AWS_Plugin_Audit.inventory.v#.csv`
- `WP_Engine_Standalone_Plugin_Audit.inventory.v#.csv`

Current file:
- `AWS_Plugin_Audit.inventory.v1.csv`
- `WP_Engine_Standalone_Plugin_Audit.inventory.v1.csv`

### Standalone plugin summary report
Purpose:
- one row per tracked plugin item per environment

Filename pattern:
- `AWS_Plugin_Audit.report-summary.v#.csv`
- `WP_Engine_Standalone_Plugin_Audit.report-summary.v#.csv`

Current file:
- `AWS_Plugin_Audit.report-summary.v1.csv`
- `WP_Engine_Standalone_Plugin_Audit.report-summary.v1.csv`

### Standalone plugin matched URL report
Purpose:
- one row per exact matched page URL for a tracked plugin item

Filename pattern:
- `AWS_Plugin_Audit.report-matched-urls.v#.csv`
- `WP_Engine_Standalone_Plugin_Audit.report-matched-urls.v#.csv`

Current file:
- `AWS_Plugin_Audit.report-matched-urls.v1.csv`
- `WP_Engine_Standalone_Plugin_Audit.report-matched-urls.v1.csv`

### Standalone plugin blog activation detail report
Purpose:
- one row per active blog for a tracked plugin item

Filename pattern:
- `AWS_Plugin_Audit.report-blog-activation-details.v#.csv`
- `WP_Engine_Standalone_Plugin_Audit.report-blog-activation-details.v#.csv`

Current file:
- `AWS_Plugin_Audit.report-blog-activation-details.v1.csv`
- `WP_Engine_Standalone_Plugin_Audit.report-blog-activation-details.v1.csv`

## Combined Component Reports

### Combined component summary report
Purpose:
- one row per tracked component per environment
- merges widgets and standalone plugins across AWS and WP Engine
- serves as the combined decision sheet

Filename pattern:
- `Component_Audit.report-summary.v#.csv`

Current file:
- `Component_Audit.report-summary.v1.csv`

Recommended default output folder:
- `Desktop/Component Audit/`

### Combined component matched URL report
Purpose:
- one row per exact matched page URL across widgets and standalone plugins
- merges AWS and WP Engine page-level findings into one source-of-truth file

Filename pattern:
- `Component_Audit.report-matched-urls.v#.csv`

Current file:
- `Component_Audit.report-matched-urls.v1.csv`

Recommended default output folder:
- `Desktop/Component Audit/`

### Standalone-plugin-only matched URL report
Purpose:
- one row per exact matched page URL for standalone plugins only
- makes the plugin-only page footprint easy to review without widget rows overwhelming the file

Filename pattern:
- `Component_Audit.report-matched-urls.standalone-plugins-only.v#.csv`

Current file:
- `Component_Audit.report-matched-urls.standalone-plugins-only.v1.csv`

Recommended default output folder:
- `Desktop/Component Audit/`

## Recommended Request Language

Use these exact phrases when asking for a rerun:

- `run the widget inventory report`
- `run the widget registry report`
- `run the widget summary report`
- `run the widget matched URL report`
- `run the widget blog activation detail report`
- `run the standalone plugin summary report`
- `run the standalone plugin matched URL report`
- `run the standalone plugin blog activation detail report`
- `run the combined component summary report`
- `run the combined component matched URL report`

## Practical Interpretation

- `summary report` = decision sheet
- `matched URL report` = exact pages
- `blog activation detail report` = active footprint across blogs/sites
- `inventory` = discovery source
- `registry` = cleaned-up canonical source of truth
- `widget report` = the widget itself is the reporting unit
- `standalone plugin report` = the plugin is the reporting unit
