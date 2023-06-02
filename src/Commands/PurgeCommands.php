<?php

namespace Drupal\private_data_purger\Commands;

use Drush\Commands\DrushCommands;
use Drupal\private_data_purger\DataPurger;

class PurgeCommands extends DrushCommands
{
  function __construct(protected DataPurger $dataPurger)
  {
  }
  /**
   *
   * @command private_data_purger:simple
   */
  public function callDataPurge($arg = "false")
  {
    $this->dataPurger->purgeData($arg);
  }
}
