<?php

namespace UnitedCMS\CollectionFieldBundle\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use UnitedCMS\CoreBundle\Entity\Fieldable;
use UnitedCMS\CoreBundle\Entity\FieldableField;

/**
 * We use this model only for validation!
 */
class Collection implements Fieldable
{

    /**
     * @var CollectionField[]
     * @Assert\Valid()
     */
    private $fields;

    /**
     * @var string
     */
    private $identifier;

    public function __construct($fields = [], $identifier)
    {
        $this->fields = new ArrayCollection();
        $this->setIdentifier($identifier);
        $this->setFields($fields);
    }

    /**
     * @return FieldableField[]|ArrayCollection
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param ArrayCollection|FieldableField[] $fields
     *
     * @return Collection
     */
    public function setFields($fields)
    {
        foreach($fields as $field) {
          if($field instanceof  CollectionField) {
            $this->addField($field);
          } elseif(is_array($field)) {
            $this->addField(new CollectionField($field));
          }
        }

        return $this;
    }

    /**
     * @param FieldableField $field
     *
     * @return Fieldable
     */
    public function addField(FieldableField $field)
    {
        if (!$field instanceof CollectionField) {
            throw new \InvalidArgumentException("'$field' is not a CollectionField.");
        }

        if (!$this->fields->containsKey($field->getIdentifier())) {
            $this->fields->set($field->getIdentifier(), $field);
            $field->setEntity($this);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getIdentifier() {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     *
     * @return Collection
     */
    public function setIdentifier(string $identifier) {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @return array
     */
    public function getLocales(): array
    {
        return [];
    }
}