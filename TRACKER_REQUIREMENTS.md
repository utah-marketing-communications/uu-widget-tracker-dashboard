# Tracker Requirements

This document defines the current and expanded requirements for the usage tracking system.

The system is no longer just a widget lookup utility. It is now a reporting system for:

- plugin audits
- widget inventory
- widget usage reporting
- cross-environment tracking across AWS multisites and WP Engine single-site installs

## Product Definition

The tracking system consists of two layers:

1. a remote collector running on inspected WordPress sites
2. a local reporting layer that queries those collectors and generates reports

Current remote collector:
- `uu-usage-tracker`

Current local reporting layer:
- CLI scripts in `uu-widget-tracker-dashboard/tools`

## Current-State Requirements

### R1. Track known items on AWS multisites
The system must be able to track known standalone plugins, widget bundles, and classic widgets across the AWS multisite environments.

### R2. Return page-level usage when available
The system must return exact matched page URLs when the tracked signal is page-resolvable.

### R3. Return activation footprint
The system must report activation information across scanned blogs/sites even when exact page matches are not found.

### R4. Support multisite scanning
The system must scan all blogs in a multisite network from one remote endpoint.

### R5. Support multiple output shapes
The system must support:

- summary reports
- matched URL reports
- blog activation detail reports

### R6. Support manual and bundled definitions
The system must support both:

- bundled plugin-managed definitions
- local/custom definitions where needed

## Expanded Requirements

### R7. Track widgets as first-class items
The system must treat widgets as a first-class reporting unit, not only as plugin-adjacent items.

### R8. Discover unknown widget families
The system must discover widget classes and widget bundles that were not manually predefined, including widgets outside `uu-so-widgets`.

### R9. Build a canonical widget registry
The system must maintain a canonical normalized widget list so reporting does not drift as discovery expands.

### R10. Track standalone plugins and widgets in one system
The system must continue to support both:

- standalone plugins with front-end output
- widgets inside larger widget bundles

### R11. Support AWS multisites and WP Engine single sites
The system must work across both:

- multisite networks on AWS
- single-site installs on WP Engine

The WP Engine site inventory should be derivable programmatically from the WP Engine Hosting Platform API so production site lists do not need to be maintained manually.

### R12. Support forward-looking widget monitoring
The system must support ongoing widget reporting for modern production widgets, not only decommission audits.

### R13. Minimize production risk
The tracking system must remain isolated from active widget rendering as much as possible. It must not require putting tracker logic back into `uu-so-widgets`.

### R14. Be deployable at scale
The remote collector must be deployable to a large number of production sites, including approximately 100 WP Engine production installs.

### R15. Keep reporting local and transparent
The reporting/execution layer must remain runnable locally so runs can be inspected, logged, and debugged outside wp-admin.

### R16. Differentiate discovery, registry, and usage
The system must clearly separate:

- discovery outputs
- canonical registry outputs
- usage reports

### R17. Use consistent naming
The system must use stable report names and file naming conventions so future runs are unambiguous.

### R18. Differentiate standalone plugins from widgets
The system must distinguish between:

- standalone plugins with front-end output
- widgets that live inside plugins or widget bundles

The naming should reflect that widgets are contained within plugins, but widgets are still a separate reporting unit.

### R19. Provide an onboarding path for new U of U-built components
The system must provide a lightweight, repeatable onboarding path for new U of U-built widgets and standalone plugins so newly created components can be tracked without ad hoc decisions each time.

### R20. Separate shared definitions from environment-specific definitions
The system must support a definition model where:

- globally safe definitions can be loaded across AWS and WP Engine
- environment-specific definitions can remain isolated to the environments where they are trustworthy

## Functional Requirements

### FR1. Remote item catalog
The remote collector must provide a trackable item catalog.

Current endpoint:
- `GET /wp-json/uu-usage-tracker/v1/items`

### FR2. Remote usage lookup
The remote collector must provide usage results for a specific item.

Current endpoint:
- `GET /wp-json/uu-usage-tracker/v1/usage?item=<slug>`

### FR3. Remote widget discovery
The remote collector must provide discovery for:

- SiteOrigin widget classes
- classic widget IDs
- content markers

Current endpoints:
- `GET /wp-json/uu-usage-tracker/v1/discovery/siteorigin-classes`
- `GET /wp-json/uu-usage-tracker/v1/discovery/classic-widget-ids`
- `GET /wp-json/uu-usage-tracker/v1/discovery/content-markers`

### FR4. Local plugin reporting
The local layer must generate:

- plugin summary report
- plugin matched URL report
- plugin blog activation detail report

### FR5. Local widget reporting
The local layer must generate:

- widget inventory report
- widget registry report
- widget summary report
- widget matched URL report
- widget blog activation detail report

### FR5a. WP Engine site inventory generation
The local layer must be able to generate a WP Engine production site map from the WP Engine API so the reporting scripts can target current production installs directly.

Minimum expectations:

- authenticate with WP Engine API credentials
- list candidate sites/installs
- filter to production environments
- prefer live production domains when available
- write a JSON map file compatible with existing reporting scripts

### FR6. Confidence and action scoring
Summary reports must provide confidence and action fields so the output is decision-ready.

### FR7. Support trackability onboarding for new components
The system must define how new U of U-built components become trackable.

Minimum onboarding expectations:

- if the component is a widget bundle, it must expose enough metadata for widget tracking
- if the component is a standalone plugin with front-end output, it must have a tracker definition or registration path
- the onboarding path should prefer centralized definitions in `uu-usage-tracker` when possible
- the onboarding path should place globally safe definitions in shared/global tracker definitions and reserve environment-specific packs for local-only signals
- if a component has unusual runtime behavior, it should add a stronger signal or marker so reporting can remain accurate

## Non-Functional Requirements

### NFR1. Safety
The system should avoid coupling tracking code to critical widget rendering paths.

### NFR2. Maintainability
The system should centralize shared reporting logic rather than duplicating behavior across many scripts.

### NFR3. Performance
The system must remain practical to run across many tracked items and environments. Current long-running widget summary behavior indicates future batching/caching improvements are needed.

### NFR4. Extensibility
The data model must accommodate:

- additional widget bundles
- future plugin types
- additional hosting environments

### NFR5. Explainability
The output should make clear whether a row represents:

- page-level usage
- blog activation
- discovery-only inventory

## Out of Scope For Now

- staging environment rollout
- full front-end crawling of every page
- moving tracker logic back into `uu-so-widgets`
- using the parent theme as the permanent home of the tracker logic

## Requirement Summary

The system should remain one unified tracking/reporting architecture with:

- `uu-usage-tracker` as the remote collector
- local scripts as the reporting layer
- support for both plugins and widgets
- support for both AWS multisites and WP Engine single-site installs
- clear separation between discovery, registry, and usage reporting
- a defined onboarding path for future U of U-built plugins and widgets
