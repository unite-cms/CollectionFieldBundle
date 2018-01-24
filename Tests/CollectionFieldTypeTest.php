<?php

namespace UnitedCMS\CollectionFieldBundle\Tests;

use GraphQL\Type\Definition\ObjectType;
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

    public function testGettingGraphQLData() {

      $field = $this->createContentTypeField('collection');
      $field->setIdentifier('f1');
      $field->getContentType()->setIdentifier('ct1');
      $field->setSettings(new FieldableFieldSettings([
        'fields' => [
          [
            'title' => 'Sub Field 1',
            'identifier' => 'f1',
            'type' => 'text',
          ],
          [
            'title' => 'Nested Field 1',
            'identifier' => 'n1',
            'type' => 'collection',
            'settings' => [
              'fields' => [
                [
                  'title' => 'Nested Field 2',
                  'identifier' => 'n2',
                  'type' => 'collection',
                  'settings' => [
                    'fields' => [
                      [
                        'title' => 'Sub Field 2',
                        'identifier' => 'f2',
                        'type' => 'text',
                      ],
                    ]
                  ],
                ]
              ]
            ],
          ]
        ],
      ]));
      $this->em->persist($field->getContentType()->getDomain()->getOrganization());
      $this->em->persist($field->getContentType()->getDomain());
      $this->em->persist($field->getContentType());
      $this->em->flush();

      $this->em->refresh($field->getContentType()->getDomain());
      $this->em->refresh($field->getContentType());
      $this->em->refresh($field);

      // Inject created domain into untied.cms.manager.
      $d = new \ReflectionProperty($this->container->get('united.cms.manager'), 'domain');
      $d->setAccessible(true);
      $d->setValue($this->container->get('united.cms.manager'), $field->getContentType()->getDomain());

      $key = ucfirst($field->getContentType()->getIdentifier()) . 'Content';
      $type = $this->container->get('united.cms.graphql.schema_type_manager')->getSchemaType($key, $field->getContentType()->getDomain());
      $this->assertInstanceOf(ObjectType::class, $type);

      // Check nested collection field structure.
      $this->assertArrayHasKey('f1', $type->getFields());
      $this->assertArrayHasKey('f1', $type->getField('f1')->getType()->getWrappedType()->getFields());
      $this->assertArrayHasKey('n1', $type->getField('f1')->getType()->getWrappedType()->getFields());
      $this->assertArrayHasKey('n2', $type->getField('f1')->getType()->getWrappedType()->getField('n1')->getType()->getWrappedType()->getFields());
      $this->assertArrayHasKey('f2', $type->getField('f1')->getType()->getWrappedType()->getField('n1')->getType()->getWrappedType()->getField('n2')->getType()->getWrappedType()->getFields());

      $this->assertEquals('Ct1F1CollectionField', $type->getField('f1')->getType()->name);
      $this->assertEquals('Ct1F1CollectionFieldRow', $type->getField('f1')->getType()->getWrappedType()->name);
      $this->assertEquals('Ct1F1N1CollectionField', $type->getField('f1')->getType()->getWrappedType()->getField('n1')->getType()->name);
      $this->assertEquals('Ct1F1N1CollectionFieldRow', $type->getField('f1')->getType()->getWrappedType()->getField('n1')->getType()->getWrappedType()->name);
      $this->assertEquals('Ct1F1N1N2CollectionField', $type->getField('f1')->getType()->getWrappedType()->getField('n1')->getType()->getWrappedType()->getField('n2')->getType()->name);
      $this->assertEquals('Ct1F1N1N2CollectionFieldRow', $type->getField('f1')->getType()->getWrappedType()->getField('n1')->getType()->getWrappedType()->getField('n2')->getType()->getWrappedType()->name);
      $this->assertEquals('String', $type->getField('f1')->getType()->getWrappedType()->getField('n1')->getType()->getWrappedType()->getField('n2')->getType()->getWrappedType()->getField('f2')->getType()->name);
    }

    public function testValidatingContent() {
      $field = $this->createContentTypeField('collection');
      $field->setSettings(new FieldableFieldSettings([
        'min_rows' => 1,
        'max_rows' => 4,
        'fields' => [
          [
            'title' => 'Sub Field 1',
            'identifier' => 'f1',
            'type' => 'text',
          ],
          [
            'title' => 'Nested Field 1',
            'identifier' => 'n1',
            'type' => 'collection',
            'settings' => [
              'fields' => [
                [
                  'title' => 'Nested Field 2',
                  'identifier' => 'n2',
                  'type' => 'collection',
                  'settings' => [
                    'fields' => [
                      [
                        'title' => 'Sub Field 2',
                        'identifier' => 'f2',
                        'type' => 'reference',
                        'settings' => [
                          'domain' => 'foo',
                          'content_type' => 'baa',
                        ],
                      ],
                    ]
                  ],
                ]
              ]
            ],
          ]
        ],
      ]));

      // Validate min rows.
      $violations = $this->container->get('united.cms.field_type_manager')->validateFieldData($field, []);
      $this->assertCount(1, $violations);
      $this->assertEquals($field->getIdentifier(), $violations[0]->getPropertyPath());
      $this->assertEquals('validation.too_few_rows', $violations[0]->getMessage());

      // Validate max rows.
      $violations = $this->container->get('united.cms.field_type_manager')->validateFieldData($field, [
        ['f1' => 'baa'],
        ['f1' => 'baa'],
        ['f1' => 'baa'],
        ['f1' => 'baa'],
        ['f1' => 'baa'],
      ]);
      $this->assertCount(1, $violations);
      $this->assertEquals($field->getIdentifier(), $violations[0]->getPropertyPath());
      $this->assertEquals('validation.too_many_rows', $violations[0]->getMessage());

      // Validate additional data (also nested).
      $violations = $this->container->get('united.cms.field_type_manager')->validateFieldData($field, [
        ['f1' => 'baa'],
        ['foo' => 'baa'],
        ['n1' => [
          ['n2' => [
            [ 'f2' => ['domain' => 'foo', 'content_type' => 'baa', 'content' => 'any'], ]
          ]]
        ]],
        ['n1' => [
          ['n2' => [
            [ 'f2' => ['domain' => 'foo'], 'foo' => 'baa', ]
          ]]
        ]],
      ]);
      $this->assertCount(3, $violations);
      $this->assertEquals($field->getIdentifier() . '.foo', $violations[0]->getPropertyPath());
      $this->assertEquals('validation.additional_data', $violations[0]->getMessage());
      $this->assertEquals('[f2]', $violations[1]->getPropertyPath());
      $this->assertEquals('validation.wrong_definition', $violations[1]->getMessage());
      $this->assertEquals($field->getIdentifier() . '.n1.n2.foo', $violations[2]->getPropertyPath());
      $this->assertEquals('validation.additional_data', $violations[2]->getMessage());
    }

    //public function testFormBuilding() {
      // TODO
    //}
}