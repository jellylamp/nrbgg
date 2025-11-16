<?php

namespace Drupal\event_rsvp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\event_rsvp\EventRsvpService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for Event RSVP actions.
 */
class EventRsvpController extends ControllerBase {

  /**
   * The Event RSVP service.
   *
   * @var \Drupal\event_rsvp\EventRsvpService
   */
  protected $rsvpService;

  /**
   * Constructs an EventRsvpController object.
   *
   * @param \Drupal\event_rsvp\EventRsvpService $rsvp_service
   *   The RSVP service.
   */
  public function __construct(EventRsvpService $rsvp_service) {
    $this->rsvpService = $rsvp_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_rsvp.service')
    );
  }

  /**
   * Sets the RSVP status for the current user.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The event node.
   * @param string $status
   *   The RSVP status (going, maybe, not_going).
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A JSON response or redirect.
   */
  public function setStatus(NodeInterface $node, $status) {
    $user = $this->currentUser();
    
    if (!$user->isAuthenticated()) {
      return new JsonResponse(['error' => 'User must be logged in'], 403);
    }

    // Set the RSVP status.
    $result = $this->rsvpService->setRsvp($node->id(), $user->id(), $status);
    
    // Check if capacity limit was hit.
    if ($result !== TRUE) {
      $this->messenger()->addWarning($result);
      return new RedirectResponse($node->toUrl()->toString());
    }

    // Invalidate cache for this event.
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['event_rsvp:' . $node->id()]);

    // If this is an AJAX request, return JSON.
    $request = \Drupal::request();
    if ($request->isXmlHttpRequest()) {
      $counts = $this->rsvpService->getCounts($node->id());
      return new JsonResponse([
        'success' => TRUE,
        'status' => $status,
        'counts' => $counts,
      ]);
    }

    // Otherwise redirect back to the node.
    $this->messenger()->addStatus($this->t('Your RSVP has been recorded.'));
    return new RedirectResponse($node->toUrl()->toString());
  }

}