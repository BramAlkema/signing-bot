<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Controller;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCA\DocuSealIntegration\Db\SigningSessionMapper;
use OCA\DocuSealIntegration\Service\MatrixService;
use OCA\DocuSealIntegration\Service\SignatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;

class MatrixSigningController extends Controller
{
    public function __construct(
        IRequest $request,
        private MatrixService $matrixService,
        private SignatureService $signatureService,
        private SigningSessionMapper $signingSessionMapper,
        private IRootFolder $rootFolder,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Test Matrix connection
     */
    #[NoAdminRequired]
    public function testConnection(): JSONResponse
    {
        $result = $this->matrixService->testConnection();
        return new JSONResponse($result);
    }

    /**
     * Create a new signing session
     *
     * Creates a Matrix room and uploads the document
     */
    #[NoAdminRequired]
    public function createSession(): JSONResponse
    {
        $filePath = $this->request->getParam('file_path');
        $signers = $this->request->getParam('signers', []); // Array of Matrix IDs

        if (!$filePath || empty($signers)) {
            return new JSONResponse(
                ['error' => 'File path and signers are required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            // Get file from Nextcloud
            $userFolder = $this->rootFolder->getUserFolder($this->userId);
            $file = $userFolder->get($filePath);

            if ($file->getMimeType() !== 'application/pdf') {
                return new JSONResponse(
                    ['error' => 'Only PDF files are supported'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $fileContent = $file->getContent();
            $fileName = $file->getName();
            $documentHash = $this->signatureService->hashDocument($fileContent);

            // Create Matrix signing room
            $roomResult = $this->matrixService->createSigningRoom(
                $fileName,
                $documentHash,
                $signers
            );

            // Post document to room
            $docResult = $this->matrixService->postDocumentForSigning(
                $roomResult['room_id'],
                $fileContent,
                $fileName,
                $signers
            );

            // Store session in database
            $session = $this->signingSessionMapper->create([
                'user_id' => $this->userId,
                'room_id' => $roomResult['room_id'],
                'document_name' => $fileName,
                'document_hash' => $documentHash,
                'mxc_uri' => $docResult['mxc_uri'],
                'original_file_path' => $filePath,
                'required_signers' => json_encode($signers),
                'status' => 'pending',
            ]);

            return new JSONResponse([
                'session_id' => $session->getId(),
                'room_id' => $roomResult['room_id'],
                'document_hash' => $documentHash,
                'signing_instructions' => $this->signatureService->getSigningInstructions($documentHash),
            ], Http::STATUS_CREATED);

        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get signing session status
     */
    #[NoAdminRequired]
    public function getSession(int $sessionId): JSONResponse
    {
        try {
            $session = $this->signingSessionMapper->find($sessionId, $this->userId);

            // Get signatures from Matrix room
            $signatures = $this->matrixService->getSignatures(
                $session->getRoomId(),
                $session->getDocumentHash()
            );

            // Verify each signature
            $verifiedSignatures = [];
            foreach ($signatures as $sig) {
                $verification = $this->signatureService->verifySignature(
                    $session->getDocumentHash(),
                    $sig['signature'],
                    $sig['public_key'],
                    $sig['key_type']
                );

                $verifiedSignatures[] = array_merge($sig, [
                    'verified' => $verification['valid'],
                    'verification_details' => $verification,
                ]);
            }

            // Check if all required signers have signed
            $requiredSigners = json_decode($session->getRequiredSigners(), true);
            $signedBy = array_column($verifiedSignatures, 'signer');
            $allSigned = empty(array_diff($requiredSigners, $signedBy));

            return new JSONResponse([
                'session' => $session->toArray(),
                'signatures' => $verifiedSignatures,
                'all_signed' => $allSigned,
                'missing_signers' => array_diff($requiredSigners, $signedBy),
            ]);

        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    /**
     * List all signing sessions for current user
     */
    #[NoAdminRequired]
    public function listSessions(): JSONResponse
    {
        try {
            $sessions = $this->signingSessionMapper->findAllForUser($this->userId);

            $result = [];
            foreach ($sessions as $session) {
                $result[] = $session->toArray();
            }

            return new JSONResponse($result);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Submit a signature for a document
     *
     * The signature should be created client-side using SSH or GPG
     */
    #[NoAdminRequired]
    public function submitSignature(int $sessionId): JSONResponse
    {
        $signature = $this->request->getParam('signature');
        $publicKey = $this->request->getParam('public_key');
        $keyType = $this->request->getParam('key_type', 'ssh-ed25519');
        $matrixId = $this->request->getParam('matrix_id');

        if (!$signature || !$publicKey || !$matrixId) {
            return new JSONResponse(
                ['error' => 'Signature, public key, and Matrix ID are required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $session = $this->signingSessionMapper->find($sessionId, $this->userId);

            // Verify the signature locally first
            $verification = $this->signatureService->verifySignature(
                $session->getDocumentHash(),
                $signature,
                $publicKey,
                $keyType
            );

            if (!$verification['valid']) {
                return new JSONResponse(
                    ['error' => 'Invalid signature', 'details' => $verification],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Post signature to Matrix room
            $eventId = $this->matrixService->postSignature(
                $session->getRoomId(),
                $session->getDocumentHash(),
                $signature,
                $publicKey,
                $keyType,
                $matrixId
            );

            // Check if all signers have now signed
            $signatures = $this->matrixService->getSignatures(
                $session->getRoomId(),
                $session->getDocumentHash()
            );
            $requiredSigners = json_decode($session->getRequiredSigners(), true);
            $signedBy = array_column($signatures, 'signer');
            $allSigned = empty(array_diff($requiredSigners, $signedBy));

            if ($allSigned) {
                $session->setStatus('completed');
                $session->setCompletedAt(new \DateTime());
                $this->signingSessionMapper->update($session);

                // Post completion message
                $this->matrixService->sendMessage(
                    $session->getRoomId(),
                    "✅ Document fully signed!\n\n" .
                    "All required parties have signed: " . implode(', ', $signedBy) . "\n\n" .
                    "Document: {$session->getDocumentName()}\n" .
                    "Hash: {$session->getDocumentHash()}"
                );
            }

            return new JSONResponse([
                'event_id' => $eventId,
                'verified' => true,
                'all_signed' => $allSigned,
            ]);

        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get signing instructions for a session
     */
    #[NoAdminRequired]
    public function getInstructions(int $sessionId): JSONResponse
    {
        try {
            $session = $this->signingSessionMapper->find($sessionId, $this->userId);

            return new JSONResponse([
                'document_hash' => $session->getDocumentHash(),
                'instructions' => $this->signatureService->getSigningInstructions(
                    $session->getDocumentHash()
                ),
            ]);

        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    /**
     * Verify a signature independently
     */
    #[NoAdminRequired]
    public function verifySignature(): JSONResponse
    {
        $data = $this->request->getParam('data');
        $signature = $this->request->getParam('signature');
        $publicKey = $this->request->getParam('public_key');
        $keyType = $this->request->getParam('key_type', 'ssh-ed25519');

        if (!$data || !$signature || !$publicKey) {
            return new JSONResponse(
                ['error' => 'Data, signature, and public key are required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $result = $this->signatureService->verifySignature(
                $data,
                $signature,
                $publicKey,
                $keyType
            );

            return new JSONResponse($result);

        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Download document from a signing session
     */
    #[NoAdminRequired]
    public function downloadDocument(int $sessionId): JSONResponse
    {
        try {
            $session = $this->signingSessionMapper->find($sessionId, $this->userId);

            $content = $this->matrixService->downloadFile($session->getMxcUri());
            $hash = hash('sha256', $content);

            // Verify hash matches
            if ($hash !== $session->getDocumentHash()) {
                return new JSONResponse(
                    ['error' => 'Document hash mismatch - file may have been tampered with'],
                    Http::STATUS_CONFLICT
                );
            }

            return new JSONResponse([
                'content' => base64_encode($content),
                'filename' => $session->getDocumentName(),
                'hash' => $hash,
                'verified' => true,
            ]);

        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_NOT_FOUND
            );
        }
    }
}
