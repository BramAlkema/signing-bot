<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20241202 extends SimpleMigrationStep
{
    /**
     * @param IOutput $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('ds_submissions')) {
            $table = $schema->createTable('ds_submissions');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);

            $table->addColumn('docuseal_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->addColumn('template_id', Types::BIGINT, [
                'notnull' => false,
                'unsigned' => true,
            ]);

            $table->addColumn('template_name', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);

            $table->addColumn('status', Types::STRING, [
                'notnull' => true,
                'length' => 50,
                'default' => 'pending',
            ]);

            $table->addColumn('original_file_path', Types::STRING, [
                'notnull' => false,
                'length' => 4000,
            ]);

            $table->addColumn('original_filename', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);

            $table->addColumn('signed_file_path', Types::STRING, [
                'notnull' => false,
                'length' => 4000,
            ]);

            $table->addColumn('submitters_json', Types::TEXT, [
                'notnull' => false,
            ]);

            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->addColumn('completed_at', Types::DATETIME, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'ds_sub_user_idx');
            $table->addIndex(['docuseal_id'], 'ds_sub_dsid_idx');
            $table->addIndex(['status'], 'ds_sub_status_idx');
            $table->addIndex(['created_at'], 'ds_sub_created_idx');
        }

        return $schema;
    }
}
