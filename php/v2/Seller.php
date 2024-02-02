<?php

namespace NW\WebService\References\Operations\Notification;

class Seller extends Contractor
{
  const EVENT_RETURN = 'tsGoodsReturn';

  private EmailNotificationService $emailService;
  private Logger $logger;

  public function __construct(EmailNotificationService $emailService, Logger $logger)
  {
    $this->emailService = $emailService;
    $this->logger = $logger;
  }

  protected function getEmailFrom()
  {
    return 'contractor@example.com';
  }

  protected function getEmailsByPermit($event)
  {
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com'];
  }

  /**
   * Отправляет email-уведомления сотрудникам
   *
   * @param array $templateData Данные для использования в шаблоне
   * @return bool Произошла ли отправка хотя бы одного email
   */
  public function notifyEmployees($templateData)
  {
    $emailFrom = $this->getEmailFrom();
    $emailsTo = $this->getEmailsByPermit($this->id, self::EVENT_RETURN);

    if (!empty($emailFrom) && count($emailsTo) > 0) {
      foreach ($emailsTo as $emailTo) {
        $messageSent = $this->emailService
          ->setEvent(NotificationEvents::CHANGE_RETURN_STATUS)
          ->setFrom($emailFrom)
          ->setTo($emailTo)
          ->setSubject(__('complaintEmployeeEmailSubject', $templateData))
          ->setMessage(__('complaintEmployeeEmailBody', $templateData))
          ->send();

        if (!$messageSent) {
          $this->logger->log("Email not send. Errors: {$this->emailService->lastError}");
        }
      }

      return true;
    }

    return false;
  }
}