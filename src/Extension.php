<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

use Generator;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\Visitor;

/**
 * @psalm-import-type TypeDefinitionImpls from Extender
 * @psalm-import-type TypeExtensionImpls from Extender
 * @psalm-import-type DefinitionImpls from Extender
 */
class Extension {
    private DocumentNode $ast;
    private ?SchemaDefinitionNode $schemaDefinition = null;
    private ?SchemaTypeExtensionNode $schemaExtension = null;
    private bool $usedSchemaExtension = false;
    /**
     * @var Array<string, TypeDefinitionImpls>
     */
    private array $types = [];
    /**
     * @var Array<string, TypeExtensionImpls>
     */
    private array $typeExtensions = [];
    /**
     * @var Array<string>
     */
    private array $usedExtensions = [];

    public function __construct(DocumentNode $ast) {
        $this->ast = $ast;

        /**
         * @var DefinitionImpls $def
         */
        foreach($ast->definitions as $def) {
            // TODO: Do we just ignore these?
            // ExecutableDefinitionNode
            // OperationDefinitionNode
            // FragmentDefinitionNode

            if($def instanceof SchemaDefinitionNode) {
                assert($this->schemaDefinition === null);

                $this->schemaDefinition = $def;
            }
            elseif($def instanceof SchemaTypeExtensionNode) {
                assert($this->schemaDefinition === null);

                $this->schemaExtension = $def;
            }
            elseif($def instanceof TypeDefinitionNode) {
                /**
                 * @var TypeDefinitionImpls $def
                 */
                $this->types[$def->name->value] = $def;
            }
            elseif($def instanceof TypeExtensionNode) {
                /**
                 * @var TypeExtensionImpls $def
                 */
                assert( ! $def instanceof SchemaTypeExtensionNode);
                $this->typeExtensions[$def->name->value] = $def;
            }
            elseif($def instanceof DirectiveDefinitionNode) {
                continue;
            }
            else {
                throw new Exception(sprintf(
                    "Unexpected schema definition node: %s.",
                    $def->kind
                ));
            }
        }
    }

    public function hasType(string $name): bool {
        return array_key_exists($name, $this->types);
    }

    /**
     * @return Array<DefinitionNode & Node>
     */
    public function getAdditionalDefinitions(): array {
        $defs = [];

        /**
         * @var DefinitionImpls $def
         */
        foreach($this->ast->definitions as $def) {
            // TypeSystemDefinitionNode
            //   SchemaDefinitionNode

            //   TypeSystemDefinitionNode
            if($def instanceof TypeExtensionNode) {
                continue;
            }

            // ExecutableDefinitionNode
            // We have checked this already, so it should still be true
            assert(
                ! $def instanceof OperationDefinitionNode &&
                ! $def instanceof FragmentDefinitionNode
            );

            // TypeSystemDefinitionNode
            //   SchemaDefinitionNode
            //   TypeDefinitionNode
            //   DirectiveDefinitionNode
            $defs[] = $def;
        }

        return $defs;
    }

    /**
     * @return ?TypeExtensionImpls
     */
    public function getTypeExtension(string $name): ?Node {
        return $this->typeExtensions[$name] ?? null;
    }

    public function setUsedExtension(string $name): void {
        $this->usedExtensions[] = $name;
    }

    public function getSchema(): ?SchemaDefinitionNode {
        return $this->schemaDefinition;
    }

    public function getSchemaExtension(): ?SchemaTypeExtensionNode {
        return $this->schemaExtension;
    }

    public function setUsedSchemaExtension(bool $value): void {
        $this->usedSchemaExtension = $value;
    }

    public function hasUnusedSchemaExtension(): bool {
        return $this->schemaExtension !== null && ! $this->usedSchemaExtension;
    }

    public function hasUnusedExtensions(): bool {
        return count(array_diff(array_keys($this->typeExtensions), $this->usedExtensions)) > 0;
    }

    /**
     * @return Array<string>
     */
    public function getUnusedExtensionNames(): array {
        return array_keys($this->typeExtensions);
    }
}
