<?php

namespace Tetranz\Select2EntityBundle\Form\DataTransformer;

use Doctrine\ORM\UnexpectedResultException;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for single mode (i.e., multiple = false)
 *
 * Class EntityToPropertyTransformer
 *
 * @package Tetranz\Select2EntityBundle\Form\DataTransformer
 */
class EntityToPropertyTransformer implements DataTransformerInterface
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
     * Transform entity to array
     */
    public function transform(mixed $value): array
    {
        $data = array();
        if (empty($value)) {
            return $data;
        }

        $text = is_null($this->textProperty)
            ? (string) $value
            : $this->accessor->getValue($value, $this->textProperty);

        if ($this->em->contains($value)) {
            $v = (string) $this->accessor->getValue($value, $this->primaryKey);
        } else {
            $v = $this->newTagPrefix . $text;
            $text = $text.$this->newTagText;
        }

        $data[$v] = $text;

        return $data;
    }

    /**
     * Transform single id value to an entity
     */
    public function reverseTransform(mixed $value): mixed
    {
        /** @var string $value */
        if (empty($value)) {
            return null;
        }

        $tagPrefixLength = strlen($this->newTagPrefix);
        $cleanValue = substr($value, $tagPrefixLength);
        $valuePrefix = substr($value, 0, $tagPrefixLength);
        if ($valuePrefix == $this->newTagPrefix) {
            $entity = new $this->className;
            $this->accessor->setValue($entity, $this->textProperty, $cleanValue);
        } else {
            try {
                $entity = $this->em->createQueryBuilder()
                    ->select('entity')
                    ->from($this->className, 'entity')
                    ->where('entity.'.$this->primaryKey.' = :id')
                    ->setParameter('id', $value)
                    ->getQuery()
                    ->getSingleResult();
            }
            catch (UnexpectedResultException) {
                throw new TransformationFailedException(sprintf('The choice "%s" does not exist or is not unique', $value));
            }
        }

        if (!$entity) {
            return null;
        }

        return $entity;
    }
}
