<?php

namespace Drupal\commerce_wise\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;

interface QuickPayInterface {

  public const array WISE_API_URL = [
    'live' => 'https://wise.com',
    'test' => 'https://sandbox.transferwise.tech',
  ];

  /**
   * Get the Wise tag.
   */
  public function getWiseTag(): string;

  /**
   * Get the public key.
   */
  public function getPublicKey(): string;

  /**
   * Get account type, business or personal.
   */
  public function getAccountType(): string;

  /**
   * Create link for payment.
   */
  public function generateQuickPayUrl(OrderInterface $order): string;

}
