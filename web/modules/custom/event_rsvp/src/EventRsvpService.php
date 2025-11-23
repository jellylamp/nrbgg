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
   *   The user ID.
   * @param string $status
   *   The RSVP status (going, maybe, not_going).
   *
   * @return bool|string
   *   TRUE on success, error message string on failure.
   */
  public function setRsvp($nid, $uid, $status) {
    $time = \Drupal::time()->getRequestTime();
    
    // Check capacity if user is trying to mark "going".
    if ($status === 'going') {
      $capacity_check = $this->checkCapacity($nid, $uid);
      if ($capacity_check !== TRUE) {
        return $capacity_check;
      }
    }

    // Use merge to handle both insert and update.
    $this->database->merge('event_rsvp')
      ->keys([
        'nid' => $nid,
        'uid' => $uid,
      ])
      ->fields([
        'nid' => $nid,
        'uid' => $uid,
        'status' => $status,
        'changed' => $time,
        'created' => $time,
      ])
      ->execute();

    return TRUE;
  }
  
  /**
   * Checks if event has reached capacity.
   *
   * @param int $nid
   *   The event node ID.
   * @param int $uid
   *   The user ID checking (to allow their own status change).
   *
   * @return bool|string
   *   TRUE if space available, error message if full.
   */
  public function checkCapacity($nid, $uid) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    
    if (!$node || !$node->hasField('field_max_event_cap')) {
      return TRUE;
    }
    
    $capacity = $node->get('field_max_event_cap')->value;
    
    // If no capacity set or capacity is 0, no limit.
    if (empty($capacity) || $capacity == 0) {
      return TRUE;
    }
    
    // Count current "going" RSVPs (excluding this user).
    $going_count = $this->database->select('event_rsvp', 'r')
      ->condition('nid', $nid)
      ->condition('status', 'going')
      ->condition('uid', $uid, '!=')
      ->countQuery()
      ->execute()
      ->fetchField();
    
    if ($going_count >= $capacity) {
      return 'This event is at capacity (' . $capacity . ' attendees). You can mark yourself as "Maybe" to join the waitlist.';
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
      ->fields('r', ['uid', 'status'])
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
      $uids[] = $row->uid;
      $rows[] = $row;
    }

    if (empty($uids)) {
      return $lists;
    }

    // Load all users at once.
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadMultiple($uids);

    // Organize by status.
    foreach ($rows as $row) {
      if (isset($users[$row->uid]) && isset($lists[$row->status])) {
        $lists[$row->status][] = [
          'uid' => $row->uid,
          'name' => $users[$row->uid]->getDisplayName(),
          'url' => $users[$row->uid]->toUrl()->toString(),
        ];
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