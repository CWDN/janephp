<?php

namespace Jane\AutoMapper\Compiler;

use Jane\AutoMapper\AutoMapperInterface;
use Jane\AutoMapper\Context;
use Jane\AutoMapper\Mapper;
use Jane\AutoMapper\MapperConfigurationInterface;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Scalar;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;

class Compiler
{
    private $parser;

    private $classDiscriminator;

    public function __construct(Parser $parser = null, ClassDiscriminatorResolverInterface $classDiscriminator = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->classDiscriminator = $classDiscriminator;
    }

    public function compile(MapperConfigurationInterface $mapperConfiguration)
    {
        $propertiesMapping = $mapperConfiguration->getPropertiesMapping();

        $uniqueVariableScope = new UniqueVariableScope();
        $sourceInput = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));
        $result = new Expr\Variable($uniqueVariableScope->getUniqueName('result'));
        $hashVariable = new Expr\Variable($uniqueVariableScope->getUniqueName('sourceHash'));
        $contextVariable = new Expr\Variable($uniqueVariableScope->getUniqueName('context'));
        $constructStatements = [];
        $injectMapperStatements = [];
        $addedDependencies = [];

        $statements = [
            new Stmt\If_(new Expr\BinaryOp\Identical(new Expr\ConstFetch(new Name('null')), $sourceInput), [
                'stmts' => [new Stmt\Return_($sourceInput)],
            ]),
        ];

        if ($mapperConfiguration->getSource() !== 'array') {
            $statements[] = new Stmt\Expression(new Expr\Assign($hashVariable, new Expr\BinaryOp\Concat(new Expr\FuncCall(new Name('spl_object_hash'), [
                new Arg($sourceInput),
            ]),
                new Scalar\String_($mapperConfiguration->getTarget())
            )));
            $statements[] = new Stmt\If_(new Expr\MethodCall($contextVariable, new Name('shouldHandleCircularReference'), [
                new Arg($hashVariable),
                new Arg(new Expr\PropertyFetch(new Expr\Variable('this'), 'circularReferenceLimit')),
            ]), [
                'stmts' => [
                    new Stmt\Return_(new Expr\MethodCall($contextVariable, 'handleCircularReference', [
                        new Arg($hashVariable),
                        new Arg($sourceInput),
                        new Arg(new Expr\PropertyFetch(new Expr\Variable('this'), 'circularReferenceLimit')),
                        new Arg(new Expr\PropertyFetch(new Expr\Variable('this'), 'circularReferenceHandler')),
                    ])),
                ],
            ]);
        }

        [$createObjectStmts, $inConstructor, $constructStatementsForCreateObjects] = $this->getCreateObjectStatements($mapperConfiguration, $result, $contextVariable, $sourceInput, $uniqueVariableScope);
        $constructStatements = array_merge($constructStatements, $constructStatementsForCreateObjects);

        $statements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\MethodCall($contextVariable, 'getObjectToPopulate')));
        $statements[] = new Stmt\If_(new Expr\BinaryOp\Identical(new Expr\ConstFetch(new Name('null')), $result), [
            'stmts' => $createObjectStmts,
        ]);

        foreach ($propertiesMapping as $propertyMapping) {
            $transformer = $propertyMapping->getTransformer();

            foreach ($transformer->getDependencies() as $dependency) {
                if (isset($addedDependencies[$dependency->getName()])) {
                    continue;
                }

                $injectMapperStatements[] = new Stmt\Expression(new Expr\Assign(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'mappers'), new Scalar\String_($dependency->getName())),
                    new Expr\MethodCall(new Expr\Variable('autoMapper'), 'getMapper', [
                        new Arg(new Scalar\String_($dependency->getSource())),
                        new Arg(new Scalar\String_($dependency->getTarget())),
                    ])
                ));
                $addedDependencies[$dependency->getName()] = true;
            }
        }

        if (\count($addedDependencies) > 0) {
            if ($mapperConfiguration->getSource() !== 'array') {
                $statements[] = new Stmt\Expression(new Expr\Assign(
                    $contextVariable,
                    new Expr\MethodCall($contextVariable, 'withReference', [
                        new Arg($hashVariable),
                        new Arg($result),
                    ])
                ));
            }

            $statements[] = new Stmt\Expression(new Expr\Assign(
                $contextVariable,
                new Expr\MethodCall($contextVariable, 'withIncrementedDepth')
            ));
        }

        /** @var PropertyMapping $propertyMapping */
        foreach ($propertiesMapping as $propertyMapping) {
            $transformer = $propertyMapping->getTransformer();

            if (\in_array($propertyMapping->getProperty(), $inConstructor, true)) {
                continue;
            }

            [$output, $propStatements] = $transformer->transform($propertyMapping->getReadAccessor()->getExpression($sourceInput), $uniqueVariableScope, $propertyMapping);
            $writeExpression = $propertyMapping->getWriteMutator()->getExpression($result, $output, $transformer->assignByRef());

            if ($writeExpression === null) {
                continue;
            }

            $propStatements[] = new Stmt\Expression($writeExpression);
            $conditions = [];

            $extractCallback = $propertyMapping->getReadAccessor()->getExtractCallback($mapperConfiguration->getSource());
            $hydrateCallback = $propertyMapping->getWriteMutator()->getHydrateCallback($mapperConfiguration->getTarget());

            if (null !== $extractCallback) {
                $constructStatements[] = new Stmt\Expression(new Expr\Assign(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'extractCallbacks'), new Scalar\String_($propertyMapping->getProperty())),
                    $extractCallback
                ));
            }

            if (null !== $hydrateCallback) {
                $constructStatements[] = new Stmt\Expression(new Expr\Assign(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'hydrateCallbacks'), new Scalar\String_($propertyMapping->getProperty())),
                    $hydrateCallback
                ));
            }

            if ($propertyMapping->checkExists()) {
                if ($mapperConfiguration->getSource() === \stdClass::class) {
                    $conditions[] = new Expr\FuncCall(new Name('property_exists'), [
                        new Arg($sourceInput),
                        new Arg(new Scalar\String_($propertyMapping->getProperty())),
                    ]);
                }

                if ($mapperConfiguration->getSource() === 'array') {
                    $conditions[] = new Expr\FuncCall(new Name('array_key_exists'), [
                        new Arg(new Scalar\String_($propertyMapping->getProperty())),
                        new Arg($sourceInput),
                    ]);
                }
            }

            $conditions[] = new Expr\MethodCall($contextVariable, 'isAllowedAttribute', [
                new Arg(new Scalar\String_($propertyMapping->getProperty())),
            ]);

            if (null !== $propertyMapping->getSourceGroups()) {
                $conditions[] = new Expr\BinaryOp\BooleanAnd(
                    new Expr\BinaryOp\NotIdentical(
                        new Expr\ConstFetch(new Name('null')),
                        new Expr\MethodCall(new Expr\Variable('context'), 'getGroups')
                    ),
                    new Expr\FuncCall(new Name('array_intersect'), [
                        new Arg(new Expr\MethodCall(new Expr\Variable('context'), 'getGroups')),
                        new Arg(new Expr\Array_(array_map(function (string $group) {
                            return new Expr\ArrayItem(new Scalar\String_($group));
                        }, $propertyMapping->getSourceGroups()))),
                    ])
                );
            }

            if (null !== $propertyMapping->getTargetGroups()) {
                $conditions[] = new Expr\BinaryOp\BooleanAnd(
                    new Expr\BinaryOp\NotIdentical(
                        new Expr\ConstFetch(new Name('null')),
                        new Expr\MethodCall(new Expr\Variable('context'), 'getGroups')
                    ),
                    new Expr\FuncCall(new Name('array_intersect'), [
                        new Arg(new Expr\MethodCall(new Expr\Variable('context'), 'getGroups')),
                        new Arg(new Expr\Array_(array_map(function (string $group) {
                            return new Expr\ArrayItem(new Scalar\String_($group));
                        }, $propertyMapping->getTargetGroups()))),
                    ])
                );
            }

            if (null !== $propertyMapping->getMaxDepth()) {
                $conditions[] = new Expr\BinaryOp\SmallerOrEqual(
                    new Expr\MethodCall($contextVariable, 'getDepth'),
                    new Scalar\LNumber($propertyMapping->getMaxDepth())
                );
            }

            if (\count($conditions) > 0) {
                $condition = array_shift($conditions);

                while (\count($conditions) > 0) {
                    $condition = new Expr\BinaryOp\BooleanAnd($condition, array_shift($conditions));
                }

                $propStatements = [new Stmt\If_($condition, [
                    'stmts' => $propStatements,
                ])];
            }

            $statements = array_merge(
                $statements,
                $propStatements
            );
        }

        $statements[] = new Stmt\Return_($result);

        $mapMethod = new Stmt\ClassMethod('map', [
            'flags' => Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [
                new Param(new Expr\Variable($sourceInput->name)),
                new Param(new Expr\Variable('context'), null, new Name\FullyQualified(Context::class)),
            ],
            'byRef' => true,
            'stmts' => $statements,
        ]);

        $injectMappersMethod = new Stmt\ClassMethod('injectMappers', [
            'flags' => Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [
                new Param(new Expr\Variable('autoMapper'), null, new Name\FullyQualified(AutoMapperInterface::class)),
            ],
            'stmts' => $injectMapperStatements,
        ]);

        $constructMethod = new Stmt\ClassMethod('__construct', [
            'flags' => Stmt\Class_::MODIFIER_PUBLIC,
            'stmts' => $constructStatements,
        ]);

        return new Stmt\Class_(new Name($mapperConfiguration->getMapperClassName()), [
            'flags' => Stmt\Class_::MODIFIER_FINAL,
            'extends' => new Name\FullyQualified(Mapper::class),
            'stmts' => [
                new Stmt\Property(Stmt\Class_::MODIFIER_PROTECTED, [
                    new Stmt\PropertyProperty('hash', new Scalar\String_($mapperConfiguration->getModificationHash())),
                ]),
                $constructMethod,
                $mapMethod,
                $injectMappersMethod,
            ],
        ]);
    }

    private function getCreateObjectStatements(MapperConfigurationInterface $mapperConfiguration, Expr\Variable $result, Expr\Variable $contextVariable, Expr\Variable $sourceInput, UniqueVariableScope $uniqueVariableScope): array
    {
        $reflectionClass = $mapperConfiguration->getTarget() === 'array' ? null : new \ReflectionClass($mapperConfiguration->getTarget());
        $targetConstructor = $reflectionClass ? $reflectionClass->getConstructor() : null;
        $propertiesMapping = $mapperConfiguration->getPropertiesMapping();
        $createObjectStatements = [];
        $inConstructor = [];
        $constructStatements = [];

        if ($mapperConfiguration->getTarget() === 'array') {
            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\Array_()));
        } elseif ($mapperConfiguration->getTarget() === \stdClass::class) {
            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\New_(new Name(\stdClass::class))));
        } elseif ($targetConstructor !== null && $mapperConfiguration->hasConstructor()) {
            $constructArguments = [];

            /** @var PropertyMapping $propertyMapping */
            foreach ($propertiesMapping as $propertyMapping) {
                if ($propertyMapping->getWriteMutator()->getParameter() === null) {
                    continue;
                }

                $constructVar = new Expr\Variable($uniqueVariableScope->getUniqueName('constructArg'));

                [$output, $propStatements] = $propertyMapping->getTransformer()->transform($propertyMapping->getReadAccessor()->getExpression($sourceInput), $uniqueVariableScope, $propertyMapping);
                $constructArguments[$propertyMapping->getWriteMutator()->getParameter()->getPosition()] = new Arg($constructVar);

                $propStatements[] = new Stmt\Expression(new Expr\Assign($constructVar, $output));
                $createObjectStatements[] = new Stmt\If_(new Expr\MethodCall($contextVariable, 'hasConstructorArgument', [
                    new Arg(new Scalar\String_($mapperConfiguration->getTarget())),
                    new Arg(new Scalar\String_($propertyMapping->getProperty())),
                ]), [
                    'stmts' => [
                        new Stmt\Expression(new Expr\Assign($constructVar, new Expr\MethodCall($contextVariable, 'getConstructorArgument', [
                            new Arg(new Scalar\String_($mapperConfiguration->getTarget())),
                            new Arg(new Scalar\String_($propertyMapping->getProperty())),
                        ]))),
                    ],
                    'else' => new Stmt\Else_($propStatements),
                ]);

                $inConstructor[] = $propertyMapping->getProperty();
            }

            foreach ($targetConstructor->getParameters() as $constructorParameter) {
                if (!array_key_exists($constructorParameter->getPosition(), $constructArguments) && $constructorParameter->isDefaultValueAvailable()) {
                    $constructVar = new Expr\Variable($uniqueVariableScope->getUniqueName('constructArg'));

                    $createObjectStatements[] = new Stmt\If_(new Expr\MethodCall($contextVariable, 'hasConstructorArgument', [
                        new Arg(new Scalar\String_($mapperConfiguration->getTarget())),
                        new Arg(new Scalar\String_($constructorParameter->getName())),
                    ]), [
                        'stmts' => [
                            new Stmt\Expression(new Expr\Assign($constructVar, new Expr\MethodCall($contextVariable, 'getConstructorArgument', [
                                new Arg(new Scalar\String_($mapperConfiguration->getTarget())),
                                new Arg(new Scalar\String_($constructorParameter->getName())),
                            ]))),
                        ],
                        'else' => new Stmt\Else_([
                            new Stmt\Expression(new Expr\Assign($constructVar, $this->getValueAsExpr($constructorParameter->getDefaultValue()))),
                        ]),
                    ]);

                    $constructArguments[$constructorParameter->getPosition()] = new Arg($constructVar);
                }
            }

            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\New_(new Name\FullyQualified($mapperConfiguration->getTarget()), $constructArguments)));
        } elseif ($targetConstructor !== null && $mapperConfiguration->isTargetCloneable()) {
            $constructStatements[] = new Stmt\Expression(new Expr\Assign(
                new Expr\PropertyFetch(new Expr\Variable('this'), 'cachedTarget'),
                new Expr\MethodCall(new Expr\New_(new Name\FullyQualified(\ReflectionClass::class), [
                    new Arg(new Scalar\String_($mapperConfiguration->getTarget())),
                ]), 'newInstanceWithoutConstructor')
            ));
            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\Clone_(new Expr\PropertyFetch(new Expr\Variable('this'), 'cachedTarget'))));
        } elseif ($targetConstructor !== null) {
            $constructStatements[] = new Stmt\Expression(new Expr\Assign(
                new Expr\PropertyFetch(new Expr\Variable('this'), 'cachedTarget'),
                new Expr\New_(new Name\FullyQualified(\ReflectionClass::class), [
                    new Arg(new Scalar\String_($mapperConfiguration->getTarget())),
                ])
            ));
            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\MethodCall(
                new Expr\PropertyFetch(new Expr\Variable('this'), 'cachedTarget'),
                'newInstanceWithoutConstructor'
            )));
        } else {
            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\New_(new Name\FullyQualified($mapperConfiguration->getTarget()))));
        }

        return [$createObjectStatements, $inConstructor, $constructStatements];
    }

    private function getValueAsExpr($value)
    {
        $expr = $this->parser->parse('<?php ' . var_export($value, true) . ';')[0];

        if ($expr instanceof Stmt\Expression) {
            return $expr->expr;
        }

        return $expr;
    }
}
