<?php
namespace Neos\Flow\Tests\Unit\Mvc\Controller\Fixtures;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\SimpleActionController;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class SimpleActionTestController extends SimpleActionController
{
    public function addTestContentAction(ActionRequest $actionRequest): ResponseInterface
    {
        $response = new Response(body: 'Simple');
        return $response;
    }
}
