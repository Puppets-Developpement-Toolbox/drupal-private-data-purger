<?php

namespace Drupal\private_data_purger;

use Entity;
//use Drupal Webform encrypt namespace
use Drupal\webform_encrypt\EncryptService;



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
    
    $config = \Drupal::config('private_data_purger.settings');
    if (!empty($config->get()['entities'])) {
      foreach ($config->get()['entities'] as $entity => $value) {
        //get out of loop   if entity does not exist

        $availableEntities = \Drupal::entityTypeManager()->getDefinitions();
        //check if $entity its type is a key of $availableEntities 
        $entity_type = $config->get('entities.' . $entity . '.entity_type');
        if (!array_key_exists($entity, $availableEntities) && !array_key_exists($entity_type, $availableEntities) ) {
          echo ('Entity ' . $entity . ' of type '.$entity_type.' does not exist.');
          break;
        }
        // use the resolveNids function to get the nids of the entities to be deleted
        $nids = $this->resolveNids($entity, $entity_type);
        $storage_handler = \Drupal::entityTypeManager()->getStorage("webform_submission");
        dump(count($nids).' records will be deleted. ');
        foreach ($nids as $nid) {
          /** @var Drupal\node\Entity $node */
          $node = $storage_handler->load($nid);
          // Drupal get node's creation date formatted to dd/mm/yyyy
          $date = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd/m/Y');
          dump('Node of type ' . $entity . ' with id ' . $nid . ' created on ' . $date . ' will be deleted.');
          //$node->getCreatedTime();
          //count the number of $nids
          if ($arg == "wet-run") {
            $storage_handler->delete([$node]);
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
    $ageToKeep = $config->get('entities.' . $entity . '.created');
    switch ($entity_type) {
      case 'webform_submission':
        $webform  = \Drupal\webform\Entity\Webform::load('contact');;
        if ($webform->hasSubmissions()) {
          $result = \Drupal::entityQuery('webform_submission')
            ->condition("created", strtotime('- ' . $ageToKeep), "<")
            ->condition('webform_id', $entity)
            ->accessCheck(FALSE)
            ->execute();
        }
        break;
      case 'classic_entity':
        $result = \Drupal::entityQuery($entity)
          ->condition("created", strtotime('- ' . $ageToKeep), "<")
          ->accessCheck(FALSE)
          ->execute();
        break;
    }

    return $result;
  }
}
