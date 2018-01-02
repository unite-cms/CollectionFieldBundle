<?php

namespace UnitedCMS\CollectionFieldBundle\SchemaType\Factories;

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use UnitedCMS\CollectionFieldBundle\Model\Collection;
use UnitedCMS\CoreBundle\Entity\FieldableField;
use UnitedCMS\CoreBundle\Field\FieldTypeInterface;
use UnitedCMS\CoreBundle\Field\FieldTypeManager;
use UnitedCMS\CoreBundle\SchemaType\SchemaTypeManager;

class CollectionFieldTypeFactory
{
    /**
     * @var FieldTypeManager $fieldTypeManager
     */
    private $fieldTypeManager;

    public function __construct(FieldTypeManager $fieldTypeManager)
    {
        $this->fieldTypeManager = $fieldTypeManager;
    }

    /**
     * Creates a new collectionField schema type.
     *
     * @param SchemaTypeManager $schemaTypeManager
     * @param FieldableField $field
     * @param Collection $collection
     * @return ObjectType
     */
    public function createCollectionFieldType(SchemaTypeManager $schemaTypeManager, FieldableField $field, Collection $collection)
    {
        $schemaTypeName = ucfirst($field->getEntity()->getIdentifier()) . ucfirst($field->getIdentifier()) . 'CollectionField';
        $schemaTypeRowName = $schemaTypeName . 'Row';

        if(!$schemaTypeManager->hasSchemaType($schemaTypeName)) {
            if(!$schemaTypeManager->hasSchemaType($schemaTypeRowName)) {

                /**
                 * @var FieldableField[] $fields
                 */
                $fields = [];

                /**
                 * @var Type[] $fieldsSchemaTypes
                 */
                $fieldsSchemaTypes = [];

                /**
                 * @var FieldTypeInterface[] $fieldTypes
                 */
                $fieldTypes = [];
                foreach($collection->getFields() as $field) {
                    $fields[$field->getIdentifier()] = $field;
                    $fieldTypes[$field->getIdentifier()] = $this->fieldTypeManager->getFieldType($field->getType());
                    $fieldTypes[$field->getIdentifier()]->setEntityField($field);
                    $fieldsSchemaTypes[$field->getIdentifier()] = $fieldTypes[$field->getIdentifier()]->getGraphQLType($schemaTypeManager);
                    $fieldTypes[$field->getIdentifier()]->unsetEntityField();
                }

                $schemaTypeManager->registerSchemaType(new ObjectType([
                    'name' => $schemaTypeRowName,
                    'fields' => function() use($fieldsSchemaTypes){
                        return $fieldsSchemaTypes;
                    },
                    'resolveField' => function($value, array $args, $context, ResolveInfo $info) use ($fields, $fieldTypes) {

                        if(!isset($fieldTypes[$info->fieldName]) || !isset($fields[$info->fieldName]) || !isset($value[$info->fieldName])) {
                            return null;
                        }

                        $return_value = null;
                        $fieldType = $this->fieldTypeManager->getFieldType($fieldTypes[$info->fieldName]->getType());
                        $fieldType->setEntityField($fields[$info->fieldName]);
                        $return_value = $fieldType->resolveGraphQLData($value[$info->fieldName]);
                        $fieldType->unsetEntityField();
                        return $return_value;
                    }
                ]));
            }
            $newSchemaType = new ListOfType($schemaTypeManager->getSchemaType($schemaTypeRowName));
            $newSchemaType->name = $schemaTypeName;
            $schemaTypeManager->registerSchemaType($newSchemaType);
        }

        return $schemaTypeManager->getSchemaType($schemaTypeName);
    }

}