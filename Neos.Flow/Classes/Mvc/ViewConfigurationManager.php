<?php
namespace Neos\Flow\Mvc;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Eel\CompilingEvaluator;
use Neos\Eel\Context;
use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Mvc\Exception\ViewNotFoundException;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

/**
 * A View Configuration Manager
 *
 * This classes compiles all configurations matching the provided
 * request out of the Views.yaml into one view configuration used
 * by the ActionController to setup up the view.
 *
 * @Flow\Scope("singleton")
 */
class ViewConfigurationManager
{
    /**
     * @var VariableFrontend
     */
    protected $cache;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var CompilingEvaluator
     */
    protected $eelEvaluator;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="mvc.view.defaultImplementation")
     * @var string
     */
    protected $defaultViewImplementation;

    /**
     * This method walks through the view configuration and applies
     * matching configurations in the order of their specifity score.
     * Possible options are currently the viewObjectName to specify
     * a different class that will be used to create the view and
     * an array of options that will be set on the view object.
     *
     * @param ActionRequest $request
     * @return array
     */
    public function getViewConfiguration(ActionRequest $request)
    {
        $cacheIdentifier = $this->createCacheIdentifier($request);

        $viewConfiguration = $this->cache->get($cacheIdentifier);
        if ($viewConfiguration === false) {
            $configurations = $this->configurationManager->getConfiguration('Views');

            $requestMatcher = new RequestMatcher($request);
            $context = new Context($requestMatcher);

            $viewConfiguration = [];
            $highestWeight = -1;
            foreach ($configurations as $order => $configuration) {
                $requestMatcher->resetWeight();
                if (!isset($configuration['requestFilter'])) {
                    $weight = $order;
                } else {
                    $result = $this->eelEvaluator->evaluate($configuration['requestFilter'], $context);
                    if ($result === false) {
                        continue;
                    }
                    $weight = $requestMatcher->getWeight() + $order;
                }
                if ($weight > $highestWeight) {
                    $viewConfiguration = $configuration;
                    $highestWeight = $weight;
                }
            }
            $this->cache->set($cacheIdentifier, $viewConfiguration);
        }

        return $viewConfiguration;
    }

    public function resolveViewObjectNameForRequest(ActionRequest $actionRequest, ViewResolveConfiguration $viewResolveConfiguration): string
    {
        $viewsConfiguration = $this->getViewConfiguration($actionRequest);
        return $viewsConfiguration['viewObjectName'] ?? $this->resolveViewObjectName($actionRequest, $viewResolveConfiguration);
    }

    /**
     * Determines the fully qualified view object name.
     *
     * @return string The fully qualified view object name or false if no matching view could be found.
     */
    private function resolveViewObjectName(ActionRequest $request, ViewResolveConfiguration $viewResolveConfiguration): string
    {
        $possibleViewObjectName = $viewResolveConfiguration->viewObjectNamePattern;
        $packageKey = $request->getControllerPackageKey();
        $subpackageKey = $request->getControllerSubpackageKey();
        $format = $request->getFormat();

        if ($subpackageKey !== null && $subpackageKey !== '') {
            $packageKey .= '\\' . $subpackageKey;
        }
        $possibleViewObjectName = str_replace([
            '@package',
            '@controller',
            '@action'
        ], [
            str_replace('.', '\\', $packageKey),
            $request->getControllerName(),
            $request->getControllerActionName()
        ], $possibleViewObjectName);

        $viewObjectName = $this->objectManager->getCaseSensitiveObjectName(strtolower(str_replace('@format', $format, $possibleViewObjectName)));
        if ($viewObjectName === null) {
            $viewObjectName = $this->objectManager->getCaseSensitiveObjectName(strtolower(str_replace('@format', '', $possibleViewObjectName)));
        }
        if ($viewObjectName === null && isset($viewResolveConfiguration->viewFormatToObjectNameMap[$format])) {
            $viewObjectName = $viewResolveConfiguration->viewFormatToObjectNameMap[$format];
        }

        if (empty($viewObjectName) && !empty($viewResolveConfiguration->defaultViewObjectName)) {
            $viewObjectName = $viewResolveConfiguration->defaultViewObjectName;
        }

        if (empty($viewObjectName)) {
            $viewObjectName = $this->defaultViewImplementation;
        }

        return $viewObjectName;
    }

    /**
     * Create a complete cache identifier for the given
     * request that conforms to cache identifier syntax
     *
     * @param ActionRequest $request
     * @return string
     */
    protected function createCacheIdentifier(ActionRequest $request)
    {
        $cacheIdentifiersParts = [];
        do {
            $cacheIdentifiersParts[] = $request->getControllerPackageKey();
            $cacheIdentifiersParts[] = $request->getControllerSubpackageKey();
            $cacheIdentifiersParts[] = $request->getControllerName();
            $cacheIdentifiersParts[] = $request->getControllerActionName();
            $cacheIdentifiersParts[] = $request->getFormat();
            $request = $request->getParentRequest();
        } while ($request instanceof ActionRequest);
        return md5(implode('-', $cacheIdentifiersParts));
    }
}
