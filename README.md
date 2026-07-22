# Package "ctw/ctw-temp"

Zero-dependency generator for per-user, per-application temporary files and directories  
on disk, for PHP 8.5+ applications.

## Features

- Builds temporary paths of the form `<basePath>[/<hash>]/<appId>[/<levelN>]`,
  defaulting to real disk (`/var/tmp/php`) instead of the RAM-backed
  `sys_get_temp_dir()`.
- Optional per-user/group `<hash>` segment isolates one user's files from
  another's on shared hosts.
- Provisions the tree in two permission tiers: a world-writable shared base and
  per-user directories beneath it.
- Atomically creates uniquely named files, with deletion guarded against path
  traversal outside the directory.
- Consistent `Path`/`File` method naming and a single exception interface for
  catching every failure mode.
- No package dependencies; requires only `ext-posix`.

## Introduction

### Why This Library Exists

Applications commonly derive a temporary working directory from
`sys_get_temp_dir()`. Since Debian 14, that location (`/tmp`) is a **`tmpfs`**
(RAM-backed) mount that fills up quickly under write-heavy workloads such as
image processing, PDF generation, or page caching.

`ctw/ctw-temp` moves temporary storage onto real disk (default
`/var/tmp/php`) and encapsulates the path-construction logic — previously
duplicated inline across the application — behind a single, tested class with
**no package dependencies**.

It replaces constructions such as:

```php
define('APP_PATH_TEMP', sprintf(
    '%s/%s_%s_temp',
    sys_get_temp_dir(),
    'www.example.com',
    hash('crc32b', 'www-data_www-data'),
));
mkdir(APP_PATH_TEMP, 0777, true);
```

with:

```php
use Ctw\Temp\Temp;

$temp = new Temp('www.example.com');     // base defaults to /var/tmp/php
define('APP_PATH_TEMP', $temp->createPath());
```

### The Path Model

```
<basePath>[/<hash>]/<appId>[/<levelN>]
```

For example:

```
/var/tmp/php/78b43994/www.example.com/page-cache
```

| Segment   | Meaning                                                       | Configurable | Default        | Required |
|-----------|--------------------------------------------------------------|--------------|----------------|----------|
| `basePath`| Base path holding the temporary tree                         | yes          | `/var/tmp/php` | –        |
| `hash`    | `crc32b` of the sanitized `<user>_<group>` (8 hex chars)     | on/off       | included       | –        |
| `appId`   | Application identifier or hostname                           | –            | –              | **yes**  |
| `levelN`  | Optional n-level directory, or a list of nested directories | yes      | omitted        | no       |

The `hash` segment isolates one user/group's files from another's on shared
hosts. It is derived from the process user and group via the POSIX extension
(encapsulated in `Ctw\Temp\Posix`, injectable into `Temp`), so
`ext-posix` is required.

## Requirements

- PHP 8.5+
- `ext-posix`

## Installation

```bash
composer require ctw/ctw-temp
```

## Usage

```php
use Ctw\Temp\Temp;

// Full constructor signature (only $appId is required):
$temp = new Temp(
    appId: 'www.example.com',
    levelN: 'page-cache',   // optional extra directory
    includeUserGroup: true, // include the per-user/group <hash> segment
    basePath: '/var/tmp/php', // default
);

$temp->getPath();       // '/var/tmp/php/78b43994/www.example.com/page-cache'

$temp->createPath();    // mkdir -p; throws RuntimeException if not writable
$temp->existsPath();    // bool

// Create and later delete a uniquely named file inside the directory:
$file = $temp->createFile('report', 'pdf'); // '/…/report-a1b2c3d4e5f6a7b8.pdf'
$temp->deleteFile($file);

$temp->clearPath();     // empty the directory, keep it (e.g. cache reset)
$temp->deletePath();    // recursively remove the directory and its contents
```

`$levelN` also accepts a list, which nests one directory per element:

```php
$temp = new Temp('www.example.com', ['page-cache', 'v2']);
$temp->getPath(); // '/var/tmp/php/78b43994/www.example.com/page-cache/v2'
```

### Public API

Method names carry a `Path` (directory) or `File` (file) qualifier so it is
always clear which is being operated on. In the source, directory operations
and file operations are grouped into separate, clearly marked sections.

| Method                                     | Operates on | Description                                                         |
|--------------------------------------------|-------------|---------------------------------------------------------------------|
| `getPath(): string`                        | –           | The assembled path (does not create it).                            |
| `createPath(): string`                     | directory   | Create the directory recursively; throws if it cannot be written.   |
| `deletePath(): bool`                       | directory   | Recursively delete the directory and its contents.                  |
| `existsPath(): bool`                        | directory   | Whether the directory currently exists.                             |
| `clearPath(): void`                        | directory   | Remove the directory's contents but keep the directory.             |
| `createFile(string $name, string $ext)`    | file        | Atomically create a uniquely named file; returns its absolute path. |
| `deleteFile(string $file): bool`           | file        | Delete a file inside the directory (refuses paths outside it).      |

### Errors

Every exception implements `Ctw\Temp\Exception\ExceptionInterface`, so a
single `catch (ExceptionInterface $e)` traps them all. Specific types let you
catch individual failure modes:

| Exception                       | Extends                    | Thrown when                                                    |
|---------------------------------|----------------------------|---------------------------------------------------------------|
| `InvalidBasePathException` | `InvalidArgumentException` | the configured base path is empty                            |
| `InvalidPathSegmentException`   | `InvalidArgumentException` | an `appId`/`levelN` segment is empty or unsafe                |
| `DirectoryNotCreatedException`  | `RuntimeException`         | a base or per-user directory cannot be created               |
| `DirectoryNotWritableException` | `RuntimeException`         | the temporary directory exists but is not writable           |
| `FileNotCreatedException`       | `RuntimeException`         | a unique file cannot be created                              |
| `PathTraversalException`        | `RuntimeException`         | a `deleteFile()` target resolves outside the directory       |
| `PosixUnavailableException`     | `RuntimeException`         | the POSIX extension is required but not loaded               |

`RuntimeException` and `InvalidArgumentException` (both in
`Ctw\Temp\Exception`) remain as intermediate base classes for broad catches.

## Permissions

`createPath()` provisions the tree in two tiers:

- the **shared base path** (`/var/tmp/php`) is created world-writable
  (`0777`, forced with `chmod()` so it survives the umask), like `/tmp`, so both
  a CLI/deploy user and the web server user (`www-data`) can create their own
  `<hash>` subtree beneath it;
- the **per-user directories** below it use `0755` (configurable via the
  `pathMode` constructor argument) and are owned by the creating user.

Whichever process runs first creates and opens up the base; the other then finds
it already in place. No manual provisioning step is required, though ops may
pre-create `/var/tmp/php` with mode `0777` if preferred.

## Testing

```bash
composer test     # PHPUnit test suite (with coverage)
composer qa       # Rector (dry-run), ECS, and PHPStan (max level)
composer qa-fix   # Apply Rector and ECS fixes, then run PHPStan
```

## Contributing

Create a feature branch, keep the code `declare(strict_types=1);` and PSR-12
compliant, run `composer qa` until it reports no issues, and open a merge
request.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Maintainer

Maintained by CTW. Report issues and propose changes on the project's
repository.
