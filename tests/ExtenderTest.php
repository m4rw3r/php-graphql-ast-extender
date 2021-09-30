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

        Extender::extend($base, $extension);
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

        Extender::extend($base, $extension);
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

        Extender::extend($base, $extension);
    }

    public function testBadExtendType2(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InputObjectTypeDefinition type 'Query'.");
        $base = Parser::parse("input Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testBadExtendType3(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InterfaceTypeDefinition type 'Query'.");
        $base = Parser::parse("interface Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testBadExtendType4(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for UnionTypeDefinition type 'Query'.");
        $base = Parser::parse("union Query = Foo type Foo { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testBadExtendType5(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for InputObjectTypeDefinition type 'Query'.");
        $base = Parser::parse("input Query { foo: Int }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testBadExtendType6(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for ScalarTypeDefinition type 'Query'.");
        $base = Parser::parse("scalar Query", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testBadExtendType7(): void {
        $this->expectException(MismatchedTypeExtensionException::class);
        $this->expectExceptionMessage("Mismatched type extension ObjectTypeExtension for EnumTypeDefinition type 'Query'.");
        $base = Parser::parse("enum Query { FOO }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query @deprecated", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testExtendObjectInterfaces(): void {
        $base = Parser::parse("type Query { foo: String }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query implements A interface A { foo: String }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query implements A {
  foo: String
}

interface A {
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testExtendObjectInterfacesDuplicate(): void {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Type \"Query\" is only allowed to implement the interface \"A\" once.");
        $base = Parser::parse("type Query implements A { foo: String } interface A { foo: String } ", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query implements A", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testExtendObjectInterfacesDuplicateAssumeValid(): void {
        $base = Parser::parse("type Query implements A { foo: String } interface A { foo: String } ", [ "noLocation" => true ]);
        $extension = Parser::parse("extend type Query implements A", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        $this->assertSame("type Query implements A & A {
  foo: String
}

interface A {
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testExtendUnion(): void {
        $base = Parser::parse("union A = B type B { foo: String }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend union A = C type C { baz: Int }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("union A = B | C

type B {
  foo: String
}

type C {
  baz: Int
}
", Printer::doPrint($extended));
    }

    public function testExtendUnionDuplicate(): void {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Union \"A\" is only allowed to contain the type \"B\" once.");
        $base = Parser::parse("union A = B type B { foo: String }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend union A = B", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("union A = B | B

type B {
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testExtendUnionDuplicateAssumeValid(): void {
        $base = Parser::parse("union A = B type B { foo: String }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend union A = B", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        $this->assertSame("union A = B | B

type B {
  foo: String
}
", Printer::doPrint($extended));
    }

    public function testExtendEnum(): void {
        $base = Parser::parse("enum MyEnum { A }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend enum MyEnum { B }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("enum MyEnum {
  A
  B
}
", Printer::doPrint($extended));
    }

    public function testExtendEnumDuplicate(): void {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Enum \"MyEnum\" is only allowed to contain the value \"A\" once.");
        $base = Parser::parse("enum MyEnum { A }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend enum MyEnum { A }", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testExtendEnumDuplicateAssumeValid(): void {
        $base = Parser::parse("enum MyEnum { A }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend enum MyEnum { A }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        $this->assertSame("enum MyEnum {
  A
  A
}
", Printer::doPrint($extended));
    }

    public function testExtendInputObject(): void {
        $base = Parser::parse("input MyInput { foo: String }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend input MyInput { bar: Int }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("input MyInput {
  foo: String
  bar: Int
}
", Printer::doPrint($extended));
    }

    public function testExtendInputObjectDuplicateField(): void {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Field \"MyInput.foo\" can only be defined once.");
        $base = Parser::parse("input MyInput { foo: String }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend input MyInput { foo: Int }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("input MyInput {
  foo: String
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testExtendInputObjectDuplicateFieldAssumeValid(): void {
        $base = Parser::parse("input MyInput { foo: String }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend input MyInput { foo: Int }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        $this->assertSame("input MyInput {
  foo: String
  foo: Int
}
", Printer::doPrint($extended));
    }

    public function testExtendWithOperation(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Unexpected schema definition node: OperationDefinition.");
        $base = Parser::parse("input MyInput { foo: String }", [ "noLocation" => true ]);
        $extension = Parser::parse("query MyQuery { foo }", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testSchema(): void {
        $base = new DocumentNode([
            "definitions" => new NodeList([]),
        ]);
        $extension = Parser::parse("schema { mutation: Mutations }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension, ["assumeValid" => true]);

        $this->assertNotSame($base, $extended);
        $this->assertSame("schema {
  mutation: Mutations
}
", Printer::doPrint($extended));
    }

    public function testSchemaExtension(): void {
        $base = Parser::parse("schema { query: Query }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend schema { mutation: Mutations }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("schema {
  query: Query
  mutation: Mutations
}
", Printer::doPrint($extended));
    }

    public function testSchemaExtensionDirectives(): void {
        $base = Parser::parse("directive @myDirective on SCHEMA schema @myDirective { query: Query }", [ "noLocation" => true ]);
        $extension = Parser::parse("directive @foo on SCHEMA extend schema @foo { mutation: Mutations }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("directive @myDirective on SCHEMA

schema @myDirective @foo {
  query: Query
  mutation: Mutations
}

directive @foo on SCHEMA
", Printer::doPrint($extended));
    }

    public function testSchemaExtensionMissing(): void {
        $this->expectException(MissingBaseSchemaException::class);
        $base = Parser::parse("type Query { query: string }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend schema { mutation: Mutations }", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }

    public function testSchemaExtensionDuplicate(): void {
        $this->expectException(DuplicateSchemaException::class);
        $base = Parser::parse("schema { query: Query }", [ "noLocation" => true ]);
        $extension = Parser::parse("schema { mutation: Mutations }", [ "noLocation" => true ]);

        $extended = Extender::extend($base, $extension);

        $this->assertNotSame($base, $extended);
        $this->assertSame("schema {
  query: Query
  mutation: Mutations
}
", Printer::doPrint($extended));
    }

    public function testSchemaExtensionDuplicateOperation(): void {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Schema operation type \"query\" is only allowed to be defined once.");
        $base = Parser::parse("schema { query: Query }", [ "noLocation" => true ]);
        $extension = Parser::parse("extend schema { query: MyQuery }", [ "noLocation" => true ]);

        Extender::extend($base, $extension);
    }
}
