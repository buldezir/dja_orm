<?php

namespace Dja\Db\Model\Query;

use Dja\Util\Inflector;
use Traversable;

/**
 * Class Pagination
 * @package Dja\Db\Model\Query
 */
class Pagination implements \IteratorAggregate
{
    /**
     * @var BaseQuerySet
     */
    protected $qs;

    /**
     * @var int
     */
    protected $itemsPerPage = 15;

    /**
     * @var int
     */
    protected $numPages = 1;

    /**
     * @var int
     */
    protected $numItems = 0;

    /**
     * @var int
     */
    protected $currentPage = 1;

    /**
     * @var bool
     */
    protected $processed = false;

    /**
     * @param $qs
     * @param int $currentPage
     * @param null $itemsPerPage
     * @throws \InvalidArgumentException
     */
    public function __construct($qs, $currentPage = 1, $itemsPerPage = null)
    {
        if ($qs instanceof BaseQuerySet) {
        } elseif ($qs instanceof Manager) {
            $qs = $qs->all();
        } else {
            throw new \InvalidArgumentException('$qs must be instanceof BaseQuerySet or Manager');
        }
        $this->qs = $qs;
        $this->setItemsPerPage($itemsPerPage);
        $this->setCurrentPage($currentPage);
    }

    /**
     * @param $name
     * @return mixed
     * @throws \OutOfBoundsException
     */
    public function __get($name)
    {
        $fn = 'get' . Inflector::classify($name);
        if (method_exists($this, $fn)) {
            return $this->$fn();
        } else {
            throw new \OutOfBoundsException("getter '{$fn}' does not exist");
        }
    }

    /**
     * @return $this
     */
    protected function _process()
    {
        if (false === $this->processed) {

            $this->numItems = $this->qs->doCount();
            $this->numPages = ceil($this->numItems / $this->getItemsPerPage());

            $this->processed = true;
        }
        return $this;
    }

    /**
     * @param int $itemsPerPage
     * @throws \LogicException
     * @return $this
     */
    public function setItemsPerPage($itemsPerPage)
    {
        if ($this->processed === true) {
            throw new \LogicException('Cant set itemsPerPage after first query');
        }
        if ($itemsPerPage > 0) {
            $this->itemsPerPage = $itemsPerPage;
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getItemsPerPage()
    {
        return $this->itemsPerPage;
    }

    /**
     * @param int $currentPage
     * @return $this
     */
    public function setCurrentPage($currentPage)
    {
        if ($currentPage > 0) {
            $this->currentPage = $currentPage;
            $this->qs = $this->qs->byPage($currentPage, $this->getItemsPerPage());
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * @return int
     */
    public function getFirstPage()
    {
        return 1;
    }

    /**
     * @return int
     */
    public function getLastPage()
    {
        return $this->getNumPages();
    }

    /**
     * @return int
     */
    public function getNumPages()
    {
        $this->_process();
        return $this->numPages;
    }

    /**
     * @return int
     */
    public function getNumItems()
    {
        $this->_process();
        return $this->numItems;
    }

    /**
     * @return bool
     */
    public function getHasNext()
    {
        return $this->getCurrentPage() < $this->getNumPages();
    }

    /**
     * @return bool
     */
    public function getHasPrev()
    {
        return $this->getCurrentPage() > 1;
    }

    /**
     * @return \Dja\Db\Model\Model[]|BaseQuerySet|QuerySet
     */
    public function getQuerySet()
    {
        return $this->qs;
    }

    /**
     * @param int $range
     * @return array
     */
    public function getPagesArray($range = 2)
    {
        $pages = [];
        if ($this->getNumPages() > ($range * 2 + 1)) {
            $min = $this->getCurrentPage() - $range;
            $max = $this->getCurrentPage() + $range;

            if ($min < $this->getFirstPage()) {
                $max = $max + ($this->getFirstPage() - $min);
                $min = $this->getFirstPage();
            }
            if ($max > $this->getLastPage()) {
                $min = $min - ($max - $this->getLastPage());
                $max = $this->getLastPage();
            }
        } else {
            $min = $this->getFirstPage();
            $max = $this->getLastPage();
        }

        for ($i = $min; $i <= $max; $i++) {
            $pages[$i] = $i;
        }
        return $pages;
    }

    /**
     * @return \Generator
     */
    public function getPagesIterator()
    {
        for ($i = $this->getFirstPage(); $i <= $this->getLastPage(); $i++) {
            yield $i;
        }
    }

    /**
     * @return Traversable
     */
    public function getIterator()
    {
        $this->_process();
        return $this->qs;
    }
}