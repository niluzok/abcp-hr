<?php

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    protected function validateRequestData($data, &$errors)
    {
        if (empty($data['resellerId'])) {
            $errors[] = 'Empty resellerId';
        }

        if (empty($data['notificationType'])) {
            $errors[] = 'Empty notificationType';
        }

        if (!Seller::getById($data['resellerId'])) {
            $errors[] = 'Seller not found!';
        }

        if (!Employee::getById($data['creatorId'])) {
            $errors[] = 'Creator not found!';
        }

        if (!Employee::getById($data['expertId'])) {
            $errors[] = 'Expert not found!';
        }

        $client = Contractor::getById($data['clientId']);
        if ($client === null || !$client->isCustomer() || ($client->getSellerId() !== $data['resellerId'])) {
            $errors[] = 'Client not found or mismatch!';
        }

        //  some $data['differences'] validations

        return empty($errors);
    }

    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');

        $errors = [];
        if(!$this->validateRequestData($data, $errors)) {
            throw \Exception(__('errorOperationInputData', $errors));
        }

        $client = Contractor::getById($data['clientId']);
        $creator = Employee::getById($data['creatorId']);
        $expert = Employee::getById($data['expertId']);

        $templateData = $this->prepareTemplateData($client, $creator, $expert, $data);

        $employeeEmailsSent = $this->employeeSendEmailNotifications($templateData);

        $clientEmailSent = false;
        $clientMobileNotificationSent = false;
        $mobileError = '';

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            $clientEmailSent = $this->clientSendEmailNotification($client, $templateData);

            if (!empty($client->mobile)) {
                $clientMobileNotificationSent = $this->clientSendMobileNotification($client, $templateData, $mobileError);
            }
        }

        return [
            'notificationEmployeeByEmail' => $employeeEmailsSent ?? false,
            'notificationClientByEmail' => $clientEmailSent ?? false,
            'notificationClientBySms' => [
                'isSent' => $clientMobileNotificationSent, 
                'message' => $mobileError,
            ],
        ];
    }

    protected function prepareTemplateData(Contractor $client, Employee $creator, Employee $expert, $data): array
    {

        $clientFullName = $client->getFullNameForClient();

        $differences = $this->getDifferencesText($data);

        $templateData = [
            'COMPLAINT_ID' => $data['complaintId'],
            'COMPLAINT_NUMBER' => $data['complaintNumber'],
            'CREATOR_ID' => $data['creatorId'],
            'CREATOR_NAME' => $creator->getFullName(), // Assume getFullName() exists
            'EXPERT_ID' => $data['expertId'],
            'EXPERT_NAME' => $expert->getFullName(), // Assume getFullName() exists
            'CLIENT_ID' => $data['clientId'],
            'CLIENT_NAME' => $clientFullName,
            'CONSUMPTION_ID' => $data['consumptionId'],
            'CONSUMPTION_NUMBER' => $data['consumptionNumber'],
            'AGREEMENT_NUMBER' => $data['agreementNumber'],
            'DATE' => $data['date'],
            'DIFFERENCES' => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        return $templateData;
    }

    protected function getDifferencesText($data): string
    {
        if ($data['notificationType'] === self::TYPE_NEW) {
            return __('NewPositionAdded', null, $data['resellerId']);
        } elseif ($data['notificationType'] === self::TYPE_CHANGE && !empty($data['differences'])) {
            return __('PositionStatusHasChanged', [
                'FROM' => Status::getName($data['differences']['from']),
                'TO' => Status::getName($data['differences']['to']),
            ], $data['resellerId']);
        }

        return '';
    }

    protected function employeeSendEmailNotifications(array $templateData): bool
    {
        $resellerId = $templateData['COMPLAINT_ID'];
        $resellerEmailFrom = getResellerEmailFrom($resellerId);
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');

        if (!empty($resellerEmailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    [
                        'emailFrom' => $resellerEmailFrom,
                        'emailTo'   => $email,
                        'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                        'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                
            }
            
            return true;
        }

        return false;
    }

    protected function clientSendEmailNotification(Contractor $client, array $templateData): bool
    {
        $resellerId = $templateData['COMPLAINT_ID'];
        $resellerEmailFrom = getResellerEmailFrom($resellerId);
        
        if (!empty($resellerEmailFrom) && !empty($client->email)) {
            MessagesClient::sendMessage([
                [
                    'emailFrom' => $resellerEmailFrom,
                    'emailTo'   => $client->email,
                    'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                    'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                ],
            ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
            
            return true;
        }

        return false;
    }

    protected function clientSendMobileNotification(Contractor $client, $templateData, &$error): bool
    {
        $resellerId = $templateData['COMPLAINT_ID'];

        $res = NotificationManager::send(
            $resellerId,
            $client->id,
            NotificationEvents::CHANGE_RETURN_STATUS,
            (int)$data['differences']['to'],
            $templateData,
            $error
        );

        return (bool)$res;
    }
}
