<?php

declare(strict_types=1);

namespace M4rw3r\GraphQLASTExtender;

use GraphQL\Language\Parser;
use GraphQL\Language\AST\DocumentNode;
use PHPUnit\Framework\TestCase;

class ExtenderText extends TestCase {
    public function testNone(): void {
        $document = Parser::parse("type Query { foo: String }");
        $emptyDocument = new DocumentNode([]);

        $newDocument = Extender::extend($document, $emptyDocument);

        $this->assertSame($newDocument, $document);
    }
}
