<?php

namespace UnitedCMS\CollectionFieldBundle\Field\Types;

use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use UnitedCMS\CollectionFieldBundle\Form\CollectionFormType;
use UnitedCMS\CollectionFieldBundle\Model\Collection;
use UnitedCMS\CollectionFieldBundle\SchemaType\Factories\CollectionFieldTypeFactory;
use UnitedCMS\CoreBundle\Entity\FieldableField;
use UnitedCMS\CoreBundle\Field\FieldableFieldSettings;
use UnitedCMS\CoreBundle\Field\FieldType;
use UnitedCMS\CoreBundle\Field\FieldTypeManager;
use UnitedCMS\CoreBundle\Form\FieldableFormField;
use UnitedCMS\CoreBundle\Form\FieldableFormType;
use UnitedCMS\CoreBundle\SchemaType\SchemaTypeManager;

class CollectionFieldType extends FieldType
{
    const TYPE                      = "collection";
    const FORM_TYPE                 = CollectionFormType::class;
    const SETTINGS                  = ['fields', 'min_rows', 'max_rows'];
    const REQUIRED_SETTINGS         = ['fields'];

    private $validator;
    private $collectionFieldTypeFactory;
    private $fieldTypeManager;

    function __construct(ValidatorInterface $validator, CollectionFieldTypeFactory $collectionFieldTypeFactory, FieldTypeManager $fieldTypeManager)
    {
        $this->validator = $validator;
        $this->collectionFieldTypeFactory = $collectionFieldTypeFactory;
        $this->fieldTypeManager = $fieldTypeManager;
    }

    private function createCollectionModel($settings, $identifier) {
        return new Collection(isset($settings->fields) ? $settings->fields : [], $identifier);
    }

    private function getIdentifierPath(FieldableField $field) {
        return $field->getEntity()->getIdentifier() . ucfirst($field->getIdentifier());
    }

    function getFormOptions(): array
    {
        $settings = null;
        if($this->fieldIsPresent() && method_exists($this->field, 'getSettings')) {
            $settings = $this->field->getSettings();
        }

        $options = [
            'label' => false,
        ];
        $options['fields'] = [];

        foreach ($this->createCollectionModel($settings, $this->getIdentifierPath($this->field))->getFields() as $fieldDefinition) {

            // Add the definition of the current field to the options.
            $options['fields'][] = new FieldableFormField(
                $this->fieldTypeManager->getFieldType($fieldDefinition->getType()),
                $fieldDefinition
            );
        }

        return array_merge(parent::getFormOptions(), [
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => true,
            'prototype_name' => '__' . $this->getIdentifierPath($this->field) . 'Name__',
            'attr' => [
                'data-identifier' => $this->getIdentifierPath($this->field),
                'min-rows' => $settings->min_rows ?? 0,
                'max-rows' => $settings->max_rows ?? 0,
            ],
            'entry_type' => FieldableFormType::class,
            'entry_options' => $options,
        ]);
    }

    function getGraphQLType(SchemaTypeManager $schemaTypeManager, $nestingLevel = 0)
    {
        return $this->collectionFieldTypeFactory->createCollectionFieldType(
            $schemaTypeManager,
            $nestingLevel,
            $this->field,
            $this->createCollectionModel($this->field->getSettings(), $this->getIdentifierPath($this->field))
        );
    }

    function getGraphQLInputType(SchemaTypeManager $schemaTypeManager, $nestingLevel = 0)
    {
      return $this->collectionFieldTypeFactory->createCollectionFieldType(
        $schemaTypeManager,
        $nestingLevel,
        $this->field,
        $this->createCollectionModel($this->field->getSettings(), $this->getIdentifierPath($this->field)),
        true
      );
    }

    function resolveGraphQLData($value)
    {
        if (!$this->fieldIsPresent()) {
            return 'undefined';
        }

        return (array)$value;
    }

    function validateData($data): array
    {
        $violations = $this->validateAdditionalData($data, $this->field->getSettings(), $this->field->getIdentifier());

        $max_rows = $this->field->getSettings()->max_rows ?? 0;
        $min_rows = $this->field->getSettings()->min_rows ?? 0;

        // Validate max_rows
        if($max_rows > 0 && $max_rows < count($data)) {
            $violations[] = new ConstraintViolation(
                'validation.too_many_rows',
                'validation.too_many_rows',
                [],
                NULL,
                $this->field->getIdentifier(),
                NULL
            );
        }

        // Validate min_rows
        if(count($data) < $min_rows) {
            $violations[] = new ConstraintViolation(
                'validation.too_few_rows',
                'validation.too_few_rows',
                [],
                NULL,
                $this->field->getIdentifier(),
                NULL
            );
        }

        return $violations;
    }

    private function validateAdditionalData($data, $settings, $identifier) {

        $violations = [];
        $collection = $this->createCollectionModel($settings, '');

        // Make sure, that there is no additional data in content that is not in settings.
        foreach($data as $row) {
            foreach (array_keys($row) as $data_key) {
                if (!$collection->getFields()->containsKey($data_key)) {
                    $violations[] = new ConstraintViolation(
                        'validation.additional_data',
                        'validation.additional_data',
                        [],
                        $row,
                        $identifier.'.'.$data_key,
                        $row
                    );

                // For nested collection fields we also need to check the children
                } elseif ($collection->getFields()->get($data_key)->getType() == static::TYPE) {
                    $violations = array_merge(
                        $violations,
                        $this->validateAdditionalData(
                            $row[$data_key],
                            $collection->getFields()->get($data_key)->getSettings(),
                            $identifier.'.'.$data_key
                        )
                    );

                // For all other fields let the field type add violations
                } else {
                    $violations = array_merge(
                        $violations,
                        $this->fieldTypeManager->validateFieldData($collection->getFields()->get($data_key), $row[$data_key])
                    );
                }
            }
        }

        return $violations;
    }


    function validateSettings(FieldableFieldSettings $settings): array
    {
        // Validate allowed and required settings.
        $violations = parent::validateSettings($settings);

        // Validate sub fields.
        if(empty($violations)) {
            foreach($settings->fields as $key => $field) {

                foreach($this->validator->validate($this->createCollectionModel($settings, '')) as $violation) {
                    $violations[] = $violation;
                }
            }
        }
        return $violations;
    }
}