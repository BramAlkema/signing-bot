<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Notification;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier
{
    public function __construct(
        private IFactory $l10nFactory,
        private IURLGenerator $urlGenerator,
        private IUserManager $userManager,
    ) {
    }

    public function getID(): string
    {
        return Application::APP_ID;
    }

    public function getName(): string
    {
        return $this->l10nFactory->get(Application::APP_ID)->t('DocuSeal');
    }

    public function prepare(INotification $notification, string $languageCode): INotification
    {
        if ($notification->getApp() !== Application::APP_ID) {
            throw new \InvalidArgumentException('Unknown app');
        }

        $l = $this->l10nFactory->get(Application::APP_ID, $languageCode);

        switch ($notification->getSubject()) {
            case 'signature_request':
                return $this->prepareSignatureRequest($notification, $l);

            case 'signature_completed':
                return $this->prepareSignatureCompleted($notification, $l);

            default:
                throw new \InvalidArgumentException('Unknown subject');
        }
    }

    private function prepareSignatureRequest(INotification $notification, $l): INotification
    {
        $params = $notification->getSubjectParameters();
        $fileName = $params['file'] ?? 'document';
        $senderId = $params['sender'] ?? '';
        $message = $params['message'] ?? '';

        // Get sender display name
        $senderName = $senderId;
        $sender = $this->userManager->get($senderId);
        if ($sender) {
            $senderName = $sender->getDisplayName();
        }

        $notification->setRichSubject(
            $l->t('{user} requested your signature on {file}'),
            [
                'user' => [
                    'type' => 'user',
                    'id' => $senderId,
                    'name' => $senderName,
                ],
                'file' => [
                    'type' => 'highlight',
                    'id' => $fileName,
                    'name' => $fileName,
                ],
            ]
        );

        $notification->setParsedSubject(
            $l->t('%s requested your signature on %s', [$senderName, $fileName])
        );

        if ($message) {
            $notification->setRichMessage($message);
            $notification->setParsedMessage($message);
        }

        // Link to the signing page
        $submissionId = $notification->getObjectId();
        $notification->setLink(
            $this->urlGenerator->linkToRouteAbsolute(
                Application::APP_ID . '.docuseal.getSubmission',
                ['id' => $submissionId]
            )
        );

        $notification->setIcon(
            $this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
            )
        );

        return $notification;
    }

    private function prepareSignatureCompleted(INotification $notification, $l): INotification
    {
        $params = $notification->getSubjectParameters();
        $fileName = $params['file'] ?? 'document';
        $signerId = $params['signer'] ?? '';

        // Get signer display name
        $signerName = $signerId;
        $signer = $this->userManager->get($signerId);
        if ($signer) {
            $signerName = $signer->getDisplayName();
        }

        $notification->setRichSubject(
            $l->t('{user} signed {file}'),
            [
                'user' => [
                    'type' => 'user',
                    'id' => $signerId,
                    'name' => $signerName,
                ],
                'file' => [
                    'type' => 'highlight',
                    'id' => $fileName,
                    'name' => $fileName,
                ],
            ]
        );

        $notification->setParsedSubject(
            $l->t('%s signed %s', [$signerName, $fileName])
        );

        $notification->setIcon(
            $this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
            )
        );

        return $notification;
    }
}
