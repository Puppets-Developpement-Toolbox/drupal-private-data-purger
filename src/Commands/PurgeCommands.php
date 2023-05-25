<?php

namespace Drupal\sodexo_private_data_purge\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drupal\sodexo_private_data_purge\DataPurger;


/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */

class PurgeCommands extends DrushCommands
{

  function __construct(protected DataPurger $dataPurger)
  {
  }
  /**
   * Command description here.
   *   Description
   *
   * @command sodexo_private_data_purge:simple
   * @aliases simple-newsletter-purge
   */
  public function callDataPurge($arg = "wet-run")
  {
    $this->dataPurger->purgeSomeEntity($arg);
  }
}
