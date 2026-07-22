# Demo scripts

Runnable, heavily-commented examples for `ctw/ctw-temp`. Each script is
self-contained and safe to run repeatedly.

Run any of them with PHP directly:

```bash
php bin/01-path-model.php
php bin/02-directory-operations.php
php bin/03-file-operations.php
php bin/04-posix.php
php bin/05-exceptions.php
```

| Script | Shows |
|---|---|
| `01-path-model.php` | How each constructor argument maps to the path string (no disk I/O). |
| `02-directory-operations.php` | `createPath` / `existsPath` / `clearPath` / `deletePath`. |
| `03-file-operations.php` | `createFile` / `deleteFile` and the path-traversal guard. |
| `04-posix.php` | The `Posix` helper and where the `<hash>` segment comes from. |
| `05-exceptions.php` | Each exception type and the `ExceptionInterface` catch-all. |

Demos 02, 03 and 05 use a throwaway base directory under the system temp
directory and clean up after themselves, so they never touch the real
`/var/tmp/php` tree.

`autoload.php` locates Composer's autoloader — this package's own `vendor/` if
you ran `composer install` here, otherwise the host application's.
