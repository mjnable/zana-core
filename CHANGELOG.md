# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.35.0 — 2026-11-21

### Security

- The compiled container and the configuration cache are signed (a documented limit until now: they are PHP files the
  application includes, so code written to `var/cache` by anything but the application ran on the next request).
  When `.env`, an `APP_*` variable or `withConfiguration()` sets `security.secret`:
  - each file carries an HMAC-SHA256 of its contents and of its kind, with a key derived from the secret;
  - a file is read once, checked, and the bytes checked are evaluated, so a file swapped after the check does not run.
    `includeCompiledCaches()` checks, then includes, for OPcache, where the web server cannot write to the cache
    directory;
  - a file without a signature (written by 2.34, or before the secret was set) is treated as missing and rewritten;
  - a file with another signature (changed, or written with another secret) stops the boot in production, naming the
    file; elsewhere, and in the console with `recompileStaleContainerInConsole()`, it is rewritten, so `cache:warm`
    runs after the secret changes.
  A secret set only in a configuration file signs nothing: the configuration cache cannot hold the key that checks it.
- `Application::compiledCachesSigned()` says whether the files are signed.

## 2.21.0 — 2026-11-14

### Added

- `SecurityRules` for the security rules of `check` (FE-68, ADR-033): `severity()` is an error with
  `security.strict: true`, and a warning otherwise, so an upgrade never fails a project's CI. Strict is off by default;
  the skeletons turn it on.
- `Finding` takes `security: true` for a security rule, and its array form gains `security`; `release:build` refuses to
  build on such an error.

## 2.20.0 — 2026-11-14

### Added

- `ApplicationBuilder::withoutDotEnv()` reads no `.env` file (FE-73), for a test that builds another environment:
  `.env.testing` is read only in the testing environment, so a test building production read the developer's `.env`
  and gave another result on another machine. Variables already in the process environment still count.

### Changed

- `withContainerCache()` caches the compiled container in production only (FE-70). Elsewhere the container is built on
  each request, so a changed constructor works at once: the cache was checked against the registrations, not the
  constructors, and a stale one failed in the browser while the tests passed. `withContainerCache($file, always: true)`
  caches in every environment, as before.

## 2.11.0 — 2026-11-01

### Added

- `ConsoleScript`: the script that started the console (recorded by the console kernel), so hints can
  say `php bin/zana migrate`, or "the console command `migrate`" where the script is unknown (FE-54,
  ADR-026).

## 2.7.0 — 2026-10-31

### Added

- `Zana\Core\Inspection`: `ApplicationCheck` (tag `core.application_checks`) and `InspectionSection`
  (tag `core.inspection_sections`), for packages and applications to add checks to the console's
  `check` and sections to its `inspect`; `Finding` and `Severity` describe one problem, with an id, a
  file and a hint (ADR-023).

## 2.4.0 — 2026-10-26

### Added

- `ApplicationBuilder::withConfigurationCache()`: in production, the configuration files' values and
  the module list come from one cache file, written on the first boot; `.env`, `APP_*` variables and
  overrides still apply on top (ADR-019). `compileConfigurationCache()` writes it for `cache:warm`;
  `Application::configurationCache()` and `compileConfigurationCache()` expose it.
- `DeploymentChecks::run()` takes the configuration cache, and warns when it cannot be written.

### Changed

- The startup deployment checks no longer check for a cache file the boot has just loaded or
  written.
- `OriginServiceCollection` forwards `uses()` (FE-40). Requires `mj-zana/container` ^2.4.

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
