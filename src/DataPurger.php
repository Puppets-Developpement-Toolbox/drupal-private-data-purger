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

  public function purgeData($arg = "dry-run")
  {
    $this->isDryrun($arg);
    $connection = \Drupal::service('database');
    $availableEntities = \Drupal::entityTypeManager()->getDefinitions();
    $config = \Drupal::config('private_data_purger.settings');

    if (!empty($config->get()['data'])) {
      foreach ($config->get()['data'] as $dataName => $dataConfig) {
          //get out of loop   if entity does not exist
          //check if $dataObject its type is a key of $availableEntities || entity is a table in the database
        ;
        if (!array_key_exists($dataName, $availableEntities) && !array_key_exists($dataConfig['entity_type'], $availableEntities) && !$connection->schema()->tableExists($dataConfig['entity_name'])) {
          \Drupal::logger('private_data_purger')->error('Data ' . $dataName . ' of type ' . $dataConfig['entity_type'] . ' does not exist.');
          break;
        }

        $this->handler($dataName, $dataConfig);
      }
    }
  }
  /**
   * Resolve the nids of the data object to be deleted
   */
  function handler($dataName, $dataConfig)
  {
    $created =  strtotime('- ' . $dataConfig['created']);
    switch ($dataConfig['entity_type']) {
      case 'webform_submission':
        //$this->purgeWebformSubmission($dataName, $dataConfig, $created);
        break;
      case 'classic_entity':
        $this->purgeClassicEntity($dataName, $dataConfig, $created);
        break;
      case 'sql_entity':
        $this->purgeSqlData($dataName, $dataConfig, $created);
        break;
    }
  }


  public function purgeWebformSubmission($dataName, $dataConfig, $created)
  {
    $webform  = \Drupal\webform\Entity\Webform::load('contact');;
    if ($webform->hasSubmissions()) {
      $result = \Drupal::entityQuery('webform_submission')
        ->condition("created", $created, "<")
        ->condition('webform_id', $dataName)
        ->accessCheck(FALSE)
        ->execute();
      $this->deleteEntity($dataName, $dataConfig, $result);
    }
  }

  public function purgeClassicEntity($dataName, $dataConfig, $created)
  {
    $result = \Drupal::entityQuery($dataName)
      ->condition("created", $created, "<")
      ->accessCheck(FALSE)
      ->execute();
    $this->deleteEntity($dataName, $dataConfig, $result);
  }

  public function purgeSqlData($dataName, $dataConfig, $created)
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
    \Drupal::logger('private_data_purger')->notice($count . ' records of ' . $dataConfig['entity_name'] . '  deleted. ');
  }

  public function getConfig()
  {
    $config = \Drupal::config('private_data_purger.settings');
    return $config;
  }

  private function isDryrun($arg)
  {
    if ($arg === "dry-run") {
      $this->dry = true;
    }
    if ($arg === "wet-run") {
      $this->dry = false;
    }
  }

  public function deleteEntity($dataName, $dataConfig, $ids)
  {
    \Drupal::logger('private_data_purger')->notice(count($ids) . ' records of ' . $dataConfig['entity_name'] . '  will be deleted. ');
    foreach ($ids as $id) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage($dataConfig['entity_name']);
      /** @var Drupal\node\Entity $node */
      $node = $storage_handler->load($id);
      // Drupal get node's creation date formatted to dd/mm/yyyy
      $date = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd/m/Y');

      //dump('Node of type ' . $dataName . ' with id ' . $id . ' created on ' . $date . ' will be deleted.');
      !$this->dry ?? $storage_handler->delete([$node]);
    }
  }
}
