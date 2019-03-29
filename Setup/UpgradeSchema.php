<?php

namespace Doofinder\Feed\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{
    const CRON_TABLE_NAME = 'doofinder_feed_cron';

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        echo $context->getVersion();

        if (version_compare($context->getVersion(), '0.1.13', '<')) {
            $this->_zeroOneThirteen($setup);
        }

        $setup->endSetup();
    }

    /**
     * @param $setup
     */
    private function _zeroOneThirteen($setup)
    {
        $tableName = $setup->getTable(self::CRON_TABLE_NAME);

        if ($setup->getConnection()->isTableExists($tableName) == true) {
            $connection = $setup->getConnection();
            $connection->changeColumn(
                $tableName,
                'offset',
                'offset',
                ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, 'nullable' => false, 'default' => '0']
            );
        }
    }
}