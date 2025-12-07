<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getRoomId()
 * @method void setRoomId(string $roomId)
 * @method string getDocumentName()
 * @method void setDocumentName(string $documentName)
 * @method string getDocumentHash()
 * @method void setDocumentHash(string $documentHash)
 * @method string getMxcUri()
 * @method void setMxcUri(string $mxcUri)
 * @method string|null getOriginalFilePath()
 * @method void setOriginalFilePath(?string $originalFilePath)
 * @method string getRequiredSigners()
 * @method void setRequiredSigners(string $requiredSigners)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method \DateTime|null getCreatedAt()
 * @method void setCreatedAt(?\DateTime $createdAt)
 * @method \DateTime|null getCompletedAt()
 * @method void setCompletedAt(?\DateTime $completedAt)
 */
class SigningSession extends Entity
{
    protected string $userId = '';
    protected string $roomId = '';
    protected string $documentName = '';
    protected string $documentHash = '';
    protected string $mxcUri = '';
    protected ?string $originalFilePath = null;
    protected string $requiredSigners = '[]';
    protected string $status = 'pending';
    protected ?\DateTime $createdAt = null;
    protected ?\DateTime $completedAt = null;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('createdAt', 'datetime');
        $this->addType('completedAt', 'datetime');
    }

    /**
     * Get required signers as array
     */
    public function getRequiredSignersArray(): array
    {
        return json_decode($this->requiredSigners, true) ?? [];
    }

    /**
     * Check if session is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Convert to array for JSON response (snake_case for consistency)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
            'room_id' => $this->getRoomId(),
            'document_name' => $this->getDocumentName(),
            'document_hash' => $this->getDocumentHash(),
            'mxc_uri' => $this->getMxcUri(),
            'original_file_path' => $this->getOriginalFilePath(),
            'required_signers' => $this->getRequiredSignersArray(),
            'status' => $this->getStatus(),
            'created_at' => $this->getCreatedAt()?->format('c'),
            'completed_at' => $this->getCompletedAt()?->format('c'),
        ];
    }
}
