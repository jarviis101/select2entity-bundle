<?php

namespace Tetranz\Select2EntityBundle\Form\DataTransformer;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for multiple mode (i.e., multiple = true)
 *
 * Class EntitiesToPropertyTransformer
 * @package Tetranz\Select2EntityBundle\Form\DataTransformer
 */
class EntitiesToPropertyTransformer implements DataTransformerInterface
{
    protected PropertyAccessor $accessor;

    public function __construct(
        protected ObjectManager $em,
        protected string $className,
        protected ?string $textProperty = null,
        protected string $primaryKey = 'id',
        protected string $newTagPrefix = '__',
        protected string $newTagText = ' (NEW)',
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Transform initial entities to array
     */
    public function transform(mixed $value): array
    {
        if (empty($value)) {
            return array();
        }

        $data = array();

        foreach ($value as $entity) {
            $text = is_null($this->textProperty)
                ? (string) $entity
                : $this->accessor->getValue($entity, $this->textProperty);

            if ($this->em->contains($entity)) {
                $v = (string) $this->accessor->getValue($entity, $this->primaryKey);
            } else {
                $v = $this->newTagPrefix . $text;
                $text = $text.$this->newTagText;
            }

            $data[$v] = $text;
        }

        return $data;
    }

    /**
     * Transform array to a collection of entities
     */
    public function reverseTransform(mixed $value): array
    {
        if (!is_array($value) || empty($value)) {
            return array();
        }

        $newObjects = array();
        $tagPrefixLength = strlen($this->newTagPrefix);
        foreach ($value as $key => $v) {
            $cleanValue = substr($v, $tagPrefixLength);
            $valuePrefix = substr($v, 0, $tagPrefixLength);
            if ($valuePrefix == $this->newTagPrefix) {
                $object = new $this->className;
                $this->accessor->setValue($object, $this->textProperty, $cleanValue);
                $newObjects[] = $object;
                unset($value[$key]);
            }
        }

        $entities = $this->em->createQueryBuilder()
            ->select('entity')
            ->from($this->className, 'entity')
            ->where('entity.'.$this->primaryKey.' IN (:ids)')
            ->setParameter('ids', $value)
            ->getQuery()
            ->getResult();

        if (count($entities) != count($value)) {
            throw new TransformationFailedException('One or more id values are invalid');
        }

        return array_merge($entities, $newObjects);
    }
}
