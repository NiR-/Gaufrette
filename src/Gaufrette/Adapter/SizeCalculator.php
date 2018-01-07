<?php

namespace Gaufrette\Adapter;

use Gaufrette\Exception\FileNotFound;
use Gaufrette\Exception\InvalidKey;
use Gaufrette\Exception\StorageFailure;

/**
 * Interface which add size calculation support to adapter.
 *
 * @author Markus Poerschke <markus@eluceo.de>
 */
interface SizeCalculator
{
    /**
     * Returns the size of the specified key.
     *
     * @param string $key
     *
     * @return int
     *
     * @throws InvalidKey     If the key is invalid or malformed.
     * @throws FileNotFound   If the key does not exist.
     * @throws StorageFailure If the underlying storage fails (adapter should not leak exceptions)
     */
    public function size($key);
}
