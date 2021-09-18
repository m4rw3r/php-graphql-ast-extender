<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

use GraphQL\Language\Parser;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use PHPUnit\Framework\TestCase;

class ExtenderText extends TestCase {
    public function testNone(): void {
        $document = Parser::parse("type Query { foo: String }");
        $emptyDocument = new DocumentNode([
            "definitions" => new NodeList([]),
        ]);

        $newDocument = Extender::extend($document, $emptyDocument);

        $this->assertSame($newDocument, $document);
    }
}
