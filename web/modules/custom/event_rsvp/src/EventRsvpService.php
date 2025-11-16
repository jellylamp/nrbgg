<?php

namespace Drupal\event_rsvp;

use Drupal\Core\Database\Connection;

/**
 * Service for managing Event RSVP data.
 */
class EventRsvpService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs an EventRsvpService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Sets an RSVP status for a user and event.
   *
   * @param int $nid
   *   The event node ID.
   * @param int $uid
   *   The user ID (0 for anonymous).
   * @param string $status
   *   The RSVP status (going, maybe, not_going).
   * @param array $data
   *   Additional data (name, email, accepted_conduct for anonymous users).
   *
   * @return bool|string
   *   TRUE on success, error message on failure.
   */
  public function setRsvp($nid, $uid, $status, array $data = []) {
    $time = \Drupal::time()->getRequestTime();
    
    // Check capacity if status is 'going'.
    if ($status === 'going') {
      $capacity_check = $this->checkCapacity($nid);
      if ($capacity_check !== TRUE) {
        return $capacity_check;
      }
    }

    $fields = [
      'nid' => $nid,
      'uid' => $uid,
      'status' => $status,
      'changed' => $time,
      'created' => $time,
    ];
    
    // Add anonymous user data if provided.
    if ($uid === 0 && !empty($data)) {
      $fields['name'] = $data['name'] ?? '';
      $fields['email'] = $data['email'] ?? '';
      $fields['accepted_conduct'] = !empty($data['accepted_conduct']) ? 1 : 0;
    }

    try {
      // For anonymous users, use email as unique key.
      if ($uid === 0 && !empty($fields['email'])) {
        $this->database->merge('event_rsvp')
          ->keys([
            'nid' => $nid,
            'email' => $fields['email'],
          ])
          ->fields($fields)
          ->execute();
      }
      else {
        $this->database->merge('event_rsvp')
          ->keys([
            'nid' => $nid,
            'uid' => $uid,
          ])
          ->fields($fields)
          ->execute();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('event_rsvp')->error('Error setting RSVP: @message', ['@message' => $e->getMessage()]);
      return 'An error occurred. Please try again.';
    }

    return TRUE;
  }
  
  /**
   * Checks if event has reached capacity.
   *
   * @param int $nid
   *   The event node ID.
   *
   * @return bool|string
   *   TRUE if space available, error message if full.
   */
  public function checkCapacity($nid) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    
    if (!$node || !$node->hasField('field_event_capacity')) {
      return TRUE;
    }
    
    $capacity = $node->get('field_event_capacity')->value;
    
    // If no capacity set or capacity is 0, no limit.
    if (empty($capacity)) {
      return TRUE;
    }
    
    // Count current "going" RSVPs.
    $going_count = $this->database->select('event_rsvp', 'r')
      ->condition('nid', $nid)
      ->condition('status', 'going')
      ->countQuery()
      ->execute()
      ->fetchField();
    
    if ($going_count >= $capacity) {
      return 'This event is at capacity. You can mark yourself as "Maybe" to join the waitlist.';
    }
    
    return TRUE;
  }

  /**
   * Gets the RSVP status for a specific user and event.
   *
   * @param int $nid
   *   The event node ID.
   * @param int $uid
   *   The user ID.
   *
   * @return string|null
   *   The RSVP status or NULL if not set.
   */
  public function getRsvp($nid, $uid) {
    $result = $this->database->select('event_rsvp', 'r')
      ->fields('r', ['status'])
      ->condition('nid', $nid)
      ->condition('uid', $uid)
      ->execute()
      ->fetchField();

    return $result ?: NULL;
  }

  /**
   * Gets counts of each RSVP status for an event.
   *
   * @param int $nid
   *   The event node ID.
   *
   * @return array
   *   Array with keys 'going', 'maybe', 'not_going' and their counts.
   */
  public function getCounts($nid) {
    $query = $this->database->select('event_rsvp', 'r')
      ->fields('r', ['status'])
      ->condition('nid', $nid)
      ->execute();

    $counts = [
      'going' => 0,
      'maybe' => 0,
      'not_going' => 0,
    ];

    foreach ($query as $row) {
      if (isset($counts[$row->status])) {
        $counts[$row->status]++;
      }
    }

    return $counts;
  }

  /**
   * Gets lists of users for each RSVP status.
   *
   * @param int $nid
   *   The event node ID.
   *
   * @return array
   *   Array with keys 'going', 'maybe', 'not_going' containing user arrays.
   */
  public function getLists($nid) {
    $query = $this->database->select('event_rsvp', 'r')
      ->fields('r', ['uid', 'status', 'name', 'email'])
      ->condition('nid', $nid)
      ->execute();

    $lists = [
      'going' => [],
      'maybe' => [],
      'not_going' => [],
    ];

    $uids = [];
    $rows = [];
    
    // First pass: collect all data.
    foreach ($query as $row) {
      if ($row->uid > 0) {
        $uids[] = $row->uid;
      }
      $rows[] = $row;
    }

    // Load all users at once.
    $users = [];
    if (!empty($uids)) {
      $users = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadMultiple($uids);
    }

    // Organize by status.
    foreach ($rows as $row) {
      if (isset($lists[$row->status])) {
        // Registered user.
        if ($row->uid > 0 && isset($users[$row->uid])) {
          $lists[$row->status][] = [
            'uid' => $row->uid,
            'name' => $users[$row->uid]->getDisplayName(),
            'url' => $users[$row->uid]->toUrl()->toString(),
            'is_anonymous' => FALSE,
          ];
        }
        // Anonymous user.
        else if ($row->uid === '0' && !empty($row->name)) {
          $lists[$row->status][] = [
            'uid' => 0,
            'name' => $row->name,
            'url' => NULL,
            'is_anonymous' => TRUE,
          ];
        }
      }
    }

    return $lists;
  }

  /**
   * Deletes an RSVP.
   *
   * @param int $nid
   *   The event node ID.
   * @param int $uid
   *   The user ID.
   *
   * @return bool
   *   TRUE on success.
   */
  public function deleteRsvp($nid, $uid) {
    $this->database->delete('event_rsvp')
      ->condition('nid', $nid)
      ->condition('uid', $uid)
      ->execute();

    return TRUE;
  }

}