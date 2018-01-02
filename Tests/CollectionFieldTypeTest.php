<?php

namespace UnitedCMS\CollectionFieldBundle\Tests;

use UnitedCMS\CoreBundle\Entity\Content;
use UnitedCMS\CoreBundle\Field\FieldableFieldSettings;
use UnitedCMS\CoreBundle\Form\FieldableFormType;
use UnitedCMS\CoreBundle\Tests\Field\FieldTypeTestCase;

class CollectionFieldTypeTest extends FieldTypeTestCase
{
    public function testAllowedFieldSettings() {
        $field = $this->createContentTypeField('collection');
        $errors = $this->container->get('validator')->validate($field);
        $this->assertCount(1, $errors);
        $this->assertEquals('validation.required', $errors->get(0)->getMessage());

        $field->setSettings(new FieldableFieldSettings([
            'fields' => [],
            'min_rows' => 0,
            'max_rows' => 100,
            'foo' => 'baa',
        ]));
        $errors = $this->container->get('validator')->validate($field);
        $this->assertCount(1, $errors);
        $this->assertEquals('validation.additional_data', $errors->get(0)->getMessage());

        $field->setSettings(new FieldableFieldSettings([
            'fields' => [],
            'min_rows' => 0,
            'max_rows' => 100,
        ]));

        $errors = $this->container->get('validator')->validate($field);
        $this->assertCount(0, $errors);
    }

    public function testAddingEmptyCollectionFieldType() {

        $field = $this->createContentTypeField('collection');

        $content = new Content();
        $content->setContentType($field->getContentType());

        // Try to validate empty collection field definitions.
        $errors = $this->container->get('validator')->validate($field);
        $this->assertCount(1, $errors);
        $this->assertEquals('validation.required', $errors->get(0)->getMessage());

        $field->setSettings(new FieldableFieldSettings([
            'fields' => [],
        ]));

        // Try to validate collection without fields.
        $this->assertCount(0, $this->container->get('validator')->validate($field));

        $form = $this->container->get('united.cms.fieldable_form_builder')->createForm($field->getContentType(), $content, ['csrf_protection' => false]);
        $this->assertInstanceOf(FieldableFormType::class, $form->getConfig()->getType()->getInnerType());
        $this->assertTrue($form->has($field->getIdentifier()));
        $this->assertEquals($field->getTitle(), $form->get($field->getIdentifier())->getConfig()->getOption('label'));

        // Submitting empty data should be valid.
        $form->submit([]);
        $this->assertTrue($form->isValid());

        // Submitting sub field data should be valid since we auto-delete empty rows, but content data must be empty.
        $form = $this->container->get('united.cms.fieldable_form_builder')->createForm($field->getContentType(), $content, ['csrf_protection' => false]);
        $form->submit([$field->getIdentifier() => [['foo' => 'baa']]]);
        $this->assertTrue($form->isValid());
        $this->assertEmpty($form->getData()[$field->getIdentifier()]);
    }

    public function testAddingCollectionFieldTypeWithFields() {
        $field = $this->createContentTypeField('collection');
        $field->setSettings(new FieldableFieldSettings([
            'fields' => [
                [
                    'title' => 'Sub Field 1',
                    'identifier' => 'f1',
                    'type' => 'text',
                ]
            ],
        ]));

        $content = new Content();
        $content->setContentType($field->getContentType());

        // Try to validate collection with sub field definitions.
        $this->assertCount(0, $this->container->get('validator')->validate($field));

        // Submitting sub field data should work, for the given fields.
        $form = $this->container->get('united.cms.fieldable_form_builder')->createForm($field->getContentType(), $content, ['csrf_protection' => false]);
        $form->submit([$field->getIdentifier() => [['f1' => 'value']]]);
        $this->assertTrue($form->isValid());
        $this->assertNotEmpty($form->getData());
        $this->assertEquals([$field->getIdentifier() => [['f1' => 'value']]], $form->getData());
    }
}