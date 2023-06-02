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

  public function purgeData(string $arg = "")
  {
    if ($arg == "dry-run") {
      $this->dry = true;
    }
    $connection = \Drupal::service('database');
    $config = \Drupal::config('private_data_purger.settings');

    //try to get data from config file, if not throw an exception
    if ($config->get('data') === null) {
      throw new \Exception('No data to purge');
    }
    foreach ($config->get()['data'] as $records => $dataConfig) {
      $this->handler($dataConfig['record_name'], $dataConfig);
    }
  }
  private function handler(string $dataName, array $dataConfig)
  {
    $ttl =  strtotime('- ' . $dataConfig['ttl']);
    //cast snake_case string to camelCase
    $record_type = str_replace('_', '', ucwords($dataConfig['record_type'], '_'));
    $functionName = 'purge' . ucfirst($record_type);
    $availableEntities = \Drupal::entityTypeManager()->getDefinitions();
    $this->$functionName($dataName, $dataConfig, $ttl, $availableEntities);
  }

  public function purgeWebformSubmission(string $dataName, array $dataConfig, int $ttl, $availableEntities = [])
  {
    $this->checkEntityValidity($dataConfig, $availableEntities);
    $result = \Drupal::entityQuery('webform_submission')
      ->condition("created", $ttl, "<")
      ->condition('webform_id', $dataName)
      ->accessCheck(FALSE)
      ->execute();
    $this->deleteEntities($dataConfig, $result);
  }

  public function purgeClassicRecord(string $dataName, array $dataConfig, int $ttl, $availableEntities = [])
  {
    $result = \Drupal::entityQuery($dataName)
      ->condition("created", $ttl, "<")
      ->accessCheck(FALSE)
      ->execute();
    $this->deleteEntities($dataConfig, $result);
  }

  public function purgeSqlRecord(string $dataName, array $dataConfig, string | int $ttl, $availableEntities = [])
  {
    // declare a connection variable typed as database service
    /** @var use Drupal\Core\Database\Connection $connection */
    $connection = \Drupal::service('database');
    if (!$connection->schema()->tableExists($dataConfig['record_name'])) {
      throw new \Exception('Config record type' . $dataConfig['record_name'] . ' : ' . $dataConfig['record_type'] . ' not found in the database');
    }


    $query = $connection->select($dataConfig['record_name'])
      ->fields($dataConfig['record_name'], [$dataConfig['field_name']]);
    if ($dataConfig['field_type'] === "timestamp") {
      $query->condition($dataConfig['field_name'], $ttl, "<");
    } else {
      $query->condition($dataConfig['field_name'], date('Y-m-d', $ttl), '<');
    }

    $count = count($query->execute()->fetchAll());

    //drupal check if table exists
    $query = $connection->delete($dataConfig['record_name']);
    if ($dataConfig['field_type'] === "timestamp") {
      $query->condition($dataConfig['field_name'], $ttl, "<");
    } else {
      $query->condition($dataConfig['field_name'], date('Y-m-d', $ttl), '<');
    }

    \Drupal::logger('private_data_purger')->notice($count . ' records of ' . $dataConfig['record_name'] . ' : ' . $dataConfig['record_type'] . '  up for deletion. ');
    
    if (!$this->dry) {
      $count =   $query->execute();
      \Drupal::logger('private_data_purger')->notice($count . ' records of ' . $dataConfig['record_name'] . ' : ' . $dataConfig['record_type'] . '  deleted. ');
    }
  }

  public function getConfig()
  {
    $config = \Drupal::config('private_data_purger.settings');
    return $config;
  }

  public function deleteEntities(array $dataConfig, array $ids)
  {
    \Drupal::logger('private_data_purger')->notice(count($ids) . ' records of ' . $dataConfig['record_name'] . ' : ' . $dataConfig['record_type'] . ' up for deletion. ');
    foreach ($ids as $id) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage($dataConfig['record_type']);
      /** @var Drupal\node\Entity $node */
      $node = $storage_handler->load($id);
      // Drupal get node's creation date formatted to dd/mm/yyyy
      $date = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd/m/Y');
      if (!$this->dry) {
        $count =  $storage_handler->delete([$node]);
      }
    }
  }

  private function checkEntityValidity($dataConfig, $availableEntities)
  {
    if (!array_key_exists($dataConfig['record_name'], $availableEntities) && !array_key_exists($dataConfig['record_type'], $availableEntities)) {
      throw new \Exception('Entity type' . $dataConfig['record_name'] . ' : ' . $dataConfig['record_type'] . ' not found in the database');
    }
  }
}
