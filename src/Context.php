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
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use RuntimeException;

/**
 * @psalm-import-type TypeExtensionImpls from Util
 * @psalm-import-type TypeDefinitionImpls from Util
 * @psalm-import-type TypeSystemDefinitionImpls from Util
 */
class Context {
    private ?SchemaDefinitionNode $schemaDefinition = null;
    /**
     * @var Array<string, TypeDefinitionImpls>
     */
    private array $types = [];
    /**
     * @var Array<string, TypeExtensionImpls>
     */
    private array $typeExtensions = [];
    /**
     * @var Array<string, DirectiveDefinitionNode>
     */
    private array $directives = [];
    /**
     * @var Array<string, bool>
     */
    private array $usedExtensions = [];

    public function addType(Node $node): void {
        if($node instanceof SchemaDefinitionNode) {
            // TODO: Check for duplicate

            $this->schemaDefinition = $node;
        }

        if($node instanceof TypeDefinitionNode) {
            assert(Util::isTypeDefinition($node));
            // TODO: Check for duplicate
            $this->types[$node->name->value] = $node;
        }

        if($node instanceof TypeExtensionNode) {
            assert(Util::isTypeExtension($node));

            // TODO: Check for duplicate
            $this->typeExtensions[$node->name->value] = $node;
        }

        if($node instanceof DirectiveDefinitionNode) {
            // TODO: Check for duplicate
            $this->directives[$node->name->value] = $node;
        }

        throw new RuntimeException(sprintf(
            "%s: Unexpected TypeSystemDefinitionNode type: %s",
            __METHOD__,
            $node::class
        ));
    }

    public function hasType(string $name): bool {
        return array_key_exists($name, $this->types);
    }

    /**
     * @return Array<string, TypeDefinitionImpls>
     */
    public function getTypes(): array {
        return $this->types;
    }

    /**
     * @return ?TypeExtensionImpls
     */
    public function getTypeExtension(string $name): ?Node {
        return $this->typeExtensions[$name] ?? null;
    }

    public function setUsedExtension(string $name): void {
        $this->usedExtensions[$name] = true;
    }
}
