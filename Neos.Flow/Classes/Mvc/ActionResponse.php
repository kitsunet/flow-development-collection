<?php
namespace Neos\Flow\Mvc;

use Neos\Flow\Http\ContentStream;
use Neos\Flow\Http\Cookie;
use Neos\Flow\Http\Headers;
use Neos\Flow\Http\Helper\ResponseInformationHelper;
use Neos\Flow\Http\Request;
use Psr\Http\Message\StreamInterface;

/**
 * This is the standard MVC response used for HTTP requests.
 *
 * Note this extends the \Neos\Flow\Http\Response only for backwards compatibility and it will
 * no longer do so from next major.
 */
class ActionResponse extends \Neos\Flow\Http\Response implements ResponseInterface
{
    /**
     * @var Response
     */
    protected $parentResponse;

    /**
     * The current point in time, used for comparisons
     *
     * @var \DateTime
     */
    protected $now;

    /**
     * The HTTP version value of this message, for example "HTTP/1.1"
     *
     * @var string
     */
    protected $version = 'HTTP/1.1';

    /**
     * The HTTP status code
     *
     * @var integer
     */
    protected $statusCode = 200;

    /**
     * The HTTP status message
     *
     * @var string
     */
    protected $statusMessage = 'OK';

    /**
     * @var Headers
     */
    protected $headers;

    /**
     * @var resource
     */
    protected $contentResource;

    /**
     * @var string
     */
    protected $charset = 'UTF-8';

    /**
     * Returns the human-readable message for the given status code.
     *
     * @param integer $statusCode
     * @return string
     * @deprecated Since Flow 5.1, use ResponseInformationHelper::getStatusMessageByCode
     * @see ResponseInformationHelper::getStatusMessageByCode()
     */
    public static function getStatusMessageByCode($statusCode)
    {
        return ResponseInformationHelper::getStatusMessageByCode($statusCode);
    }

    /**
     * Construct this Response
     *
     * @param ResponseInterface $parentResponse Deprecated parameter
     */
    public function __construct(ResponseInterface $parentResponse = null)
    {
        $this->headers = new Headers();
        $this->headers->set('Content-Type', 'text/html; charset=' . $this->charset);
        $this->parentResponse = $parentResponse;
        $this->contentResource = fopen('php://temp', 'a+b');
    }

    /**
     * Get a HTTP response based on information in this ActionResponse
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getHttpResponse(): \Psr\Http\Message\ResponseInterface
    {
        $contentCopy = fopen('php://temp', 'a+b');
        rewind($this->contentResource);
        stream_copy_to_stream($this->contentResource, $contentCopy, -1, 0);
        rewind($contentCopy);

        $httpResponse = new \Neos\Flow\Http\Response();
        $httpResponse->setHeaders($this->headers);
        $httpResponse = $httpResponse
            ->withStatus($this->statusCode, $this->statusMessage)
            ->withBody(new ContentStream($contentCopy))
            ->withProtocolVersion($this->version);
        return $httpResponse;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return ActionResponse
     */
    public static function createFromHttpResponse(\Psr\Http\Message\ResponseInterface $response)
    {
        $actionResponse = new static();
        fclose($actionResponse->contentResource);
        $actionResponse->statusCode = $response->getStatusCode();
        $actionResponse->statusMessage = $response->getReasonPhrase();
        $originalContentResource = $response->getBody()->detach();
        $newContentResource = fopen('php://temp', 'a+b');
        if (is_resource($originalContentResource)) {
            stream_copy_to_stream($originalContentResource, $newContentResource, -1, 0);
            fclose($originalContentResource);
        }
        $actionResponse->contentResource = $newContentResource;
        $headers = $response->getHeaders();
        if (is_array($headers)) {
            $headers = new Headers($headers);
        }

        $actionResponse->setHeaders($headers);
        return $actionResponse;
    }

    /**
     * Creates a response from the given raw, that is plain text, HTTP response.
     *
     * @param string $rawResponse
     * @param Response $parentResponse Deprecated parameter. Parent response, if called recursively
     *
     * @throws \InvalidArgumentException
     * @return ActionResponse
     * @deprecated Since Flow 5.1
     */
    public static function createFromRaw($rawResponse, \Neos\Flow\Http\Response $parentResponse = null)
    {
        /** @var ActionResponse $response */
        $response = new ActionResponse($parentResponse);

        // see https://tools.ietf.org/html/rfc7230#section-3.5
        $lines = explode(chr(10), $rawResponse);
        $statusLine = array_shift($lines);

        if (substr($statusLine, 0, 5) !== 'HTTP/') {
            throw new \InvalidArgumentException('The given raw HTTP message is not a valid response.', 1335175601);
        }
        list($httpAndVersion, $statusCode, $reasonPhrase) = explode(' ', $statusLine, 3);
        $version = explode('/', $httpAndVersion)[1];
        if (strlen($statusCode) !== 3) {
            // See https://tools.ietf.org/html/rfc7230#section-3.1.2
            throw new \InvalidArgumentException('The given raw HTTP message contains an invalid status code.', 1502981352);
        }
        $response = $response->setStatus((integer)$statusCode, trim($reasonPhrase));
        $response = $response->setVersion($version);

        $parsingHeader = true;
        $headers = new Headers();
        foreach ($lines as $line) {
            if ($parsingHeader) {
                if (trim($line) === '') {
                    $parsingHeader = false;
                    continue;
                }
                $headerSeparatorIndex = strpos($line, ':');
                if ($headerSeparatorIndex === false) {
                    throw new \InvalidArgumentException('The given raw HTTP message contains an invalid header.', 1502984804);
                }
                $fieldName = trim(substr($line, 0, $headerSeparatorIndex));
                $fieldValue = trim(substr($line, strlen($fieldName) + 1));
                if (strtoupper(substr($fieldName, 0, 10)) === 'SET-COOKIE') {
                    $cookie = Cookie::createFromRawSetCookieHeader($fieldValue);
                    if ($cookie !== null) {
                        $headers->setCookie($cookie);
                    }
                } else {
                    $headers->set($fieldName, $fieldValue, false);
                }
            } else {
                $response->appendContent($line . chr(10));
            }
        }
        if ($parsingHeader === true) {
            throw new \InvalidArgumentException('The given raw HTTP message contains no separating empty line between header and body.', 1502984823);
        }

        $response->setHeaders($headers);

        return $response;
    }

    /**
     * Return the parent response or NULL if none exists.
     *
     * @return Response the parent response, or NULL if none
     */
    public function getParentResponse()
    {
        return $this->parentResponse;
    }

    /**
     * Appends content to the already existing content.
     *
     * @param string $content More response content
     * @return ActionResponse This response, for method chaining
     */
    public function appendContent($content)
    {
        fwrite($this->contentResource, $content);
        return $this;
    }

    /**
     * Returns the response content without sending it.
     *
     * @return string The response content
     */
    public function getContent()
    {
        return stream_get_contents($this->contentResource, -1, 0);
    }

    /**
     * @param string $content
     * @return \Neos\Flow\Http\AbstractMessage|void
     */
    public function setContent($content)
    {
        fclose($this->contentResource);
        $this->contentResource = fopen('php://temp','a+b');
        fwrite($this->contentResource, $content);
    }

    /**
     * @return resource
     */
    public function getContentResource()
    {
        return $this->contentResource;
    }

    /**
     * Sets the HTTP status code and (optionally) a customized message.
     *
     * @param integer $code The status code
     * @param string $message If specified, this message is sent instead of the standard message
     * @return ActionResponse This response, for method chaining
     * @throws \InvalidArgumentException if the specified status code is not valid
     * @see withStatus()
     */
    public function setStatus($code, $message = null)
    {
        if (!is_int($code)) {
            throw new \InvalidArgumentException('The HTTP status code must be of type integer, ' . gettype($code) . ' given.', 1220526013);
        }
        if ($message === null) {
            $message = ResponseInformationHelper::getStatusMessageByCode($code);
        }
        $this->statusCode = $code;
        $this->statusMessage = ($message === null) ? ResponseInformationHelper::getStatusMessageByCode($code) : $message;

        return $this;
    }

    /**
     * Returns status code and status message.
     *
     * @return string The status code and status message, eg. "404 Not Found"
     * @see getStatusCode()
     */
    public function getStatus()
    {
        return $this->statusCode . ' ' . $this->statusMessage;
    }

    /**
     * Returns the status code.
     *
     * @return integer The status code, eg. 404
     * @api
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Returns the HTTP version value of this message, for example "HTTP/1.1"
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Sets the HTTP version value of this message, for example "HTTP/1.1"
     *
     * @param string $version
     * @return ActionResponse
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * PSR-7 MessageInterface
     *
     * @return string HTTP protocol version.
     * @deprecated after Flow 5.1
     */
    public function getProtocolVersion()
    {
        return explode('/', $this->version)[1];
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     *
     * @param string $version HTTP protocol version
     * @return ActionResponse
     * @deprecated after Flow 5.1
     */
    public function withProtocolVersion($version)
    {
        $this->setVersion('HTTP/' . $version);

        return $this;
    }

    /**
     * Replaces all possibly existing HTTP headers with the ones specified
     *
     * @param Headers $headers
     * @return void
     */
    public function setHeaders(Headers $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Returns the HTTP headers of this request
     *
     * @return Headers
     * @api
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Sets the current point in time.
     *
     * This date / time is used internally for comparisons in order to determine the
     * freshness of this response. By default this DateTime object is set automatically
     * through dependency injection, configured in the Objects.yaml of the Flow package.
     *
     * Unless you are mocking the current time in a test, there is probably no need
     * to use this function. Also note that this method must be called before any
     * of the Response methods are used and it must not be called a second time.
     *
     * @param \DateTime $now The current point in time
     * @return ActionResponse
     * @see withHeader()
     */
    public function setNow(\DateTime $now)
    {
        $this->now = clone $now;
        $this->now->setTimezone(new \DateTimeZone('UTC'));
        $this->headers->set('Date', $this->now);
        return $this;
    }

    /**
     * Sets the Date header.
     *
     * The given date must either be an RFC2822 parseable date string or a DateTime
     * object. The timezone will be converted to GMT internally, but the point in
     * time remains the same.
     *
     * @param string|\DateTime $date
     * @return ActionResponse This response, for method chaining
     * @see withHeader()
     */
    public function setDate($date)
    {
        $this->headers->set('Date', $date);

        return $this;
    }

    /**
     * Returns the date from the Date header.
     *
     * The returned date is configured to be in the GMT timezone.
     *
     * @return \DateTime The date of this response
     * @see getHeader()
     */
    public function getDate()
    {
        return $this->headers->get('Date');
    }

    /**
     * Sets the Last-Modified header.
     *
     * The given date must either be an RFC2822 parseable date string or a DateTime
     * object. The timezone will be converted to GMT internally, but the point in
     * time remains the same.
     *
     * @param string|\DateTime $date
     * @return ActionResponse This response, for method chaining
     * @see withHeader()
     */
    public function setLastModified($date)
    {
        $this->headers->set('Last-Modified', $date);

        return $this;
    }

    /**
     * Returns the date from the Last-Modified header or NULL if no such header
     * is present.
     *
     * The returned date is configured to be in the GMT timezone.
     *
     * @return \DateTime The last modification date or NULL
     * @see getHeader()
     */
    public function getLastModified()
    {
        return $this->headers->get('Last-Modified');
    }

    /**
     * Sets the Expires header.
     *
     * The given date must either be an RFC2822 parseable date string or a DateTime
     * object. The timezone will be converted to GMT internally, but the point in
     * time remains the same.
     *
     * In order to signal that the response has already expired, the date should
     * be set to the same date as the Date header (that is, $now). To communicate
     * an infinite expiration time, the date should be set to one year in the future.
     *
     * Expiration times should not be more than one year in the future, according
     * to RFC 2616 / 14.21
     *
     * @param string|\DateTime $date
     * @return ActionResponse This response, for method chaining
     * @see withHeader()
     */
    public function setExpires($date)
    {
        $this->headers->set('Expires', $date);

        return $this;
    }

    /**
     * Returns the date from the Expires header or NULL if no such header
     * is present.
     *
     * The returned date is configured to be in the GMT timezone.
     *
     * @return \DateTime The expiration date or NULL
     * @see getHeader()
     */
    public function getExpires()
    {
        return $this->headers->get('Expires');
    }

    /**
     * Returns the age of this responds in seconds.
     *
     * The age is determined either by an explicitly set Age header or by the
     * difference between Date and "now".
     *
     * Note that, according to RFC 2616 / 13.2.3, the presence of an Age header implies
     * that the response is not first-hand. You should therefore only explicitly set
     * an Age header if this is the case.
     *
     * @return integer The age in seconds
     * @see getHeader()
     */
    public function getAge()
    {
        if ($this->headers->has('Age')) {
            return $this->headers->get('Age');
        } else {
            $dateHeaderValue = $this->headers->get('Date');
            if (is_array($dateHeaderValue)) {
                $dateHeaderValue = reset($dateHeaderValue);
            }

            $dateTimestamp = ($dateHeaderValue instanceof \DateTimeInterface) ? $dateHeaderValue->getTimestamp() : (new \DateTime($dateHeaderValue))->getTimestamp();
            $nowTimestamp = $this->now->getTimestamp();

            return ($nowTimestamp > $dateTimestamp) ? ($nowTimestamp - $dateTimestamp) : 0;
        }
    }

    /**
     * Sets the maximum age in seconds before this response becomes stale.
     *
     * This method sets the "max-age" directive in the Cache-Control header.
     *
     * @param integer $age The maximum age in seconds
     * @return ActionResponse This response, for method chaining
     * @see withHeader()
     */
    public function setMaximumAge($age)
    {
        $this->headers->setCacheControlDirective('max-age', $age);

        return $this;
    }

    /**
     * Returns the maximum age in seconds before this response becomes stale.
     *
     * This method returns the value from the "max-age" directive in the
     * Cache-Control header.
     *
     * @return integer The maximum age in seconds, or NULL if none has been defined
     * @see getHeader()
     */
    public function getMaximumAge()
    {
        return $this->headers->getCacheControlDirective('max-age');
    }

    /**
     * Sets the maximum age in seconds before this response becomes stale in shared
     * caches, such as proxies.
     *
     * This method sets the "s-maxage" directive in the Cache-Control header.
     *
     * @param integer $maximumAge The maximum age in seconds
     * @return ActionResponse This response, for method chaining
     * @see withHeader()
     */
    public function setSharedMaximumAge($maximumAge)
    {
        $this->headers->setCacheControlDirective('s-maxage', $maximumAge);

        return $this;
    }

    /**
     * Returns the maximum age in seconds before this response becomes stale in shared
     * caches, such as proxies.
     *
     * This method returns the value from the "s-maxage" directive in the
     * Cache-Control header.
     *
     * @return integer The maximum age in seconds, or NULL if none has been defined
     * @see getHeader()
     */
    public function getSharedMaximumAge()
    {
        return $this->headers->getCacheControlDirective('s-maxage');
    }

    /**
     * Renders the HTTP headers - including the status header - of this response
     *
     * @return array The HTTP headers
     * @deprecated after Flow 5.1
     * @see ResponseInformationHelper::prepareHeaders()
     */
    public function renderHeaders()
    {
        return ResponseInformationHelper::prepareHeaders($this);
    }

    /**
     * Sets the respective directive in the Cache-Control header.
     *
     * A response flagged as "public" may be cached by any cache, even if it normally
     * wouldn't be cacheable in a shared cache.
     *
     * @return ActionResponse This response, for method chaining
     */
    public function setPublic()
    {
        $this->headers->setCacheControlDirective('public');

        return $this;
    }

    /**
     * Sets the respective directive in the Cache-Control header.
     *
     * A response flagged as "private" tells that it is intended for a specific
     * user and must not be cached by a shared cache.
     *
     * @return ActionResponse This response, for method chaining
     */
    public function setPrivate()
    {
        $this->headers->setCacheControlDirective('private');

        return $this;
    }

    /**
     * Returns the value(s) of the specified header
     *
     * If one such header exists, the value is returned as a single string.
     * If multiple headers of that name exist, the values are returned as an array.
     * If no such header exists, NULL is returned.
     *
     * Dates are returned as DateTime objects with the timezone set to GMT.
     *
     * @param string $name Name of the header
     * @return array|string An array of field values if multiple headers of that name exist, a string value if only one value exists and NULL if there is no such header.
     * @api
     * NOTE: This method signature will change in next major of Flow according to PSR-7. It will ALWAYS return an array of strings and nothing else.
     */
    public function getHeader($name)
    {
        return $this->headers->get($name);
    }

    /**
     * Checks if the specified header exists.
     *
     * @param string $name Name of the HTTP header
     * @return boolean
     * @api
     */
    public function hasHeader($name)
    {
        return $this->headers->has($name);
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     * @deprecated after Flow 5.1
     */
    public function getHeaderLine($name)
    {
        $headerLine = $this->headers->get($name);
        if ($headerLine === null) {
            $headerLine = '';
        }

        if (is_array($headerLine)) {
            $headerLine = implode(', ', $headerLine);
        }

        return $headerLine;
    }

    /**
     * Sets the specified HTTP header
     *
     * DateTime objects will be converted to a string representation internally but
     * will be returned as DateTime objects on calling getHeader().
     *
     * Please note that dates are normalized to GMT internally, so that getHeader() will return
     * the same point in time, but not necessarily in the same timezone, if it was not
     * GMT previously. GMT is used synonymously with UTC as per RFC 2616 3.3.1.
     *
     * @param string $name Name of the header, for example "Location", "Content-Description" etc.
     * @param array|string|\DateTime $values An array of values or a single value for the specified header field
     * @param boolean $replaceExistingHeader If a header with the same name should be replaced. Default is true.
     * @return ActionResponse This message, for method chaining
     * @throws \InvalidArgumentException
     */
    public function setHeader($name, $values, $replaceExistingHeader = true)
    {
        switch ($name) {
            case 'Content-Type':
                if (is_array($values)) {
                    if (count($values) !== 1) {
                        throw new \InvalidArgumentException('The "Content-Type" header must be unique and thus only one field value may be specified.', 1454949291);
                    }
                    $values = (string)$values[0];
                }
                if (stripos($values, 'charset') === false && stripos($values, 'text/') === 0) {
                    $values .= '; charset=' . $this->charset;
                }
                break;
        }

        $this->headers->set($name, $values, $replaceExistingHeader);

        return $this;
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return ActionResponse
     * @throws \InvalidArgumentException for invalid header names or values.
     * @deprecated after Flow 5.1
     */
    public function withHeader($name, $value)
    {
        $this->setHeader($name, $value, true);

        return $this;
    }

    /**
     * Return an instance without the specified header.
     *
     * @param string $name Case-insensitive header field name to remove.
     * @return ActionResponse
     * @deprecated after Flow 5.1
     */
    public function withoutHeader($name)
    {
        $this->headers->remove($name);

        return $this;
    }

    /**
     * Sets the character set for this message.
     *
     * If the content type of this message is a text/* media type, the character
     * set in the respective Content-Type header will be updated by this method.
     *
     * @param string $charset A valid IANA character set identifier
     * @return self This message, for method chaining
     * @see http://www.iana.org/assignments/character-sets
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;

        if ($this->headers->has('Content-Type')) {
            $contentType = $this->headers->get('Content-Type');
            if (stripos($contentType, 'text/') === 0) {
                $matches = [];
                if (preg_match('/(?P<contenttype>.*); ?charset[^;]+(?P<extra>;.*)?/iu', $contentType, $matches)) {
                    $contentType = $matches['contenttype'];
                }
                $contentType .= '; charset=' . $this->charset . (isset($matches['extra']) ? $matches['extra'] : '');
                $this->setHeader('Content-Type', $contentType, true);
            }
        }

        return $this;
    }

    /**
     * Returns the character set of this response.
     *
     * Note that the default character in Flow is UTF-8.
     *
     * @return string An IANA character set identifier
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * Analyzes this response, considering the given request and makes additions
     * or removes certain headers in order to make the response compliant to
     * RFC 2616 and related standards.
     *
     * It is recommended to call this method before the response is sent and Flow
     * does so by default in its built-in HTTP request handler.
     *
     * @param Request $request The corresponding request
     * @return void
     * @deprecated after Flow 5.1
     * @see ResponseInformationHelper::makeStandardsCompliant()
     */
    public function makeStandardsCompliant(Request $request)
    {
        if ($request->hasHeader('If-Modified-Since') && $this->headers->has('Last-Modified') && $this->statusCode === 200) {
            $ifModifiedSinceDate = $request->getHeader('If-Modified-Since');
            $lastModifiedDate = $this->headers->get('Last-Modified');
            if ($lastModifiedDate <= $ifModifiedSinceDate) {
                $this->setStatus(304);
                $this->content = '';
            }
        } elseif ($request->hasHeader('If-Unmodified-Since') && $this->headers->has('Last-Modified')
            && (($this->statusCode >= 200 && $this->statusCode <= 299) || $this->statusCode === 412)) {
            $unmodifiedSinceDate = $request->getHeader('If-Unmodified-Since');
            $lastModifiedDate = $this->headers->get('Last-Modified');
            if ($lastModifiedDate > $unmodifiedSinceDate) {
                $this->setStatus(412);
            }
        }

        if (in_array($this->statusCode, [100, 101, 204, 304])) {
            $this->content = '';
        }

        if ($this->headers->getCacheControlDirective('no-cache') !== null
            || $this->headers->has('Expires')) {
            $this->headers->removeCacheControlDirective('max-age');
        }

        if ($request->getMethod() === 'HEAD') {
            if (!$this->headers->has('Content-Length')) {
                $this->headers->set('Content-Length', strlen($this->content));
            }
            $this->content = '';
        }

        if (!$this->headers->has('Content-Length')) {
            $this->headers->set('Content-Length', strlen($this->content));
        }

        if ($this->headers->has('Transfer-Encoding')) {
            $this->headers->remove('Content-Length');
        }
    }

    /**
     * Sets the given cookie to in the headers of this message.
     *
     * This is a shortcut for $message->getHeaders()->setCookie($cookie);
     *
     * @param Cookie $cookie The cookie to set
     * @return void
     * @deprecated Since Flow 5.1, replacement only on ServerRequestInterface
     * @see Request::withCookieParams()
     */
    public function setCookie(Cookie $cookie)
    {
        $this->headers->setCookie($cookie);
    }

    /**
     * Returns a cookie specified by the given name
     *
     * This is a shortcut for $message->getHeaders()->getCookie($name);
     *
     * @param string $name Name of the cookie
     * @return Cookie The cookie or NULL if no such cookie exists
     */
    public function getCookie($name)
    {
        return $this->headers->getCookie($name);
    }

    /**
     * Returns all cookies attached to the headers of this message
     *
     * This is a shortcut for $message->getHeaders()->getCookies();
     *
     * @return array An array of Cookie objects
     */
    public function getCookies()
    {
        return $this->headers->getCookies();
    }

    /**
     * Checks if the specified cookie exists
     *
     * This is a shortcut for $message->getHeaders()->hasCookie($name);
     *
     * @param string $name Name of the cookie
     * @return boolean
     */
    public function hasCookie($name)
    {
        return $this->headers->hasCookie($name);
    }

    /**
     * Removes the specified cookie from the headers of this message, if it exists
     *
     * This is a shortcut for $message->getHeaders()->removeCookie($name);
     *
     * Note: This will remove the cookie object from this Headers container. If you
     *       intend to remove a cookie in the user agent (browser), you should call
     *       the cookie's expire() method and _not_ remove the cookie from the Headers
     *       container.
     *
     * @param string $name Name of the cookie to remove
     * @return void
     */
    public function removeCookie($name)
    {
        $this->headers->removeCookie($name);
    }

    /**
     * Sends the HTTP headers.
     *
     * If headers have been sent previously, this method fails silently.
     *
     * @return void
     * @codeCoverageIgnore
     * @deprecated after Flow 5.1
     */
    public function sendHeaders()
    {
        if (headers_sent() === true) {
            return;
        }
        foreach ($this->renderHeaders() as $header) {
            header($header, false);
        }
        foreach ($this->headers->getCookies() as $cookie) {
            header('Set-Cookie: ' . $cookie, false);
        }
    }

    /**
     * Renders and sends the whole web response
     *
     * @return void
     * @codeCoverageIgnore
     */
    public function send()
    {
        $this->sendHeaders();
        if ($this->content !== null) {
            echo $this->getContent();
        }
    }

    /**
     * Return the Status-Line of this Response Message, consisting of the version, the status code and the reason phrase
     * Would be, for example, "HTTP/1.1 200 OK" or "HTTP/1.1 400 Bad Request"
     *
     * @return string
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6.1
     * @deprecated after Flow 5.1
     * @see ResponseInformationHelper::generateStatusLine
     */
    public function getStatusLine()
    {
        return ResponseInformationHelper::generateStatusLine($this);
    }

    /**
     * Returns the first line of this Response Message, which is the Status-Line in this case
     *
     * @return string The Status-Line of this Response
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html chapter 4.1 "Message Types"
     * @deprecated after Flow 5.1
     * @see ResponseInformationHelper::generateStatusLine
     */
    public function getStartLine()
    {
        return ResponseInformationHelper::generateStatusLine($this);
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated status and reason phrase.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @param int $code The 3-digit integer result code to set.
     * @param string $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     * @return self
     * @throws \InvalidArgumentException For invalid status code arguments.
     * @deprecated after Flow 5.1
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $newResponse = clone $this;
        $newResponse->setStatus($code, ($reasonPhrase === '' ? null : $reasonPhrase));

        return $newResponse;
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * Because a reason phrase is not a required element in a response
     * status line, the reason phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 7231 recommended reason phrase (or those
     * listed in the IANA HTTP Status Code Registry) for the response's
     * status code.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @return string Reason phrase; must return an empty string if none present.
     * @deprecated after Flow 5.1
     */
    public function getReasonPhrase()
    {
        return $this->statusMessage;
    }

    /**
     * Set the body (content) of this response.
     * Deprecated method for PSR-7 that will not return a new instance in this case.
     * The body MUST be a StreamInterface object.

     *
     * @param StreamInterface $body Body.
     * @return self
     * @throws \InvalidArgumentException When the body is not valid.
     * @deprecated after Flow 5.1
     * @see setContent()|appendContent()
     */
    public function withBody(StreamInterface $body)
    {
        $this->setContent($body->getContents());
        return $this;
    }

    /**
     * Headers should also be cloned when the message is cloned.
     */
    public function __clone()
    {
        $this->headers = clone $this->headers;
    }

    /**
     * Cast the response to a string: return the content part of this response
     *
     * @return string The same as getContent(), an empty string if getContent() returns a value which can't be cast into a string
     * @api
     */
    public function __toString()
    {
        return (string)stream_get_contents($this->contentResource, -1, 0);
    }
}
