# ZBF-Migrations

ZBF-Migrations is a simple PHP migration tool designed as a Composer package. It provides a lightweight way to manage database migrations using raw SQL files.

## Installation

Since this package is not published on Packagist, you need to manually add the repository to your `composer.json` file before installing it.

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/zborovo-sk/zbf-migrations.git"
        }
    ],
    "require": {
        "zborovo-sk/zbf-migrations": "dev-main"
    }
}
```

Run Composer to install dependencies:

```sh
composer install
```

## Usage

Once installed, you can use the `Migrator` class to manage your database migrations.

### Basic Example

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use ZborovoSK\ZBFMigrations\Migrator;
use PDO;

$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'password');
$migrator = new Migrator($pdo, 'migrations', __DIR__ . '/../migrations');

$migrator->migrate();
```

### How It Works

1. The `Migrator` class takes a `PDO` instance, the name of the migrations table, and the path to the migrations directory.
2. It checks if the migrations table exists and creates it if necessary.
3. It scans the migrations directory for new SQL migration files.
4. Each migration file is executed in a transaction.
5. After successful execution, the migration is recorded in the database to prevent duplicate execution.

## Directory Structure

```
project-root/
│── migrations/
│   ├── 20240201_create_users_table.sql
│   ├── 20240202_add_email_to_users.sql
│── src/
│   ├── Migrator.php
│── public/
│   ├── migrate.php
│── vendor/
│── composer.json
```

## Limitations

- **No Abstraction**: Migrations are raw SQL files, meaning they are database-specific.
- **No Rollback (Down Migrations)**: Once a migration is applied, it cannot be undone automatically.

## License

This project is licensed under the GPL-3.0 License.

