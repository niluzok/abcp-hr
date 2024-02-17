<?php

namespace NW\WebService\References\Operations\Notification\ClientNotifications;

/**
 * Класс для отправки email. Готовит и отправляет email клиенту
 */
class ClientEmailNotification implements ClientNotificationInterface
{
  private EmailNotificationService $emailService;
  private LoggerInterface $logger;
  private Client $client;
  private Seller $reseller;

  private string $subject;
  private string $message;

  public function __construct(EmailNotificationService $emailService, LoggerInterface $logger)
  {
    $this->emailService = $emailService;
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
    $this->subject = $templateData['subject'] ?? null;
    $this->message = $templateData['message'] ?? null;

    return $this;
  }

  public function notify(): void
  {
    $resellerEmailFrom = $this->reseller->getEmailFrom();

    if (!empty($resellerEmailFrom) && !empty($this->client->getEmail())) {
      $messageSent = $this->emailService
        ->setEvent(NotificationEvents::CHANGE_RETURN_STATUS)
        ->setFrom($resellerEmailFrom)
        ->setTo($this->client->getEmail())
        ->setSubject(__('complaintEmployeeEmailSubject', $this->subject))
        ->setMessage(__('complaintEmployeeEmailBody', $this->message))
        ->send()
      ;

      if (!$messageSent) {
        $this->logger->log("Email not sent. Errors: {$this->emailService->lastError}");
      }
    }
  }
}
