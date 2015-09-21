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
use TYPO3\Flow\Annotations\After;

/**
 * Interface for a Flow Package class
 *
 * @api
 */
interface PackageInterface {

	const PATTERN_MATCH_PACKAGEKEY = '/^[a-z0-9]+\.(?:[a-z0-9][\.a-z0-9]*)+$/i';

	const DIRECTORY_CLASSES = 'Classes/';
	const DIRECTORY_CONFIGURATION = 'Configuration/';
	const DIRECTORY_DOCUMENTATION = 'Documentation/';
	const DIRECTORY_METADATA = 'Meta/';
	const DIRECTORY_TESTS_FUNCTIONAL = 'Tests/Functional/';
	const DIRECTORY_TESTS_UNIT = 'Tests/Unit/';
	const DIRECTORY_RESOURCES = 'Resources/';

	const DEFAULT_COMPOSER_TYPE = 'typo3-flow-package';

	/**
	 * Invokes custom PHP code directly after the package manager has been initialized.
	 *
	 * @param \TYPO3\Flow\Core\Bootstrap $bootstrap The current bootstrap
	 * @return void
	 */
	public function boot(\TYPO3\Flow\Core\Bootstrap $bootstrap);

	/**
	 * Returns the array of filenames of the class files
	 *
	 * @return array An array of class names (key) and their filename, including the relative path to the package's directory
	 * @api
	 */
	public function getClassFiles();

	/**
	 * Returns the package key of this package.
	 *
	 * @return string
	 * @api
	 */
	public function getPackageKey();

	/**
	 * Returns the packages composer name
	 *
	 * @return string
	 * @api
	 */
	public function getComposerName();

	/**
	 * Returns an array of all declared autoload namespaces contained in this package
	 *
	 * @return string[]
	 * @api
	 */
	public function getNamespaces();

	/**
	 * Returns the PHP namespace of classes in this package.
	 *
	 * @return string
	 * @api
	 * @deprecated Use getNamespaces()
	 */
	public function getNamespace();

	/**
	 * Tells if this package is protected and therefore cannot be deactivated or deleted
	 *
	 * @return boolean
	 * @api
	 */
	public function isProtected();

	/**
	 * Tells if files in the Classes directory should be registered and object management enabled for this package.
	 *
	 * @return boolean
	 */
	public function isObjectManagementEnabled();

	/**
	 * Sets the protection flag of the package
	 *
	 * @param boolean $protected TRUE if the package should be protected, otherwise FALSE
	 * @return void
	 * @api
	 */
	public function setProtected($protected);

	/**
	 * Returns the full path to this package's main directory
	 *
	 * @return string Path to this package's main directory
	 * @api
	 */
	public function getPackagePath();

	/**
	 * Returns the full path to this package's Classes directory
	 *
	 * @return string Path to this package's Classes directory
	 * @api
	 * @deprecated
	 */
	public function getClassesPath();

	/**
	 * Returns the full path to the package's classes namespace entry path,
	 * e.g. "My.Package/ClassesPath/My/Package/"
	 *
	 * @return string Path to this package's Classes directory
	 * @api
	 * @deprecated
	 */
	public function getClassesNamespaceEntryPath();

	/**
	 * Returns the full path to this package's Resources directory
	 *
	 * @return string Path to this package's Resources directory
	 * @api
	 */
	public function getResourcesPath();

	/**
	 * Returns the full path to this package's Configuration directory
	 *
	 * @return string Path to this package's Configuration directory
	 * @api
	 */
	public function getConfigurationPath();

	/**
	 * Get the composer manifest for package. Optionally accessing specific parts by giving a path.
	 *
	 * @param string $path A path of "." separated keys to directly access some specific part of the composer manifest.
	 * @return mixed
	 * @api
	 */
	public function getComposerManifest($path = NULL);

	/**
	 * Retrieve a version number for this package.
	 *
	 * @return string
	 * @api
	 */
	public function getInstalledVersion();
}
