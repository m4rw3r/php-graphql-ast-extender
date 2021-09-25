<?php

declare(strict_types=1);

namespace GraphQLASTExtender;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
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
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Field \"Query.foo\" can only be defined once.");
        $base = Parser::parse("type Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query { foo: String }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testObjectFieldCollisionAssumeValid(): void {
        $base = Parser::parse("type Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query { foo: String }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testInterfaceFieldCollision(): void {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Field \"Query.foo\" can only be defined once.");
        $base = Parser::parse("interface Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend interface Query { foo: String }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testInterfaceFieldCollisionAssumeValid(): void {
        $base = Parser::parse("interface Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend interface Query { foo: String }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        $this->assertSame("interface Query {
  foo: Int
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testDirective(): void {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("The directive \"myDirective\" can only be used once at this location.");

        $base = Parser::parse("directive @myDirective on OBJECT
type Query @myDirective { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @myDirective", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testDirectiveAssumeValid(): void {
        $base = Parser::parse("directive @myDirective(value: String) on OBJECT
type Query @myDirective { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @myDirective(value: \"extra\")", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        $this->assertSame("directive @myDirective(value: String) on OBJECT

type Query @myDirective @myDirective(value: \"extra\") {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testDirectiveDuplicate(): void {
        $base = Parser::parse("type Query @deprecated { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated(reason: \"test\")", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        // This will fail validation:
        $this->assertSame("type Query @deprecated @deprecated(reason: \"test\") {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendType(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension InputObjectTypeExtension for ObjectTypeDefinition type 'Query'.");
        $base = Parser::parse("type Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend input Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendTypeAssumeValid(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension InputObjectTypeExtension for ObjectTypeDefinition type 'Query'.");
        $base = Parser::parse("type Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend input Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendType2(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InputObjectTypeDefinition type 'Query'.");
        $base = Parser::parse("input Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendTypeAssumeValid2(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InputObjectTypeDefinition type 'Query'.");
        $base = Parser::parse("input Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendType3(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InterfaceTypeDefinition type 'Query'.");
        $base = Parser::parse("interface Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendTypeAssumeValid3(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InterfaceTypeDefinition type 'Query'.");
        $base = Parser::parse("interface Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendType4(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for UnionTypeDefinition type 'Query'.");
        $base = Parser::parse("union Query = Foo type Foo { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendTypeAssumeValid4(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for UnionTypeDefinition type 'Query'.");
        $base = Parser::parse("union Query = Foo type Foo { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendType5(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InputObjectTypeDefinition type 'Query'.");
        $base = Parser::parse("input Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendTypeAssumeValid5(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InputObjectTypeDefinition type 'Query'.");
        $base = Parser::parse("input Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendType6(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for ScalarTypeDefinition type 'Query'.");
        $base = Parser::parse("scalar Query", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendTypeAssumeValid6(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for ScalarTypeDefinition type 'Query'.");
        $base = Parser::parse("scalar Query", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendType7(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for EnumTypeDefinition type 'Query'.");
        $base = Parser::parse("enum Query { FOO }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testBadExtendTypeAssumeValid7(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for EnumTypeDefinition type 'Query'.");
        $base = Parser::parse("enum Query { FOO }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query {
  foo: Int
}
", Printer::doPrint($extended));
    }
}
