<?php

declare(strict_types=1);

namespace GraphQLASTExtender\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
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

class UniqueFieldNames extends ValidationRule
{
    /** @var array<string, NameNode> */
    private $knownNames = [];

    /** @var array<array<string, NameNode>> */
    private $knownNameStack = [];

    public function getVisitor(ValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    public function getSDLVisitor(SDLValidationContext $context) {
        return $this->getASTVisitor($context);
    }

    public function getASTVisitor(ASTValidationContext $context): array {
        $this->knownNames = [];
        $this->knownNameStack = [];

        return [
            NodeKind::INPUT_OBJECT_TYPE_DEFINITION => [
                'enter' => function () : void {
                    $this->knownNameStack[] = $this->knownNames;
                    $this->knownNames = [];
                },
                'leave' => function () : void {
                    $this->knownNames = array_pop($this->knownNameStack);
                },
            ],
            NodeKind::OBJECT_TYPE_DEFINITION => [
                'enter' => function () : void {
                    $this->knownNameStack[] = $this->knownNames;
                    $this->knownNames = [];
                },
                'leave' => function () : void {
                    $this->knownNames = array_pop($this->knownNameStack);
                },
            ],
            NodeKind::INTERFACE_TYPE_DEFINITION => [
                'enter' => function () : void {
                    $this->knownNameStack[] = $this->knownNames;
                    $this->knownNames = [];
                },
                'leave' => function () : void {
                    $this->knownNames = array_pop($this->knownNameStack);
                },
            ],
            NodeKind::FIELD_DEFINITION =>
            /**
             * @param string|int $unusedKey
             * @param Node|NodeList $unusedParent
             */
            function(
                FieldDefinitionNode $node,
                $unusedKey,
                $unusedParent,
                array $unusedPath,
                array $ancestors
            ) use ($context) : VisitorOperation {
                $fieldName = $node->name->value;
                $ancestorsCount = count($ancestors);
                $objectNode = $ancestors[$ancestorsCount - 1];

                assert(
                    $objectNode instanceof ObjectTypeDefinitionNode ||
                    $objectNode instanceof InterfaceTypeDefinitionNode
                );

                if (isset($this->knownNames[$fieldName])) {
                    $parentName = $objectNode->name->value;

                    $context->reportError(new Error(
                        self::duplicateInputFieldMessage($parentName, $fieldName),
                        [$objectNode, $node]
                    ));
                } else {
                    $this->knownNames[$fieldName] = $node->name;
                }

                return Visitor::skipNode();
            },
            NodeKind::INPUT_VALUE_DEFINITION =>
            /**
             * @param string|int $unusedKey
             * @param Node|NodeList $unusedParent
             */
            function(
                InputValueDefinitionNode $node,
                $unusedKey,
                $unusedParent,
                array $unusedPath,
                array $ancestors
            ) use ($context): VisitorOperation {
                $fieldName = $node->name->value;
                $ancestorsCount = count($ancestors);
                $objectNode = $ancestors[$ancestorsCount - 1];

                assert($objectNode instanceof InputObjectTypeDefinitionNode);

                if (isset($this->knownNames[$fieldName])) {
                    $parentName = $objectNode->name->value;

                    $context->reportError(new Error(
                        self::duplicateInputFieldMessage($parentName, $fieldName),
                        [$objectNode, $node]
                    ));
                } else {
                    $this->knownNames[$fieldName] = $node->name;
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateInputFieldMessage(
        string $parentName,
        string $fieldName
    ): string {
        return sprintf('Field "%s.%s" can only be defined once.', $parentName, $fieldName);
    }
}
