# Component Onboarding

This document defines the lightweight onboarding rules for new U of U-built widgets and standalone plugins.

The goal is to make new components trackable without inventing a new process each time.

## Core Rule

Every new U of U-built front-end component should have a clear path into the tracking system.

There are two main component types:

- widgets
- standalone plugins

Widgets live inside plugins or widget bundles, but widgets are still tracked as their own reporting unit.

## Widget Onboarding Rule

If the new component is a widget or widget bundle:

1. it should expose enough metadata for widget tracking
2. it should be discoverable by class, registration metadata, or another strong identifier
3. it should be added to the centralized tracking path so it appears in widget inventory and widget usage reports

Preferred implementation path:

- register the widget cleanly so the tracker can discover it
- add or expose a stable widget class name
- add a centralized definition in `uu-usage-tracker` when needed

Best-case outcome:

- the widget shows up in widget inventory automatically
- the widget can be normalized into the widget registry
- the widget can be counted in summary, matched URL, and blog activation detail reports

## Standalone Plugin Onboarding Rule

If the new component is a standalone plugin with front-end output:

1. it should have a tracker definition or registration path
2. it should expose at least one strong, durable signal for usage detection
3. that signal should be added to `uu-usage-tracker` definitions or another centralized mechanism

Preferred signals:

- shortcode
- page template
- SiteOrigin class
- classic widget ID
- plugin-specific content marker
- stable meta key/value

If the plugin has unusual runtime behavior:

- add a stronger marker or signal so reporting remains reliable

## Centralization Rule

Prefer centralized tracking definitions in `uu-usage-tracker` whenever possible.

Definition placement rule:

- if a definition is precise and safe across environments, put it in a shared/global definition file
- if a definition is only reliable for one network, one legacy environment, or one site family, keep it in an environment-specific pack

Use local one-off overrides only when:

- the component is environment-specific
- the signal is temporary
- or a shared centralized definition would create noise

## Safety Rule

Do not put new tracking logic back into `uu-so-widgets` just because it is convenient.

Preferred order:

1. `uu-usage-tracker` as the remote collector
2. local scripts as the reporting layer
3. centralized definitions in the tracker

Only use theme-level bootstrap or temporary deployment helpers if rollout constraints require it.

## Practical Checklist

When a new U of U-built component is created, ask:

1. Is this a widget or a standalone plugin?
2. What is the strongest stable signal we can track?
3. Does it need a centralized definition in `uu-usage-tracker`?
4. Will it appear automatically in widget discovery, or does it need explicit registration?
5. Does it need an extra marker to make page-level reporting reliable?

## Success Condition

A new component is considered properly onboarded when:

- it can be discovered
- it can be named consistently
- it can be reported in the appropriate workflow
- and it does not require a custom one-off investigation every time someone wants usage data
