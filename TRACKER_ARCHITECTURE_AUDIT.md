# Tracker Architecture Audit

This document audits the current implementation against the requirements in:

- `TRACKER_REQUIREMENTS.md`

## Overall Assessment

The current architecture is directionally correct and should be extended rather than replaced.

The strongest architectural decision already made is the split between:

- remote collection in `uu-usage-tracker`
- local reporting via CLI scripts

That split aligns well with the expanded requirements and should remain the core model.

## What Is Working Well

### 1. Remote/local separation
Status:
- strong

Why:
- keeps execution and debugging local
- avoids running heavy report generation in wp-admin
- supports transparent reruns and inspection

Requirements supported:
- `R15`
- `NFR2`
- `NFR5`

### 2. `uu-usage-tracker` as a standalone remote collector
Status:
- strong

Why:
- avoids putting tracking logic back into `uu-so-widgets`
- supports both plugin and widget reporting
- supports multisite and single-site architecture in principle

Requirements supported:
- `R10`
- `R11`
- `R13`
- `FR1`
- `FR2`
- `FR3`

### 3. Existing AWS standalone plugin audit workflow
Status:
- strong

Why:
- summary, matched URL, and blog activation reports are working
- report naming is now explicit
- confidence/action scoring exists

Requirements supported:
- `R1`
- `R2`
- `R3`
- `R5`
- `R17`
- `FR4`
- `FR6`

### 4. Widget workflow foundation
Status:
- promising

Why:
- widget inventory, registry, and reporting scripts now exist
- widget summary and detail reports reconcile cleanly
- current outputs show the model works beyond `uu-so-widgets`

Requirements supported:
- `R7`
- `R8`
- `R9`
- `R12`
- `FR5`

## Where The Current Architecture Is Showing Strain

### 1. Too much duplicated reporting logic across scripts
Severity:
- medium

Observation:
- plugin and widget scripts repeat the same patterns:
  - parse inputs
  - fetch remote `usage`
  - compute match count
  - compute activation labels
  - compute confidence/action
  - flatten rows into CSV

Risk:
- behavior drift between scripts
- harder maintenance as more reports are added

Requirements affected:
- `NFR2`
- `NFR4`

Recommendation:
- refactor shared logic into reusable helpers in `tools/`
- centralize:
  - usage fetch/caching
  - activation formatting
  - match-source formatting
  - confidence/action policies

### 2. Widget summary performance is expensive
Severity:
- high for future scale

Observation:
- the widget summary report took several minutes across only three AWS environments
- the current model performs many per-item remote usage lookups

Risk:
- poor runtime when expanded to ~100 WP Engine production sites
- repeated recomputation across summary/matched URL/activation reports

Requirements affected:
- `R14`
- `NFR3`

Recommendation:
- introduce a shared local cache layer for per-run remote payloads
- avoid recomputing the same `usage` payload in separate scripts during the same reporting session
- consider generating summary/matched URL/blog activation from one intermediate local dataset

### 3. Discovery is still only partly enriched
Severity:
- medium

Observation:
- current widget inventory mostly comes from the item catalog
- only a small number of rows are `discovered_only`
- discovery still lacks strong plugin/bundle context for some widgets

Risk:
- inventory remains incomplete or ambiguous as more widget bundles are discovered
- registry family inference is doing too much guesswork

Requirements affected:
- `R8`
- `R9`
- `FR3`
- `NFR4`

Recommendation:
- add a richer remote widget discovery endpoint
- include:
  - widget class
  - widget label if known
  - plugin/bundle context if detectable
  - whether discovered in registration, saved content, or both

### 4. Registry family inference is heuristic
Severity:
- medium

Observation:
- bundle/family assignment is currently inferred from slug/class naming patterns

Risk:
- false grouping or blank grouping for newly discovered widget families
- manual cleanup burden rises as scope expands

Requirements affected:
- `R8`
- `R9`
- `NFR4`

Recommendation:
- move from heuristic-only family inference to:
  - discovery metadata when available
  - optional manual override mapping file
  - registry review workflow for ambiguous rows

### 5. WP Engine rollout path is not yet operationalized
Severity:
- high

Observation:
- the architecture supports single-site installs conceptually
- but deployment, activation, and environment list management are not yet solved
- WP Engine production targets are still assumed to come from a manually maintained site list

Risk:
- system remains AWS-heavy in practice
- WP Engine expansion could become manual and inconsistent

Requirements affected:
- `R11`
- `R14`

Recommendation:
- create a dedicated WP Engine rollout plan
- keep `uu-usage-tracker` as a standalone plugin
- do not move tracking logic into `uu-so-widgets`
- do not make the parent theme the permanent tracker home
- if needed, use the parent theme only as a temporary deployment/bootstrap helper
- add a local WP Engine inventory script that builds the production site map from the WP Engine Hosting Platform API

## Requirements Coverage Summary

### Well covered now
- `R1`
- `R2`
- `R3`
- `R4`
- `R5`
- `R7`
- `R10`
- `R12`
- `R13`
- `R15`
- `R16`
- `R17`

### Partially covered now
- `R6`
- `R8`
- `R9`
- `R11`
- `R14`

### Not yet fully operationalized
- large-scale WP Engine rollout support
- enriched remote widget discovery with bundle/plugin context
- maintainable shared reporting abstraction
- performance strategy for large environment counts

## Recommended Refactor Work

### Refactor 1: Shared local usage payload cache
Priority:
- high

Purpose:
- reduce repeated remote usage lookups across summary and detail reports

Suggested direction:
- add one shared helper that stores fetched `usage` payloads by:
  - environment
  - canonical slug

### Refactor 2: Shared report computation helpers
Priority:
- high

Purpose:
- prevent logic drift across plugin and widget report scripts

Suggested shared concerns:
- match count
- activation label
- match source formatting
- confidence calculation
- action calculation

### Refactor 3: Enriched remote widget discovery
Priority:
- medium-high

Purpose:
- improve inventory quality before WP Engine expansion

Suggested endpoint:
- `GET /wp-json/uu-usage-tracker/v1/discovery/widgets`

### Refactor 4: Registry override support
Priority:
- medium

Purpose:
- allow manual cleanup for ambiguous bundle/family mappings

Suggested artifact:
- local override CSV or JSON keyed by widget class / canonical slug

### Refactor 5: Batch-oriented report generation
Priority:
- medium

Purpose:
- improve runtime for WP Engine scale

Suggested direction:
- create one intermediate snapshot per reporting run
- derive summary and detail reports from that snapshot

## Recommended Decision Before WP Engine Expansion

Proceed with the current architecture, but do not expand blindly to WP Engine until these steps are addressed:

1. consolidate shared reporting logic
2. add or improve widget discovery metadata
3. define the WP Engine deployment strategy for `uu-usage-tracker`
4. decide whether to add per-run caching/snapshot generation

## Final Recommendation

Do **not** replace the current system.

Do:

- keep `uu-usage-tracker` as the remote collector
- keep local scripts as the reporting layer
- refactor the script layer so it is more shared and scalable
- enrich discovery before broad WP Engine rollout
- treat WP Engine support as a deployment and scaling phase, not a reason to build a new tracking system
