<?php

namespace Drupal\commerce_wise\Event;

final class WiseEvents {


  /**
   * Allow altering the redirect link before initiating payment.
   *
   * @Event
   *
   * @see \Drupal\commerce_wise\Event\QuickPayLinkEvent
   */
  const string WISE_QUICK_PAY_LINK = 'commerce_wise.quick_pay_link';

  /**
   * Allow altering how order is matched to transfer reference.
   *
   * @Event
   *
   * @see \Drupal\commerce_wise\Event\QuickPayReferenceEvent
   */
  const string WISE_QUICK_PAY_REFERENCE = 'commerce_wise.quick_pay_reference';

}
