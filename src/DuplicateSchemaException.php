<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

class DuplicateSchemaException extends Exception {
    public function __construct() {
        parent::__construct("Duplicate schema definition");
    }
}
