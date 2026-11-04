# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `Subscriptions::subscribe()` writes a site's sign-up, with the consent its form asked for, to the newsletter
  add-on's outbox in the site's database. It withholds anyone suppressed for a complaint or an erasure.
- A `ConfigProvider` that wires it from `contenir_audience` and the add-on's own `extensions.<handle>` bindings and
  pepper, the keys the CMS add-on reads.
