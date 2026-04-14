<?php

namespace Drupal\organization_validation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
* Provides a confirmation page for the organization validation workflow.
*/
class ConfirmationController extends ControllerBase
{
  /**
  * The request stack service.
  *
  * @var \Symfony\Component\HttpFoundation\RequestStack
  */
  protected $requestStack;

  /**
  * Constructs a ConfirmationController object.
  *
  * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
  *   The request stack service.
  */
  public function __construct(RequestStack $request_stack)
  {
    $this->requestStack = $request_stack;
  }

  /**
  * {@inheritdoc}
  */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
  * Displays a confirmation message using Drupal's default layout.
  *
  * @return array
  *   Render array for the confirmation page.
  */
  public function confirmationPage()
  {
    $request = $this->requestStack->getCurrentRequest();
    $message = $request->query->get('message', $this->t('An unexpected error occurred.'));

    return [
      '#title' => $this->t('Confirmation'),
      '#type' => 'markup',
      '#markup' => '<div class="confirmation-message">
              <div class="message-box">
                <h2>' . $this->t('Process Completed') . '</h2>
                <p>' . $message . '</p>
                <a href="/" class="button">' . $this->t('Return to Home') . '</a>
              </div>
             </div>',
      '#allowed_tags' => ['div', 'h2', 'p', 'a'],
      '#attached' => [
        'library' => ['organization_validation/confirmation_styles'],
      ],
    ];
  }
}
