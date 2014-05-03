<?php

namespace PHPCR\Util\NodeType\Reader\YAML;

use Symfony\Component\Yaml\Yaml;
use PHPCR\SessionInterface;

/**
 * Translate a YAML file into node type definitions.
 *
 * Namespaces can also be registered.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class YAMLReader
{
    protected $session;

    /**
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
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
     * Return an array of node type definition templates for the given file.
     * If a namespaces key is present, register those namespaces with the repository also.
     *
     * @param string $yaml YAML string
     *
     * @return array
     */
    public function getNodeTypeDefinitionTemplates($yaml)
    {
        $data = Yaml::parse($yaml);

        if (isset($data['namespaces'])) {
            foreach ($data['namespaces'] as $prefix => $url) {
                $this->getNamespaceRegistry()->registerNamespace($prefix, $url);
            }
        }

        foreach ($data['node_types'] as $name => $nodeType) {
            $this->createNodeTypeTemplate($name, $nodeType);
        }
    }

    /**
     * Create the node type template from the given array
     * representing a node type
     *
     * @param array $nodeType
     *
     * @return NodeTypeTemplateInterface
     */
    public function createNodeTypeTemplate($name, $nodeType)
    {
        $ntTemplate = $this->getNodeTypeManager()->createNodeTypeTemplate();

        $t = $this->getNodeTypeManager()->createNodeDefinitionTemplate();

        if (isset($nodeType['namespace'])) {
            $name = $nodeType['namespace'] . ':' . $name;
        }

        $t->setName($name);

        $s = function ($name, $callback) use ($nodeType, $t) {
            if (isset($nodeType[$name])) {
                $v = $nodeType[$name];
                $callback($t, $v);
            }
        };

        $s('auto_created',                function ($t, $v) { $t->setAutoCreated((boolean) $v); });
        $s('mandatory',                   function ($t, $v) { $t->setMandatory((boolean) $v); });
        $s('on_parent_version',           function ($t, $v) { 
            $t->setOnParentVersion(constant('\PHPCR\Version\OnParentVersionAction::' . strtoupper($v))); 
        });
        $s('protected',                   function ($t, $v) { $t->setProtected((boolean) $v); });
        $s('required_primary_types',      function ($t, $v) { $t->setRequiredPrimaryTypeNames((array) $v); });
        $s('default_primary_type',        function ($t, $v) { $t->setDefaultPrimaryTypeName($v); });
        $s('same_name_siblings',          function ($t, $v) { $t->setSameNameSiblings((boolean) $v); });

        $ntTemplate->getNodeDefinitionTemplates()->append($t);

        if (isset($nodeType['properties'])) {
            foreach ($nodeType['properties'] as $name => $property) {
                if (isset($nodeType['namespace'])) {
                    $name = $nodeType['namespace'] . ':' . $name;
                }

                $t = $this->getNodeTypeManager()->createPropertyDefinitionTemplate();
                $t->setName($name);

                $s = function ($name, $callback) use ($property, $t) {
                    if (isset($property[$name])) {
                        $v = $property[$name];
                        $callback($t, $v);
                    }
                };
                $s('auto_created',              function ($t, $v) { $t->setAutoCreated((boolean) $v); });
                $s('mandatory',                 function ($t, $v) { $t->setMandatory((boolean) $v); });
                $s('multiple',                  function ($t, $v) { $t->setMultiple((boolean) $v); });
                $s('on_parent_version',         function ($t, $v) {
                    $t->setOnParentVersion(constant('\PHPCR\Version\OnParentVersionAction::' . strtoupper($v))); 
                });
                $s('protected',                 function ($t, $v) { $t->setProtected((boolean) $v); });
                $s('required_type',             function ($t, $v) {
                    $intVal = constant('PHPCR\PropertyType::' . strtoupper($v));
                    $t->setRequiredType($intVal);
                });
                $s('value_constraints',         function ($t, $v) { $t->setValueConstraints((array) $v); });
                $s('default_value',             function ($t, $v) { $t->setDefaultValues((array) $v); });
                $s('available_query_operators', function ($t, $v) {
                    $queryOperators = array();
                    foreach ($v as $queryOperator) {
                        $queryOperator = constant('PHPCR\Query\QOM\QueryObjectModelConstantsInterface::' . strtoupper($queryOperator));
                        $queryOperators[] = $queryOperator;
                    }
                    $t->setAvailableQueryOperators($queryOperators);
                });
                $s('full_text_searchable',      function ($t, $v) { $t->setFullTextSearchable((boolean) $v); });
                $s('query_orderable',           function ($t, $v) { $t->setQueryOrderable((boolean) $v); });

                $ntTemplate->getPropertyDefinitionTemplates()->append($t);
            }
        }

        return $ntTemplate;
    }
}
