<?php

namespace PHPCR\Util\Tests\NodeType\Serializer;

use PHPCR\SimpleCredentials;
use PHPCR\NodeType\NodeDefinitionTemplateInterface;
use PHPCR\Version\OnParentVersionAction;
use PHPCR\Util\NodeType\Serializer\YAMLSerializer;

class YAMLSerializerTest extends BaseTestCase
{
    protected $serializer;
    protected $nodeType;
    protected $nodeDefinition;
    protected $propertyDefinition;

    public function setUp()
    {
        parent::setUp();

        $this->serializer = new YAMLSerializer();
        $this->nodeType = $this->prophesize('PHPCR\NodeType\NodeTypeInterface');
        $this->nodeDefinition = $this->prophesize('PHPCR\NodeType\NodeDefinitionInterface');
        $this->propertyDefinition = $this->prophesize('PHPCR\NodeType\PropertyDefinitionInterface');

        $this->nodeType->getName()->willReturn('test:article');
        $this->nodeType->isAbstract()->willReturn(true);
        $this->nodeType->isMixin()->willReturn(true);
        $this->nodeType->hasOrderableChildNodes()->willReturn(true);
        $this->nodeType->getPrimaryItemName()->willReturn('comment');
        $this->nodeType->isQueryable()->willReturn(true);
        $this->nodeType->getDeclaredSupertypeNames()->willReturn(array(
            'nt:unstructured'
        ));

        $this->propertyDefinition->getName()->willReturn('test:title');
        $this->propertyDefinition->isAutoCreated()->willReturn(true);
        $this->propertyDefinition->isMandatory()->willReturn(true);
        $this->propertyDefinition->getOnParentVersion()->willReturn(1);
        $this->propertyDefinition->isProtected()->willReturn(true);
        $this->propertyDefinition->getRequiredType()->willReturn('STRING');
        $this->propertyDefinition->getValueConstraints()->willReturn('.*');
        $this->propertyDefinition->getDefaultValues()->willReturn(array('default_value'));
        $this->propertyDefinition->isMultiple()->willReturn(true);
        $this->propertyDefinition->getAvailableQueryOperators()->willReturn(array());
        $this->propertyDefinition->isFullTextSearchable()->willReturn(true);
        $this->propertyDefinition->isQueryOrderable()->willReturn(true);

        $this->nodeDefinition->getName()->willReturn('test:comment');
        $this->nodeDefinition->isAutoCreated()->willReturn(true);
        $this->nodeDefinition->isMandatory()->willReturn(true);
        $this->nodeDefinition->getOnParentVersion()->willReturn(1);
        $this->nodeDefinition->isProtected()->willReturn(true);
        $this->nodeDefinition->getRequiredPrimaryTypeNames()->willReturn(array(
            'nt:unstructured',
            'nt:base',
        ));
        $this->nodeDefinition->getDefaultPrimaryTypeName()->willReturn('nt:unstructured');
        $this->nodeDefinition->allowsSameNameSiblings()->willReturn(false);

        $this->nodeType->getDeclaredPropertyDefinitions()->willReturn(array(
            $this->propertyDefinition
        ));

        $this->nodeType->getDeclaredChildNodeDefinitions()->willReturn(array(
            $this->nodeDefinition
        ));
    }

    public function testSerializer()
    {
        $res = $this->serializer->serialize($this->nodeType->reveal());
        $expected = <<<EOT
name: 'test:article'
declared_supertypes:
    - 'nt:unstructured'
abstract: true
mixin: true
orderable_child_nodes: true
queryable: true
primary_item: comment
properties:
    -
        name: 'test:title'
        auto_created: true
        mandatory: true
        on_parent_version: COPY
        protected: true
        required_type: undefined
        value_contraints: '.*'
        default_values:
            - default_value
        multiple: true
        available_query_operators: {  }
        full_text_searchable: true
        query_orderable: true
children:
    -
        name: 'test:comment'
        auto_created: true
        mandatory: true
        on_parent_version: COPY
        protected: true
        required_primary_types:
            - 'nt:unstructured'
            - 'nt:base'
        default_primary_type: 'nt:unstructured'
        same_name_siblings: false

EOT
        ;

        $this->assertEquals($res, $expected);
    }
}
