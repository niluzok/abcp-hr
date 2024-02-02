<?php

namespace NW\WebService\References\Operations\Notification;

class Client extends Contractor
{
    private EmailNotificationService $emailService;
    private MobileNotificationService $mobileService;
    private Logger $logger;

    public function __construct(EmailNotificationService $emailService, MobileNotificationService $mobileService, Logger $logger)
    {
        $this->emailService = $emailService;
        $this->mobileService = $mobileService;
        $this->logger = $logger;
    }

    /**
     * Отправляет уведомления клиенту
     *
     * @param Seller $reseller 
     * @param array $templateData Данные для использования в шаблоне email
     * @return void
     */
    protected function notify(Seller $reseller, array $templateData): void
    {
        $emailSent = $this->notifyByEmail($reseller, $templateData);
        
        $mobileSent = false;

        if (!empty($this->mobile)) {
            $mobileSent = $this->notifyByMobile($reseller, $templateData);
        }

        return $emailSent && $mobileSent;
    }

    /**
     * Отправляет уведомления по email
     *
     * @param Seller $reseller
     * @param array $templateData Данные для заполнения шаблона письма
     * @return bool
     */
    protected function notifyByEmail(Seller $reseller, array $templateData): bool
    {
        $resellerEmailFrom = $reseller->getEmailFrom();

        if (!empty($resellerEmailFrom) && !empty($this->email)) {
            $messageSent = $this->emailService
                ->setEvent(NotificationEvents::CHANGE_RETURN_STATUS)
                ->setFrom($resellerEmailFrom)
                ->setTo($this->email)
                ->setSubject(__('complaintEmployeeEmailSubject', $templateData))
                ->setMessage(__('complaintEmployeeEmailBody', $templateData))
                ->send();

            if (!$messageSent) {
                $this->logger->log("Email not send. Errors: {$this->emailService->lastError}");
            }

            return true;
        }

        return false;
    }

   /**
    * Отправляет уведомления на мобильный
    *
    * @param Seller $reseller
    * @param array $templateData Данные для заполнения шаблона письма
    * @return bool
    */
    protected function notifyByMobile(Seller $reseller, array $templateData): bool
    {
        if(!$this->mobile) {
          return;
        }

        $messageSent = $this->mobileService
            ->setReseller($reseller)
            ->setClient($this)
            ->setEvent(NotificationEvents::CHANGE_RETURN_STATUS)
            ->setNewStatus((int)$templateData['DIFFERENCES_TO'])
            ->send();

        if (!$messageSent) {
          $this->logger->log("Mobile notification not send. Errors: {$this->mobileService->lastError}");
        }

        return $messageSent;
    }
}