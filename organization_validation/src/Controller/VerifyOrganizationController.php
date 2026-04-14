<?php

namespace Drupal\organization_validation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\user\Entity\User;

/**
* Handles the organization verification logic.
*/
class VerifyOrganizationController extends ControllerBase
{
  /**
  * Verifies the user's organization.
  */
  public function verify($user)
  {
    $user = User::load($user);
    if (!$user) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('User not found.');
    }

    $organization_name = $user->get('field_organization_name')->value;

    if (empty($organization_name)) {
      return [
       '#markup' => '<p><strong>Error:</strong> No organization name found for this user.</p>',
      ];
    }

    // ✅ Fix: Explicitly disable access checking
    $query = \Drupal::entityQuery('node')
     ->condition('type', 'organization')
     ->condition('title', $organization_name)
     ->accessCheck(false) // ✅ Prevents QueryException error
     ->range(0, 1);
    $result = $query->execute();

    if (!empty($result)) {
      // Organization exists; redirect to its page.
      $organization_id = reset($result);
      return new RedirectResponse(\Drupal::service('path.alias_manager')->
       getAliasByPath('/node/' . $organization_id));
    } else {
      // Organization does not exist; redirect to the add form with prefilled data.
      $url = \Drupal\Core\Url::fromRoute('node.add', [
      'node_type' => 'organizations',
      ], [
       'query' => [
        'title' => $organization_name,
       ],
      ]);
      return new RedirectResponse($url->toString());
    }
  }
}
