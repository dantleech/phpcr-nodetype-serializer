<?php

namespace PHPCR\Util\NodeType\Serializer;

use Symfony\Component\Yaml\Yaml;
use PHPCR\SessionInterface;
use PHPCR\Util\NodeType\Serializer\Exception\InvalidConfigurationException;
use PHPCR\PropertyType;
use PHPCR\NodeType\ItemDefinitionInterface;
use RomaricDrigon\MetaYaml\MetaYaml;
use PHPCR\Version\OnParentVersionAction;
use RomaricDrigon\MetaYaml\Loader\YamlLoader;

/**
 * Deserializes a YAML file into node type definitions.
 *
 * Namespaces can also be registered.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class YAMLDeserializer
{
    protected $session;

    protected $nodeTypeMap = array();
    protected $propertyMap = array();
    protected $nodeDefinitionMap = array();

    /**
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
        $this->configure();
    }

    private function configure()
    {
        $this->nodeTypeMap = array(
            'name' => function ($t, $v) { $t->setName($v); },
            'auto_created' => function ($t, $v) { $t->setAutoCreated((boolean) $v); },
            'declared_supertypes' => function ($t, $v) { $t->setDeclaredSuperTypeNames((array) $v); },
            'abstract' => function ($t, $v) { $t->setAbstract($v); },
            'mixin' => function ($t, $v) { $t->setMixin((boolean) $v); },
            'orderable_child_nodes' => function ($t, $v) { $t->setOrderableChildNodes((boolean) $v); },
            'primary_item' => function ($t, $v) { $t->setPrimaryItemName((string) $v); },
            'queryable' => function ($t, $v) { $t->setQueryable((boolean) $v); },
        );

        $this->childDefinitionMap = array_merge(
            $this->getItemDefinitionMap(),
            array(
                'default_primary_type' => function ($t, $v) { $t->setDefaultPrimaryTypeName($v); },
                'same_name_siblings' => function ($t, $v) { $t->setSameNameSiblings((boolean) $v); },
                'required_primary_types' => function ($t, $v) { $t->setRequiredPrimaryTypeNames((array) $v); },
            )
        );

        $this->propertyMap = array_merge(
            $this->getItemDefinitionMap(),
            array(
                'multiple' => function ($t, $v) { $t->setMultiple((boolean) $v); },
                'default_values' => function ($t, $v) { $t->setDefaultValues((array) $v); },
                'full_text_searchable' => function ($t, $v) { $t->setFullTextSearchable((boolean) $v); },
                'query_orderable' => function ($t, $v) { $t->setQueryOrderable((boolean) $v); },
                'value_constraints' => function ($t, $v) { $t->setValueConstraints((array) $v); },
                'required_type' => function ($t, $v) {$t->setRequiredType(PropertyType::valueFromName($v)); },
                'available_query_operators' => function ($t, $v) {
                    $queryOperators = array();
                    foreach ($v as $queryOperator) {
                        $queryOperator = constant('PHPCR\Query\QOM\QueryObjectModelConstantsInterface::' . strtoupper(str_replace('.', '_', $queryOperator)));
                        $queryOperators[] = $queryOperator;
                    }
                    $t->setAvailableQueryOperators($queryOperators);
                }
            )
        );
    }

    /**
     * @return NodeTypeManagerInterface
     */
    protected function getNodeTypeManager()
    {
        return $this->session->getWorkspace()->getNodeTypeManager();
    }

    /**
     * @return NamespaceRegistryInterface
     */
    protected function getNamespaceRegistry()
    {
        return $this->session->getWorkspace()->getNamespaceRegistry();
    }

    /**
     * Deserialize a single node type definition
     *
     * @param string $yaml
     *
     * @return NodeTypeTemplateInterface
     */
    public function deserialize($yaml)
    {
        $data = Yaml::parse($yaml);

        return $this->createNtTemplate($data);
    }

    public function deserializeAggregate($yaml)
    {
        $data = Yaml::parse($yaml);
        $data = new \ArrayObject($data);

        if (isset($data['namespaces'])) {
            foreach ($data['namespaces'] as $prefix => $url) {
                $this->getNamespaceRegistry()->registerNamespace($prefix, $url);
            }
        }

        $ntTemplates = array();
        if (isset($data['node_types'])) {
            foreach ($data['node_types'] as $nodeType) {
                $ntTemplates[] = $this->createNtTemplate($nodeType);
            }
        }

        return $ntTemplates;
    }

    private function validateNodeType($data)
    {
        $schema = Yaml::parse(file_get_contents(__DIR__.'/schema/node-type.yml'));
        $schema = new MetaYaml($schema);
        // $schema->validateSchema();
        $schema->validate($data);
    }

    /**
     * Create a node type template
     *
     * @return PHPCR\NodeType\NodeTypeTemplateInterface
     */
    private function createNtTemplate($v)
    {
        $this->validateNodeType($v);

        $ntTemplate = $this->getNodeTypeManager()->createNodeTypeTemplate();

        foreach ($this->nodeTypeMap as $key => $setter) {
            if (isset($v[$key])) {
                $setter($ntTemplate, $v[$key]);
            }
        }

        if (isset($v['children'])) {
            foreach ($v['children'] as $childData) {
                $ntTemplate->getNodeDefinitionTemplates()->append($this->createChildTemplate($childData));
            }
        }

        if (isset($v['properties'])) {
            foreach ($v['properties'] as $propertyData) {
                $ntTemplate->getPropertyDefinitionTemplates()->append($this->createPropertyTemplate($propertyData));
            }
        }

        return $ntTemplate;
    }

    /**
     * Create a child node definition template
     *
     * @return PHPCR\NodeType\NodeDefinitionTemplateInterface
     */
    private function createChildTemplate($v)
    {
        $ndTemplate = $this->getNodeTypeManager()->createNodeDefinitionTemplate();

        foreach ($this->childDefinitionMap as $key => $setter) {
            if (isset($v[$key])) {
                $setter($ndTemplate, $v[$key]);
            }
        }

        return $ndTemplate;
    }

    /**
     * Create a property definition template
     *
     * @return PHPCR\NodeType\PropertyDefinitionTemplateInterface
     */
    private function createPropertyTemplate($v)
    {
        $pTemplate = $this->getNodeTypeManager()->createPropertyDefinitionTemplate();

        foreach ($this->propertyMap as $key => $setter) {
            if (isset($v[$key])) {
                $setter($pTemplate, $v[$key]);
            }
        }

        return $pTemplate;
    }

    private function getItemDefinitionMap()
    {
        return array(
            'name' => function ($t, $v) { $t->setName($v); },
            'auto_created' => function ($t, $v) { $t->setAutoCreated((boolean) $v); },
            'mandatory' => function ($t, $v) { $t->setMandatory((boolean) $v); },
            'protected' => function ($t, $v) { $t->setProtected((boolean) $v); },
            'on_parent_version' => function ($t, $v) { $t->setOnParentVersion(OnParentVersionAction::valueFromName($v)); },
        );
    }
}
