<?php

namespace Drupal\private_data_purger;

/**
 * DataPurger service.
 */
class DataPurger
{
  //declare a private int property 
  public $dry = false;

  
  public function purgeData($dry_run = false)
  {
    $this->dry = $dry_run;
   
    $config = \Drupal::config('private_data_purger.settings');

    //try to get data from config file, if not throw an exception
    if ($config->get('data') === null) {
      throw new \Exception('No data to purge');
    }
    foreach ($config->get()['data'] as $records => $dataConfig) {
      $this->handler($dataConfig);
    }

    $this->dry = false;
  }
  private function handler(array $dataConfig)
  {
    $ttl =  strtotime('- ' . $dataConfig['ttl']);
    //cast snake_case string to camelCase
    $record_type = str_replace('_', '', ucwords($dataConfig['record_type'], '_'));
    $functionName = 'purge' . ucfirst($record_type);
    $this->$functionName(
      $dataConfig['record_name'], 
      $ttl,
      $dataConfig
    );
  }

  public function purgeWebformSubmission(string $dataName, int $ttl)
  {
    // test if webform module is enabled
    if (!\Drupal::moduleHandler()->moduleExists('webform')) {
      throw new \Exception('Webform module is not enabled');
    }
    $result = \Drupal::entityQuery('webform_submission')
      ->condition("created", $ttl, "<")
      ->condition('webform_id', $dataName)
      ->accessCheck(FALSE)
      ->execute();
    $this->deleteEntities('webform_submission', $result);
  }

  /**
   * purge Entity of type entity_type
   */
  public function purgeClassicRecord(string $entity_type, int $ttl)
  {
    $availableEntities = \Drupal::entityTypeManager()->getDefinitions();
    if (!array_key_exists($entity_type, $availableEntities)) {
      throw new \Exception(strtr(
        'Entity type {name} : {type} not found in the database', [
          '{name}' => $entity_type,
          '{type}' => 'entity',
        ]));
    }
    $result = \Drupal::entityQuery($entity_type)
      ->condition('created', $ttl, '<')
      ->accessCheck(FALSE)
      ->execute();
    $this->deleteEntities($entity_type, $result);
  }

  public function purgeSqlRecord(string $table, int $ttl, array $dataConfig)
  {
    // declare a connection variable typed as database service
    /** @var \Drupal\Core\Database\Connection */
    $connection = \Drupal::service('database');
    if (!$connection->schema()->tableExists($table)) {
      throw new \Exception('Config record type' . $table . ' : sql_record not found in the database');
    }

    //drupal check if table exists
    $method = $this->dry ? 'select' : 'delete';
    $query = $connection->$method($table);

    $castedTtl = $dataConfig['field_type'] === 'timestamp' ? $ttl : date('Y-m-d', $ttl);
    
    $query->condition($dataConfig['field_name'], $castedTtl, "<");

    if (!$this->dry) {
      $count = $query->execute();
    } else {
      $count = current($query->countQuery()->execute()->fetch());
    }

    \Drupal::logger('private_data_purger')->notice(
      '{count} records of {name} : {type}  up for deletion. ', [
      'count' => $count,
      'name' => $dataConfig['record_name'],
      'type' => $dataConfig['record_type'],
    ]);

  }

  public function deleteEntities($storage_name, array $ids)
  {
    if ($this->dry === true) return;
    $storage_handler = \Drupal::entityTypeManager()->getStorage($storage_name);
    $chunks = array_chunk($ids, 100);
    foreach($chunks as $ids_chunk) {
      $to_delete = $storage_handler->loadMultiple($ids_chunk);
      $storage_handler->delete($to_delete);
      
    }
  }

}
