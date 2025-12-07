<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Submission>
 */
class SubmissionMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'ds_submissions', Submission::class);
    }

    /**
     * Find submission by ID for a specific user
     */
    public function find(int $id, string $userId): Submission
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntity($qb);
    }

    /**
     * Find submission by DocuSeal ID
     */
    public function findByDocuSealId(int $docusealId): ?Submission
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('docuseal_id', $qb->createNamedParameter($docusealId, IQueryBuilder::PARAM_INT)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Find all submissions for a user
     */
    public function findAllForUser(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('created_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Find submissions by status
     */
    public function findByStatus(string $status, ?string $userId = null): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter($status)));

        if ($userId !== null) {
            $qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        }

        $qb->orderBy('created_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Create submission from DocuSeal API response
     */
    public function createFromDocuSeal(
        array $docuSealResponse,
        string $userId,
        ?string $originalFilePath = null
    ): Submission {
        $submission = new Submission();

        $submission->setUserId($userId);
        $submission->setDocusealId((int) ($docuSealResponse['id'] ?? $docuSealResponse['submission_id'] ?? 0));
        $submission->setTemplateId($docuSealResponse['template_id'] ?? null);
        $submission->setTemplateName($docuSealResponse['template_name'] ?? null);
        $submission->setStatus('pending');
        $submission->setOriginalFilePath($originalFilePath);
        $submission->setOriginalFilename($originalFilePath ? basename($originalFilePath) : null);
        $submission->setSubmitters($docuSealResponse['submitters'] ?? []);
        $submission->setCreatedAt(new \DateTime());

        return $this->insert($submission);
    }

    /**
     * Update submission status
     */
    public function updateStatus(int $id, string $status): Submission
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        $submission = $this->findEntity($qb);
        $submission->setStatus($status);

        if ($status === 'completed') {
            $submission->setCompletedAt(new \DateTime());
        }

        return $this->update($submission);
    }

    /**
     * Delete old completed submissions (cleanup)
     */
    public function deleteOlderThan(\DateTime $date): int
    {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->lt(
                'completed_at',
                $qb->createNamedParameter($date->format('Y-m-d H:i:s'))
            ))
            ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('completed')));

        return $qb->executeStatement();
    }
}
