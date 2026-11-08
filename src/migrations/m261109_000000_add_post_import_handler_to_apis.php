<?php

namespace runwildstudio\easyapi\migrations;

use craft\db\Migration;

class m261109_000000_add_post_import_handler_to_apis extends Migration
{
    public function safeUp(): void
    {
        $table = $this->db->getTableSchema('{{%easyapi_apis}}');
        if ($table !== null && !isset($table->columns['postImportHandler'])) {
            $this->addColumn('{{%easyapi_apis}}', 'postImportHandler', $this->string()->null()->after('duplicateHandle'));
        }
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%easyapi_apis}}', 'postImportHandler');
    }
}
