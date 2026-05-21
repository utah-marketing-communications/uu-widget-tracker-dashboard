# Widget Audit Implementation Plan

## Goal
Build a forward-looking widget audit workflow that can answer:

- what widgets exist across our environments
- which plugin or bundle each widget comes from
- which blogs/sites use each widget
- which exact pages use each widget when page-level matches are available
- how many total matched pages and active blogs exist for each widget

This plan extends the current tracker architecture instead of replacing it.

## Current Foundation

### Remote tracker
`uu-usage-tracker` already provides the right base architecture:

- `GET /wp-json/uu-usage-tracker/v1/items`
- `GET /wp-json/uu-usage-tracker/v1/usage?item=<slug>`
- `GET /wp-json/uu-usage-tracker/v1/discovery/siteorigin-classes`
- `GET /wp-json/uu-usage-tracker/v1/discovery/classic-widget-ids`
- `GET /wp-json/uu-usage-tracker/v1/discovery/content-markers`

It also already returns:

- page-level matches in `posts`
- `match_sources`
- `usage_scope`
- `activation`
- multisite scan context

### Local scripts
The local reporting layer already works well for standalone plugin audits and should remain the primary execution path.

Current scripts:

- `tools/fill-audit-summary-csv.php`
- `tools/export-audit-url-details-csv.php`
- `tools/export-audit-blog-activation-details-csv.php`

## Recommendation
Do **not** build a separate remote system for widgets.

Instead:

1. keep `uu-usage-tracker` as the remote collector
2. add widget-specific discovery and reporting scripts locally
3. create a canonical widget registry between discovery and usage reporting

This gives us one architecture with two workflows:

- standalone plugin audit workflow
- forward-looking widget inventory and usage workflow

## Deliverables

### Inventory source of truth
- `Widget_Audit.inventory.v1.csv`

### Widget reports
- `Widget_Audit.report-summary.v1.csv`
- `Widget_Audit.report-matched-urls.v1.csv`
- `Widget_Audit.report-blog-activation-details.v1.csv`

### Optional support artifact
- `Widget_Audit.registry.v1.csv`

## Data Model

Each canonical widget row should eventually include:

- `Widget Slug`
- `Widget Class`
- `Widget Label`
- `Plugin Folder`
- `Plugin File`
- `Bundle / Family`
- `Widget Type`
  - `siteorigin`
  - `classic`
  - `other`
- `Seen In`
  - `registered_code`
  - `saved_content`
  - `both`
- `Discovery Source`
- `Notes`

## Phase 1: Widget Discovery
Purpose: discover all widgets that actually exist across AWS multisites and WP Engine installs.

### New script
- `tools/export-widget-inventory-csv.php`

### Input
- site map JSON, same pattern as current audit scripts

### Output
- `Widget_Audit.inventory.v1.csv`

### What it should collect
- SiteOrigin classes returned by discovery
- SiteOrigin classes found in saved `panels_data`
- classic widget IDs seen in sidebars
- item catalog rows from `/items`
- plugin/bundle association when inferable
- which multisite or single site the widget was seen on

### Important behavior
- do not assume `uu-so-widgets` is the only widget source
- treat CAP widgets and other bundle-specific widgets as first-class inventory items
- keep duplicate sightings so we can later normalize them into one registry

## Phase 2: Canonical Widget Registry
Purpose: normalize discovery output into one stable list of widgets we care about tracking.

### New script
- `tools/build-widget-registry-csv.php`

### Input
- `Widget_Audit.inventory.v1.csv`

### Output
- `Widget_Audit.registry.v1.csv`

### Responsibilities
- collapse repeated sightings of the same widget class
- assign a canonical `Widget Slug`
- capture plugin/bundle ownership
- note ambiguous mappings for manual cleanup
- flag rows that still need a stronger definition

### Why this matters
Without a registry, usage reports will drift and naming will get messy as we discover more bundles outside `uu-so-widgets`.

## Phase 3: Widget Usage Reports
Purpose: turn the registry into decision-ready widget usage reporting.

### New scripts
- `tools/fill-widget-summary-csv.php`
- `tools/export-widget-matched-urls-csv.php`
- `tools/export-widget-blog-activation-details-csv.php`

### Inputs
- `Widget_Audit.registry.v1.csv`
- site map JSON

### Outputs
- `Widget_Audit.report-summary.v1.csv`
- `Widget_Audit.report-matched-urls.v1.csv`
- `Widget_Audit.report-blog-activation-details.v1.csv`

### Summary report should show
- widget slug
- widget class
- plugin/bundle
- multisite/site group
- plugin activation footprint
- matched page count
- confidence
- action

### Matched URL report should show
- widget slug
- widget class
- plugin/bundle
- site name
- blog ID
- page title
- permalink
- matched-by signal

### Blog activation detail report should show
- widget slug
- plugin/bundle
- active blog count
- scanned blog count
- active site name
- active site URL
- matched plugin file

## Phase 4: Remote Tracker Enhancements
Purpose: improve widget discovery completeness without changing the overall architecture.

### Recommended remote additions
1. Add a richer widget discovery endpoint
- proposed endpoint: `GET /wp-json/uu-usage-tracker/v1/discovery/widgets`

### Proposed payload
- widget class
- widget label
- widget type
- plugin folder/file if detectable
- whether found in registration, saved content, or both

2. Add bundle/plugin context to discovered SiteOrigin classes when possible

3. Keep classic widget discovery separate but include enough metadata to join it into the same inventory

### Why
This gives us a much cleaner inventory pass before we try to count usage.

## Phase 5: Rollout Order

### Step 1: AWS multisites first
Run discovery and registry building across:

- Bryce
- Capitol Reef
- Zion

This will expose the real widget families already in the wild and help clean up naming before broader rollout.

### Step 2: Validate inventory completeness
Review:

- CAP widgets
- `uu-so-widgets`
- other standalone widget bundles discovered in the data

### Step 3: Add WP Engine production site inventory
Before broad WP Engine reporting, generate the production site list from the WP Engine Hosting Platform API instead of maintaining it by hand.

#### New support script
- `tools/export-wpengine-production-map.php`

#### Purpose
- authenticate to WP Engine with API credentials
- list sites and/or installs
- filter to production environments only
- enrich installs with primary/custom domain data when needed
- write a JSON map file that the other local scripts can consume

#### Recommended output
- `maps/wpengine-production-sites.v1.json`

#### Expected map shape
```json
{
  "Example Site": "https://www.example.com/",
  "Another Site": "https://another.example.edu/"
}
```

#### Notes
- prefer live production domains over temporary WP Engine cnames when available
- keep AWS maps and WP Engine maps separate initially
- this production map becomes the source of truth for WP Engine report runs

### Step 4: Expand to WP Engine installs
Deploy `uu-usage-tracker` to the target WP Engine installs and run the same discovery workflow there using the generated production map.

### Step 5: Regenerate the shared widget registry
Merge AWS + WP Engine discovery into one canonical registry.

### Step 6: Run usage reporting from the registry
This becomes the recurring, forward-looking widget monitoring workflow.

## Execution Order

### Immediate next build order
1. `export-widget-inventory-csv.php`
2. `build-widget-registry-csv.php`
3. `fill-widget-summary-csv.php`
4. `export-widget-matched-urls-csv.php`
5. `export-widget-blog-activation-details-csv.php`

### After scripts exist
1. run widget inventory on AWS multisites
2. review and clean registry
3. run widget usage reports
4. generate WP Engine production site map from the API
5. expand to WP Engine installs

## Success Criteria

We should consider the widget audit workflow successful when we can answer:

- how many distinct widget classes exist in production
- which plugin/bundle each widget belongs to
- how many blogs/sites use each widget
- how many exact page matches exist for each widget
- which widgets are heavily used, lightly used, inactive, or need definition work

## Naming Standard

To keep this clear later, use these names consistently:

### Source files
- `inventory`
- `registry`

### Reports
- `summary report`
- `matched URL report`
- `blog activation detail report`

### Widget-specific report filenames
- `Widget_Audit.inventory.v1.csv`
- `Widget_Audit.registry.v1.csv`
- `Widget_Audit.report-summary.v1.csv`
- `Widget_Audit.report-matched-urls.v1.csv`
- `Widget_Audit.report-blog-activation-details.v1.csv`

## Decision
Use the current tracker system as the core engine, extend it with widget discovery and widget-specific local reports, and avoid building a separate parallel monitoring system.
