## mongodb-migrations-bundle

This bundle integrates the [DoesntMattr MongoDB Migrations library](https://github.com/doesntmattr/mongodb-migrations).
into Symfony so that you can safely and quickly manage MongoDB migrations.

This is a new iteration which has the minimal integration into Symfony. At the moment it is not thought for external use.

Installation
============

Add the following to your composer.json file:

```json
{
    "require": {
        "graviton/mongodb-migrations-bundle": "~1.0"
    }
}
```

Install the libraries by running:

```bash
composer install
```

Be sure to enable the bundle in AppKernel.php by including the following:

```php
// app/AppKernel.php
public function registerBundles()
{
    $bundles = array(
        //...
        new Graviton\MigrationBundle\GravitonMigrationBundle(),
    );
}
```
