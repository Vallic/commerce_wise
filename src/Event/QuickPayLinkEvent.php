<?php

namespace Drupal\commerce_wise\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired before creating url.
 */
class QuickPayLinkEvent extends Event {

  /**
   * Constructs a new QuickPayLinkEvent object.
   */
  public function __construct(protected OrderInterface $order, protected string $reference, protected Price $balance) {}

  /**
   * Get the order payload.
   */
  public function getReference(): string {
    return $this->reference;
  }

  /**
   * Set the new payload.
   */
  public function setReference(string $reference): self {
    $this->reference = $reference;
    return $this;
  }

  /**
   * Set the new balance.
   */
  public function setBalance(Price $balance): self {
    $this->balance = $balance;
    return $this;
  }

  /**
   * Get the balance amount.
   */
  public function getBalance(): Price {
    return $this->balance;
  }

  /**
   * Get the order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

}
