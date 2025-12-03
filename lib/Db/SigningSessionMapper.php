<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<SigningSession>
 */
class SigningSessionMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'docuseal_signing_sessions', SigningSession::class);
    }

    /**
     * Find session by ID for a specific user
     */
    public function find(int $id, string $userId): SigningSession
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntity($qb);
    }

    /**
     * Find session by room ID
     */
    public function findByRoomId(string $roomId): ?SigningSession
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('room_id', $qb->createNamedParameter($roomId)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Find all sessions for a user
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
     * Find sessions by status
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
     * Create a new signing session
     */
    public function create(array $data): SigningSession
    {
        $session = new SigningSession();

        $session->setUserId($data['user_id']);
        $session->setRoomId($data['room_id']);
        $session->setDocumentName($data['document_name']);
        $session->setDocumentHash($data['document_hash']);
        $session->setMxcUri($data['mxc_uri']);
        $session->setOriginalFilePath($data['original_file_path'] ?? null);
        $session->setRequiredSigners($data['required_signers']);
        $session->setStatus($data['status'] ?? 'pending');
        $session->setCreatedAt(new \DateTime());

        return $this->insert($session);
    }

    /**
     * Find sessions where user is a required signer
     */
    public function findWhereUserIsSigner(string $matrixId): array
    {
        $qb = $this->db->getQueryBuilder();

        // JSON search - this is a simplistic approach
        // For production, consider a separate signers table
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->like(
                'required_signers',
                $qb->createNamedParameter('%' . $matrixId . '%')
            ))
            ->orderBy('created_at', 'DESC');

        return $this->findEntities($qb);
    }
}
