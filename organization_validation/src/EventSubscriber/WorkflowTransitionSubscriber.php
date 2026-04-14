<?php

namespace Drupal\organization_validation\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\workflow\Event\WorkflowTransitionEvent;

/**
 *
 */
class WorkflowTransitionSubscriber implements EventSubscriberInterface {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      'workflow.transition' => 'onWorkflowTransition',
    ];
  }

  /**
   *
   */
  public function onWorkflowTransition(WorkflowTransitionEvent $event) {
    $transition = $event->getTransition();
    $entity = $event->getEntity();

    \Drupal::logger('organization_validation')->debug('Node is published 0');
    // Check if this is an Organization entity being published.
    if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'organization') {
      \Drupal::logger('organization_validation')->debug('Node is published 1');
      if ($transition->getToState()->isPublished()) {
        \Drupal::logger('organization_validation')->debug('Node is published 2');

        // // Prepare email
        // $mailManager = \Drupal::service('plugin.manager.mail');
        // $module = 'organization_validation';
        // $key = 'organization_published';
        // $to = 'admin@example.com'; // Change to the recipient email
        // $params['subject'] = "An Organization has been published";
        // $params['message'] = "The organization '" . $entity->label() . "' has been published.";
        // $langcode = \Drupal::currentUser()->getPreferredLangcode();
        // $send = true;
        // // Send email
        // $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
      }
    }
  }

}
