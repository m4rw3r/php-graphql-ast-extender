<?php

declare(strict_types=1);

namespace GraphQLASTExtender\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;
use GraphQL\Validator\Rules\ValidationRule;
use function sprintf;

class UniqueEnumValues extends ValidationRule
{
    public function getVisitor(ValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    public function getSDLVisitor(SDLValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    private function assertUniqueNames(
        ASTValidationContext $context,
        EnumTypeDefinitionNode $node
    ): VisitorOperation {
        $knownNames = [];

        foreach($node->values as $value) {
            $valueName = $value->name->value;

            if(isset($knownNames[$valueName])) {
                $parentName = $node->name->value;

                $context->reportError(new Error(
                    self::duplicateEnumValueMessage($parentName, $valueName),
                    [$node, $value]
                ));
            }
            else {
                $knownNames[$valueName] = $node->name;
            }
        }

        return Visitor::skipNode();
    }

    public function getASTVisitor(ASTValidationContext $context): array {
        return [
            NodeKind::ENUM_TYPE_DEFINITION =>
                fn(EnumTypeDefinitionNode $node): VisitorOperation =>
                    $this->assertUniqueNames($context, $node),
        ];
    }

    public static function duplicateEnumValueMessage(
        string $parentName,
        string $fieldName
    ): string {
        return sprintf('Enum "%s" is only allowed to contain the value "%s" once.', $parentName, $fieldName);
    }
}