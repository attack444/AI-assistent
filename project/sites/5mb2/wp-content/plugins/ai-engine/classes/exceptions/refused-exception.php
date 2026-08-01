<?php

/**
 * Thrown when a query is refused before reaching the AI (usage limits, banned
 * IPs or words, or any mwai_ai_allowed filter). Unlike provider errors, the
 * message is written for the end user and safe to display as-is. The reason
 * lets REST layers attach machine-readable flags (e.g. overLimit) so clients
 * can react beyond showing the message, like locking the chat input.
 */
class Meow_MWAI_RefusedException extends Exception {
  public $reason = null;

  public function __construct( $message, $reason = null ) {
    parent::__construct( $message );
    $this->reason = $reason;
  }
}
