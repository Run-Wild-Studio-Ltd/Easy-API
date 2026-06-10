<?php

namespace runwildstudio\easyapi\migrations;

use craft\db\Migration;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createTables();

        return true;
    }

    public function safeDown(): bool
    {
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    protected function createTables(): void
    {
        $this->createTable('{{%easyapi_apis}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'apiUrl' => $this->text()->notNull(),
            'contentType' => $this->string(),
            'authorizationType' => $this->string(),
            'authorizationUrl' => $this->string(),
            'authorizationAppId' => $this->string(),
            'authorizationAppSecret' => $this->string(),
            'authorizationScope' => $this->string(),
            'authorizationGrantType' => $this->string(),
            'authorizationUsername' => $this->string(),
            'authorizationPassword' => $this->string(),
            'authorizationRedirect' => $this->string(),
            'authorizationCode' => $this->string(),
            'authorizationRefreshToken' => $this->string(),
            'authorizationCustomParameters' => $this->string(),
            'authorization' => $this->string(),
            'httpAction' => $this->string(),
            'updateElementIdField' => $this->text(),
            'direction' => $this->string(),
            'requestHeader' => $this->string(),
            'requestBody' => $this->string(),
            'siteId' => $this->string(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'parentElementType' => $this->string(),
            'parentElementGroup' => $this->text(),
            'parentElementIdField' => $this->text(),
            'parentFilter' => $this->text(),
            'offsetField' => $this->text(),
            'offsetUpateURL' => $this->text(),
            'offsetTermination' => $this->text(),
            'queueRequest' => $this->boolean()->notNull()->defaultValue(false),
            'queueOrder' => $this->text(),
            'useLive' => $this->boolean()->notNull()->defaultValue(false),
            'feedId' => $this->string(),

            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),

            //Feed Me fields required
            'elementType' => $this->string()->notNull(),
            'elementGroup' => $this->text(),
            'duplicateHandle' => $this->text(),
            'postImportHandler' => $this->string(),
        ]);
    }

    protected function removeTables(): void
    {
        $this->dropTableIfExists('{{%easyapi_apis}}');
    }
}
