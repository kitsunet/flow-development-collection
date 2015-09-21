<?php
namespace TYPO3\Flow\Tests\Unit\Package;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Package\Exception\InvalidPackageStateException;
use TYPO3\Flow\Package\MetaData\PackageConstraint;
use TYPO3\Flow\Package\MetaDataInterface;
use TYPO3\Flow\Package\Package;
use org\bovigo\vfs\vfsStream;
use TYPO3\Flow\Package\PackageManager;
use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\Flow\Tests\UnitTestCase;

/**
 * Testcase for the package class
 *
 */
class PackageTest extends UnitTestCase {

	/**
	 * @var PackageManager
	 */
	protected $mockPackageManager;

	/**
	 */
	public function setUp() {
		vfsStream::setup('Packages');
		$this->mockPackageManager = $this->getMockBuilder(\TYPO3\Flow\Package\PackageManager::class)->disableOriginalConstructor()->getMock();
		ObjectAccess::setProperty($this->mockPackageManager, 'composerManifestData', array(), TRUE);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\Flow\Package\Exception\InvalidPackageKeyException
	 */
	public function constructorThrowsInvalidPackageKeyExceptionIfTheSpecifiedPackageKeyIsNotValid() {
		$packagePath = 'vfs://Packages/Vendor.TestPackage';
		mkdir($packagePath, 0777, TRUE);
		new Package('InvalidPackageKey', 'invalid-package-key', $packagePath);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\Flow\Package\Exception\InvalidPackagePathException
	 */
	public function constructorThrowsInvalidPackagePathExceptionIfTheSpecifiedPackagePathDoesNotExist() {
		new Package('Vendor.TestPackage', 'vendor/test-package', './ThisPackageSurelyDoesNotExist');
	}

	/**
	 * @test
	 * @expectedException \TYPO3\Flow\Package\Exception\InvalidPackagePathException
	 */
	public function constructorThrowsInvalidPackagePathExceptionIfTheSpecifiedClassesPathHasALeadingSlash() {
		$packagePath = 'vfs://Packages/Vendor.TestPackage/';
		mkdir($packagePath, 0777, TRUE);
		new Package('Vendor.TestPackage', 'vendor/test-package', $packagePath, '/tmp');
	}

	/**
	 * @test
	 * @expectedException \TYPO3\Flow\Package\Exception\InvalidPackageManifestException
	 */
	public function constructorThrowsInvalidPackageManifestExceptionIfNoComposerManifestWasFound() {
		$packagePath = 'vfs://Packages/Vendor.TestPackage/';
		mkdir($packagePath, 0777, TRUE);
		new Package('Vendor.TestPackage', 'vendor/test-package', $packagePath);
	}

	/**
	 */
	public function validPackageKeys() {
		return array(
			array('Doctrine.DBAL'),
			array('TYPO3.Flow'),
			array('RobertLemke.Flow.Twitter'),
			array('Sumphonos.Stem'),
			array('Schalke04.Soccer.MagicTrainer')
		);
	}

	/**
	 * @test
	 * @dataProvider validPackageKeys
	 */
	public function constructAcceptsValidPackageKeys($packageKey) {
		$packagePath = 'vfs://Packages/' . str_replace('\\', '/', $packageKey) . '/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "' . $packageKey . '", "type": "flow-test"}');

		$package = new Package($packageKey, $packageKey, $packagePath);
		$this->assertEquals($packageKey, $package->getPackageKey());
	}

	/**
	 */
	public function invalidPackageKeys() {
		return array(
			array('TYPO3..Flow'),
			array('RobertLemke.Flow. Twitter'),
			array('Schalke*4')
		);
	}

	/**
	 * @test
	 * @dataProvider invalidPackageKeys
	 * @expectedException \TYPO3\Flow\Package\Exception\InvalidPackageKeyException
	 */
	public function constructRejectsInvalidPackageKeys($packageKey) {
		$packagePath = 'vfs://Packages/' . str_replace('\\', '/', $packageKey) . '/';
		mkdir($packagePath, 0777, TRUE);
		new Package($this->mockPackageManager, $packageKey, $packagePath);
	}

	/**
	 * @test
	 */
	public function getNamespaceReturnsThePsr0NamespaceIfAPsr0MappingIsDefined() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage", "type": "flow-test", "autoload": { "psr-0": { "Namespace1": "path1" }, "psr-4": { "Namespace2": "path2" } }}');
		$package = new Package('Acme.MyPackage', 'acme/mypackage', $packagePath);
		$this->assertEquals('Namespace1', $package->getNamespace());
	}

	/**
	 * @test
	 */
	public function getNamespaceReturnsTheFirstPsr0NamespaceIfMultiplePsr0MappingsAreDefined() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage4123/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage4123", "type": "flow-test", "autoload": { "psr-0": { "Namespace1": "path2", "Namespace2": "path2" } }}');
		$package = new Package('Acme.MyPackage4123', 'acme/mypackage4123', $packagePath);
		$this->assertEquals('Namespace1', $package->getNamespace());
	}

	/**
	 * @test
	 */
	public function getNamespaceReturnsPsr4NamespaceIfNoPsr0MappingIsDefined() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage3412/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage3412", "type": "flow-test", "autoload": { "psr-4": { "Namespace2": "path2" } }}');
		$package = new Package('Acme.MyPackage3412', 'acme/mypackage3412', $packagePath);
		$this->assertEquals('Namespace2', $package->getNamespace());
	}

	/**
	 * @test
	 */
	public function getNamespaceReturnsTheFirstPsr4NamespaceIfMultiplePsr4MappingsAreDefined() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage2341/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage2341", "type": "flow-test", "autoload": { "psr-4": { "Namespace2": "path2", "Namespace3": "path3" } }}');
		$package = new Package('Acme.MyPackage2341', 'acme/mypackage2341', $packagePath);
		$this->assertEquals('Namespace2', $package->getNamespace());
	}

	/**
	 * @test
	 */
	public function getNamespaceReturnsThePhpNamespaceCorrespondingToThePackageKeyIfNoPsrMappingIsDefined() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage1234/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage1234", "type": "flow-test"}');
		$package = new Package('Acme.MyPackage1234', 'acme/mypackage1234', $packagePath);
		$this->assertEquals('Acme\\MyPackage1234', $package->getNamespace());
	}

	/**
	 * @test
	 */
	public function getClassesPathReturnsPathToClasses() {
		$package = new Package('TYPO3.Flow', 'typo3/flow', FLOW_PATH_FLOW);
		$packageClassesPath = $package->getClassesPath();
		$expected = $package->getPackagePath() . Package::DIRECTORY_CLASSES;
		$this->assertEquals($expected, $packageClassesPath);
	}

	/**
	 * @test
	 */
	public function getClassesPathReturnsNormalizedPathToClasses() {
		$packagePath = 'vfs://Packages/Application/Acme/MyPackage/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage", "type": "flow-test", "autoload": {"psr-0": {"Acme\\MyPackage": "no/trailing/slash/"}}}');

		$package = new Package('Acme.MyPackage', 'acme/mypackage', $packagePath);

		$packageClassesPath = $package->getClassesPath();
		$expected = $package->getPackagePath() . 'no/trailing/slash/';

		$this->assertEquals($expected, $packageClassesPath);
	}

	/**
	 * @test
	 */
	public function aPackageCanBeFlaggedAsProtected() {
		$packagePath = 'vfs://Packages/Application/Vendor/Dummy/';
		mkdir($packagePath, 0700, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "vendor/dummy", "type": "flow-test"}');
		$package = new Package('Vendor.Dummy', 'vendor/dummy', $packagePath);

		$this->assertFalse($package->isProtected());
		$package->setProtected(TRUE);
		$this->assertTrue($package->isProtected());
	}

	/**
	 * @test
	 */
	public function isObjectManagementEnabledTellsIfObjectManagementShouldBeEnabledForPackages() {
		$packagePath = 'vfs://Packages/Application/Vendor/Dummy/';
		mkdir($packagePath, 0700, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "vendor/dummy", "type": "typo3-flow-test"}');
		$package = new Package('Vendor.Dummy', 'vendor/dummy', $packagePath);

		$this->assertTrue($package->isObjectManagementEnabled());
	}

	/**
	 * @test
	 */
	public function getClassFilesReturnsAListOfClassFilesOfThePackage() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage", "type": "flow-test", "autoload": {"psr-0": {"Acme\\MyPackage": "Classes/"}}}');

		mkdir($packagePath . 'Classes/Acme/MyPackage/Controller', 0770, TRUE);
		mkdir($packagePath . 'Classes/Acme/MyPackage/Domain/Model', 0770, TRUE);

		file_put_contents($packagePath . 'Classes/Acme/MyPackage/Controller/FooController.php', '');
		file_put_contents($packagePath . 'Classes/Acme/MyPackage/Domain/Model/Foo.php', '');
		file_put_contents($packagePath . 'Classes/Acme/MyPackage/Domain/Model/Bar.php', '');

		$expectedClassFilesArray = array(
			'Acme\MyPackage\Controller\FooController' => 'Classes/Acme/MyPackage/Controller/FooController.php',
			'Acme\MyPackage\Domain\Model\Foo' => 'Classes/Acme/MyPackage/Domain/Model/Foo.php',
			'Acme\MyPackage\Domain\Model\Bar' => 'Classes/Acme/MyPackage/Domain/Model/Bar.php',
		);

		$package = new Package('Acme.MyPackage', 'acme/mypackage', $packagePath, ['psr-0' => ['acme\\MyPackage' => 'Classes/']]);
		foreach ($package->getClassFiles() as $className => $classPath) {
			$this->assertArrayHasKey($className, $expectedClassFilesArray);
			$this->assertEquals($expectedClassFilesArray[$className], $classPath);
		}
	}

	/**
	 * @test
	 */
	public function packageMetaDataContainsPackageType() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage", "type": "flow-test"}');

		$package = new Package($this->getMock(\TYPO3\Flow\Package\PackageManager::class), 'Acme.MyPackage', $packagePath, 'Classes');

		$metaData = $package->getPackageMetaData();
		$this->assertEquals('flow-test', $metaData->getPackageType());
	}

	/**
	 * @test
	 */
	public function getPackageMetaDataAddsRequiredPackagesAsConstraint() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage", "type": "flow-test", "require": { "some/other/package": "*" }}');

		$mockPackageManager = $this->getMockBuilder(PackageManager::class)->disableOriginalConstructor()->getMock();
		$mockPackageManager->expects($this->once())->method('getPackageKeyFromComposerName')->with('some/other/package')->will($this->returnValue('Some.Other.Package'));

		$package = new Package($mockPackageManager, 'Acme.MyPackage', $packagePath, 'Classes');
		$metaData = $package->getPackageMetaData();
		$packageConstraints = $metaData->getConstraintsByType(MetaDataInterface::CONSTRAINT_TYPE_DEPENDS);

		$this->assertCount(1, $packageConstraints);

		$expectedConstraint = new PackageConstraint(MetaDataInterface::CONSTRAINT_TYPE_DEPENDS, 'Some.Other.Package');
		$this->assertEquals($expectedConstraint, $packageConstraints[0]);
	}

	/**
	 * @test
	 */
	public function getPackageMetaDataIgnoresUnresolvableConstraints() {
		$packagePath = 'vfs://Packages/Application/Acme.MyPackage/';
		mkdir($packagePath, 0777, TRUE);
		file_put_contents($packagePath . 'composer.json', '{"name": "acme/mypackage", "type": "flow-test", "require": { "non/existing/package": "*" }}');

		$mockPackageManager = $this->getMockBuilder(PackageManager::class)->disableOriginalConstructor()->getMock();
		$mockPackageManager->expects($this->once())->method('getPackageKeyFromComposerName')->with('non/existing/package')->will($this->throwException(new InvalidPackageStateException()));

		$package = new Package($mockPackageManager, 'Acme.MyPackage', $packagePath, 'Classes');
		$metaData = $package->getPackageMetaData();
		$packageConstraints = $metaData->getConstraintsByType(MetaDataInterface::CONSTRAINT_TYPE_DEPENDS);

		$this->assertCount(0, $packageConstraints);
	}
}
