<?php
namespace TYPO3\Flow\Package;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Core\ClassLoader;
use TYPO3\Flow\Utility\Files;
use TYPO3\Flow\Utility\PhpAnalyzer;

/**
 * Class for building Packages
 */
class PackageFactory {

	/**
	 * Returns a package instance.
	 *
	 * @param string $packagesBasePath the base install path of packages,
	 * @param string $packagePath path to package, relative to base path
	 * @param string $packageKey key / name of the package
	 * @param string $composerName
	 * @param array $autoloadConfiguration Autoload configuration as defined in composer.json
	 * @param array $packageClassInformation
	 * @return PackageInterface
	 * @throws Exception\CorruptPackageException
	 */
	public function create($packagesBasePath, $packagePath, $packageKey, $composerName, array $autoloadConfiguration = [], array $packageClassInformation = NULL) {
		$absolutePackagePath = Files::concatenatePaths(array($packagesBasePath, $packagePath)) . '/';

		if ($packageClassInformation === NULL) {
			$packageClassInformation = $this->detectFlowPackageFilePath($packageKey, $absolutePackagePath, $autoloadConfiguration);
		}

		$packageClassName = Package::class;
		if (!empty($packageClassInformation)) {
			$packageClassName = $packageClassInformation['className'];
			$packageClassPath = Files::concatenatePaths(array($absolutePackagePath, $packageClassInformation['pathAndFilename']));
			require_once($packageClassPath);
		}

		$package = new $packageClassName($packageKey, $composerName, $absolutePackagePath, $autoloadConfiguration);
		if (!$package instanceof PackageInterface) {
			throw new Exception\CorruptPackageException(sprintf('The package class of package "%s" does not implement \TYPO3\Flow\Package\PackageInterface. Check the file "%s".', $packageKey, $packageClassInformation['pathAndFilename']), 1427193370);
		}

		return $package;
	}

	/**
	 * Detects if the package contains a package file and returns the path and classname.
	 *
	 * @param string $packageKey The package key
	 * @param string $absolutePackagePath Absolute path to the package
	 * @param array $autoloadDirectives
	 * @return array The path to the package file and classname for this package or an empty array if none was found.
	 * @throws Exception\CorruptPackageException
	 */
	public function detectFlowPackageFilePath($packageKey, $absolutePackagePath, array $autoloadDirectives = []) {
		if (isset($autoloadDirectives[ClassLoader::MAPPING_TYPE_PSR4])) {
			$packageClassPathAndFilename = Files::concatenatePaths(array('Classes', 'Package.php'));
		} else {
			$packageClassPathAndFilename = Files::concatenatePaths(array('Classes', str_replace('.', '/', $packageKey), 'Package.php'));
		}
		$absolutePackageClassPath = Files::concatenatePaths(array($absolutePackagePath, $packageClassPathAndFilename));
		if (!is_file($absolutePackageClassPath)) {
			return [];
		}

		$packageClassContents = file_get_contents($absolutePackageClassPath);
		$packageClassName = (new PhpAnalyzer($packageClassContents))->extractFullyQualifiedClassName();
		if ($packageClassName === NULL) {
			throw new Exception\CorruptPackageException(sprintf('The package "%s" does not contain a valid package class. Check if the file "%s" really contains a class.', $packageKey, $packageClassPathAndFilename), 1327587091);
		}

		return array('className' => $packageClassName, 'pathAndFilename' => $packageClassPathAndFilename);
	}
}
