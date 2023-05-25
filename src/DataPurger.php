<?php

namespace Drupal\sodexo_private_data_purge;

use Entity;

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
    $config = \Drupal::config('sodexo_private_data_purge.settings');
    foreach ($config->get()['entities'] as $entity => $filter) {
      $ageToKeep = $config->get('entities.'.$entity.'.created');
      $result = \Drupal::entityQuery("newsletter_e_mail_entity")
        ->condition("created", strtotime('-'.$ageToKeep), "<")
        ->execute();
      $storage_handler = \Drupal::entityTypeManager()->getStorage("newsletter_e_mail_entity");
      foreach ($result as $nid) {
        $node = $storage_handler->load($nid);
        // Drupal get node's creation date formatted to dd/mm/yyyy
        $date = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd/m/Y');
        dump('Node of type '.$entity.' with id '.$nid.' created on '.$date.' will be deleted.');
        //$node->getCreatedTime();
        if($arg == "wet-run"){
          $storage_handler->delete([$node]);
        }
      }
    }
  }
}
