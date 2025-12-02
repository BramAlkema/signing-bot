<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getDocusealId()
 * @method void setDocusealId(int $docusealId)
 * @method int|null getTemplateId()
 * @method void setTemplateId(?int $templateId)
 * @method string|null getTemplateName()
 * @method void setTemplateName(?string $templateName)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getOriginalFilePath()
 * @method void setOriginalFilePath(?string $originalFilePath)
 * @method string|null getOriginalFilename()
 * @method void setOriginalFilename(?string $originalFilename)
 * @method string|null getSignedFilePath()
 * @method void setSignedFilePath(?string $signedFilePath)
 * @method string|null getSubmittersJson()
 * @method void setSubmittersJson(?string $submittersJson)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime|null getCompletedAt()
 * @method void setCompletedAt(?\DateTime $completedAt)
 */
class Submission extends Entity
{
    protected string $userId = '';
    protected int $docusealId = 0;
    protected ?int $templateId = null;
    protected ?string $templateName = null;
    protected string $status = 'pending';
    protected ?string $originalFilePath = null;
    protected ?string $originalFilename = null;
    protected ?string $signedFilePath = null;
    protected ?string $submittersJson = null;
    protected ?\DateTime $createdAt = null;
    protected ?\DateTime $completedAt = null;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('docusealId', 'integer');
        $this->addType('templateId', 'integer');
    }

    /**
     * Get submitters as array
     */
    public function getSubmitters(): array
    {
        if (empty($this->submittersJson)) {
            return [];
        }
        return json_decode($this->submittersJson, true) ?? [];
    }

    /**
     * Set submitters from array
     */
    public function setSubmitters(array $submitters): void
    {
        $this->submittersJson = json_encode($submitters);
    }

    /**
     * Check if submission is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'userId' => $this->getUserId(),
            'docusealId' => $this->getDocusealId(),
            'templateId' => $this->getTemplateId(),
            'templateName' => $this->getTemplateName(),
            'status' => $this->getStatus(),
            'originalFilePath' => $this->getOriginalFilePath(),
            'originalFilename' => $this->getOriginalFilename(),
            'signedFilePath' => $this->getSignedFilePath(),
            'submitters' => $this->getSubmitters(),
            'createdAt' => $this->getCreatedAt()?->format('c'),
            'completedAt' => $this->getCompletedAt()?->format('c'),
        ];
    }
}
