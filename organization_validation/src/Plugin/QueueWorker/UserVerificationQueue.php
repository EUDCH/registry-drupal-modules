<?php

namespace Drupal\organization_validation\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\user\Entity\User;
use Drupal\Core\Url;

/**
 * Processes user verification in a queue to avoid database locks.
 *
 * @QueueWorker(
 *   id = "user_verification_queue",
 *   title = @Translation("User Verification Queue"),
 *   cron = {"time" = 60}
 * )
 */
class UserVerificationQueue extends QueueWorkerBase {

  /**
   * Processes the queued verification request.
   */
  public function processItem($data) {
    $user = User::load($data['uid']);
    if (!$user) {
      return;
    }

    // ? Set user as active
    $user->set('status', 1);
    $user->save();

    // ? Generate verification link
    $verification_link = Url::fromRoute('organization_validation.verify_organization', [
      'user' => $user->id(),
    ], ['absolute' => TRUE])->toString();

    // ? Log the verification link
    \Drupal::logger('organization_validation')
      ->notice('Verification link for user @user: <a href=":link" target="_blank">:link</a>', [
        '@user' => $user->getDisplayName(),
        ':link' => $verification_link,
      ]);

    // ? Send email notification
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'organization_validation';
    $key = 'verify_organization';
    $to = $user->getEmail();
    $params['message'] = "Your account has been verified and activated. Please verify your organization using this link: $verification_link";
    $params['subject'] = 'Account Verified and Activated - Verify Your Organization';
    $langcode = $user->getPreferredLangcode();
    $send = TRUE;

    $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
  }

}
