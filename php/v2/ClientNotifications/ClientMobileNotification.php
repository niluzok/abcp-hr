<?php

namespace NW\WebService\References\Operations\Notification\ClientNotifications;

/**
 * Класс для отправки уведомления на мобильный. Готовит и отправляет 
 * уведомление клиенту на мобильный
 */
class ClientMobileNotification implements ClientNotificationInterface
{
  private MobileNotificationService $mobileService;
  private LoggerInterface $logger;
  private Client $client;
  private Seller $reseller;

  private int $differences;

  public function __construct(MobileNotificationService $mobileService, LoggerInterface $logger)
  {
    $this->mobileService = $mobileService;
    $this->logger = $logger;
  }

  public function setClient(Client $client): self
  {
    $this->client = $client;
    return $this;
  }

  public function setReseller(Seller $reseller): self
  {
    $this->reseller = $reseller;
    return $this;
  }

  public function setParams(array $templateData): self
  {
    $this->differences = (int)$templateData['DIFFERENCES_TO'];

    return $this;
  }

  public function notify(): void
  {
    if (!empty($this->client->getMobile())) {
      $messageSent = $this->mobileService
        ->setReseller($this->reseller)
        ->setClient($this->client)
        ->setEvent(NotificationEvents::CHANGE_RETURN_STATUS)
        ->setNewStatus($this->differences)
        ->send()
      ;

      if (!$messageSent) {
        $this->logger->log("Mobile notification not sent. Errors: {$this->mobileService->lastError}");
      }
    }
  }
}
