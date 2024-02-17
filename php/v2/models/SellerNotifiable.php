<?php

namespace NW\WebService\References\Operations\Notification\models;

/**
 * Подкласс Продавца, добавляет возможность уведомлений сотрудников
 */
class SellerNotifiable extends Seller
{
  const EVENT_RETURN = 'tsGoodsReturn';

  private array $notificationClients;

  public function __construct(array $notificationClients)
  {
    parent::__construct();

    foreach ($notificationClients as $notificationClient) {
      $this->addNotificationClient($notificationClient);
    }
  }

  private function addNotificationClient(EmployeeNotificationInterface $notificationClient): void
  {
    $this->notificationClients[] = $notificationClient;
  }

  /**
   * Отправляет уведомления сотрудникам
   *
   * @param array $templateData Данные для использования в шаблоне
   * @return bool Произошла ли отправка хотя бы одного email
   */
  public function notifyEmployees($templateData)
  {
    foreach ($this->notificationClients as $notificationClient) {
      $notificationClient
        ->setReseller($this)
        ->setRecipients($emailsTo)
        ->setParams($templateData)
        ->notify()
      ;
    }

    return false;
  }

}