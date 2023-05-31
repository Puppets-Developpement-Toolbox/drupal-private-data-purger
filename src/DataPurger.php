<?php

namespace Drupal\private_data_purger;

/**
 * DataPurger service.
 */
class DataPurger
{

  /**
   * I came here to purge data and chew bubblegum... and I'm all out of bubblegum.
   */

  public function purgeSomeEntity($arg)
  {
    $connection = \Drupal::service('database');
    $availableEntities = \Drupal::entityTypeManager()->getDefinitions();
    $config = \Drupal::config('private_data_purger.settings');
    if (!empty($config->get()['entities'])) {
      foreach ($config->get()['entities'] as $entity => $value) {
        //get out of loop   if entity does not exist
        //check if $entity its type is a key of $availableEntities || entity is a table in the database


        $entity_type = $config->get('entities.' . $entity . '.entity_type');
        $entity_name = $config->get('entities.' . $entity . '.entity_name');
        if (!array_key_exists($entity, $availableEntities) && !array_key_exists($entity_type, $availableEntities) && !$connection->schema()->tableExists($entity_name)) {
          echo ('Entity ' . $entity . ' of type ' . $entity_type . ' does not exist.');
          break;
        }
        // use the resolveNids function to get the nids of the entities to be deleted
        $nids = $this->resolveNids($entity, $entity_type);


        dump(count($nids) . ' records of' . $entity . '  will be deleted. ');
        if ($arg === "wet-run") {
          foreach ($nids as $nid) {
            if (!$entity_type === 'sql_entity') {
              $storage_handler = \Drupal::entityTypeManager()->getStorage($entity_name);
              /** @var Drupal\node\Entity $node */
              $node = $storage_handler->load($nid);
              // Drupal get node's creation date formatted to dd/mm/yyyy
              $date = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd/m/Y');
              dump('Node of type ' . $entity . ' with id ' . $nid . ' created on ' . $date . ' will be deleted.');
              //$storage_handler->delete([$node]);
            } else {
              $connection->delete($entity_name)
                ->condition('id', $nid);
              //->execute();
              dump('Node  ' . $nid . ' from ' . $entity_name . ' deleted.');
            }
          }
        }
      }
    }
  }

  /**
   * Resolve the nids of the entities to be deleted
   */
  function resolveNids($entity, $entity_type)
  {
    $config = \Drupal::config('private_data_purger.settings');
    $ageToKeep =  strtotime('- ' . $config->get('entities.' . $entity . '.created'));

    switch ($entity_type) {
      case 'webform_submission':
        $webform  = \Drupal\webform\Entity\Webform::load('contact');;
        if ($webform->hasSubmissions()) {
          $result = \Drupal::entityQuery('webform_submission')
            ->condition("created", $ageToKeep, "<")
            ->condition('webform_id', $entity)
            ->accessCheck(FALSE)
            ->execute();
        }
        break;
      case 'classic_entity':
        $result = \Drupal::entityQuery($entity)
          ->condition("created", $ageToKeep, "<")
          ->accessCheck(FALSE)
          ->execute();
        break;
      case 'sql_entity':
        // declare a connection variable typed as database service
        /** @var use Drupal\Core\Database\Connection $node */
        $connection = \Drupal::service('database');
        //drupal check if table exists

        $query = $connection->select($config->get('entities.' . $entity . '.entity_name'), 'go')
          ->fields(
            'go',
            [$config->get('entities.' . $entity . '.id_column')]
          );

        if ($config->get('entities.' . $entity . '.db_field_type') == "timestamp") {

          $query->condition($config->get('entities.' . $entity . '.db_field_name'), $ageToKeep, "<");
        } else {
          $query->condition($config->get('entities.' . $entity . '.db_field_name'), date('Y-m-d', $ageToKeep), '<');
        }
        $result = $query->execute()->fetchAll();

        // array from id key of $query
        $result = array_column($result, $config->get('entities.' . $entity . '.id_column'));
        break;
    }

    return $result;
  }
}
