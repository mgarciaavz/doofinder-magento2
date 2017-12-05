<?php

namespace Doofinder\Feed\Helper;

class Search extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Doofinder\Feed\Helper\StoreConfig
     */
    private $_storeConfig;

    /**
     * @var \Doofinder\Feed\Search\SearchClientFactory
     */
    private $_searchFactory;

    /**
     * @var \Doofinder\Feed\Search\ManagementClientFactory
     */
    private $_dmaFactory;

    /**
     * @var \Doofinder\Feed\Wrapper\Throttle
     */
    private $_throttleFactory;

    /**
     * @var \Doofinder\Api\Management\SearchEngine[]
     */
    private $_searchEngines = null;

    /**
     * @var \Doofinder\Api\Search\Client|null
     */
    private $_lastSearch = null;

    /**
     * @var \Doofinder\Api\Search\Results|null
     */
    private $_lastResults = null;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Doofinder\Feed\Helper\StoreConfig $storeConfig,
        \Doofinder\Feed\Search\SearchClientFactory $searchFactory,
        \Doofinder\Feed\Search\ManagementClientFactory $dmaFactory,
        \Doofinder\Feed\Wrapper\ThrottleFactory $throttleFactory
    ) {
        $this->_storeConfig = $storeConfig;
        $this->_searchFactory = $searchFactory;
        $this->_dmaFactory = $dmaFactory;
        $this->_throttleFactory = $throttleFactory;
        parent::__construct($context);
    }

    /**
     * Perform a doofinder search on given key.
     *
     * @param string $queryText
     * @param int $limit
     * @param int $offset
     *
     * @return array - The array od product ids from first page
     */
    public function performDoofinderSearch($queryText)
    {
        $hashId = $this->_storeConfig->getHashId($this->getStoreCode());
        $apiKey = $this->_storeConfig->getApiKey();
        $limit = $this->_storeConfig->getSearchRequestLimit($this->getStoreCode());

        $client = $this->_searchFactory->create($hashId, $apiKey);

        try {
            // @codingStandardsIgnoreStart
            $results = $client->query($queryText, null, ['rpp' => $limit, 'transformer' => 'onlyid', 'filter' => []]);
            // @codingStandardsIgnoreEnd
        } catch (\Doofinder\Api\Search\Error $e) {
            $results = null;
            $this->_logger->critical($e->getMessage());
        }

        // Store objects
        $this->_lastSearch = $client;
        $this->_lastResults = $results;

        return $results ? $this->retrieveIds($results) : [];
    }

    /**
     * Retrieve ids from Doofinder results
     *
     * @param \Doofinder\Api\Search\Results $results
     * @return array
     */
    private function retrieveIds(\Doofinder\Api\Search\Results $results)
    {
        $ids = [];
        foreach ($results->getResults() as $result) {
            $ids[] = $result['id'];
        }

        return $ids;
    }

    /**
     * Fetch all results of last doofinder search
     *
     * @return array - The array of products ids from all pages
     */
    public function getAllResults()
    {
        if (!$this->_lastResults) {
            return [];
        }

        $limit = $this->_storeConfig->getSearchTotalLimit($this->getStoreCode());
        $ids = $this->retrieveIds($this->_lastResults);

        while (count($ids) < $limit && ($results = $this->_lastSearch->nextPage())) {
            $ids = array_merge($ids, $this->retrieveIds($results));
        }

        return $ids;
    }

    /**
     * Returns fetched results count
     *
     * @return int
     */
    public function getResultsCount()
    {
        return $this->_lastResults ? $this->_lastResults->getProperty('total') : 0;
    }

    /**
     * Returns current store code
     *
     * @return string
     */
    private function getStoreCode()
    {
        return $this->_storeConfig->getStoreCode();
    }

    /**
     * Get Doofinder Search Engine
     *
     * @param string $storeCode
     * @return \Doofinder\Feed\Wrapper\Throttle
     */
    private function getDoofinderSearchEngine()
    {
        if ($this->_searchEngines === null) {
            $this->_searchEngines = [];

            // Create DoofinderManagementApi instance
            $doofinderApi = $this->_throttleFactory->create([
                'obj' => $this->_dmaFactory->create($this->_storeConfig->getApiKey())
            ]);

            foreach ($doofinderApi->getSearchEngines() as $searchEngine) {
                $this->_searchEngines[$searchEngine->hashid] = $searchEngine;
            }
        }

        // Prepare SearchEngine instance
        $hashId = $this->_storeConfig->getHashId($this->getStoreCode());
        if (!empty($this->_searchEngines[$hashId])) {
            return $this->_throttleFactory->create(['obj' => $this->_searchEngines[$hashId]]);
        }

        throw new \Magento\Framework\Exception\LocalizedException(
            __('Search engine with HashID %1 doesn\'t exists. Please, check your configuration.', $hashId)
        );
    }

    /**
     * Update Doofinder items
     *
     * @param array $items
     */
    public function updateDoofinderItems(array $items)
    {
        $searchEngine = $this->getDoofinderSearchEngine();
        $result = $searchEngine->updateItems('product', array_values($items));

        if (!$result) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('There was an error during Doofinder index items update.')
            );
        }
    }

    /**
     * Delete Doofinder items
     *
     * @param array $items
     */
    public function deleteDoofinderItems(array $items)
    {
        $searchEngine = $this->getDoofinderSearchEngine();
        $result = $searchEngine->deleteItems('product', array_map(function ($item) {
            return $item['id'];
        }, $items));

        if (!$result) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('There was an error during Doofinder index items deletion.')
            );
        }

        if (!empty($result['errors'])) {
            $this->_logger->warning(__(
                'Following items could not be deleted from Doofinder index: %1.',
                implode(', ', $result['errors'])
            ));
            return false;
        }

        return true;
    }

    /**
     * Delete Doofinder index
     */
    public function deleteDoofinderIndex()
    {
        if (!$this->getDoofinderSearchEngine()->deleteType('product')) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('There was an error during Doofinder index deletion')
            );
        }
    }

    /**
     * Create Doofinder index
     */
    public function createDoofinderIndex()
    {
        if (!$this->getDoofinderSearchEngine()->addType('product')) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('There was an error during Doofinder index creation')
            );
        }
    }

    /**
     * Get store id from dimensions
     *
     * @param \Magento\Framework\Search\Request\Dimension[] $dimensions
     * @return int
     */
    public function getStoreIdFromDimensions(array $dimensions)
    {
        foreach ($dimensions as $dimension) {
            if ($dimension->getName() == 'scope') {
                return $dimension->getValue();
            }
        }

        return null;
    }
}
