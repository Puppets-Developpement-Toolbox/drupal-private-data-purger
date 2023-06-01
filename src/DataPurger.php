<?php

namespace Drupal\private_data_purger;

//import namespace of drupal logger
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * DataPurger service.
 */
class DataPurger
{
  //declare a private int property 
  private $dry = true;

  /**
   * I came here to purge data and chew bubblegum... and I'm all out of bubblegum.
   */

  public function purgeData(string $arg = "dry-run")
  {
    $this->isDryrun($arg);
    $connection = \Drupal::service('database');
    $availableEntities = \Drupal::entityTypeManager()->getDefinitions();
    $config = \Drupal::config('private_data_purger.settings');

    //try to get data from config file, if not throw an exception
    if ($config->get('data') === null) {
      throw new \Exception('No data to purge');
    }
    foreach ($config->get()['data'] as $dataName => $dataConfig) {
      if (!array_key_exists($dataName, $availableEntities) && !array_key_exists($dataConfig['record_type'], $availableEntities) && !$connection->schema()->tableExists($dataConfig['record_name'])) {
        throw new \Exception('Config record types not found in the database');
      }
      $this->handler($dataName, $dataConfig);
    }
  }
  private function handler(string $dataName, array $dataConfig)
  {
    $created =  strtotime('- ' . $dataConfig['created']);
    //cast camel case string to camelCase
    $record_type = lcfirst(str_replace('_', '', ucwords($dataConfig['record_type'], '_')));
    $functionName = 'purge' . $record_type;
    $functionName($dataName, $dataConfig, $created);
  }

  public function purgeWebformSubmission(string $dataName, array $dataConfig, int $created)
  {
    $webform  = \Drupal\webform\Entity\Webform::load('contact');;
    if ($webform->hasSubmissions()) {
      $result = \Drupal::entityQuery('webform_submission')
        ->condition("created", $created, "<")
        ->condition('webform_id', $dataName)
        ->accessCheck(FALSE)
        ->execute();
      $this->deleteEntities($dataConfig, $result);
    }
  }

  public function purgeClassicRecord(string $dataName, array $dataConfig, int $created)
  {
    $result = \Drupal::entityQuery($dataName)
      ->condition("created", $created, "<")
      ->accessCheck(FALSE)
      ->execute();
    $this->deleteEntities($dataConfig, $result);
  }

  public function purgeSqlRecord(string $dataName, array $dataConfig, string | int $created)
  {
    // declare a connection variable typed as database service
    /** @var use Drupal\Core\Database\Connection $connection */
    $connection = \Drupal::service('database');
    //drupal check if table exists
    $query = $connection->delete($dataName);

    if ($dataConfig['field_type'] === "timestamp") {
      $query->condition($dataConfig['field_name'], $created, "<");
    } else {
      $query->condition($dataConfig['field_name'], date('Y-m-d', $created), '<');
    }
    $count = 11;
    !$this->dry ?? $count = $query->execute();
    \Drupal::logger('private_data_purger')->notice($count . ' records of ' . $dataConfig['record_name'] . '  deleted. ');
  }

  public function getConfig()
  {
    $config = \Drupal::config('private_data_purger.settings');
    return $config;
  }


  private function isDryrun(string $arg): void
  {
    if ($arg === "dry-run") {
      $this->dry = true;
    }
    if ($arg === "wet-run") {
      $this->dry = false;
    }
  }

  public function deleteEntities(array $dataConfig, array $ids)
  {
    \Drupal::logger('private_data_purger')->notice(count($ids) . ' records of ' . $dataConfig['record_name'] . '  will be deleted. ');
    foreach ($ids as $id) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage($dataConfig['record_name']);
      /** @var Drupal\node\Entity $node */
      $node = $storage_handler->load($id);
      // Drupal get node's creation date formatted to dd/mm/yyyy
      $date = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd/m/Y');

      //dump('Node of type ' . $dataName . ' with id ' . $id . ' created on ' . $date . ' will be deleted.');
      !$this->dry ?? $storage_handler->delete([$node]);
    }
  }
}
