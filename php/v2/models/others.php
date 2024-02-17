<?php

namespace NW\WebService\References\Operations\Notification\models;

/**
 * @property Seller $Seller
 */
class Contractor
{
    const TYPE_CUSTOMER = 0;
    public $id;
    public $type;
    public $name;

    public static function getById(int $resellerId): self
    {
        return new self($resellerId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }

    public function getFullNameForClient(): string
    {
        return $this->getFullName() ?? $this->name;
    }
}

class Employee extends Contractor
{
}

class Client extends Contractor
{
}

class Seller extends Contractor
{
    const EVENT_RETURN = 'tsGoodsReturn';

    protected function getEmailFrom()
    {
        return 'contractor@example.com';
    }

    protected function getEmailsByPermit($event)
    {
        // fakes the method
        return ['someemeil@example.com', 'someemeil2@example.com'];
    }
}

class Status
{
    public $id, $name;

    public static function getName(int $id): string
    {
        $a = [
            0 => 'Completed',
            1 => 'Pending',
            2 => 'Rejected',
        ];

        return $a[$id];
    }
}

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    public function getRequest($pName)
    {
        return $_REQUEST[$pName];
    }
}

class NotificationEvents
{
    const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    const NEW_RETURN_STATUS    = 'newReturnStatus';
}