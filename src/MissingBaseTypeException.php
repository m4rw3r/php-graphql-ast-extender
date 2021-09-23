<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

class MissingBaseTypeException extends Exception {
    /**
     * @param Array<string> $typeNames
     */
    public function __construct(array $typeNames) {
        parent::__construct(sprintf(
            "Missing base-%s for type-extensions to %s",
            count($typeNames) > 1 ? "types" : "type",
            implode(", ", array_map(function(string $s): string {
                return sprintf("'%s'", $s);
            }, $typeNames)),
        ));
    }
}
