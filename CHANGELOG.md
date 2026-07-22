# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0]

### Added

- `Temp` now accepts a list for the constructor's `levelN` parameter, nesting
  one directory per element (e.g. `['page-cache', 'v2']` builds
  `.../page-cache/v2`). Each element is sanitized independently, and an empty or
  unsafe element is rejected with `InvalidPathSegmentException`. Passing a single
  string still yields one directory, as before.

### Changed

- Renamed the `Temp` constructor's first parameter from `appId` to `id`.
  Positional calls are unaffected; callers using the `appId:` named argument
  must switch to `id:`.
- Renamed the `Temp` constructor's second parameter from `level2` to `levelN`
  to reflect that it can now nest arbitrarily deep. Positional calls are
  unaffected; callers using the `level2:` named argument must switch to `levelN:`.
- Expanded `README.md` (Features, Requirements, Installation, Testing and
  Contributing sections) and the `bin/` demo scripts to cover the list form.

## [1.0.0]

### Added

- Initial release of `ctw/ctw-temp`.
- `Ctw\Temp\Temp`: zero-package-dependency generator for per-user,
  per-application temporary directories of the form
  `<basePath>[/<hash>]/<appId>[/<level2>]`, defaulting `basePath` to
  `/var/tmp/php` (real disk) instead of the `tmpfs`-backed `sys_get_temp_dir()`.
- `getPath()`, plus directory methods `createPath()`, `deletePath()`,
  `existsPath()`, and `clearPath()` and file methods `createFile()` and
  `deleteFile()`. The `Path`/`File` qualifier makes it clear whether a method
  operates on the directory or on a file within it.
- `Ctw\Temp\Posix`: resolves the sanitized current user/group for the
  `<hash>` segment via the POSIX extension; injectable into `Temp`.
- Exception hierarchy under `Ctw\Temp\Exception\ExceptionInterface`: base
  classes `RuntimeException` and `InvalidArgumentException`, plus the specific
  types `DirectoryNotCreatedException`, `DirectoryNotWritableException`,
  `FileNotCreatedException`, `PathTraversalException`, `PosixUnavailableException`,
  `InvalidBasePathException`, and `InvalidPathSegmentException`.
