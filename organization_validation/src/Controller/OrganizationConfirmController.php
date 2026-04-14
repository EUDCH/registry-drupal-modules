<?php

namespace Drupal\organization_validation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles Organisation Confirmation page.
 */
class OrganizationConfirmController extends ControllerBase {
  /**
   * The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Dependency Injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Organisation Confirmation Page.
   */
  public function confirmPage(RouteMatchInterface $route_match) {
    $org_id = $route_match->getParameter('org');
    $organization = $this->entityTypeManager->getStorage('node')->load($org_id);

    if (!$organization) {
      return ['#markup' => '<p>Organisation not found.</p>'];
    }

    $org_name = $organization->label();
    $org_url = $organization->toUrl()->toString();
    $country = $organization->get('field_country')->value ?? 'Unknown';

    return [
      '#theme' => 'confirmation_page',
      '#org_name' => $org_name,
      '#country' => $country,
      '#org_url' => $org_url,
      '#form' => \Drupal::formBuilder()
        ->getForm('Drupal\organization_validation\Form\ConfirmOrganisationForm', $org_id),
    ];
  }

}
