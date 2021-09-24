<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;

/**
 * @psalm-import-type TypeExtensionImpls from Extender
 * @psalm-import-type TypeDefinitionImpls from Extender
 */
class MismatchedTypeExtensionException extends Exception {
    /**
     * @param TypeDefinitionImpls $base
     * @param TypeExtensionImpls $extension
     */
    public function __construct(
        TypeDefinitionNode $base,
        TypeExtensionNode $extension
    ) {
        parent::__construct(sprintf(
            "Mismatched type extension %s for %s type '%s'.",
            $extension->kind,
            $base->kind,
            $base->name->value,
        ));
    }
}
