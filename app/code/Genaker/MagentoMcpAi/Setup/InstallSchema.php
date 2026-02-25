<?php
namespace Genaker\MagentoMcpAi\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Install chatbot conversations table
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        // Create chatbot_conversations table
        if (!$installer->tableExists('chatbot_conversations')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable('chatbot_conversations')
            )->addColumn(
                'conversation_id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
                'Conversation ID'
            )->addColumn(
                'customer_email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Customer Email'
            )->addColumn(
                'customer_name',
                Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Customer Name'
            )->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                'Store ID'
            )->addColumn(
                'status',
                Table::TYPE_TEXT,
                50,
                ['nullable' => false, 'default' => 'active'],
                'Conversation Status'
            )->addColumn(
                'conversation_data',
                Table::TYPE_TEXT,
                '2M',
                ['nullable' => true],
                'Conversation Data (JSON)'
            )->addColumn(
                'started_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Started At'
            )->addColumn(
                'last_activity_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                'Last Activity At'
            )->addColumn(
                'closed_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => true],
                'Closed At'
            )->addColumn(
                'ip_address',
                Table::TYPE_TEXT,
                40,
                ['nullable' => true],
                'Customer IP Address'
            )->addColumn(
                'user_agent',
                Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Customer User Agent'
            )->addColumn(
                'transcript_sent',
                Table::TYPE_BOOLEAN,
                null,
                ['nullable' => false, 'default' => 0],
                'Transcript Sent to Support'
            )->addIndex(
                $installer->getIdxName('chatbot_conversations', ['customer_email']),
                ['customer_email']
            )->addIndex(
                $installer->getIdxName('chatbot_conversations', ['store_id']),
                ['store_id']
            )->addIndex(
                $installer->getIdxName('chatbot_conversations', ['status']),
                ['status']
            )->addIndex(
                $installer->getIdxName('chatbot_conversations', ['started_at']),
                ['started_at']
            )->addIndex(
                $installer->getIdxName('chatbot_conversations', ['last_activity_at']),
                ['last_activity_at']
            )->setComment('Chatbot Conversations Table');
            
            $installer->getConnection()->createTable($table);
        }

        // Create chatbot_messages table
        if (!$installer->tableExists('chatbot_messages')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable('chatbot_messages')
            )->addColumn(
                'message_id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
                'Message ID'
            )->addColumn(
                'conversation_id',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true],
                'Conversation ID'
            )->addColumn(
                'is_from_customer',
                Table::TYPE_BOOLEAN,
                null,
                ['nullable' => false, 'default' => 1],
                'Is Message From Customer'
            )->addColumn(
                'message_content',
                Table::TYPE_TEXT,
                '64k',
                ['nullable' => false],
                'Message Content'
            )->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )->addColumn(
                'tokens_used',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => true, 'unsigned' => true],
                'Tokens Used'
            )->addIndex(
                $installer->getIdxName('chatbot_messages', ['conversation_id']),
                ['conversation_id']
            )->addForeignKey(
                $installer->getFkName('chatbot_messages', 'conversation_id', 'chatbot_conversations', 'conversation_id'),
                'conversation_id',
                $installer->getTable('chatbot_conversations'),
                'conversation_id',
                Table::ACTION_CASCADE
            )->setComment('Chatbot Messages Table');

            $installer->getConnection()->createTable($table);
        }

        $installer->endSetup();
    }
}
