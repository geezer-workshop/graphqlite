<?php


namespace TheCodingMachine\GraphQL\Controllers\Mappers;


use function get_class;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\Type;
use Porpaginas\Result;
use RuntimeException;
use function strpos;
use function substr;

class PorpaginasTypeMapper implements TypeMapperInterface
{
    /**
     * @var array<string, ObjectType>
     */
    private $cache = [];

    /**
     * Returns true if this type mapper can map the $className FQCN to a GraphQL type.
     *
     * @param string $className The exact class name to look for (this function does not look into parent classes).
     * @return bool
     */
    public function canMapClassToType(string $className): bool
    {
        return is_a($className, Result::class, true);
    }

    /**
     * Maps a PHP fully qualified class name to a GraphQL type.
     *
     * @param string $className The exact class name to look for (this function does not look into parent classes).
     * @param OutputType|null $subType An optional sub-type if the main class is an iterator that needs to be typed.
     * @param RecursiveTypeMapperInterface $recursiveTypeMapper
     * @return ObjectType
     * @throws CannotMapTypeExceptionInterface
     */
    public function mapClassToType(string $className, ?OutputType $subType, RecursiveTypeMapperInterface $recursiveTypeMapper): ObjectType
    {
        if (!$this->canMapClassToType($className)) {
            throw CannotMapTypeException::createForType($className);
        }
        if ($subType === null) {
            throw PorpaginasMissingParameterException::noSubType();
        }

        return $this->getObjectType($subType);
    }

    private function getObjectType(OutputType $subType): ObjectType
    {
        if (!isset($subType->name)) {
            throw new RuntimeException('Cannot get name property from sub type '.get_class($subType));
        }

        $name = $subType->name;

        $typeName = 'PorpaginasResult_'.$name;

        if (!isset($this->cache[$typeName])) {
            $this->cache[$typeName] = new ObjectType([
                'name' => $typeName,
                'fields' => function() use ($subType) {
                    return [
                        'items' => [
                            'type' => Type::nonNull(Type::listOf(Type::nonNull($subType))),
                            'args' => [
                                'limit' => Type::int(),
                                'offset' => Type::int(),
                            ],
                            'resolve' => function (Result $root, $args) {
                                if (!isset($args['limit']) && isset($args['offset'])) {
                                    throw PorpaginasMissingParameterException::missingLimit();
                                }
                                if (isset($args['limit'])) {
                                    return $root->take($args['offset'] ?? 0, $args['limit']);
                                }
                                return $root;
                            }
                        ],
                        'count' => [
                            'type' => Type::int(),
                            'description' => 'The total count of items.',
                            'resolve' => function (Result $root) {
                                return $root->count();
                            }
                        ]
                    ];
                }
            ]);
        }

        return $this->cache[$typeName];
    }

    /**
     * Returns true if this type mapper can map the $typeName GraphQL name to a GraphQL type.
     *
     * @param string $typeName The name of the GraphQL type
     * @return bool
     */
    public function canMapNameToType(string $typeName): bool
    {
        return strpos($typeName, 'PorpaginasResult_') === 0;
    }

    /**
     * Returns a GraphQL type by name (can be either an input or output type)
     *
     * @param string $typeName The name of the GraphQL type
     * @param RecursiveTypeMapperInterface $recursiveTypeMapper
     * @return Type&(InputType|OutputType)
     * @throws CannotMapTypeExceptionInterface
     */
    public function mapNameToType(string $typeName, RecursiveTypeMapperInterface $recursiveTypeMapper): Type
    {
        if (!$this->canMapNameToType($typeName)) {
            throw CannotMapTypeException::createForName($typeName);
        }

        $subTypeName = substr($typeName, 17);

        $subType = $recursiveTypeMapper->mapNameToType($subTypeName);

        if (!$subType instanceof OutputType) {
            throw CannotMapTypeException::mustBeOutputType($subTypeName);
        }

        return $this->getObjectType($subType);
    }

    /**
     * Returns the list of classes that have matching input GraphQL types.
     *
     * @return string[]
     */
    public function getSupportedClasses(): array
    {
        // We cannot get the list of all possible porpaginas results but this is not an issue.
        // getSupportedClasses is only useful to get classes that can be hidden behind interfaces
        // and Porpaginas results are not part of those.
        return [];
    }

    /**
     * Returns true if this type mapper can map the $className FQCN to a GraphQL input type.
     *
     * @param string $className
     * @return bool
     */
    public function canMapClassToInputType(string $className): bool
    {
        return false;
    }

    /**
     * Maps a PHP fully qualified class name to a GraphQL input type.
     *
     * @param string $className
     * @param RecursiveTypeMapperInterface $recursiveTypeMapper
     * @return InputObjectType
     */
    public function mapClassToInputType(string $className, RecursiveTypeMapperInterface $recursiveTypeMapper): InputObjectType
    {
        throw CannotMapTypeException::createForInputType($className);
    }
}
