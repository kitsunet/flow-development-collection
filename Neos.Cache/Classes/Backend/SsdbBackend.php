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
 *
 */
class SsdbBackend extends IndependentAbstractBackend implements TaggableBackendInterface, PhpCapableBackendInterface
{
    use RequireOnceFromValueTrait;

    /**
     * @var \SSDB
     */
    protected $client;

    /**
     * @var string
     */
    protected $host = '127.0.0.1';

    /**
     * @var int
     */
    protected $port = 8888;

    /**
     * @var string
     */
    protected $identifierPrefix;

    /**
     * @param string $entryIdentifier
     * @param string $data
     * @param array $tags
     * @param int $lifetime
     * @throws \SSDBException
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null)
    {
        $expiration = $lifetime !== null ? $lifetime : $this->defaultLifetime;
        $prefixedIdentifier = $this->getPrefixedIdentifier($entryIdentifier);

        $this->client->batch();
        if ($expiration === 0) {
            $this->client->set($prefixedIdentifier, $data);
        } else {
            $this->client->setx($prefixedIdentifier, $data, $expiration);
        }
        $this->client->qclear('tags_' . $prefixedIdentifier);
        foreach (array_values($tags) as $tag) {
            $this->client->qpush('tags_' . $prefixedIdentifier, $tag);
            $this->client->zset('tag_' . $this->identifierPrefix . $tag, $prefixedIdentifier, 1);
        }
        $this->client->exec();
    }

    /**
     * @param string $entryIdentifier
     * @return bool|mixed
     * @throws \SSDBException
     */
    public function get($entryIdentifier)
    {
        $res = $this->client->get($this->getPrefixedIdentifier($entryIdentifier));
        return $res === null ? false : $res;
    }

    /**
     * @param string $entryIdentifier
     * @return bool
     * @throws \SSDBException
     */
    public function has($entryIdentifier)
    {
        return $this->client->exists($this->getPrefixedIdentifier($entryIdentifier));
    }

    /**
     * @param string $entryIdentifier
     * @return bool
     * @throws \SSDBException
     */
    public function remove($entryIdentifier)
    {
        $prefixedIdentifier = $this->getPrefixedIdentifier($entryIdentifier);
        $this->removePrefixedIdentifier($prefixedIdentifier);
        return true;
    }

    /**
     * @param string $prefixedIdentifier
     * @throws \SSDBException
     */
    protected function removePrefixedIdentifier($prefixedIdentifier)
    {
        $taggedWith = $this->client->qrange('tags_' . $prefixedIdentifier, 0, -1);
        $this->client->batch();
        foreach ($taggedWith as $tag) {
            $this->client->zdel('tag_' . $this->identifierPrefix . $tag, $prefixedIdentifier);
        }
        $this->client->qclear('tags_' . $prefixedIdentifier);
        $this->client->del($prefixedIdentifier);
        $this->client->exec();
    }

    /**
     * @throws \SSDBException
     */
    public function flush()
    {
        $batch = [
            'k' => [],
            'q' => [],
            'z' => []
        ];
        foreach ($this->client->keys($this->identifierPrefix, '', PHP_INT_MAX) as $entryIdentifier) {
            $batch['k'][] = $entryIdentifier;
        }
        foreach ($this->client->qlist('tags_' . $this->identifierPrefix, '', PHP_INT_MAX) as $entryIdentifier) {
            $batch['q'][] = $entryIdentifier;
        }
        foreach ($this->client->zlist('tag_' . $this->identifierPrefix, '', PHP_INT_MAX) as $entryIdentifier) {
            $batch['z'][] = $entryIdentifier;
        }

        $this->client->batch();
        foreach ($batch['k'] as $entryIdentifier) {
            $this->client->del($entryIdentifier);
        }
        foreach ($batch['q'] as $queueIdentifier) {
            $this->client->qclear($queueIdentifier);
        }
        foreach ($batch['z'] as $zIdentifier) {
            $this->client->zclear($zIdentifier);
        }
        $this->client->exec();
    }

    public function collectGarbage()
    {
    }

    /**
     * @param string $tag
     * @return int|void
     * @throws \SSDBException
     */
    public function flushByTag($tag)
    {
        foreach ($this->findIdentifiersByTag($tag) as $identifier) {
            $this->removePrefixedIdentifier($identifier);
        }
    }

    /**
     * @param string $tag
     * @return array
     * @throws \SSDBException
     */
    public function findIdentifiersByTag($tag)
    {
        return $this->client->zkeys('tag_' . $this->identifierPrefix . $tag, '', 1, 1, PHP_INT_MAX);
    }

    /**
     * Constructs this backend
     *
     * @param EnvironmentConfiguration $environmentConfiguration
     * @param array $options Configuration options - depends on the actual backend
     * @api
     */
    public function __construct(EnvironmentConfiguration $environmentConfiguration = null, array $options = [])
    {
        require_once(__DIR__ . '/../../Resources/Private/Php/Ssdb.php');
        parent::__construct($environmentConfiguration, $options);
        $this->client = new \SimpleSSDB($this->host, $this->port);
    }

    /**
     * @param string $host
     */
    protected function setHost(string $host)
    {
        $this->host = $host;
    }

    /**
     * @param int $port
     */
    protected function setPort(int $port)
    {
        $this->port = $port;
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

        $pathHash = substr(md5($this->environmentConfiguration->getApplicationIdentifier() . $cache->getIdentifier()), 0, 12);
        $this->identifierPrefix = 'Flow_' . $pathHash . '_';
    }

    /**
     * Returns the internally used, prefixed entry identifier for the given public
     * entry identifier.
     *
     * While Flow applications will mostly refer to the simple entry identifier, it
     * may be necessary to know the actual identifier used by the cache backend
     * in order to share cache entries with other applications. This method allows
     * for retrieving it.
     *
     * @param string $entryIdentifier The short entry identifier, for example "NumberOfPostedArticles"
     * @return string The prefixed identifier, for example "Flow694a5c7a43a4_NumberOfPostedArticles"
     * @api
     */
    public function getPrefixedIdentifier($entryIdentifier): string
    {
        return $this->identifierPrefix . $entryIdentifier;
    }
}
