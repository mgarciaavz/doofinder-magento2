<?php

namespace Doofinder\Feed\Controller\Feed;

/**
 * Class Index
 *
 * @package Doofinder\Feed\Controller\Feed
 */
class Index extends \Doofinder\Feed\Controller\Base
{
    /**
     * @var \Doofinder\Feed\Helper\Data
     */
    protected $_helperData;

    /**
     * @var \Doofinder\Feed\Model\GeneratorFactory
     */
    protected $_generatorFactory;

    /**
     * @var \Doofinder\Feed\Helper\FeedConfig
     */
    protected $_feedConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInteface
     */
    protected $_storeManager;

    /**
     * Index constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
     * @param \Doofinder\Feed\Helper\Data $helperData
     * @param \Doofinder\Feed\Model\GeneratorFactory $generatorFactory
     * @param \Doofinder\Feed\Helper\FeedConfig $feedConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Doofinder\Feed\Helper\Data $helperData,
        \Doofinder\Feed\Model\GeneratorFactory $generatorFactory,
        \Doofinder\Feed\Helper\FeedConfig $feedConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context, $jsonResultFactory);

        $this->_helperData = $helperData;
        $this->_generatorFactory = $generatorFactory;
        $this->_feedConfig = $feedConfig;
        $this->_storeManager = $storeManager;
    }

    /**
     * Execute. Return feed with xml content type.
     */
    public function execute()
    {
        $storeCode = $this->getParamString('store');

        // Do not proceed if password check fails
        if (!$this->checkPassword($storeCode)) {
            throw new \Magento\Framework\Exception\NotFoundException(
                __('Unauthorized access to feed.')
            );
        }

        $feedConfig = $this->_feedConfig->getFeedConfig($storeCode, $this->getFeedCustomParams());

        // Set current store for generator
        if ($storeCode) {
            $this->_storeManager->setCurrentStore($storeCode);
        }

        // Enforce transforming offset to last processed entity id
        $feedConfig['data']['config']['fetchers']['Product']['transform_offset'] = true;

        $generator = $this->_generatorFactory->create($feedConfig);
        $generator->run();

        $feed = $generator->getProcessor('Xml')->getFeed();

        $this->_setXmlHeaders();
        $this->getResponse()->setBody($feed);
    }

    /**
     * Get custom params for generator.
     *
     * @return array
     */
    protected function getFeedCustomParams()
    {
        $params = [
            'offset' => $this->getParamInt('offset'),
            'limit' => $this->getParamInt('limit'),
        ];

        return array_filter($params, function ($value) {
            return !is_null($value);
        });
    }

    /**
     * Check password
     *
     * @param string $storeCode
     * @return boolean
     */
    protected function checkPassword($storeCode)
    {
        $password = $this->_feedConfig->getFeedPassword($storeCode);
        return !$password || $this->getParamString('password') == $password;
    }
}
