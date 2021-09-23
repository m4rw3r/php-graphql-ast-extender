<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use PHPUnit\Framework\TestCase;

class ExtenderText extends TestCase {
    public function testEmpty(): void {
        $base = new DocumentNode([
            "definitions" => new NodeList([]),
        ]);
        $extension = new DocumentNode([
            "definitions" => new NodeList([]),
        ]);

        $extended = Extender::extend($base, $extension);

        $this->assertSame($base, $extended);
    }

    public function testEmptyExtension(): void {
        $base = Parser::parse("type Query { foo: String }");
        $extension = new DocumentNode([
            "definitions" => new NodeList([]),
        ]);

        $extended = Extender::extend($base, $extension);

        $this->assertSame($base, $extended);
        $this->assertSame("type Query {
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testEmptyBase(): void {
        $base = new DocumentNode([
            "definitions" => new NodeList([]),
        ]);
        $extension = Parser::parse("type Query { foo: String }");

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testDuplicateType(): void {
        $this->expectExceptionMessage("Duplicate type definition 'Query'");
        $this->expectException(DuplicateTypeException::class);

        $base = Parser::parse("type Query { foo: String }");
        $extension = Parser::parse("type Query { foo: String }");

        Extender::extend($base, $extension);
    }

    public function testUnusedExtension(): void {
        $this->expectExceptionMessage("Missing base-type for type-extensions to 'Query'");
        $this->expectException(MissingBaseTypeException::class);

        $base = new DocumentNode([
            "definitions" => new NodeList([]),
        ]);
        $extension = Parser::parse("extend type Query { foo: String }", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testObjectFieldCollision(): void {
        $base = Parser::parse("type Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query { foo: String }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        // This will fail validation:
        $this->assertSame("type Query {
  foo: Int
  foo: String
}
", Printer::doPrint($extended));
    }
}
