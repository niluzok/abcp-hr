<?php

namespace NW\WebService\References\Operations\Notification\ClientNotifications;

interface ClientNotificationInterface
{
  public function setClient(Client $client): self;
  public function setReseller(Seller $reseller): self;
  public function setParams(array $templateData): self;
  public function notify(): void;
}
