# PHP CLI Password Manager

PHP CLI Password Manager is a small command line tool written in PHP 8.4. It stores all credentials in a single encrypted vault file and is designed for local use on your machine.

The application is used only through an interactive console menu. All operations (init, add, get, update, delete) are done via prompts, not by passing flags directly.

## Features

- Encrypted single-file vault (`vault.dat`)
- Master password hashed with bcrypt
- Vault key derived with libsodium Argon2id (medium settings)
- Authenticated encryption with XChaCha20-Poly1305 (libsodium)
- Strict input validation for all fields
- File permission checks for vault and log files
- Process lock to prevent running multiple instances
- Fully interactive console UI (menu-based)
- Optional Linux clipboard integration via `xclip` (passwords copied, never printed)

## Requirements

- PHP 8.4 CLI - [PHP 8.4 Installation](https://www.php.net/downloads.php?usage=web&os=linux&osvariant=linux-debian&version=8.4)
- PHP extensions:
    - `ext-sodium` (included automatically in `php8.4-common`)
    - `ext-pcntl` (included automatically in `php8.4-cli`)
- Composer (for installing PHP dependencies) - [Get Composer](https://getcomposer.org/download/)
- Unix-like OS (Linux recommended, tested on Ubuntu 24.04)

### Checking that required PHP extensions are installed

Both required extensions come bundled with base PHP 8.4 packages:

| PHP Extension | Provided By |
|---------------|-------------|
| sodium        | php8.4-common |
| pcntl         | php8.4-cli |

To verify that both are installed and active:

```bash
php -m | grep -E "sodium|pcntl"
```

## Frameworks and Libraries

This project uses:

- Symfony Console (`symfony/console`)
- Symfony Filesystem (`symfony/filesystem`)
- Monolog (`monolog/monolog`)
- Ramsey UUID (`ramsey/uuid`)
- Respect Validation (`respect/validation`)
- PHP extensions `ext-sodium` and `ext-pcntl`

## Installation

1. Download or clone this project to your machine.

2. Install PHP dependencies with Composer:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. (Optional) Make the main CLI script executable:

   ```bash
   chmod +x bin/pm
   ```

## Configuration

By default, all vault data and logs are stored under the project directory:

- Vault directory: `<project_root>/vaults`
- Vault file: `vault.dat`
- Log file: `pm.log`
- Lock file: `vault.lock`

The application will create the directory if it does not exist and will enforce secure permissions (0700 for directories, 0600 for files).

## Usage (Interactive Only)

All actions are done inside the interactive menu.

### Start the application

```bash
php bin/pm
# or, if executable:
./bin/pm
```

You will see a numbered list of available commands like:

- `pm init` - initialize a new vault
- `pm add` - add a new credential
- `pm get` - view a credential
- `pm update` - update a credential
- `pm delete` - delete a credential

You choose commands by entering their number. The application then asks for all required data step by step.

### Initialize a new vault

1. Start the app:

   ```bash
   php bin/pm
   ```

2. In the menu, select the command to initialize a vault (for example, the line with `pm init`).
3. Set a master password when asked and repeat it to confirm.
4. If a vault already exists, the program will:
    - Warn you that the old vault and logs will be permanently deleted.
    - Ask you to confirm the current master password before purging.

If confirmation fails, the old vault is not deleted.

### Add a credential

1. Start the app and open the menu.
2. Choose the `add` command from the list.
3. The program will ask for:
    - Service
    - Username
    - Optional note
    - Password (hidden input)

All fields are validated. Errors are shown directly in the console, and you can try again.

### Get a credential

1. Start the app and open the menu.
2. Choose the `get` command.
3. The program will:
    - Ask for the master password to unlock the vault.
    - Show available services.
    - Let you pick a service and, if needed, a specific entry.

The password is never printed in clear text. Instead, a placeholder like `[hidden]` is shown. On supported Linux systems with `xclip`, the password is copied to the clipboard.

### Update a credential

1. Start the app and open the menu.
2. Choose the `update` command.
3. The program will:
    - Ask for the master password.
    - Show services and entries so you can pick one.
    - Display current data (password still hidden).
    - Offer a small menu to edit:
        - Service
        - Username
        - Password
        - Note

For password changes, the new password is entered through a hidden prompt. All inputs are validated.

### Delete a credential

1. Start the app and open the menu.
2. Choose the `delete` command.
3. The program will:
    - Ask for the master password.
    - Let you pick a service and an entry.
    - Show the selected entry and ask you to confirm deletion.

If you do not confirm, nothing is removed.

## Security Notes

- Vault data is encrypted with libsodium using XChaCha20-Poly1305.
- The vault key is derived from the master password using Argon2id with medium settings.
- The master password is stored only as a bcrypt hash.
- Vault data and logs are stored with strict file permissions.
- A process lock file prevents multiple instances from modifying the vault at the same time.
- Passwords and other secrets are wiped from memory when possible using `sodium_memzero`.

This tool is intended for local use and learning. Always keep backups of your vault and use a strong master password.
