<?php
namespace Neos\Flow\Http;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseInterface;

/**
 * @Flow\Scope("singleton")
 */
class ResponseSender
{
    /**
     * @param ResponseInterface $response
     * @return bool
     */
    public function send(ResponseInterface $response): bool
    {
        $hasBeenSend = $this->sendHeaders($response);
        $hasBeenSend = $hasBeenSend ? $this->sendBody($response) : $hasBeenSend;

        return $hasBeenSend;
    }

    /**
     * @param ResponseInterface $response
     * @return bool
     */
    private function sendHeaders(ResponseInterface $response): bool
    {
        if (headers_sent() === true) {
            return false;
        }

        header($this->generateStatusLine($response));

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, (string)$value), false);
            }
        }

        return true;
    }

    /**
     * Return the Request-Line of this Request Message, consisting of the method, the URI and the HTTP version
     * Would be, for example, "GET /foo?bar=baz HTTP/1.1"
     * Note that the URI part is, at the moment, only possible in the form "abs_path" since the
     * actual requestUri of the Request cannot be determined during the creation of the Request.
     *
     * @param ResponseInterface $response
     * @return string
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6.1
     */
    private function generateStatusLine(ResponseInterface $response): string
    {
        return sprintf("HTTP/%s %s %s\r\n", $response->getProtocolVersion(), $response->getStatusCode(), $response->getReasonPhrase());
    }

    /**
     * @param ResponseInterface $response
     * @return bool
     */
    private function sendBody(ResponseInterface $response): bool
    {
        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        while (!$stream->eof()) {
            echo $stream->read(1024 * 8);
        }

        return true;
    }
}
