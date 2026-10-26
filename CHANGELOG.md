# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.3.0 — 2026-10-26

### Changed

- Tests and tooling (`tests/`, `phpunit.xml.dist`, `phpstan.neon.dist`) are no longer in Composer
  archives, so they stay out of production `vendor/` folders (FE-17).

## 2.2.0 — 2026-10-26

### Added

- `CacheClearerInterface` and `CacheWarmerInterface`, collected by the tags `CLEARER_TAG` and
  `WARMER_TAG`, for `cache:clear` and `cache:warm` (FE-18).
- `DeploymentChecks`: runs the built-in and tagged deployment checks, for the startup and the
  `doctor` command (FE-18).
- `ApplicationBuilder::recompileStaleContainerInConsole()`: the console rebuilds a compiled
  container older than the code, so `make:*` commands do not leave it stale.
- `Application::containerCache()` and `compileContainer()`, used by the cache commands.

## 2.1.0 — 2026-10-26

### Added

- Deployment checks after boot (FE-24): debug on a public `app.url`, debug in production,
  placeholder secrets in production (`app.secret_keys`), and an unwritable container cache
  directory. Applications add checks tagged `core.deployment_checks`; a fatal result throws
  `UnsafeDeploymentException`. `app.deployment_checks: false` disables them.
- `ApplicationBuilder::withConfiguration()`: a last configuration layer, above environment
  variables, with nested or dotted keys (FE-09).
- The `testing` environment loads `.env.testing` instead of `.env` when it exists (FE-09).
- `ApplicationBuilder::basePath()`.

### Changed

- `ApplicationException` is no longer final, so `UnsafeDeploymentException` can extend it.

## 2.0.0 — 2026-10-26

The final 2.0 release. No changes since 2.0.0-rc.1.

## 2.0.0-rc.1 — 2026-10-25

### Changed

- The container cache file now returns its class name, so a cached boot is one include served by
  OPcache instead of a file read. Before, reading the file cost more than the compiled container
  saved on Windows. Cache files written by earlier versions still load.

## 2.0.0-beta.1 — 2026-10-25

### Added

- `Correlation`: one helper to generate, validate and propagate correlation ids for HTTP and
  console.

## 2.0.0-alpha.3 — 2026-10-24

First release of the 2.0 line. Compose an application from service providers and business modules, then boot it. Requires PHP 8.5 or later.

Documentation: [the core guide](https://github.com/mjnable/zana-docs/blob/main/guides/core.md).
