<?php
namespace Neos\Flow\Mvc\Controller;

use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Exception\ViewNotFoundException;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Flow\Mvc\ViewConfigurationManager;

/**
 *
 */
class ViewSupport
{
    public function __construct(
        private ViewConfigurationManager $viewConfigurationManager
    ) {

    }

    /**
     * Prepares a view for the current action and stores it in $this->view.
     * By default, this method tries to locate a view with a name matching
     * the current action.
     *
     * @return ViewInterface the resolved view
     * @throws ViewNotFoundException if no view can be resolved
     */
    protected function resolveView(ActionRequest $request)
    {
        $viewsConfiguration = $this->viewConfigurationManager->getViewConfiguration($request);
        $viewObjectName = $this->defaultViewImplementation;
        if (!empty($this->defaultViewObjectName)) {
            $viewObjectName = $this->defaultViewObjectName;
        }
        $viewObjectName = $this->resolveViewObjectName($request) ?: $viewObjectName;
        if (isset($viewsConfiguration['viewObjectName'])) {
            $viewObjectName = $viewsConfiguration['viewObjectName'];
        }

        if (!is_a($viewObjectName, ViewInterface::class, true)) {
            throw new ViewNotFoundException(sprintf(
                'View class has to implement ViewInterface but "%s" in action "%s" of controller "%s" does not.',
                $viewObjectName,
                $request->getControllerActionName(),
                get_class($this)
            ), 1355153188);
        }

        $viewOptions = $viewsConfiguration['options'] ?? [];
        $view = $viewObjectName::createWithOptions($viewOptions);

        $this->emitViewResolved($view);

        return $view;
    }

    /**
     * Determines the fully qualified view object name.
     *
     * @return mixed The fully qualified view object name or false if no matching view could be found.
     */
    private function resolveViewObjectName(ActionRequest $request): string
    {
        $possibleViewObjectName = $this->viewObjectNamePattern;
        $packageKey = $request->getControllerPackageKey();
        $subpackageKey = $request->getControllerSubpackageKey();
        $format = $request->getFormat();

        if ($subpackageKey !== null && $subpackageKey !== '') {
            $packageKey .= '\\' . $subpackageKey;
        }
        $possibleViewObjectName = str_replace('@package', str_replace('.', '\\', $packageKey), $possibleViewObjectName);
        $possibleViewObjectName = str_replace('@controller', $request->getControllerName(), $possibleViewObjectName);
        $possibleViewObjectName = str_replace('@action', $request->getControllerActionName(), $possibleViewObjectName);

        $viewObjectName = $this->objectManager->getCaseSensitiveObjectName(strtolower(str_replace('@format', $format, $possibleViewObjectName)));
        if ($viewObjectName === null) {
            $viewObjectName = $this->objectManager->getCaseSensitiveObjectName(strtolower(str_replace('@format', '', $possibleViewObjectName)));
        }
        if ($viewObjectName === null && isset($this->viewFormatToObjectNameMap[$format])) {
            $viewObjectName = $this->viewFormatToObjectNameMap[$format];
        }

        return $viewObjectName;
    }
}
