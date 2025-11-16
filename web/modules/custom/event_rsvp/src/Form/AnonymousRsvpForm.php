<?php

namespace Drupal\event_rsvp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\event_rsvp\EventRsvpService;
use Drupal\Core\Url;

/**
 * Form for anonymous users to RSVP.
 */
class AnonymousRsvpForm extends FormBase {

  /**
   * The Event RSVP service.
   *
   * @var \Drupal\event_rsvp\EventRsvpService
   */
  protected $rsvpService;

  /**
   * Constructs an AnonymousRsvpForm object.
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_rsvp_anonymous_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL, $status = NULL) {
    if (!$node || !$status) {
      return $form;
    }

    $form['#prefix'] = '<div id="anonymous-rsvp-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $node,
    ];

    $form['status'] = [
      '#type' => 'hidden',
      '#value' => $status,
    ];

    $form['intro'] = [
      '#markup' => '<p><strong>' . $this->t('Please provide your information to RSVP:') . '</strong></p>',
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Name'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your Email'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#description' => $this->t('We will only use this to contact you about this event.'),
    ];

    $conduct_url = Url::fromRoute('entity.node.canonical', ['node' => \Drupal::config('event_rsvp.settings')->get('code_of_conduct_nid') ?: 4])->toString();
    
    $form['accepted_conduct'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have read and agree to the <a href="@url" target="_blank">Code of Conduct</a>', [
        '@url' => $conduct_url,
      ]),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit RSVP'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'anonymous-rsvp-form-wrapper',
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => [
        'onclick' => 'this.closest(".anonymous-rsvp-form-container").style.display = "none"; return false;',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    
    // Validate email format.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->getValue('nid');
    $status = $form_state->getValue('status');
    $data = [
      'name' => $form_state->getValue('name'),
      'email' => $form_state->getValue('email'),
      'accepted_conduct' => $form_state->getValue('accepted_conduct'),
    ];

    $result = $this->rsvpService->setRsvp($nid, 0, $status, $data);

    if ($result === TRUE) {
      $this->messenger()->addStatus($this->t('Thank you! Your RSVP has been recorded.'));
      
      // Invalidate cache.
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['event_rsvp:' . $nid]);
    }
    else {
      $this->messenger()->addError($result);
    }

    // Redirect to the event page.
    $form_state->setRedirect('entity.node.canonical', ['node' => $nid]);
  }

  /**
   * AJAX submit callback.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }

    $response = new \Drupal\Core\Ajax\AjaxResponse();
    $response->addCommand(new \Drupal\Core\Ajax\RedirectCommand(Url::fromRoute('entity.node.canonical', ['node' => $form_state->getValue('nid')])->toString()));
    return $response;
  }

}