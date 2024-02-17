<?php

namespace NW\WebService\References\Operations\Notification\models;

/**
 * Подкласс Клиента, добавляет возможность отправлять клиенту уведомления
 */
class ClientNotifiable extends Client
{
    private array $notificationClients;

    public function __construct(array $notificationClients)
    {
        parent::__construct();
        
        foreach ($notificationClients as $notificationClient) {
            $this->addNotificationClient($notificationClient);
        }
    }

    private function addNotificationClient(ClientNotificationInterface $notificationClient): void
    {
        $this->notificationClients[] = $notificationClient;
    }

    protected function notify(Seller $reseller, array $templateData): void
    {
        foreach ($this->notificationClients as $notification) {
            $notification
                ->setClient($this)
                ->setReseller($reseller)
                ->setParams($templateData)
                ->notify()
            ;
        }
    }

}
