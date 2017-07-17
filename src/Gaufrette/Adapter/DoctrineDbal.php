<?php

namespace Gaufrette\Adapter;

use Gaufrette\Adapter;
use Gaufrette\Exception\StorageFailure;
use Gaufrette\Util;
use Doctrine\DBAL\Connection;

/**
 * Doctrine DBAL adapter.
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 * @author Antoine Hérault <antoine.herault@gmail.com>
 * @author Leszek Prabucki <leszek.prabucki@gmail.com>
 */
class DoctrineDbal implements Adapter,
                              ChecksumCalculator,
                              ListKeysAware
{
    protected $connection;
    protected $table;
    protected $columns = array(
        'key' => 'key',
        'content' => 'content',
        'mtime' => 'mtime',
        'checksum' => 'checksum',
    );

    /**
     * @param Connection $connection The DBAL connection
     * @param string     $table      The files table
     * @param array      $columns    The column names
     */
    public function __construct(Connection $connection, $table, array $columns = array())
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->columns = array_replace($this->columns, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function keys()
    {
        try {
            $stmt = $this->connection->executeQuery(sprintf(
                'SELECT %s FROM %s',
                $this->getQuotedColumn('key'),
                $this->getQuotedTable()
            ));

            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            throw StorageFailure::unexpectedFailure('keys', [], $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename($sourceKey, $targetKey)
    {
        try {
            $this->connection->update(
                $this->table,
                array($this->getQuotedColumn('key') => $targetKey),
                array($this->getQuotedColumn('key') => $sourceKey)
            );
        } catch (\Exception $e) {
            throw StorageFailure::unexpectedFailure('rename', [
                'sourceKey' => $sourceKey,
                'targetKey' => $targetKey,
            ], $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mtime($key)
    {
        try {
            return $this->getColumnValue($key, 'mtime');
        } catch (\Exception $e) {
            throw StorageFailure::unexpectedFailure('mtime', ['key' => $key], $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checksum($key)
    {
        try {
            return $this->getColumnValue($key, 'checksum');
        } catch (\Exception $e) {
            throw StorageFailure::unexpectedFailure('checksum', ['key' => $key], $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        try {
            return (bool) $this->connection->fetchColumn(
                sprintf(
                    'SELECT COUNT(%s) FROM %s WHERE %s = :key',
                    $this->getQuotedColumn('key'),
                    $this->getQuotedTable(),
                    $this->getQuotedColumn('key')
                ),
                array('key' => $key)
            );
        } catch (\Exception $e) {
            throw StorageFailure::unexpectedFailure('exists', ['key' => $key], $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($key)
    {
        try {
            return $this->getColumnValue($key, 'content');
        } catch (\Exception $e) {
            throw StorageFailure::unexpectedFailure('read', ['key' => $key], $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        try {
            $this->connection->delete(
                $this->table,
                array($this->getQuotedColumn('key') => $key)
            );
        } catch (\Exception $e) {
            throw StorageFailure::unexpectedFailure('delete', ['key' => $key], $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($key, $content)
    {
        $values = array(
            $this->getQuotedColumn('content') => $content,
            $this->getQuotedColumn('mtime') => time(),
            $this->getQuotedColumn('checksum') => Util\Checksum::fromContent($content),
        );

        try {
            $this->upsert($key, $values);
        } catch (\Exception $e) {
            if ($e instanceof StorageFailure) {
                throw $e;
            }

            throw StorageFailure::unexpectedFailure('write', ['key' => $key], $e);
        }
    }

    private function upsert($key, array $values)
    {
        if ($this->exists($key)) {
            $this->connection->update(
                $this->table,
                $values,
                array($this->getQuotedColumn('key') => $key)
            );
        } else {
            $values[$this->getQuotedColumn('key')] = $key;
            $this->connection->insert($this->table, $values);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @TODO: should behave like AwsS3
     */
    public function isDirectory($key)
    {
        return false;
    }

    private function getColumnValue($key, $column)
    {
        $value = $this->connection->fetchColumn(
            sprintf(
                'SELECT %s FROM %s WHERE %s = :key',
                $this->getQuotedColumn($column),
                $this->getQuotedTable(),
                $this->getQuotedColumn('key')
            ),
            array('key' => $key)
        );

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function listKeys($prefix = '')
    {
        $prefix = trim($prefix);

        $keys = $this->connection->fetchAll(
            sprintf(
                'SELECT %s AS _key FROM %s WHERE %s LIKE :pattern',
                $this->getQuotedColumn('key'),
                $this->getQuotedTable(),
                $this->getQuotedColumn('key')
            ),
            array('pattern' => sprintf('%s%%', $prefix))
        );

        return array(
            'dirs' => array(),
            'keys' => array_map(function ($value) {
                    return $value['_key'];
                },
                $keys),
        );
    }

    private function getQuotedTable()
    {
        return $this->connection->quoteIdentifier($this->table);
    }

    private function getQuotedColumn($column)
    {
        return $this->connection->quoteIdentifier($this->columns[$column]);
    }
}
