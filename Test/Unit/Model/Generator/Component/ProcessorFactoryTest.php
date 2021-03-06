<?php

namespace Doofinder\Feed\Test\Unit\Model\Generator\Component;

use Doofinder\Feed\Test\Unit\BaseTestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * Test class for \Doofinder\Feed\Model\Generator\Component\ProcessorFactory
 */
class ProcessorFactoryTest extends BaseTestCase
{
    /**
     * @var \Doofinder\Feed\Model\Generator\Component\ProcessorFactory
     */
    private $model;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManagerMock;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->objectManagerMock = $this->getMock(
            \Magento\Framework\ObjectManagerInterface::class,
            [],
            [],
            '',
            false
        );

        $this->model = $this->getMock(
            \Doofinder\Feed\Model\Generator\Component\ProcessorFactory::class,
            null,
            [
                'objectManager' => $this->objectManagerMock,
                'instanceName' => \Test\Unit\Doofinder\Feed::class
            ]
        );
    }

    /**
     * Test create() method
     *
     * @return void
     */
    public function testCreate()
    {
        $this->objectManagerMock->expects($this->once())->method('create')
            ->with(\Test\Unit\Doofinder\Feed\Processor\Test::class, ['sample' => 'data']);

        $this->model->create(['sample' => 'data'], 'Test');
    }
}
