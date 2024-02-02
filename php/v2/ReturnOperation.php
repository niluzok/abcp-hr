<?php

namespace NW\WebService\References\Operations\Notification;

/**
 * Обрабатывает операции уведомлений о возвратах
 */
class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * Проверяет данные, переданные в запросе
     *
     * @param array $data Входные данные 
     * @param array &$errors Собирает ошибки, найденные во время валидации
     * @return bool Валидны или нет данные
     */
    protected function validateRequestData(array $data, array &$errors): bool
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
     * Обработчик операции
     *
     * @throws \Exception Выбрасывает исключение, если валидация не пройдена или произошла ошибка
     * @return array Возвращает массив с результатами
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');

        $errors = [];
        if(!$this->validateRequestData($data, $errors)) {
            throw new \Exception(__('errorOperationInputData', $errors));
        }

        $reseller = Seller::getById($data['resellerId']);
        $client = Contractor::getById($data['clientId']);
        $creator = Employee::getById($data['creatorId']);
        $expert = Employee::getById($data['expertId']);

        $templateData = $this->prepareTemplateData($client, $creator, $expert, $data);

        $employeeEmailsSent = $reseller->notifyEmployees($templateData);

        $clientEmailSent = false;
        $clientMobileNotificationSent = false;
        $mobileError = '';

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($data['notificationType'] === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            $clientEmailSent = $client->notify($templateData);
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

    /**
     * Подготавливает данные для подстановки в шаблон
     *
     * @param Contractor $client Экземпляр заказчика (клиента)
     * @param Employee $creator Экземпляр сотрудника, создавшего операцию
     * @param Employee $expert Экземпляр экспертного сотрудника
     * @param array $data Данные, необходимые для шаблона
     * @return array Возвращает массив подготовленных данных
     */
    protected function prepareTemplateData(Contractor $client, Employee $creator, Employee $expert, array $data): array
    {

        $clientFullName = $client->getFullNameForClient();

        $differences = $this->getChangedStatusMessage($data);

        $templateData = [
            'COMPLAINT_ID' => $data['complaintId'],
            'RESELLER_ID' => $data['complaintId'],
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
            'DIFFERENCES_FROM' => $data['differences']['from'],
            'DIFFERENCES_TO' => $data['differences']['to'],
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        return $templateData;
    }

    /**
     * Генерирует текст о изменившимся статусе
     *
     * @param array $data Данные
     * @return string Возвращает строку с описанием изменения
     */
    protected function getChangedStatusMessage(array $data): string
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

    /**
     * Отправляет мобильное уведомление клиенту
     *
     * @param Contractor $client Клиент, которому будет отправлено мобильное уведомление
     * @param array $templateData Данные для уведомления
     * @param string &$error Сообщение об ошибке, если отправка уведомления не удалась
     * @return bool Возвращает true, если уведомление успешно отправлено, иначе false
     */
    protected function clientSendMobileNotification(Contractor $client, array $templateData, &$error): bool
    {
        $resellerId = $templateData['COMPLAINT_ID'];

        $res = NotificationManager::send(
            $resellerId,
            $client->id,
            NotificationEvents::CHANGE_RETURN_STATUS,
            (int)$templateData['DIFFERENCES_TO'],
            $templateData,
            $error
        );

        return (bool)$res;
    }
}
