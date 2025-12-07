<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add Matrix signing sessions table
 */
class Version001001Date20241203 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('ds_sign_sessions')) {
            $table = $schema->createTable('ds_sign_sessions');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);

            $table->addColumn('room_id', Types::STRING, [
                'notnull' => true,
                'length' => 255,
                'comment' => 'Matrix room ID',
            ]);

            $table->addColumn('document_name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);

            $table->addColumn('document_hash', Types::STRING, [
                'notnull' => true,
                'length' => 64,
                'comment' => 'SHA-256 hash of document',
            ]);

            $table->addColumn('mxc_uri', Types::STRING, [
                'notnull' => true,
                'length' => 255,
                'comment' => 'Matrix content URI for document',
            ]);

            $table->addColumn('original_file_path', Types::STRING, [
                'notnull' => false,
                'length' => 4000,
            ]);

            $table->addColumn('required_signers', Types::TEXT, [
                'notnull' => true,
                'comment' => 'JSON array of Matrix user IDs',
            ]);

            $table->addColumn('status', Types::STRING, [
                'notnull' => true,
                'length' => 50,
                'default' => 'pending',
            ]);

            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->addColumn('completed_at', Types::DATETIME, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'ds_sess_user_idx');
            $table->addIndex(['room_id'], 'ds_sess_room_idx');
            $table->addIndex(['document_hash'], 'ds_sess_hash_idx');
            $table->addIndex(['status'], 'ds_sess_status_idx');
        }

        return $schema;
    }
}
