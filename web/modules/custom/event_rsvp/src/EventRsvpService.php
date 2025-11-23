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
   * @return bool
   *   TRUE on success.
   */
  public function setRsvp($nid, $uid, $status) {
    $time = \Drupal::time()->getRequestTime();

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