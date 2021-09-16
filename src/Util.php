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

/**
 * @psalm-type TypeExtensionImpls ScalarTypeExtensionNode | ObjectTypeExtensionNode | InterfaceTypeExtensionNode | UnionTypeExtensionNode | EnumTypeExtensionNode | InputObjectTypeExtensionNode
 * @psalm-type TypeDefinitionImpls ScalarTypeDefinitionNode | ObjectTypeDefinitionNode | InterfaceTypeDefinitionNode | UnionTypeDefinitionNode | EnumTypeDefinitionNode | InputObjectTypeDefinitionNode
 * @psalm-type TypeSystemDefinitionImpls SchemaDefinitionNode | TypeDefinitionImpls | TypeExtensionImpls | DirectiveDefinitionNode
 */
class Util {
    /**
     * @psalm-assert-if-true TypeDefinitionImpls $node
     * @param Node|TypeDefinitionNode $node
     */
    public static function isTypeDefinition($node): bool {
        return $node instanceof TypeDefinitionNode;
    }

    /**
     * @psalm-assert-if-true TypeExtensionImpls $node
     * @param Node|TypeExtensionNode $node
     */
    public static function isTypeExtension($node): bool {
        return $node instanceof TypeExtensionNode;
    }

    /**
     * @psalm-assert-if-true TypeSystemDefinitionImpls $node
     * @param TypeSystemDefinitionNode $node
     */
    public static function isTypeSystemDefinition($node): bool {
        return $node instanceof TypeSystemDefinitionNode;
    }

    /**
     *
     */
    public static function mergeNodeList(NodeList $orig, NodeList $toAdd): NodeList {
        if($toAdd->count() === 0) {
            return;
        }

        $items = [];

        foreach($orig as $o) {

        }
    }
}
