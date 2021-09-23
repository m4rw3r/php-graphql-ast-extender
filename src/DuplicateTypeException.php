<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

class DuplicateTypeException extends Exception {
    public function __construct(string $typeName) {
        parent::__construct(sprintf(
            "Duplicate type definition '%s'.",
            $typeName,
        ));
    }
}
