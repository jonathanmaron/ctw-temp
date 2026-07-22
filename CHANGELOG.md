# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
