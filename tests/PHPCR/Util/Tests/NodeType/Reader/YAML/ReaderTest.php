<?php

namespace PHPCR\Util\Tests\NodeType\Reader\YAML;

use PHPCR\SimpleCredentials;
use PHPCR\NodeType\NodeDefinitionTemplateInterface;
use PHPCR\Version\OnParentVersionAction;
use PHPCR\Util\NodeType\Reader\YAML\YAMLReader;

class ReaderTest extends BaseTestCase
{
    protected $session;
    protected $workspace;
    protected $nsRegistry;
    protected $ntManager;

    public function setUp()
    {
        parent::setUp();
        $this->session     = $this->prophesize('PHPCR\SessionInterface');
        $this->workspace   = $this->prophesize('PHPCR\WorkspaceInterface');
        $this->nsRegistry  = $this->prophesize('PHPCR\NamespaceRegistryInterface');
        $this->ntManager   = $this->prophesize('PHPCR\NodeType\NodeTypeManagerInterface');
        $this->ntTemplate  = $this->prophesize('PHPCR\NodeType\NodeTypeTemplateInterface');
        $this->ndTemplate  = $this->prophesize('PHPCR\NodeType\NodeDefinitionTemplateInterface');
        $this->pdTemplate  = $this->prophesize('PHPCR\NodeType\PropertyDefinitionTemplateInterface');
        $this->ndTemplates = new \ArrayObject();
        $this->pdTemplates = new \ArrayObject();

        $this->parser = new YAMLReader($this->session->reveal());

        $this->session->getWorkspace()->willReturn($this->workspace);
        $this->workspace->getNamespaceRegistry()->willReturn($this->nsRegistry);
        $this->workspace->getNodeTypeManager()->willReturn($this->ntManager);
    }

    public function testReader()
    {
        $this->nsRegistry->registerNamespace('test', 'http://www.example.com/test')->shouldBeCalled();
        $this->nsRegistry->registerNamespace('boo', 'http://www.example.com/boo')->shouldBeCalled();

        // setup
        $this->ntManager->createNodeTypeTemplate()->willReturn($this->ntTemplate);
        $this->ntManager->createNodeDefinitionTemplate()->willReturn($this->ndTemplate);
        $this->ntManager->createPropertyDefinitionTemplate()->willReturn($this->pdTemplate);
        $this->ntTemplate->getNodeDefinitionTemplates()->willReturn($this->ndTemplates);
        $this->ntTemplate->getPropertyDefinitionTemplates()->willReturn($this->pdTemplates);


        // node definition assertions
        $assertions = array(
            'setName' => 'test:article',
            'setAutoCreated' => true,
            'setMandatory' => true,
            'setOnParentVersion' => OnParentVersionAction::COPY,
            'setProtected' => false,
            'setRequiredPrimaryTypeNames' => array(
                'nt:unstructured',
                'nt:base',
            ),
            'setDefaultPrimaryTypeName' => 'nt:unstructured',
            'setSameNameSiblings' => true,
        );

        foreach ($assertions as $methodName => $expectedValue) {
            $this->ndTemplate->$methodName($expectedValue)->shouldBeCalled();
        }

        // property definition assertions
        $assertions = array(
            'setName' => 'test:title',
            'setAutoCreated' => true,
            'setMandatory' => true,
            'setOnParentVersion' => OnParentVersionAction::COPY,
            'setProtected' => true,
            'setRequiredType' => 1,
            'setValueConstraints' => array('foobar.*'),
            'setDefaultValues' => array('Default Value'),
            'setMultiple' => false,
            'setAvailableQueryOperators' => array(
                'jcr.operator.equal.to',
                'jcr.operator.not.equal.to',
            ),
            'setFullTextSearchable' => true,
            'setQueryOrderable' => true,
        );

        foreach ($assertions as $methodName => $expectedValue) {
            $this->pdTemplate->$methodName($expectedValue)->shouldBeCalled();
        }

        $this->parser->getNodeTypeDefinitionTemplates(file_get_contents(__DIR__ . '/../../../../../../fixtures/nodetype1.yml'));
    }
}
