<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

class MissingBaseSchemaException extends Exception {
    public function __construct() {
        parent::__construct("Missing base-schema for schema-extension");
    }
}
