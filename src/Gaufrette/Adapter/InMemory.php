<?php

namespace Gaufrette\Adapter;

use Gaufrette\Adapter;
use Gaufrette\Content;
use Gaufrette\Util;

/**
 * In memory adapter.
 *
 * Stores some files in memory for test purposes
 *
 * @author Antoine Hérault <antoine.herault@gmail.com>
 */
class InMemory implements Adapter,
                          MimeTypeProvider
{
    protected $files = array();

    /**
     * @param array $files An array of files
     */
    public function __construct(array $files = array())
    {
        $this->setFiles($files);
    }

    /**
     * Defines the files.
     *
     * @param array $files An array of files
     */
    public function setFiles(array $files)
    {
        $this->files = array();
        foreach ($files as $key => $file) {
            if (!is_array($file)) {
                $file = array('content' => $file);
            }

            $file = array_merge(array(
                'content' => null,
                'mtime' => null,
            ), $file);

            $this->setFile($key, $file['content'], $file['mtime']);
        }
    }

    /**
     * Defines a file.
     *
     * @param string $key     The key
     * @param string $content The content
     * @param int    $mtime   The last modified time (automatically set to now if NULL)
     */
    public function setFile($key, $content = null, $mtime = null)
    {
        if (null === $mtime) {
            $mtime = time();
        }

        $this->files[$key] = array(
            'content' => (string) $content,
            'mtime' => (integer) $mtime,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function read($key)
    {
        return $this->files[$key]['content'];
    }

    /**
     * {@inheritdoc}
     */
    public function rename($sourceKey, $targetKey)
    {
        $content = $this->read($sourceKey);
        $this->delete($sourceKey);

        return (boolean) $this->write($targetKey, Content::fromString($content));
    }

    /**
     * {@inheritdoc}
     *
     * @TODO: remove $metadata parameter
     */
    public function write($key, Content $content, array $metadata = null)
    {
        $rawContent = $content->getFullContent();

        $this->files[$key]['content'] = $rawContent;
        $this->files[$key]['mtime'] = time();

        return Util\Size::fromContent($rawContent);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        return array_key_exists($key, $this->files);
    }

    /**
     * {@inheritdoc}
     */
    public function keys()
    {
        return array_keys($this->files);
    }

    /**
     * {@inheritdoc}
     */
    public function mtime($key)
    {
        return isset($this->files[$key]['mtime']) ? $this->files[$key]['mtime'] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        unset($this->files[$key]);
        clearstatcache();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isDirectory($path)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function mimeType($key)
    {
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        return $fileInfo->buffer($this->files[$key]['content']);
    }
}
