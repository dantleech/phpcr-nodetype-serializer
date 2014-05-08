<?php

namespace PHPCR\Util\NodeType\Serializer;

use Symfony\Component\Yaml\Yaml;
use PHPCR\SessionInterface;
use PHPCR\Util\NodeType\Serializer\Exception\InvalidConfigurationException;
use PHPCR\PropertyType;

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
    protected $map = array();
    protected $errors = array();

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
     * Configure the valid keys, validation and setters
     */
    protected function configure()
    {
        $this->map('base', 'namespaces',
            $this->getScalarArrayValidator()
        );
        $this->map('base', 'node_types', 'array');

        $this->map('nodeType', 'namespace',             'string');
        $this->map('nodeType', 'children',              'array');
        $this->map('nodeType', 'auto_created',          'boolean', function ($t, $v) { $t->setAutoCreated((boolean) $v); });
        $this->map('nodeType', 'properties',            'array');
        $this->map('nodeType', 'abstract',              'boolean', function ($t, $v) { $t->setAbstract($v); });
        $this->map('nodeType', 'mixin',                 'boolean', function ($t, $v) { $t->setMixin((boolean) $v); });
        $this->map('nodeType', 'orderable_child_nodes', 'boolean', function ($t, $v) { $t->setOrderableChildNodes((boolean) $v); });
        $this->map('nodeType', 'primary_item_name',     'string',  function ($t, $v) { $t->setPrimaryItemName((string) $v); });
        $this->map('nodeType', 'queryable',             'boolean', function ($t, $v) { $t->setQueryable((boolean) $v); });
        $this->map('nodeType', 'declared_super_type_names', 
            $this->getScalarArrayValidator('string'),
            function ($t, $v) { $t->setDeclaredSuperTypeNames((array) $v); }
        );

        $this->map('child', 'namespace',            'string');
        $this->map('child', 'auto_created',         'boolean',   function ($t, $v) { $t->setAutoCreated((boolean) $v); });
        $this->map('child', 'mandatory',            'boolean',   function ($t, $v) { $t->setMandatory((boolean) $v); });
        $this->map('child', 'protected',            'boolean',   function ($t, $v) { $t->setProtected((boolean) $v); });
        $this->map('child', 'default_primary_type', 'string', function ($t, $v) { $t->setDefaultPrimaryTypeName($v); });
        $this->map('child', 'same_name_siblings',   'boolean',   function ($t, $v) { $t->setSameNameSiblings((boolean) $v); });
        $this->map('child', 'on_parent_version',
            $this->getOnParentActionValidator(),
            function ($t, $v) {
                $t->setOnParentVersion(constant('\PHPCR\Version\OnParentVersionAction::' . strtoupper($v)));
            }
        );
        $this->map('child', 'required_primary_types',
            $this->getScalarArrayValidator(),
            function ($t, $v) { $t->setRequiredPrimaryTypeNames((array) $v); }
        );

        $this->map('property', 'auto_created',         'boolean',   function ($t, $v) { $t->setAutoCreated((boolean) $v); });
        $this->map('property', 'namespace',            'string');
        $this->map('property', 'mandatory',            'boolean',   function ($t, $v) { $t->setMandatory((boolean) $v); });
        $this->map('property', 'multiple',             'boolean',   function ($t, $v) { $t->setMultiple((boolean) $v); });
        $this->map('property', 'protected',            'boolean',   function ($t, $v) { $t->setProtected((boolean) $v); });
        $this->map('property', 'default_value',        'scalar', function ($t, $v) { $t->setDefaultValues((array) $v); });
        $this->map('property', 'full_text_searchable', 'boolean',   function ($t, $v) { $t->setFullTextSearchable((boolean) $v); });
        $this->map('property', 'query_orderable',      'boolean',   function ($t, $v) { $t->setQueryOrderable((boolean) $v); });

        $this->map('property', 'required_type',
            $this->getTypeValidator(),
            function ($t, $v) {
                $intVal = constant('PHPCR\PropertyType::' . strtoupper($v));
                $t->setRequiredType($intVal);
            }
        );
        $this->map('property', 'value_constraints',
            $this->getScalarArrayValidator(),
            function ($t, $v) { $t->setValueConstraints((array) $v); }
        );
        $this->map('property', 'available_query_operators', 
            $this->getAvailableQueryOperatorsValidator(),
            function ($t, $v) {
                $queryOperators = array();
                foreach ($v as $queryOperator) {
                    $queryOperator = constant('PHPCR\Query\QOM\QueryObjectModelConstantsInterface::' . strtoupper($queryOperator));
                    $queryOperators[] = $queryOperator;
                }
                $t->setAvailableQueryOperators($queryOperators);
            }
        );
        $this->map('property', 'on_parent_version',
            $this->getOnParentActionValidator(),
            function ($t, $v) {
                $t->setOnParentVersion(constant('PHPCR\Version\OnParentVersionAction::' . strtoupper($v)));
            }
        );
    }

    /**
     * Used by configure()
     */
    private function map($type, $name, $validator, \Closure $setter = null)
    {
        $this->map[$type][$name] = array(
            'validator' => $validator, 
            'setter'    => $setter
        );
    }

    /**
     * Validate a set of key => value pairs for the given type
     *
     * Errors are added to class level $errors array
     *
     * @param string
     * @param array
     */
    protected function validateBlock($type, $data)
    {
        $data = (array) $data;

        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $this->map[$type])) {
                $this->errors[] = sprintf('Unknown key "%s", must be one of "%s"', $key, implode(', ', array_keys($this->map[$type])));
                continue;
            }

            $validator = $this->map[$type][$key]['validator'];

            if (is_string($validator)) {
                if ($validator == 'scalar') {
                    if (!is_scalar($value)) {
                        $this->errors[] = sprintf('Value for key "%s" should be a "scalar"', $key);
                    }
                } elseif (gettype($value) !== $validator) {
                    $this->errors[] = sprintf('Value for key "%s" should be a "%s"', $key, $validator);
                }
            } elseif ($validator instanceof \Closure) {
                list($valid, $message) = $validator($value);
                if (!$valid) {
                    $this->errors[] = sprintf('Value for key "%s" is not valid: %s', $key, $message);
                }
            } else {
                throw new \RuntimeException('Invalid validator');
            }
        }
    }

    /**
     * Validate the parsed YAML data
     *
     * @param $data array
     */
    protected function validate($data)
    {
        $this->validateBlock('base', $data);

        if (isset($data['node_types'])) {
            foreach ($data['node_types'] as $name => $nodeTypeData) {
                $this->validateBlock('nodeType', $nodeTypeData);

                if (isset($nodeTypeData['children'])) {
                    foreach ($nodeTypeData['children'] as $name => $childData) {
                        $this->validateBlock('child', $childData);
                    }
                }

                if (isset($nodeTypeData['properties'])) {
                    foreach ($nodeTypeData['properties'] as $name => $propertyData) {
                        $this->validateBlock('property', $propertyData);
                    }
                }
            }
        }

        if (count($this->errors) > 0) {
            throw new InvalidConfigurationException(sprintf(
                "Invalid configuration: \n\n - %s",
                implode("\n - ", $this->errors)
            ));
        }
    }

    /**
     * Return a validator closure to ensure that an array contains an array of scalar values
     *
     * @return \Closure
     */
    protected function getScalarArrayValidator()
    {
        return function ($v) {
            if (!is_array($v)) {
                return array(false, 'Value is not an array');
            }

            $errors = array();
            foreach ($v as $key => $vv) {
                if (!is_scalar($vv)) {
                    $errors[] = sprintf('The value for key "%s" is not a scalar value', $key);
                }
            }

            if (count($errors) > 0) {
                return array(false, implode("\n", $errors));
            }

            return array(true, null);
        };
    }

    /**
     * Return a validator closure to ensure that a given value is a valid parent action action
     *
     * @return \Closure
     */
    protected function getOnParentActionValidator()
    {
        return function ($v) {
            return array(
                defined('PHPCR\Version\OnParentVersionAction::' . strtoupper($v)),
                'Unknown on parent version action, should be one of COPY, VERSION, INITIALIZE, COMPUTE, IGNORE or ABORT'
            );
        };
    }

    /**
     * Return a validator closure to ensure that a given value is a valid PHPCR type
     *
     * @return \Closure
     */
    protected function getTypeValidator()
    {
        return function ($v) {
            try {
                PropertyType::valueFromName($v);
            } catch (\InvalidArgumentException $e) {
                return array(false, $e->getMessage());
            }

            return array(true, null);
        };
    }

    /**
     * Return a validator closure to ensure that a given value is a valid query operators value
     *
     * @return \Closure
     */
    protected function getAvailableQueryOperatorsValidator()
    {
        return function ($v) {
            if (!is_array($v)) {
                return array(false, 'Value is not an array');
            }

            return array(true, null);
        };
    }

    /**
     * Get the name including the namespace if it is set in the parent data
     *
     * @param string $name
     * @param array  $data
     */
    private function getName($name, $data)
    {
        if (isset($data['namespace'])) {
            $name = $data['namespace'] . ':' . $name;
        }

        return $name;
    }

    /**
     * Return an array of node type definition templates for the given file.
     * If a namespaces key is present, register those namespaces with the repository also.
     *
     * @param string $yaml YAML string
     *
     * @return array
     */
    public function getNodeTypeTemplates($yaml)
    {
        $data = Yaml::parse($yaml);
        $data = new \ArrayObject($data);

        $this->configure();
        $this->validate($data);

        if (isset($data['namespaces'])) {
            foreach ($data['namespaces'] as $prefix => $url) {
                $this->getNamespaceRegistry()->registerNamespace($prefix, $url);
            }
        }

        $ntTemplates = array();
        if (isset($data['node_types'])) {
            foreach ($data['node_types'] as $name => $nodeType) {
                $ntTemplate = $this->createNtTemplate($name, $nodeType);
            }
        }

        return $ntTemplates;
    }

    /**
     * Create a node type template
     *
     * @return PHPCR\NodeType\NodeTypeTemplateInterface
     */
    private function createNtTemplate($name, $nodeTypeData)
    {
        $ntTemplate = $this->getNodeTypeManager()->createNodeTypeTemplate();
        $ntTemplate->setName($this->getName($name, $nodeTypeData));

        foreach (array_keys($nodeTypeData) as $key) {
            $this->applySetter('nodeType', $key, $ntTemplate, $nodeTypeData);

            if (isset($nodeTypeData['children'])) {
                foreach ($nodeTypeData['children'] as $name => $childData) {
                    $ntTemplate->getNodeDefinitionTemplates()->append($this->createChildTemplate($name, $childData));
                }
            }

            if (isset($nodeTypeData['properties'])) {
                foreach ($nodeTypeData['properties'] as $name => $propertyData) {
                    $ntTemplate->getPropertyDefinitionTemplates()->append($this->createPropertyTemplate($name, $propertyData));
                }
            }
        }

        return $ntTemplate;
    }

    /**
     * Create a child node definition template
     *
     * @return PHPCR\NodeType\NodeDefinitionTemplateInterface
     */
    private function createChildTemplate($name, $childData)
    {
        $ndTemplate = $this->getNodeTypeManager()->createNodeDefinitionTemplate();
        $ndTemplate->setName($this->getName($name, $childData));

        foreach (array_keys($childData) as $key) {
            $this->applySetter('child', $key, $ndTemplate, $childData);
        }

        return $ndTemplate;
    }

    /**
     * Create a property definition template
     *
     * @return PHPCR\NodeType\PropertyDefinitionTemplateInterface
     */
    private function createPropertyTemplate($name, $propertyData)
    {
        $pTemplate = $this->getNodeTypeManager()->createPropertyDefinitionTemplate();
        $pTemplate->setName($this->getName($name, $propertyData));

        foreach (array_keys($propertyData) as $key) {
            $this->applySetter('property', $key, $pTemplate, $propertyData);
        }

        return $pTemplate;
    }

    /**
     * Apply the mapped setter only if one has been set.
     *
     * @param string $type
     * @param string $name
     * @param mixed  $template
     * @param array  $data
     */
    private function applySetter($type, $name, $template, $data)
    {
        $setter = $this->map[$type][$name]['setter'];
        if ($setter) {
            $value = $data[$name];
            $setter($template, $value, $data);
        }
    }
}
