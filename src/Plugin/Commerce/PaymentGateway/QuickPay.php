<?php

namespace Drupal\commerce_wise\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_wise\Event\QuickPayLinkEvent;
use Drupal\commerce_wise\Event\QuickPayReferenceEvent;
use Drupal\commerce_wise\Event\WiseEvents;
use Drupal\commerce_wise\PluginForm\OffsiteRedirect\PaymentOffsiteForm;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides the Wise Quick Pay payment link offsite gateway.
 */
#[CommercePaymentGateway(
  id: "wise_quick_pay",
  label: new TranslatableMarkup("Wise Quick Pay"),
  display_label: new TranslatableMarkup("Wise"),
  forms: [
    "offsite-payment" => PaymentOffsiteForm::class,
  ],
  payment_type: "payment_default",
  requires_billing_information: FALSE,
)]
class QuickPay extends OffsitePaymentGatewayBase implements QuickPayInterface {

  use LoggerChannelTrait;

  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'wise_tag' => '',
      'public_key' => '',
      'account_type' => 'business',
      'logging' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['wise_tag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wise tag'),
      '#default_value' => $this->configuration['wise_tag'],
      '#required' => TRUE,
    ];

    $form['public_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Public Key'),
      '#default_value' => $this->configuration['public_key'],
      '#description' => $this->t('Public key, copy from @url', ['@url' => 'https://docs.wise.com/api-docs/webhooks-notifications/event-handling#requests']),
      '#required' => TRUE,
    ];

    $form['account_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Account type'),
      '#options' => [
        'business' => $this->t('Business'),
      ],
      '#default_value' => $this->configuration['account_type'],
      '#required' => TRUE,
    ];

    $form['logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log webhook events'),
      '#default_value' => $this->configuration['logging'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['wise_tag'] = $values['wise_tag'];
      $this->configuration['public_key'] = $values['public_key'];
      $this->configuration['account_type'] = $values['account_type'];
      $this->configuration['logging'] = $values['logging'];
    }
  }

  /**
   * Create payment upon notify.
   */
  protected function createPayment(OrderInterface $order, array $payload): PaymentInterface {
    $amount = new Price((string) $payload['data']['amount'], $payload['data']['currency']);
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $payment_storage->create([
      'state' => 'completed',
      'amount' => $amount,
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'remote_id' => $payload['data']['balance_id'],
      'remote_state' => 'completed',
    ]);

    $payment->save();
    return $payment;
  }

  /**
   * Verify webhook.
   */
  protected function verifyWebhookSignature(string $payload, string $signature): bool {
    // Decode base64 signature
    $signature = base64_decode($signature, TRUE);
    if (!$signature) {
      return FALSE;
    }

    // Load public key
    $publicKey = openssl_pkey_get_public($this->getPublicKey());
    if (!$publicKey) {
      return FALSE;
    }

    // Verify signature with SHA256
    $isVerified = openssl_verify(
      $payload,
      $signature,
      $publicKey,
      OPENSSL_ALGO_SHA256
    );

    if ($isVerified) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $body = $request->getContent();

    if (empty($body)) {
      throw new BadRequestHttpException();
    }

    if (!$this->verifyWebhookSignature($body, $request->headers->get('X-Signature-SHA256'))) {
      return FALSE;
    }

    if ($this->configuration['logging']) {
      $this->getLogger('commerce_wise')->debug($body);
    }
    $payload = Json::decode($body);

    if (isset($payload['data']['transfer_reference']) && $payload['event_type'] === 'balances#update') {
      $reference = $payload['data']['transfer_reference'];
      $order = is_numeric($reference) ? $this->entityTypeManager->getStorage('commerce_order')->load($reference) : NULL;
      $event = new QuickPayReferenceEvent($reference, $order);
      $this->eventDispatcher->dispatch($event, WiseEvents::WISE_QUICK_PAY_REFERENCE);
      if ($order = $event->getOrder()) {
        try {
          $this->createPayment($order, $payload);
        }
        catch (\Exception $e) {
          $this->getLogger('commerce_wise')->error($e->getMessage());
          throw new BadRequestHttpException();
        }

        $order->getState()->applyTransitionById('place');
        $order->unlock();
        $order->save();
      }
    }

    return new JsonResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function getWiseTag(): string {
    return $this->configuration['wise_tag'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicKey(): string {
    return $this->configuration['public_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountType(): string {
    return $this->configuration['account_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function generateQuickPayUrl(OrderInterface $order): string {
    $event = new QuickPayLinkEvent($order, $order->id(), $order->getTotalPrice());
    $this->eventDispatcher->dispatch($event, WiseEvents::WISE_QUICK_PAY_LINK);
    $reference = $event->getReference();
    $balance = $event->getBalance();
    return sprintf('%s/pay/business/%s?amount=%s&currency=%s&description=%s', self::WISE_API_URL[$this->getMode()], $this->getWiseTag(), $balance->getNumber(), $balance->getCurrencyCode(), $reference);

  }

}
