# Changelog

All notable changes to `filament-logger` will be documented in this file.

## Unreleased

### Added

- Event, user (searchable) and date range filters, the latter with presets (today, yesterday, last 7/30 days, this month)
- Tabs on the list page, one per enabled log type, configurable with `tabs.enabled`
- `filters` config section (`causer_model`, `causer_search_column`, `options_cache_ttl`)
- Real tests for the filters and tabs (Pest + Livewire)
- A Filament panel plugin for registering the resource and configurable chart widgets
- A built-in 14-day activity trend chart that follows the list page filters
- Logout and failed-login access events
- Plugin installation, chart configuration and activity retention documentation

### Changed

- The type and subject type filters now allow selecting several values
- The type, event and subject type options now include the values already stored in the table, not only the configured ones; the subject type options also include `models.register`
- The single-day "Logged at" filter was replaced by a range filter (its `logged_at` field is now `from` / `until`)

## v1.2.0 - 2026-02-25

- upgrade to Filament v5

## v1.0.1 - 2025-08-25

- add columnSpanFull() to properties section

## v1.0.0 - 2025-08-21

### Updated

- Use translation key instead of config for navigation group
