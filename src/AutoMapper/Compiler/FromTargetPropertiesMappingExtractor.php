<?php

namespace Jane\AutoMapper\Compiler;

use Jane\AutoMapper\Compiler\Accessor\ReadAccessor;
use SebastianBergmann\GlobalState\RuntimeException;
use Symfony\Component\PropertyInfo\Type;

class FromTargetPropertiesMappingExtractor extends PropertiesMappingExtractor
{
    public function getPropertiesMapping(string $source, string $target, bool $allowConstruct = true): array
    {
        $targetProperties = $this->propertyInfoExtractor->getProperties($target);

        if (!\in_array($source, ['array', \stdClass::class])) {
            throw new RuntimeException('Only array or stdClass are accepted as a source');
        }

        if (null === $targetProperties) {
            return [];
        }

        $mapping = [];

        foreach ($targetProperties as $property) {
            if (!$this->propertyInfoExtractor->isWritable($target, $property)) {
                continue;
            }

            $targetTypes = $this->propertyInfoExtractor->getTypes($target, $property);
            $sourceTypes = [];

            foreach ($targetTypes as $type) {
                $sourceTypes[] = $this->transformType($source, $type);
            }

            $transformer = $this->transformerFactory->getTransformer($sourceTypes, $targetTypes);

            if (null === $transformer) {
                continue;
            }

            $sourceAccessor = new ReadAccessor(ReadAccessor::TYPE_ARRAY_DIMENSION, $property);

            if ($source === \stdClass::class) {
                $sourceAccessor = new ReadAccessor(ReadAccessor::TYPE_PROPERTY, $property);
            }

            $targetMutator = $this->accessorExtractor->getWriteMutator($target, $property, $allowConstruct);

            $mapping[] = new PropertyMapping(
                $sourceAccessor,
                $targetMutator,
                $transformer,
                $property,
                true,
                $this->getGroups($source, $property),
                $this->getGroups($target, $property)
            );
        }

        return $mapping;
    }

    private function transformType(string $source, Type $type = null): ?Type
    {
        if ($type === null) {
            return null;
        }

        $builtinType = $type->getBuiltinType();
        $className = $type->getClassName();

        if ($type->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT && $type->getClassName() !== \stdClass::class) {
            $builtinType = $source === 'array' ? Type::BUILTIN_TYPE_ARRAY : Type::BUILTIN_TYPE_OBJECT;
            $className = $source === 'array' ? null : \stdClass::class;
        }

        return new Type(
            $builtinType,
            $type->isNullable(),
            $className,
            $type->isCollection(),
            $this->transformType($source, $type->getCollectionKeyType()),
            $this->transformType($source, $type->getCollectionValueType())
        );
    }

    public function getReverseExtractor(): PropertiesMappingExtractorInterface
    {
        return new FromSourcePropertiesMappingExtractor($this->propertyInfoExtractor, $this->accessorExtractor, $this->transformerFactory);
    }
}
