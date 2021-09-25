<?php

declare(strict_types=1);

namespace GraphQLASTExtender\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;
use GraphQL\Validator\Rules\ValidationRule;
use function array_pop;
use function sprintf;

class UniqueObjectInterfaces extends ValidationRule {
    public function getVisitor(ValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    public function getSDLVisitor(SDLValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    /**
     * @param ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $node
     */
    private function assertUniqueInterfaces(ASTValidationContext $context, $node): VisitorOperation {
        $knownNames = [];

        foreach($node->interfaces as $iface) {
            $ifaceName = $iface->name->value;

            if(isset($knownNames[$ifaceName])) {
                $parentName = $node->name->value;

                $context->reportError(new Error(
                    self::duplicateInterfaceMessage($parentName, $ifaceName),
                    [$node, $iface]
                ));
            }
            else {
                $knownNames[$ifaceName] = $node->name;
            }
        }

        return Visitor::skipNode();
    }

    public function getASTVisitor(ASTValidationContext $context): array {
        return [
            NodeKind::OBJECT_TYPE_DEFINITION =>
                fn(ObjectTypeDefinitionNode $node): VisitorOperation =>
                    $this->assertUniqueInterfaces($context, $node),
            NodeKind::INTERFACE_TYPE_DEFINITION =>
                fn(InterfaceTypeDefinitionNode $node): VisitorOperation =>
                    $this->assertUniqueInterfaces($context, $node),
        ];
    }

    public static function duplicateInterfaceMessage(
        string $parentName,
        string $fieldName
    ): string {
        return sprintf('Type "%s" is only allowed to implement the interface "%s" once.', $parentName, $fieldName);
    }
}
