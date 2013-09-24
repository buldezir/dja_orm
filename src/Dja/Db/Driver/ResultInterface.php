<?php
/**
 * User: Alexander.Arutyunov
 * Date: 21.08.13
 * Time: 17:52
 */

namespace Dja\Db\Driver;

use Countable;
use Iterator;

interface ResultInterface extends Countable, Iterator
{
    /**
     * Force buffering
     *
     * @return void
     */
    public function buffer();

    /**
     * Check if is buffered
     *
     * @return bool|null
     */
    public function isBuffered();

    /**
     * Is query result?
     *
     * @return bool
     */
    public function isQueryResult();

    /**
     * Get affected rows
     *
     * @return integer
     */
    public function getAffectedRows();

    /**
     * Get generated value
     *
     * @return mixed|null
     */
    public function getGeneratedValue();

    /**
     * Get the resource
     *
     * @return mixed
     */
    public function getResource();

    /**
     * Get field count
     *
     * @return integer
     */
    public function getFieldCount();
}
