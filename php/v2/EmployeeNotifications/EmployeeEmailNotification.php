<?php

namespace NW\WebService\References\Operations\Notification\EmployeeNotifications;

/**
 * Класс готовит и отправляет email сотрудникам
 */
class EmployeeEmailNotification implements EmployeeNotificationInterface
{
  private EmailNotificationService $emailService;
  private LoggerInterface $logger;
  private Seller $reseller;

  private string $subject;
  private string $message;

  public function __construct(EmailNotificationService $emailService, LoggerInterface $logger)
  {
    $this->emailService = $emailService;
    $this->logger = $logger;
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
    $emailsTo = $this->reseller->getEmailsByPermit($this->id, self::EVENT_RETURN);
    
    foreach ($emailsTo as $emailTo) {
      if (!empty($resellerEmailFrom) && !empty($recipient)) {
        $messageSent = $this->emailService
          ->setEvent(NotificationEvents::CHANGE_RETURN_STATUS)
          ->setFrom($resellerEmailFrom)
          ->setTo($emailTo)
          ->setSubject(__('complaintEmployeeEmailSubject', $this->subject))
          ->setMessage(__('complaintEmployeeEmailBody', $this->message))
          ->send();

        if (!$messageSent) {
          $this->logger->log("Email not sent. Errors: {$this->emailService->lastError}");
        }
      }
    }
  }
}
