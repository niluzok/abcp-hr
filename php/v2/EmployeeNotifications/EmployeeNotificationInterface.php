<?php

namespace NW\WebService\References\Operations\Notification\EmployeeNotifications;

interface EmployeeNotificationInterface
{
  public function setClient(Client $client): self;
  public function setReseller(Seller $reseller): self;
  public function setParams(array $templateData): self;
  public function notify(): void;
}
