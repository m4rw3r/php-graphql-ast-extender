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
 * @psalm-type TypeExtensionImpls ScalarTypeExtensionNode | ObjectTypeExtensionNode | InterfaceTypeExtensionNode | UnionTypeExtensionNode | EnumTypeExtensionNode | InputObjectTypeExtensionNode
 * @psalm-type TypeDefinitionImpls ScalarTypeDefinitionNode | ObjectTypeDefinitionNode | InterfaceTypeDefinitionNode | UnionTypeDefinitionNode | EnumTypeDefinitionNode | InputObjectTypeDefinitionNode
 * @psalm-type TypeSystemDefinitionImpls SchemaDefinitionNode | TypeDefinitionImpls | TypeExtensionImpls | DirectiveDefinitionNode
 */
class Extender {
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
     * @template T of ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode
     *
     * @param T $base
     * @param ObjectTypeExtensionNode|InterfaceTypeExtensionNode $extension
     * @return T
     */
    public static function extendObjectInterfaces(
        TypeDefinitionNode $base,
        TypeExtensionNode $extension
    ): TypeDefinitionNode {
        if($extension->interfaces->count() > 0) {
            $base = clone $base;

            // Duplicates are handled by GraphQL validation since it is a list
            /**
             * Bad type on InterfaceTypeExtensionNode::$interfaces.
             *
             * @psalm-suppress PossiblyInvalidArgument
             */
            $base->interfaces = $base->interfaces->merge($extension->interfaces);
        }

        return $base;
    }

    /**
     * @template T of ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode
     *
     * @param T $base
     * @param ObjectTypeExtensionNode|InterfaceTypeExtensionNode $extension
     * @return T
     */
    public static function extendObjectFields(
        TypeDefinitionNode $base,
        TypeExtensionNode $extension
    ): TypeDefinitionNode {
        if($extension->fields->count() > 0) {
            $base = clone $base;

            // Duplicates are handled by GraphQL validation since it is a list
            $base->fields = $base->fields->merge($extension->fields);
        }

        return $base;
    }

    /**
     * @template T of ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode
     *
     * @param T $base
     * @param ObjectTypeExtensionNode|InterfaceTypeExtensionNode $extension
     * @return T
     */
    public static function extendObjectType(
        TypeDefinitionNode $base,
        TypeExtensionNode $extension
    ): TypeDefinitionNode {
        return self::extendObjectFields(self::extendObjectInterfaces($base, $extension), $extension);
    }

    public static function extendUnionType(
        UnionTypeDefinitionNode $base,
        UnionTypeExtensionNode $extension
    ): UnionTypeDefinitionNode {
        if($extension->types->count() > 0) {
            $base = clone $base;

            $base->types = $base->types->merge($extension->types);
        }

        return $base;
    }

    public static function extendEnumType(
        EnumTypeDefinitionNode $base,
        EnumTypeExtensionNode $extension
    ): EnumTypeDefinitionNode {
        if($extension->values->count() > 0) {
            $base = clone $base;

            $base->values = $base->values->merge($extension->values);
        }

        return $base;
    }

    // Special for input object types
    public static function extendInputObjectType(
        InputObjectTypeDefinitionNode $base,
        InputObjectTypeExtensionNode $extension
    ): InputObjectTypeDefinitionNode {
        if($extension->fields->count() > 0) {
            $base = clone $base;

            // Duplicates are handled by GraphQL validation since it is a list
            $base->fields = $base->fields->merge($extension->fields);
        }

        return $base;
    }

    /**
     * @param TypeDefinitionImpls $node
     */
    public static function extendType(
        TypeDefinitionNode $node,
        Extension $schemaExtension
    ): ?TypeDefinitionNode {
        $name = $node->name->value;
        $extension = $schemaExtension->getTypeExtension($name);

        if( ! $extension) {
            return null;
        }

        // Common code
        $newNode = self::extendDirectives($node, $extension);

        /**
         * We have an else just in case.
         *
         * @psalm-suppress RedundantConditionGivenDocblockType
         */
        if($newNode instanceof ScalarTypeDefinitionNode) {
            // TODO: Better error
            assert($extension instanceof ScalarTypeExtensionNode);

            // Do nothing, we only add directives to scalars
        }
        elseif($newNode instanceof ObjectTypeDefinitionNode) {
            // TODO: Better error
            assert($extension instanceof ObjectTypeExtensionNode);

            $newNode = self::extendObjectType($newNode, $extension);
        }
        elseif($newNode instanceof InterfaceTypeDefinitionNode) {
            // TODO: Better error
            assert($extension instanceof InterfaceTypeExtensionNode);

            $newNode = self::extendObjectType($newNode, $extension);
        }
        elseif($newNode instanceof UnionTypeDefinitionNode) {
            // TODO: Better error
            assert($extension instanceof UnionTypeExtensionNode);

            $newNode = self::extendUnionType($newNode, $extension);
        }
        elseif($newNode instanceof EnumTypeDefinitionNode) {
            // TODO: Better error
            assert($extension instanceof EnumTypeExtensionNode);

            $newNode = self::extendEnumType($newNode, $extension);
        }
        elseif($newNode instanceof InputObjectTypeDefinitionNode) {
            // TODO: Better error
            assert($extension instanceof InputObjectTypeExtensionNode);

            $newNode = self::extendInputObjectType($newNode, $extension);
        }
        else {
            throw new RuntimeException(sprintf(
                "%s: Unknown schema definition node type %s",
                __METHOD__,
                $newNode::class
            ));
        }

        $schemaExtension->setUsedExtension($name);

        return $newNode !== $node ? $newNode : null;
    }

    public static function extend(
        DocumentNode $base,
        DocumentNode $extension
    ): DocumentNode {
        $schemaExtension = new Extension($extension);

        $checkType = static function(Node $node)
            use($schemaExtension): void {
                /**
                 * @var TypeDefinitionImpls $node
                 */
                if($schemaExtension->hasType($node->name->value)) {
                    throw new DuplicateTypeException($node->name->value);
                }
            };

        $extendType = static function(TypeDefinitionNode $node)
            use($schemaExtension): ?TypeDefinitionNode {
                /**
                 * @var TypeDefinitionImpls $node
                 */
                return self::extendType($node, $schemaExtension);
            };

        $extendDocument = static function(DocumentNode $doc) use($schemaExtension): ?DocumentNode {
            $addDefs = $schemaExtension->getAdditionalDefinitions();
            $newDoc = $doc;

            if(count($addDefs) > 0) {
                $newDoc = clone $newDoc;

                $newDoc->definitions = $newDoc->definitions->merge($addDefs);
            }

            return $newDoc !== $doc ? $newDoc : null;
        };

        /**
         * @psalm-suppress InvalidArgument
         * @var DocumentNode
         */
        $base = Visitor::visit($base, [
            "enter" => [
                NodeKind::SCALAR_TYPE_DEFINITION => $checkType,
                NodeKind::OBJECT_TYPE_DEFINITION => $checkType,
                NodeKind::INTERFACE_TYPE_DEFINITION => $checkType,
                NodeKind::UNION_TYPE_DEFINITION => $checkType,
                NodeKind::ENUM_TYPE_DEFINITION => $checkType,
                NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $checkType,
            ],
            "leave" => [
                NodeKind::DOCUMENT => $extendDocument,
                NodeKind::SCALAR_TYPE_DEFINITION => $extendType,
                NodeKind::OBJECT_TYPE_DEFINITION => $extendType,
                NodeKind::INTERFACE_TYPE_DEFINITION => $extendType,
                NodeKind::UNION_TYPE_DEFINITION => $extendType,
                NodeKind::ENUM_TYPE_DEFINITION => $extendType,
                NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $extendType,
            ],
        ]);

        if($schemaExtension->hasUnusedExtensions()) {
            throw new MissingBaseTypeException($schemaExtension->getUnusedExtensionNames());
        }

        return $base;
    }
}
