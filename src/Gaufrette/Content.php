<?php

namespace Gaufrette;

class Content
{
    /** @var callable */
    private $chunkGenerator;

    private function __construct(callable $chunkGenerator)
    {
        $this->chunkGenerator = $chunkGenerator;
    }

    /**
     * Returns a generator of chunk content.
     *
     * @return \Generator
     */
    public function getContentChunks()
    {
        return call_user_func($this->chunkGenerator);
    }

    /**
     * Returns all the chunks into one string.
     *
     * @return string
     */
    public function getFullContent()
    {
        $content = '';

        foreach ($this->getContentChunks() as $chunk) {
            $content .= $chunk;
        }

        return $content;
    }

    /**
     * Creates a Content instance from a string.
     *
     * Internally, the string is the unique chunk of the Content.
     *
     * @param string $content
     *
     * @return Content
     */
    public static function fromString($content)
    {
        return new self(function () use ($content) {
            yield $content;
        });
    }

    /**
     * Creates a Content instance from a file path.
     *
     * @param string $path
     * @param int    $chunkSize How much bytes are read from the filesystem per chunk
     *
     * @return Content
     */
    public static function fromPath($path, $chunkSize = 1024)
    {
        return new self(function () use ($path, $chunkSize) {
            $fp = fopen($path, 'r');
            fseek($fp, 0);

            while (!feof($fp)) {
                yield fread($fp, $chunkSize);
            }

            fclose($fp);
        });
    }

    /**
     * Creates a Content instance from an open resource.
     *
     * The resource should remain open for the whole lifetime of the Content instance.
     *
     * @param resource $resource
     * @param int      $chunkSize How much bytes are read from the filesystem per chunk
     *
     * @return Content
     */
    public static function fromResource($resource, $chunkSize = 1024)
    {
        return new self(function () use ($resource, $chunkSize) {
            while (!feof($resource)) {
                yield fread($resource, $chunkSize);
            }
        });
    }
}
