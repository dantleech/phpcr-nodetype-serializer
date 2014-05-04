<?php

namespace PHPCR\Util\Tests\NodeType\Importer\YAML;

use PHPCR\SimpleCredentials;
use PHPCR\NodeType\NodeDefinitionTemplateInterface;
use PHPCR\Version\OnParentVersionAction;
use PHPCR\Util\NodeType\Importer\YAML\YAMLImporter;

class YAMLImporterTest extends BaseTestCase
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

        $this->parser = new YAMLImporter($this->session->reveal());

        $this->session->getWorkspace()->willReturn($this->workspace);
        $this->workspace->getNamespaceRegistry()->willReturn($this->nsRegistry);
        $this->workspace->getNodeTypeManager()->willReturn($this->ntManager);

        // setup
        $this->ntManager->createNodeTypeTemplate()->willReturn($this->ntTemplate);
        $this->ntManager->createNodeDefinitionTemplate()->willReturn($this->ndTemplate);
        $this->ntManager->createPropertyDefinitionTemplate()->willReturn($this->pdTemplate);
        $this->ntTemplate->getNodeDefinitionTemplates()->willReturn($this->ndTemplates);
        $this->ntTemplate->getPropertyDefinitionTemplates()->willReturn($this->pdTemplates);
    }

    public function testImporter()
    {
        $this->nsRegistry->registerNamespace('test', 'http://www.example.com/test')->shouldBeCalled();
        $this->nsRegistry->registerNamespace('boo', 'http://www.example.com/boo')->shouldBeCalled();

        // node type defininition assertions
        $assertions = array(
            'setName' => 'test:article',
            'setAbstract' => true,
            'setMixin' => false,
            'setOrderableChildNodes' => true,
            'setQueryable' => true,
            'setPrimaryItemName' => 'comment',
        );

        foreach ($assertions as $methodName => $expectedValue) {
            $this->ntTemplate->$methodName($expectedValue)->shouldBeCalled();
        }

        // node definition assertions
        $assertions = array(
            'setName' => 'test:comment',
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

        $this->parser->getNodeTypeTemplates(file_get_contents(__DIR__ . '/../../../../../../fixtures/nodetype1.yml'));
    }

    public function testInvalidKeys1()
    {
        $this->setExpectedException('PHPCR\Util\NodeType\Importer\Exception\InvalidConfigurationException', <<<EOT
- Unknown key "invalid1", must be one of "namespace, children, auto_created, properties, abstract, mixin, orderable_child_nodes, primary_item_name, queryable, declared_super_type_names"
 - Unknown key "invalid2", must be one of "namespace, children, auto_created, properties, abstract, mixin, orderable_child_nodes, primary_item_name, queryable, declared_super_type_names"
 - Unknown key "invalid4", must be one of "namespace, auto_created, mandatory, protected, default_primary_type, same_name_siblings, on_parent_version, required_primary_types"
 - Unknown key "invalid3", must be one of "auto_created, namespace, mandatory, multiple, protected, default_value, full_text_searchable, query_orderable, required_type, value_constraints, available_query_operators, on_parent_version
EOT
        );

        $this->parser->getNodeTypeTemplates(file_get_contents(__DIR__ . '/../../../../../../fixtures/nodetype2.yml'));
    }

    public function testInvalidKeys2()
    {
        $this->setExpectedException('PHPCR\Util\NodeType\Importer\Exception\InvalidConfigurationException', <<<EOT
 - Unknown key "barbar", must be one of "namespaces, node_types"
 - Unknown key "invalid1", must be one of "namespace, children, auto_created, properties, abstract, mixin, orderable_child_nodes, primary_item_name, queryable, declared_super_type_names"
 - Unknown key "invalid2", must be one of "namespace, children, auto_created, properties, abstract, mixin, orderable_child_nodes, primary_item_name, queryable, declared_super_type_names"
 - Unknown key "0", must be one of "namespace, auto_created, mandatory, protected, default_primary_type, same_name_siblings, on_parent_version, required_primary_types"
 - Unknown key "0", must be one of "auto_created, namespace, mandatory, multiple, protected, default_value, full_text_searchable, query_orderable, required_type, value_constraints, available_query_operators, on_parent_version"
EOT
        );
        $this->parser->getNodeTypeTemplates(file_get_contents(__DIR__ . '/../../../../../../fixtures/nodetype3.yml'));
    }
}
