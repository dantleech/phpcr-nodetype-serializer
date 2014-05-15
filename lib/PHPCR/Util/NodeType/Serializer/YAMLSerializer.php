<?php

namespace PHPCR\Util\NodeType\Serializer;

use PHPCR\PropertyType;
use Symfony\Component\Yaml\Dumper;
use PHPCR\NodeType\NodeTypeInterface;
use Symfony\Component\Yaml\Yaml;
use PHPCR\NodeType\ItemDefinitionInterface;
use PHPCR\Version\OnParentVersionAction;

/**
 * PHPCR node type YAML serializer
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class YAMLSerializer
{
    /**
     * Convert a node type into YAML format
     *
     * @param NodeTypeInterface
     *
     * @return string
     */
    public function serialize(NodeTypeInterface $nt)
    {
        $out = array(
            'name' => $nt->getName(),
            'declared_supertypes' => $nt->getDeclaredSupertypeNames(),
            'abstract' => (boolean) $nt->isAbstract(),
            'mixin' => (boolean) $nt->isMixin(),
            'orderable_child_nodes' => (boolean) $nt->hasOrderableChildNodes(),
            'queryable' => (boolean) $nt->isQueryable(),
            'primary_item' => $nt->getPrimaryItemName(),
            'properties' => array(),
            'children' => array(),
        );

        foreach ($nt->getDeclaredPropertyDefinitions() as $pd) {
            $property = $this->getItemDefinitionArray($pd);
            $property = array_merge($property, array(
                'required_type' => PropertyType::nameFromValue($pd->getRequiredType()),
                'value_constraints' => $pd->getValueConstraints(),
                'default_value' => $pd->getDefaultValues(),
                'multiple' => (boolean) $pd->isMultiple(),
                'available_query_operators' => $pd->getAvailableQueryOperators(),
                'full_text_searchable' => (boolean) $pd->isFullTextSearchable(),
                'query_orderable' => (boolean) $pd->isQueryOrderable(),
            ));
            $out['properties'][] = $property;
        }

        foreach ($nt->getDeclaredChildNodeDefinitions() as $cd) {
            $child = $this->getItemDefinitionArray($cd);
            $child = array_merge($child, array(
                'required_primary_types' => $cd->getRequiredPrimaryTypeNames(),
                'default_primary_type' => $cd->getDefaultPrimaryTypeName(),
                'same_name_siblings' => (boolean) $cd->allowsSameNameSiblings(),
            ));

            $out['children'][] = $child;
        }

        return Yaml::dump($out, 10);
    }

    private function getItemDefinitionArray(ItemDefinitionInterface $id)
    {
        return array(
            'name' => $id->getName(),
            'auto_created' => (boolean) $id->isAutoCreated(),
            'mandatory' => (boolean) $id->isMandatory(),
            'on_parent_version' => OnParentVersionAction::nameFromValue($id->getOnParentVersion()),
            'protected' => (boolean) $id->isProtected(),
        );
    }
}
