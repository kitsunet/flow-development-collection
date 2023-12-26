<?php
namespace Neos\Flow\Mvc;

/**
 *
 */
class ViewResolveConfiguration
{
    public function __construct(
        public string $defaultViewObjectName = '',
        public string $viewObjectNamePattern = '',
        public array $viewFormatToObjectNameMap = []
    ) {}
}
