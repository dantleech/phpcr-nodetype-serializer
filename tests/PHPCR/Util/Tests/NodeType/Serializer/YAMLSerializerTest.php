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
        $this->nodeType->getDeclaredPropertyDefinitions()->willReturn(array(
        ));
        $this->nodeType->getDeclaredChildNodeDefinitions()->willReturn(array(
        ));
    }

    public function testSerializer()
    {
        $res = $this->serializer->serialize($this->nodeType->reveal());
        error_log($res);
    }
}
