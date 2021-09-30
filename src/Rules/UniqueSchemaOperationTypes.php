<?php

declare(strict_types=1);

namespace GraphQLASTExtender\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;
use GraphQL\Validator\Rules\ValidationRule;
use function sprintf;

class UniqueSchemaOperationTypes extends ValidationRule {
    public function getSDLVisitor(SDLValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    public function assertUniqueOperationTypes(
        ASTValidationContext $context,
        SchemaDefinitionNode $node
    ): VisitorOperation {
        $usedOperations = [];

        foreach($node->operationTypes as $op) {
            if(array_key_exists($op->operation, $usedOperations)) {
                $context->reportError(new Error(
                    self::duplicateSchemaOperation($op->operation),
                    [$node, $op]
                ));

                continue;
            }

            $usedOperations[$op->operation] = true;
        }

        return Visitor::skipNode();
    }

    public function getASTVisitor(ASTValidationContext $context): array {
        return [
            NodeKind::SCHEMA_DEFINITION =>
                fn(SchemaDefinitionNode $node): VisitorOperation =>
                    $this->assertUniqueOperationTypes($context, $node),
        ];
    }

    public static function duplicateSchemaOperation(
        string $operationType
    ): string {
        return sprintf('Schema operation type "%s" is only allowed to be defined once.', $operationType);
    }
}
