<?php

namespace runwildstudio\easyapi\migrations;

use craft\db\Migration;

class m251126_000001_add_authorization_scope_to_apis extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%easyapi_apis}}', 'authorizationScope', $this->string()->null());
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%easyapi_apis}}', 'authorizationScope');
    }
}
