<?php

namespace Drupal\commerce_wise\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired before creating url.
 */
class QuickPayReferenceEvent extends Event {

  /**
   * Constructs a new QuickPayReferenceEvent object.
   */
  public function __construct(protected string $reference, protected ?OrderInterface $order = NULL) {}

  /**
   * Get the order payload.
   */
  public function getReference(): string {
    return $this->reference;
  }

  /**
   * Set relevant order.
   */
  public function setOrder(?OrderInterface $order): self {
    $this->order = $order;
    return $this;
  }

  /**
   * Get the order.
   */
  public function getOrder(): ?OrderInterface {
    return $this->order;
  }

}
