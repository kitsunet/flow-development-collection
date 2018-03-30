<?php
namespace Neos\Cache\Backend;

/*
 * This file is part of the Neos.Cache package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use MongoDB\Collection;
use Neos\Cache\Backend\AbstractBackend as IndependentAbstractBackend;
use Neos\Cache\EnvironmentConfiguration;
use Neos\Cache\Exception;
use Neos\Cache\Exception\InvalidDataException;
use Neos\Cache\Frontend\FrontendInterface;

/**
 * A caching backend which stores cache entries by using Memcache/Memcached.
 *
 * This backend uses the following types of cache keys:
 * - tag_xxx
 *   xxx is tag name, value is array of associated identifiers identifier. This
 *   is "forward" tag index. It is mainly used for obtaining content by tag
 *   (get identifier by tag -> get content by identifier)
 * - ident_xxx
 *   xxx is identifier, value is array of associated tags. This is "reverse" tag
 *   index. It provides quick access for all tags associated with this identifier
 *   and used when removing the identifier
 * - tagIndex
 *   Value is a List of all tags (array)
 *
 * Each key is prepended with a prefix. By default prefix consists from two parts
 * separated by underscore character and ends in yet another underscore character:
 * - "Flow"
 * - MD5 of script path and filename and SAPI name
 * This prefix makes sure that keys from the different installations do not
 * conflict.
 *
 * Note: When using the Memcache backend to store values of more than ~1 MB, the
 * data will be split into chunks to make them fit into the caches limits.
 *
 * @api
 */
class MongoBackend extends IndependentAbstractBackend implements TaggableBackendInterface, PhpCapableBackendInterface
{
    use RequireOnceFromValueTrait;

    /**
     * Max bucket size, (1024*1024)-42 bytes
     *
     * @var int
     */
    const MAX_BUCKET_SIZE = 1048534;

    /**
     * Instance of the PHP Memcache/Memcached class
     *
     * @var \MongoDB\Client
     */
    protected $mongoClient;

    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var array
     */
    protected $uriOptions = [];

    /**
     * @var array
     */
    protected $driverOptions = [];

    /**
     * A prefix to separate stored data from other data possible stored in the memcache
     *
     * @var string
     */
    protected $identifierPrefix;

    /**
     * {@inheritdoc}
     */
    public function __construct(EnvironmentConfiguration $environmentConfiguration, array $options = [])
    {
        if (!class_exists(\MongoDB\Client::class, true)) {
            throw new Exception('The composer library "mongodb/mongodb" needs to be required to use this backend.', 1521384632484);
        }
        parent::__construct($environmentConfiguration, $options);
    }

    /**
     * Initializes the identifier prefix when setting the cache.
     *
     * @param FrontendInterface $cache
     * @return void
     */
    public function setCache(FrontendInterface $cache)
    {
        parent::setCache($cache);
        $this->collection = $this->connect();
    }

    /**
     * Connect to Mongo
     * @return Collection
     */
    protected function connect()
    {
        $this->mongoClient = new \MongoDB\Client($this->uri, $this->uriOptions, $this->driverOptions);
        $collection = $this->mongoClient->selectCollection(md5($this->environmentConfiguration->getApplicationIdentifier()), $this->cacheIdentifier);
        $collection->createIndex(['tags' => 1]);
        return $collection;
    }

    /**
     * @param mixed $uri
     */
    protected function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * @param array $uriOptions
     */
    protected function setUriOptions(array $uriOptions)
    {
        $this->uriOptions = $uriOptions;
    }

    /**
     * @param array $driverOptions
     */
    protected function setDriverOptions(array $driverOptions)
    {
        $this->driverOptions = $driverOptions;
    }

    /**
     * Saves data in the cache.
     *
     * @param string $entryIdentifier An identifier for this specific cache entry
     * @param string $data The data to be stored
     * @param array $tags Tags to associate with this cache entry
     * @param integer $lifetime Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited lifetime.
     * @return void
     * @throws Exception if no cache frontend has been set.
     * @throws \InvalidArgumentException if the identifier is not valid or the final memcached key is longer than 250 characters
     * @throws InvalidDataException if $data is not a string
     * @api
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null)
    {
        $expiration = $lifetime !== null ? $lifetime : $this->defaultLifetime;
        // Memcache considers values over 2592000 sec (30 days) as UNIX timestamp
        // thus $expiration should be converted from lifetime to UNIX timestamp
        if ($expiration > 2592000) {
            $expiration += time();
        }

        $updateResult = $this->collection->replaceOne(
            ['_id' => $entryIdentifier],
            [
                'value' => $data,
                'tags' => $tags,
                'expiration' => $expiration
            ],
            ['upsert' => true]
        );
    }

    /**
     * Loads data from the cache.
     *
     * @param string $entryIdentifier An identifier which describes the cache entry to load
     * @return mixed The cache entry's content as a string or FALSE if the cache entry could not be loaded
     * @api
     */
    public function get($entryIdentifier)
    {
        $result = $this->collection->findOne(['_id' => $entryIdentifier]);
        return $result === null ? false : $result['value'];
    }

    /**
     * Checks if a cache entry with the specified identifier exists.
     *
     * @param string $entryIdentifier An identifier specifying the cache entry
     * @return boolean TRUE if such an entry exists, FALSE if not
     * @api
     */
    public function has($entryIdentifier): bool
    {
        return $this->get($entryIdentifier) !== false ? true : false;
    }

    /**
     * Removes all cache entries matching the specified identifier.
     * Usually this only affects one entry but if - for what reason ever -
     * old entries for the identifier still exist, they are removed as well.
     *
     * @param string $entryIdentifier Specifies the cache entry to remove
     * @return boolean TRUE if (at least) an entry could be removed or FALSE if no entry was found
     * @api
     */
    public function remove($entryIdentifier): bool
    {
        $result = $this->collection->deleteOne(['_id' => $entryIdentifier]);
        return $result->getDeletedCount() ? true : false;
    }

    /**
     * Finds and returns all cache entry identifiers which are tagged by the
     * specified tag.
     *
     * @param string $tag The tag to search for
     * @return array An array with identifiers of all matching entries. An empty array if no entries matched
     * @api
     */
    public function findIdentifiersByTag($tag): array
    {
        $ids = [];
        foreach ($this->collection->find(['tags' => $tag], ['projection' => []]) as $document) {
            $ids = $document['_id'];
        }

        return $ids;
    }

    /**
     * Removes all cache entries of this cache.
     *
     * @return void
     * @throws Exception
     * @api
     */
    public function flush()
    {
        $this->collection->drop();
        $this->collection = $this->mongoClient->selectCollection(md5($this->environmentConfiguration->getApplicationIdentifier()), $this->cacheIdentifier);
    }

    /**
     * Removes all cache entries of this cache which are tagged by the specified tag.
     *
     * @param string $tag The tag the entries must have
     * @return integer The number of entries which have been affected by this flush
     * @api
     */
    public function flushByTag($tag): int
    {
        $result = $this->collection->deleteMany(['tags' => $tag]);
        return $result->getDeletedCount();
    }

    /**
     * Does nothing, as memcache/memcached does GC itself
     *
     * @return void
     * @api
     */
    public function collectGarbage()
    {
    }
}
