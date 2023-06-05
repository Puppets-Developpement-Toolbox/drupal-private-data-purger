# drupal_private_data_purger
**GPDR** friendly module that purges the private data records in your database 



# Installation 
```
composer require drupal/private_data_purge
```

```
drush en drupal/private_data_purge
```

```
drush cex
```
**Customize** your config/sync/private_data_purger.settings.yml according to [/config/install/private_data_purger.settings.yml](https://github.com/Puppets-Developpement-Toolbox/drupal-private-data-purger/blob/main/config/install/private_data_purger.settings.yml)

```
drush cim
```

# Usage. 
This modules includes a cron to automatically stay up to date with your policy.
