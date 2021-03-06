<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model;

use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowTransitionType;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Model\TransitionAssembler;
use Oro\Bundle\WorkflowBundle\Model\Condition\Configurable as ConfigurableCondition;
use Oro\Bundle\WorkflowBundle\Model\Action\Configurable as ConfigurableAction;

class TransitionAssemblerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $conditionFactory;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $actionFactory;

    /**
     * @var TransitionAssembler
     */
    protected $assembler;

    /**
     * @var array
     */
    protected $transitionDefinitions = array(
        'empty_definition' => array(),
        'with_condition' => array(
            'conditions' => array('@true' => null)
        ),
        'with_post_actions' => array(
            'post_actions' => array('@assign_value' => array('parameters' => array('$attribute', 'first_value')))
        ),
        'with_init_actions' => array(
            'init_actions' => array('@assign_value' => array('parameters' => array('$attribute', 'first_value')))
        ),
        'full_definition' => array(
            'conditions' => array('@true' => null),
            'post_actions' => array('@assign_value' => array('parameters' => array('$attribute', 'first_value'))),
            'init_actions' => array('@assign_value' => array('parameters' => array('$attribute', 'first_value'))),
        )
    );

    protected function setUp()
    {
        $this->conditionFactory = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Condition\ConditionFactory')
            ->disableOriginalConstructor()
            ->getMock();
        $this->actionFactory = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Action\ActionFactory')
            ->disableOriginalConstructor()
            ->getMock();
        $this->assembler = new TransitionAssembler($this->conditionFactory, $this->actionFactory);
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\AssemblerException
     * @dataProvider missedTransitionDefinitionDataProvider
     * @param array $configuration
     */
    public function testAssembleNoRequiredTransitionDefinitionException($configuration)
    {
        $this->assembler->assemble($configuration, array(), array());
    }

    public function missedTransitionDefinitionDataProvider()
    {
        return array(
            'no options' => array(
                array(
                    'name' => array()
                )
            ),
            'no transition_definition' => array(
                array(
                    'name' => array(
                        '' => 'test'
                    )
                )
            )
        );
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\AssemblerException
     * @dataProvider incorrectTransitionDefinitionDataProvider
     * @param array $definitions
     */
    public function testUnknownTransitionDefinitionAssembler($definitions)
    {
        $configuration = array(
            'test' => array(
                'transition_definition' => 'unknown'
            )
        );
        $this->assembler->assemble($configuration, $definitions, array());
    }

    public function incorrectTransitionDefinitionDataProvider()
    {
        return array(
            'no definitions' => array(
                array()
            ),
            'unknown definition' => array(
                array('known' => array())
            )
        );
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\AssemblerException
     * @dataProvider incorrectStepsDataProvider
     * @param array $steps
     */
    public function testUnknownStepException($steps)
    {
        $configuration = array(
            'test' => array(
                'transition_definition' => 'transition_definition',
                'label' => 'label',
                'step_to' => 'unknown'
            )
        );
        $definitions = array('transition_definition' => array());
        $this->assembler->assemble($configuration, $definitions, $steps);
    }

    public function incorrectStepsDataProvider()
    {
        return array(
            'no steps' => array(
                array()
            ),
            'unknown step' => array(
                array('known' => $this->getStep())
            )
        );
    }

    /**
     * @dataProvider configurationDataProvider
     * @param array $configuration
     * @param array $transitionDefinition
     */
    public function testAssemble(array $configuration, array $transitionDefinition)
    {
        $steps = array(
            'step' => $this->getStep()
        );

        $expectedCondition = null;
        $expectedAction = null;
        if (array_key_exists('conditions', $transitionDefinition)) {
            $expectedCondition = $this->getCondition();
            $this->conditionFactory->expects($this->once())
                ->method('create')
                ->with(
                    ConfigurableCondition::ALIAS,
                    $transitionDefinition['conditions']
                )
                ->will($this->returnValue($expectedCondition));
        }
        $actionFactoryCallCount = 0;
        if (array_key_exists('post_actions', $transitionDefinition)) {
            $actionFactoryCallCount++;
        }
        if (array_key_exists('init_actions', $transitionDefinition)) {
            $actionFactoryCallCount++;
        }
        if ($actionFactoryCallCount) {
            $expectedAction = $this->getAction();
            $this->actionFactory->expects($this->exactly($actionFactoryCallCount))
                ->method('create')
                ->with(
                    ConfigurableAction::ALIAS,
                    $transitionDefinition['post_actions']
                )
                ->will($this->returnValue($this->getAction()));
        }

        $transitions = $this->assembler->assemble(
            array('test' => $configuration),
            $this->transitionDefinitions,
            $steps
        );

        $configuration = array_merge(
            array(
                'is_start' => false,
                'form_type' => WorkflowTransitionType::NAME,
                'form_options' => array(),
                'frontend_options' => array(),
            ),
            $configuration
        );

        $this->assertInstanceOf('Doctrine\Common\Collections\ArrayCollection', $transitions);
        $this->assertCount(1, $transitions);
        $this->assertTrue($transitions->containsKey('test'));

        /** @var Transition $actualTransition */
        $actualTransition = $transitions->get('test');
        $this->assertEquals('test', $actualTransition->getName());
        $this->assertEquals($steps['step'], $actualTransition->getStepTo());
        $this->assertEquals($configuration['label'], $actualTransition->getLabel());
        $this->assertEquals($configuration['frontend_options'], $actualTransition->getFrontendOptions());
        $this->assertEquals($configuration['is_start'], $actualTransition->isStart());
        $this->assertEquals($configuration['form_type'], $actualTransition->getFormType());
        $this->assertEquals($configuration['form_options'], $actualTransition->getFormOptions());
        $this->assertEquals($expectedCondition, $actualTransition->getCondition());
        $this->assertEquals($expectedAction, $actualTransition->getPostAction());
    }

    public function configurationDataProvider()
    {
        return array(
            'empty_definition' => array(
                'configuration' => array(
                    'transition_definition' => 'empty_definition',
                    'label' => 'label',
                    'step_to' => 'step',
                    'form_type' => 'custom_workflow_transition',
                    'frontend_options' => array('class' => 'foo', 'icon' => 'bar'),
                ),
                'transitionDefinition' => $this->transitionDefinitions['empty_definition'],
            ),
            'with_condition' => array(
                'configuration' => array(
                    'transition_definition' => 'with_condition',
                    'label' => 'label',
                    'step_to' => 'step',
                ),
                'transitionDefinition' => $this->transitionDefinitions['with_condition'],
            ),
            'with_post_actions' => array(
                'configuration' => array(
                    'transition_definition' => 'with_post_actions',
                    'label' => 'label',
                    'step_to' => 'step',
                ),
                'transitionDefinition' => $this->transitionDefinitions['with_post_actions'],
            ),
            'full_definition' => array(
                'configuration' => array(
                    'transition_definition' => 'full_definition',
                    'label' => 'label',
                    'step_to' => 'step',
                ),
                'transitionDefinition' => $this->transitionDefinitions['full_definition'],
            ),
            'start_transition' => array(
                'configuration' => array(
                    'transition_definition' => 'empty_definition',
                    'label' => 'label',
                    'step_to' => 'step',
                    'is_start' => true,
                ),
                'transitionDefinition' => $this->transitionDefinitions['empty_definition'],
            ),
        );
    }

    protected function getStep()
    {
        return $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Step')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getCondition()
    {
        return $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Condition\ConditionInterface')
            ->getMockForAbstractClass();
    }

    protected function getAction()
    {
        return $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Action\ActionInterface')
            ->getMockForAbstractClass();
    }
}
