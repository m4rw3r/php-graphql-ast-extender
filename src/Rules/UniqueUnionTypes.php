<?php

declare(strict_types=1);

namespace GraphQLASTExtender\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;
use GraphQL\Validator\Rules\ValidationRule;
use function array_pop;
use function sprintf;

class UniqueUnionTypes extends ValidationRule
{
    public function getVisitor(ValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    public function getSDLVisitor(SDLValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    private function assertUniqueNames(
        ASTValidationContext $context,
        UnionTypeDefinitionNode $node
    ): VisitorOperation {
        $knownNames = [];

        foreach($node->types as $type) {
            $typeName = $type->name->value;

            if(isset($knownNames[$typeName])) {
                $parentName = $node->name->value;

                $context->reportError(new Error(
                    self::duplicateInterfaceMessage($parentName, $typeName),
                    [$node, $type]
                ));
            }
            else {
                $knownNames[$typeName] = $node->name;
            }
        }

        return Visitor::skipNode();
    }

    public function getASTVisitor(ASTValidationContext $context): array {
        return [
            NodeKind::UNION_TYPE_DEFINITION =>
                fn(UnionTypeDefinitionNode $node): VisitorOperation =>
                    $this->assertUniqueNames($context, $node),
        ];
    }

    public static function duplicateInterfaceMessage(
        string $parentName,
        string $fieldName
    ): string {
        return sprintf('Union "%s" is only allowed to contain the type "%s" once.', $parentName, $fieldName);
    }
}
