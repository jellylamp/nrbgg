<?php

namespace Drupal\event_rsvp\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\event_rsvp\EventRsvpService;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Provides an Event RSVP block.
 *
 * @Block(
 *   id = "event_rsvp_block",
 *   admin_label = @Translation("Event RSVP"),
 *   category = @Translation("Custom")
 * )
 */
class EventRsvpBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Event RSVP service.
   *
   * @var \Drupal\event_rsvp\EventRsvpService
   */
  protected $rsvpService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new EventRsvpBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\event_rsvp\EventRsvpService $rsvp_service
   *   The RSVP service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventRsvpService $rsvp_service, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->rsvpService = $rsvp_service;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_rsvp.service'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');
    
    if (!$node || $node->bundle() !== 'event') {
      return [];
    }

    $nid = $node->id();
    $uid = $this->currentUser->id();
    $current_status = $this->rsvpService->getRsvp($nid, $uid);
    $counts = $this->rsvpService->getCounts($nid);
    $lists = $this->rsvpService->getLists($nid);

    $build = [];

    // RSVP Buttons (only for logged-in users).
    if ($this->currentUser->isAuthenticated()) {
      $build['buttons'] = [
        '#theme' => 'event_rsvp_buttons',
        '#nid' => $nid,
        '#current_status' => $current_status,
        '#counts' => $counts,
        '#attached' => [
          'library' => ['event_rsvp/rsvp'],
        ],
      ];
    }
    else {
      $build['login_message'] = [
        '#markup' => '<p>' . $this->t('Please <a href="@login">log in</a> to RSVP for this event.', [
          '@login' => Url::fromRoute('user.login')->toString(),
        ]) . '</p>',
      ];
    }

    // RSVP Lists.
    $build['lists'] = [
      '#theme' => 'event_rsvp_list',
      '#going' => $lists['going'],
      '#maybe' => $lists['maybe'],
      '#not_going' => $lists['not_going'],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return array_merge(parent::getCacheContexts(), ['user', 'url.path']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node) {
      $tags[] = 'event_rsvp:' . $node->id();
    }
    return $tags;
  }

}