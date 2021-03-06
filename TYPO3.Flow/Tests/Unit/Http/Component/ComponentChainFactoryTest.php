<?php
namespace TYPO3\Flow\Tests\Unit\Http\Component;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Http\Component\ComponentChainFactory;
use TYPO3\Flow\Http\Component\ComponentInterface;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Tests\UnitTestCase;

/**
 * Test case for the Http Component Chain Factory
 */
class ComponentChainFactoryTest extends UnitTestCase
{
    /**
     * @var ComponentChainFactory
     */
    protected $componentChainFactory;

    /**
     * @var ObjectManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockObjectManager;

    /**
     * @var ComponentInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockComponent;

    public function setUp()
    {
        $this->componentChainFactory = new ComponentChainFactory();

        $this->mockObjectManager = $this->createMock(\TYPO3\Flow\Object\ObjectManagerInterface::class);
        $this->inject($this->componentChainFactory, 'objectManager', $this->mockObjectManager);

        $this->mockComponent = $this->createMock(\TYPO3\Flow\Http\Component\ComponentInterface::class);
    }

    /**
     * @test
     */
    public function createInitializesComponentsInTheRightOrderAccordingToThePositionDirective()
    {
        $chainConfiguration = array(
            'foo' => array(
                'component' => 'Foo\Component\ClassName',
            ),
            'bar' => array(
                'component' => 'Bar\Component\ClassName',
                'position' => 'before foo',
            ),
            'baz' => array(
                'component' => 'Baz\Component\ClassName',
                'position' => 'after bar'
            ),
        );

        $this->mockObjectManager->expects($this->at(0))->method('get')->with('Bar\Component\ClassName')->will($this->returnValue($this->mockComponent));
        $this->mockObjectManager->expects($this->at(1))->method('get')->with('Baz\Component\ClassName')->will($this->returnValue($this->mockComponent));
        $this->mockObjectManager->expects($this->at(2))->method('get')->with('Foo\Component\ClassName')->will($this->returnValue($this->mockComponent));

        $this->componentChainFactory->create($chainConfiguration);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Http\Component\Exception
     */
    public function createThrowsExceptionIfComponentClassNameIsNotConfigured()
    {
        $chainConfiguration = array(
            'foo' => array(
                'position' => 'start',
            ),
        );

        $this->componentChainFactory->create($chainConfiguration);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Http\Component\Exception
     */
    public function createThrowsExceptionIfComponentClassNameDoesNotImplementComponentInterface()
    {
        $chainConfiguration = array(
            'foo' => array(
                'component' => 'Foo\Component\ClassName',
            ),
        );

        $this->mockObjectManager->expects($this->at(0))->method('get')->with('Foo\Component\ClassName')->will($this->returnValue(new \stdClass()));
        $this->componentChainFactory->create($chainConfiguration);
    }
}
