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
  public $dry = false;

  /**
   * I came here to purge data and chew bubblegum... and I'm all out of bubblegum.
   */

  public function purgeData(string $arg = "dry-run")
  {
    if ($arg == "dry-run") {
      $this->dry = true;
    }
    $connection = \Drupal::service('database');
    $availableEntities = \Drupal::entityTypeManager()->getDefinitions();
    $config = \Drupal::config('private_data_purger.settings');

    //try to get data from config file, if not throw an exception
    if ($config->get('data') === null) {
      throw new \Exception('No data to purge');
    }
    foreach ($config->get()['data'] as $records => $dataConfig) {
      if (!array_key_exists($dataConfig['record_name'], $availableEntities) && !array_key_exists($dataConfig['record_type'], $availableEntities) && !$connection->schema()->tableExists($dataConfig['record_name'])) {
        throw new \Exception('Config record type' . $dataConfig['record_name'] . ' : ' . $dataConfig['record_type'] . ' not found in the database');
      }
      $this->handler($dataConfig['record_name'], $dataConfig);
    }
  }
  private function handler(string $dataName, array $dataConfig)
  {
    $ttl =  strtotime('- ' . $dataConfig['ttl']);
    //cast snake_case string to camelCase
    $record_type = str_replace('_', '', ucwords($dataConfig['record_type'], '_'));
    $functionName = 'purge' . ucfirst($record_type);

    $this->$functionName($dataName, $dataConfig, $ttl);
  }

  public function purgeWebformSubmission(string $dataName, array $dataConfig, int $ttl)
  {
    $webform  = \Drupal\webform\Entity\Webform::load($dataConfig['record_name']);;
    if ($webform->hasSubmissions()) {
      $result = \Drupal::entityQuery('webform_submission')
        ->condition("created", $ttl, "<")
        ->condition('webform_id', $dataName)
        ->accessCheck(FALSE)
        ->execute();
      $this->deleteEntities($dataConfig, $result);
    }
  }

  public function purgeClassicRecord(string $dataName, array $dataConfig, int $ttl)
  {
    $result = \Drupal::entityQuery($dataName)
      ->condition("created", $ttl, "<")
      ->accessCheck(FALSE)
      ->execute();
    $this->deleteEntities($dataConfig, $result);
  }

  public function purgeSqlRecord(string $dataName, array $dataConfig, string | int $ttl)
  {
    // declare a connection variable typed as database service
    /** @var use Drupal\Core\Database\Connection $connection */
    $connection = \Drupal::service('database');
    //drupal check if table exists
    $query = $connection->delete($dataConfig['record_name']);
    if ($dataConfig['field_type'] === "timestamp") {
      $query->condition($dataConfig['field_name'], $ttl, "<");
    } else {
      $query->condition($dataConfig['field_name'], date('Y-m-d', $ttl), '<');
    }
    $count = 0;
    if (!$this->dry) {
      $count =   $query->execute();
    }
    \Drupal::logger('private_data_purger')->notice($count . ' records of ' . $dataConfig['record_name'] . '  deleted. ');
  }

  public function getConfig()
  {
    $config = \Drupal::config('private_data_purger.settings');
    return $config;
  }



  public function deleteEntities(array $dataConfig, array $ids)
  {
    \Drupal::logger('private_data_purger')->notice(count($ids) . ' records of ' . $dataConfig['record_name'] . '  will be deleted. ');
    foreach ($ids as $id) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage($dataConfig['record_type']);
      /** @var Drupal\node\Entity $node */
      $node = $storage_handler->load($id);
      // Drupal get node's creation date formatted to dd/mm/yyyy
      $date = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd/m/Y');

      !$this->dry ?? $storage_handler->delete([$node]);
    }
  }
}
