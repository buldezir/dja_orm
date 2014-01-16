<?php
/**
 * User: Alexander.Arutyunov
 * Date: 14.01.14
 * Time: 17:01
 */

namespace Dja\Db\Model\Query;

class RawQuerySet implements \Countable, \Iterator
{
	/**
     * @var Metadata
     */
    protected $metadata;

    /**
     * cache
     * @var string
     */
    protected $modelClassName;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;
	
	/**
     * @var \Doctrine\DBAL\Statement
     */
    protected $currentStatement;
	
	/**
     * @var string
     */
    protected $table;
	
	/**
     * @var array
     */
    protected $data = [];

    /**
     * @var int
     */
    protected $rowCount = 0;
	
	/**
     * @var array
     */
    protected $currentFetchedRow = [];

    /**
     * @var int
     */
    protected $internalPointer = 0;
	
	/**
     * @var string
     */
    protected $queryStringCache;

	public function __construct(Metadata $metadata, $query, Connection $db = null)
    {
		$this->metadata = $metadata;
        $this->modelClassName = $metadata->getModelClass();
        $this->table = $metadata->getDbTableName();
        if (null !== $db) {
            $this->db = $db;
        } else {
            $this->db = $metadata->getDbConnection();
        }
		$this->queryStringCache = $query;
	}
	
	/**
     * fetches all rows and stores them in array
     * @return array
     */
    public function cached()
    {
		if (empty($this->data)) {
			foreach ($this as $i => $row) {
				$this->data[$i] = $row;
			}
		}
        return $this->data;
    }
	
	protected function execute()
    {
        if ($this->currentStatement === null) {
            $this->currentStatement = $this->db->query($this->queryStringCache);
            $this->rowCount = $this->currentStatement->rowCount();
        }
        return $this->currentStatement;
    }
	
	/**
     * @return mixed|null
     */
    public function current()
    {
        $this->currentFetchedRow = $this->execute()->fetch(\PDO::FETCH_ASSOC);
        if (false === $this->currentFetchedRow) {
            return null;
        } else {
            $mapper = $this->rowDataMapper;
            return $mapper($this->currentFetchedRow);
        }
    }

    /**
     * ++
     */
    public function next()
    {
        $this->internalPointer++;
    }

    /**
     * @return int|mixed
     */
    public function key()
    {
        return $this->internalPointer;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->internalPointer < $this->rowCount;
    }

    /**
     * start new iteration
     */
    public function rewind()
    {
        $this->internalPointer = 0;
        $this->currentStatement = null;
        $this->execute();
    }

    /**
     * @return int
     */
    public function count()
    {
        $this->execute();
        return $this->rowCount;
    }
}