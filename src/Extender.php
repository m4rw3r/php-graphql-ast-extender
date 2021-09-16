<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\Visitor;
use RuntimeException;

/**
 * @psalm-import-type TypeDefinitionImpls from Util
 * @psalm-import-type TypeExtensionImpls from Util
 */
class Extender {
    public function __construct(DocumentNode $initialAST) {

    }

    /**
     * @param TypeDefinitionImpls $base
     * @param TypeExtensionImpls $extension
     * @return TypeDefinitionImpls
     */
    public static function extendDirectives(
        TypeDefinitionNode $base,
        TypeExtensionNode $extension
    ): TypeDefinitionNode {
        if($extension->directives->count() > 0) {
            $base = clone $base;

            // Duplicates are verified after using a GraphQL validation pass
            $base->directives = $base->directives->merge($extension->directives);
        }

        return $base;
    }

    /**
     * @param ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $base
     * @param ObjectTypeExtensionNode|InterfaceTypeExtensionNode $extension
     * @reutrn ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode
     */
    public static function extendObjectType(
        TypeDefinitionNode $base,
        TypeExtensionNode $extension
    ): TypeDefinitionNode {
        if($extension->interfaces->count() > 0) {
            $base = clone $base;

            // TODO: No duplicates allowed
            /**
             * Bad type on InterfaceTypeExtensionNode::$interfaces.
             *
             * @psalm-suppress PossiblyInvalidArgument
             */
            $base->interfaces = $base->interfaces->merge($extension->interfaces);
        }

        if($extension->fields->count() > 0) {
            $base = clone $base;

            // TODO: No duplicates allowed
            $base->fields = $base->fields->merge($extension->fields);
        }

        return $base;
    }

    public static function extend(DocumentNode $initial, DocumentNode $extension): DocumentNode {
        // TODO: Code
        // TODO: Additional values to merge?
        // TODO: Throw on duplicates
        // TODO: Throw on missing types when extending
        // TODO: How to extend unions?

        // SchemaDefinitionNode ?

        $context = new Context();

        $collectType = static function(Node $typeDef) use($context): void {
            $context->addType($typeDef);
        };

        Visitor::visit($extension, [
            // TODO: Do we just ignore these?
            // ExecutableDefinitionNode
            // OperationDefinitionNode
            // FragmentDefinitionNode

            // Skip the schema?
            NodeKind::SCHEMA_DEFINITION => $collectType,
            NodeKind::DIRECTIVE_DEFINITION => $collectType,
            // TypeExtensionNode
            NodeKind::SCALAR_TYPE_EXTENSION => $collectType,
            NodeKind::OBJECT_TYPE_EXTENSION => $collectType,
            NodeKind::INTERFACE_TYPE_EXTENSION => $collectType,
            NodeKind::UNION_TYPE_EXTENSION => $collectType,
            NodeKind::ENUM_TYPE_EXTENSION => $collectType,
            NodeKind::INPUT_OBJECT_TYPE_EXTENSION => $collectType,
            // Type definitions
            NodeKind::SCALAR_TYPE_DEFINITION => $collectType,
            NodeKind::OBJECT_TYPE_DEFINITION => $collectType,
            NodeKind::INTERFACE_TYPE_DEFINITION => $collectType,
            NodeKind::UNION_TYPE_DEFINITION => $collectType,
            NodeKind::ENUM_TYPE_DEFINITION => $collectType,
            NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $collectType,
        ]);

        $checkType =
            /**
             * @param TypeDefinitionImpls $node
             */
            static function(Node $node) use($context): void {
                if($context->hasType($node->name->value)) {
                    throw new RuntimeException(sprintf(
                        "%s: Duplicate type definition '%s'",
                        __CLASS__,
                        $node->name->value
                    ));
                }
            };

        $extendType =
            static function(TypeDefinitionNode $node) use($context): ?TypeDefinitionNode {
                assert(Util::isTypeDefinition($node));

                $name = $node->name->value;
                $extension = $context->getTypeExtension($name);

                if( ! $extension) {
                    return null;
                }

                $newNode = self::extendDirectives($node, $extension);

                if($newNode instanceof ScalarTypeDefinitionNode) {
                    // TODO: Better error
                    assert($extension instanceof ScalarTypeExtensionNode);
                }

                if($newNode instanceof ObjectTypeDefinitionNode) {
                    // TODO: Better error
                    assert($extension instanceof ObjectTypeExtensionNode);

                    $newNode = self::extendObjectType($newNode, $extension);
                }

                if($newNode instanceof ObjectTypeDefinitionNode) {
                    // TODO: Better error
                    assert($extension instanceof ObjectTypeExtensionNode);

                    $newNode = self::extendObjectType($newNode, $extension);
                }

                if($newNode instanceof InterfaceTypeDefinitionNode) {
                    // TODO: Better error
                    assert($extension instanceof InterfaceTypeExtensionNode);

                    $newNode = self::extendObjectType($newNode, $extension);
                }

                // TODO: Union extension
                // TODO: Enum extension

                $context->setUsedExtension($name);

                return $newNode !== $node ? $newNode : null;
            };

        // TODO: Keep track of used extension nodes
        Visitor::visit($initial, [
            "enter" => [
                NodeKind::SCALAR_TYPE_DEFINITION => $checkType,
                NodeKind::OBJECT_TYPE_DEFINITION => $checkType,
                NodeKind::INTERFACE_TYPE_DEFINITION => $checkType,
                NodeKind::UNION_TYPE_DEFINITION => $checkType,
                NodeKind::ENUM_TYPE_DEFINITION => $checkType,
                NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $checkType,
            ],
            "leave" => [
                NodeKind::DOCUMENT => static function() use($context) {

                },
                NodeKind::SCALAR_TYPE_DEFINITION => $extendType,
                NodeKind::OBJECT_TYPE_DEFINITION => $extendType,
                NodeKind::INTERFACE_TYPE_DEFINITION => $extendType,
                NodeKind::UNION_TYPE_DEFINITION => $extendType,
                NodeKind::ENUM_TYPE_DEFINITION => $extendType,
                NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $extendType,
            ],
        ]);

        return $initial;
    }
}
