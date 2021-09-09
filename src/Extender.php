<?php

declare(strict_types=1);

namespace M4rw3r\GraphQLASTExtender;

use GraphQL\Language\AST\DocumentNode;

class Extender {
    public function __construct(DocumentNode $initialAST) {

    }

    public static function extend(DocumentNode $initial, DocumentNode $additional): DocumentNode {
        // TODO: Code
        // TODO: Additional values to merge?
        // TODO: Throw on duplicates
        // TODO: Throw on missing types when extending
        // TODO: How to extend unions?
        return $initial;
    }
}
