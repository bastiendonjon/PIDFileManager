# PIDFileManager
PID Manager for prevent overlapping in cron task

### Requirements
- PHP >= 5.4.0

### Composer installation
You must modify your `composer.json` file and run `composer update` to include the latest version of the package in your project:

```json
"require": {
    "bastiendonjon/PIDFileManager": "1.0.*"
}
```

Or you can run the `composer require` command from your terminal:

```
composer require bastiendonjon/PIDFileManager:1.0.x
```

## Usage
```php
// Usage in simple task :
$elem = new PIDFileManager('myProcessName', storage_path());
$elem->start();
*
// Usage in daemon task :
$elem = new PIDFileManager('myProcessName', storage_path());
$elem->start();
while(true) {
    sleep(1)
    $elem->oneLoop();
}
```