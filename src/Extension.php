<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

use Generator;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\Visitor;
use RuntimeException;

/**
 * @psalm-import-type TypeDefinitionImpls from Extender
 * @psalm-import-type TypeExtensionImpls from Extender
 */
class Extension {
    private DocumentNode $ast;
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
     * @var Array<string>
     */
    private array $usedExtensions = [];

    public function __construct(DocumentNode $ast) {
        $this->ast = $ast;

        foreach($ast->definitions as $def) {
            // TODO: Do we just ignore these?
            // ExecutableDefinitionNode
            // OperationDefinitionNode
            // FragmentDefinitionNode

            if($def instanceof SchemaDefinitionNode) {
                assert($this->schemaDefinition === null);

                $this->schemaDefinition = $def;
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
                $this->typeExtensions[$def->name->value] = $def;
            }
            else {
                throw new RuntimeException(sprintf(
                    "%s: Unknown schema definition node type %s",
                    __METHOD__,
                    $def::class
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

        foreach($this->ast->definitions as $def) {
            if($def instanceof TypeExtensionNode) {
                continue;
            }

            if($def instanceof SchemaDefinitionNode) {
                continue;
            }

            if($def instanceof TypeExtensionNode) {
                continue;
            }

            // ExecutableDefinitionNode
            // OperationDefinitionNode
            // FragmentDefinitionNode

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
