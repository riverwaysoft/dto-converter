<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Riverwaysoft\DtoConverter\Ast\Converter;
use Riverwaysoft\DtoConverter\Bridge\ApiPlatform\ApiPlatformInputTypeResolver;
use Riverwaysoft\DtoConverter\ClassFilter\DocBlockCommentFilter;
use Riverwaysoft\DtoConverter\ClassFilter\NegationFilter;
use Riverwaysoft\DtoConverter\ClassFilter\PhpAttributeFilter;
use Riverwaysoft\DtoConverter\CodeProvider\FileSystemCodeProvider;
use Riverwaysoft\DtoConverter\Language\Dart\DartGenerator;
use Riverwaysoft\DtoConverter\Language\Dart\DartImportGenerator;
use Riverwaysoft\DtoConverter\Language\TypeScript\ClassNameTypeResolver;
use Riverwaysoft\DtoConverter\Language\TypeScript\DateTimeTypeResolver;
use Riverwaysoft\DtoConverter\Language\TypeScript\TypeScriptGenerator;
use Riverwaysoft\DtoConverter\Language\TypeScript\TypeScriptGeneratorOptions;
use Riverwaysoft\DtoConverter\Language\TypeScript\TypeScriptImportGenerator;
use Riverwaysoft\DtoConverter\OutputWriter\EntityPerClassOutputWriter\DtoTypeDependencyCalculator;
use Riverwaysoft\DtoConverter\OutputWriter\EntityPerClassOutputWriter\EntityPerClassOutputWriter;
use Riverwaysoft\DtoConverter\OutputWriter\EntityPerClassOutputWriter\KebabCaseFileNameGenerator;
use Riverwaysoft\DtoConverter\OutputWriter\EntityPerClassOutputWriter\SnakeCaseFileNameGenerator;
use Riverwaysoft\DtoConverter\OutputWriter\SingleFileOutputWriter\SingleFileOutputWriter;
use Spatie\Snapshots\MatchesSnapshots;

class EndToEndTest extends TestCase
{
    use MatchesSnapshots;

    private string $codeNestedDto = <<<'CODE'
<?php

class UserCreate {
    public string $id;
    public ?Profile $profile;
}

class FullName {
    public string $firstName;
    public string $lastName;
}

class Profile {
    public FullName|null|string $name;
    public int $age;
}
CODE;

    public function testNormalizationTsDefault(): void
    {
        $codeAttribute = <<<'CODE'
<?php

class UserCreate {
    /** @var string[] */
    public array $achievements;
    /** @var int[][] */
    public array $matrix;
    public ?string $name;
    public string|int|string|null|null $duplicatesInType;
    public int|string|float $age;
    public bool|null $isApproved;
    public float $latitude;
    public float $longitude;
    public mixed $mixed;
}

class CloudNotify {
    public function __construct(public string $id, public string|null $fcmToken, string $notPublicIgnoreMe)
    {
    }
}
CODE;

        $normalized = (new Converter())->convert([$codeAttribute]);
        $this->assertMatchesJsonSnapshot($normalized->getList());
        $results = (new TypeScriptGenerator(new SingleFileOutputWriter('generated.ts')))->generate($normalized);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new TypeScriptSnapshotComparator());
    }

    public function testNestedDtoNormalize(): void
    {
        $normalized = (new Converter())->convert([$this->codeNestedDto]);
        $this->assertMatchesJsonSnapshot($normalized->getList());
    }

    public function testNestedDtoConvert(): void
    {
        $normalized = (new Converter())->convert([$this->codeNestedDto]);
        $results = (new TypeScriptGenerator(new SingleFileOutputWriter('generated.ts'), [new ClassNameTypeResolver()]))->generate($normalized);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new TypeScriptSnapshotComparator());
    }


    public function testUseTypeOverEnumTs(): void
    {
        $code = <<<'CODE'
<?php

use MyCLabs\Enum\Enum;

final class ColorEnum extends Enum
{
    private const RED = 0;
    private const GREEN = 1;
    private const BLUE = 2;
}

final class RoleEnum extends Enum
{
    private const ADMIN = 'admin';
    private const READER = 'reader';
    private const EDITOR = 'editor';
}

class User
{
    public string $id;
    public ColorEnum $themeColor;
    public RoleEnum $role;
}
CODE;

        $normalized = (new Converter())->convert([$code]);
        $typeScriptGenerator = new TypeScriptGenerator(
            new SingleFileOutputWriter('generated.ts'),
            [new ClassNameTypeResolver()],
            new TypeScriptGeneratorOptions(useTypesInsteadOfEnums: true),
        );
        $results = $typeScriptGenerator->generate($normalized);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new TypeScriptSnapshotComparator());
    }

    public function testDart(): void
    {
        $codeDart = <<<'CODE'
<?php

use MyCLabs\Enum\Enum;

final class ColorEnum extends Enum
{
    private const RED = 0;
    private const GREEN = 1;
    private const BLUE = 2;
}

class Category
{
    public string $id;
    public string $title;
    public int $rating;
    /** @var Recipe[] */
    public array $recipes;
}

class Recipe
{
    public string $id;
    public ?string $imageUrl;
    public string|null $url;
    public bool $isCooked;
    public float $weight;
}

class User
{
    public string $id;
    public ?User $bestFriend;
    /** @var User[] */
    public array $friends;
    public ColorEnum $themeColor;
}
CODE;

        $normalized = (new Converter())->convert([$codeDart]);
        $results = (new DartGenerator(new SingleFileOutputWriter('generated.dart'), [new ClassNameTypeResolver()]))->generate($normalized);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new DartSnapshotComparator());
    }

    public function testNormalizationDirectory(): void
    {
        $converter = new Converter();
        $fileProvider = new FileSystemCodeProvider('/\.php$/');
        $result = $converter->convert($fileProvider->getListings(__DIR__ . '/Fixtures'));
        $this->assertMatchesJsonSnapshot($result->getList());
        $results = (new TypeScriptGenerator(new SingleFileOutputWriter('generated.ts'), [new ClassNameTypeResolver()]))->generate($result);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new TypeScriptSnapshotComparator());
    }

    public function testNormalizationWithCustomTypeResolvers(): void
    {
        $codeWithDateTime = <<<'CODE'
<?php

class UserCreate
{
    public \DateTimeImmutable $createdAt;
    public DateTime $updatedAt;
    public ?DateTimeImmutable $promotedAt;
}

class UserCreateConstructor
{
    public function __construct(
       public DateTimeImmutable $createdAt,
       public \DateTime $updatedAt,
       public ?\DateTimeImmutable $promotedAt,
    )
    {
    
    }
}
CODE;

        $converter = new Converter();
        $result = $converter->convert([$codeWithDateTime]);
        $typeScriptGenerator = new TypeScriptGenerator(new SingleFileOutputWriter('generated.ts'), [new ClassNameTypeResolver(), new DateTimeTypeResolver()]);
        $results = ($typeScriptGenerator)->generate($result);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new TypeScriptSnapshotComparator());
    }

    public function testFilterClassesByDocBlock(): void
    {
        $codeWithDateTime = <<<'CODE'
<?php

/** @DTO */
final class ColorEnum extends Enum
{
    private const RED = 0;
    private const GREEN = 1;
    private const BLUE = 2;
}

/** @DTO */
class Category
{
    public string $id;
    public string $title;
    public int $rating;
    /** @var Recipe[] */
    public array $recipes;
}

/** @DTO */
class Recipe
{
    public string $id;
    public ?string $imageUrl;
    public string|null $url;
    public bool $isCooked;
    public float $weight;
}

class IgnoreMe {

}

/** @DTO */
class User
{
    public string $id;
    public ?User $bestFriend;
    /** @var User[] */
    public array $friends;
    public ColorEnum $themeColor;
}


CODE;

        $converter = new Converter(new DocBlockCommentFilter('@DTO'));
        $result = $converter->convert([$codeWithDateTime]);

        $this->assertTrue($result->hasDtoWithType('User'));
        $this->assertTrue($result->hasDtoWithType('Recipe'));
        $this->assertTrue($result->hasDtoWithType('Category'));
        $this->assertTrue($result->hasDtoWithType('ColorEnum'));
        $this->assertFalse($result->hasDtoWithType('IgnoreMe'));
    }

    public function testExcludeFilterClassesByDocBlock(): void
    {
        $codeWithDateTime = <<<'CODE'
<?php

final class ColorEnum extends Enum
{
    private const RED = 0;
    private const GREEN = 1;
    private const BLUE = 2;
}

class Category
{
    public string $id;
    public string $title;
    public int $rating;
    /** @var Recipe[] */
    public array $recipes;
}

class Recipe
{
    public string $id;
    public ?string $imageUrl;
    public string|null $url;
    public bool $isCooked;
    public float $weight;
}

/** @ignore */
class IgnoreMe {

}

class User
{
    public string $id;
    public ?User $bestFriend;
    /** @var User[] */
    public array $friends;
    public ColorEnum $themeColor;
}

CODE;

        $classesWithoutIgnoreFilter = new NegationFilter(new DocBlockCommentFilter('@ignore'));
        $converter = new Converter($classesWithoutIgnoreFilter);
        $result = $converter->convert([$codeWithDateTime]);

        $this->assertTrue($result->hasDtoWithType('User'));
        $this->assertTrue($result->hasDtoWithType('Recipe'));
        $this->assertTrue($result->hasDtoWithType('Category'));
        $this->assertTrue($result->hasDtoWithType('ColorEnum'));
        $this->assertFalse($result->hasDtoWithType('IgnoreMe'));
    }


    public function testFilterClassesByPhpAttribute(): void
    {
        $codeWithDateTime = <<<'CODE'
<?php

#[\Attribute(\Attribute::TARGET_CLASS)]
class Dto
{

}

#[Dto]
final class ColorEnum extends Enum
{
    private const RED = 0;
    private const GREEN = 1;
    private const BLUE = 2;
}

#[Dto]
class Category
{
    public string $id;
    public string $title;
    public int $rating;
    /** @var Recipe[] */
    public array $recipes;
}

#[Dto]
class Recipe
{
    public string $id;
    public ?string $imageUrl;
    public string|null $url;
    public bool $isCooked;
    public float $weight;
}

class IgnoreMe {

}

#[Dto]
class User
{
    public string $id;
    public ?User $bestFriend;
    /** @var User[] */
    public array $friends;
    public ColorEnum $themeColor;
}


CODE;

        $converter = new Converter(new PhpAttributeFilter('Dto'));
        $result = $converter->convert([$codeWithDateTime]);

        $this->assertTrue($result->hasDtoWithType('User'));
        $this->assertTrue($result->hasDtoWithType('Recipe'));
        $this->assertTrue($result->hasDtoWithType('Category'));
        $this->assertTrue($result->hasDtoWithType('ColorEnum'));
        $this->assertFalse($result->hasDtoWithType('IgnoreMe'));
    }

    public function testEntityPerClassOutputWriterTypeScript(): void
    {
        $normalized = (new Converter())->convert([$this->codeNestedDto]);

        $fileNameGenerator = new KebabCaseFileNameGenerator('.ts');
        $typeScriptGenerator = new TypeScriptGenerator(
            new EntityPerClassOutputWriter(
                $fileNameGenerator,
                new TypeScriptImportGenerator(
                    $fileNameGenerator,
                    new DtoTypeDependencyCalculator()
                )
            ),
            [
                new ClassNameTypeResolver(),
            ]
        );
        $results = $typeScriptGenerator->generate($normalized);

        $this->assertCount(3, $results);
        $this->assertMatchesSnapshot($results);
    }

    public function testEntityPerClassOutputWriterDart(): void
    {
        $normalized = (new Converter())->convert([$this->codeNestedDto]);

        $fileNameGenerator = new SnakeCaseFileNameGenerator('.dart');
        $typeScriptGenerator = new DartGenerator(
            new EntityPerClassOutputWriter(
                $fileNameGenerator,
                new DartImportGenerator(
                    $fileNameGenerator,
                    new DtoTypeDependencyCalculator()
                )
            ),
            [
                new ClassNameTypeResolver(),
            ]
        );
        $results = $typeScriptGenerator->generate($normalized);

        $this->assertCount(3, $results);
        $this->assertMatchesSnapshot($results);
    }

    public function testApiPlatformInput(): void
    {
        $codeWithDateTime = <<<'CODE'
<?php

use MyCLabs\Enum\Enum;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Dto
{

}

#[Dto]
final class ColorEnum extends Enum
{
    private const RED = 0;
    private const GREEN = 1;
    private const BLUE = 2;
}

#[Dto]
final class GenderEnum extends Enum
{
    private const UNKNOWN = null;
    private const MAN = 0;
    private const WOMAN = 1;
}

class Profile
{
    public string $firstName;
    public string $lastName;
}

#[Dto]
class ProfileOutput
{
    public string $firstName;
    public string $lastName;
    public GenderEnum $gender;
    public ColorEnum $color;
}

class LocationEmbeddable {
  public function __construct(
    private float $lat,
    private $lan,
  ) {}
}

class Money {

}

class Industry {}

#[Dto]
class UserCreateInput
{
    /* The time when the user was promoted */
    public Profile $profile;
    // The time when the user was promoted
    public ?DateTimeImmutable $promotedAt;
    public ColorEnum $userTheme;
    /** @var Industry[]|null  */
    public array|null $industriesUnion = null;
    /** @var Industry[]|null  */
    public ?array $industriesNullable = null;
    public Money $money;
    public GenderEnum $gender;
    public LocationEmbeddable $location;
}

CODE;

        $converter = new Converter(new PhpAttributeFilter('Dto'));
        $result = $converter->convert([$codeWithDateTime]);
        $typeScriptGenerator = new TypeScriptGenerator(
            new SingleFileOutputWriter('generated.ts'),
            [
                new DateTimeTypeResolver(),
                new ApiPlatformInputTypeResolver([
                    'LocationEmbeddable' => '{ lat: string; lan: string }',
                    'Money' => '{ currency: string; amount: number }',
                ]),
                new ClassNameTypeResolver(),
            ]
        );
        $results = ($typeScriptGenerator)->generate($result);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new TypeScriptSnapshotComparator());

        // use TS template literal
        $typeScriptGenerator = new TypeScriptGenerator(
            new SingleFileOutputWriter('generated.ts'),
            [
                new DateTimeTypeResolver(),
                new ApiPlatformInputTypeResolver([
                    'LocationEmbeddable' => '{ lat: string; lan: string }',
                    'Money' => '{ currency: string; amount: number }',
                ], true),
                new ClassNameTypeResolver(),
            ]
        );
        $results = ($typeScriptGenerator)->generate($result);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new TypeScriptSnapshotComparator());
    }

    public function testUnknownTypeThrows(): void
    {
        $codeWithDateTime = <<<'CODE'
<?php

#[\Attribute(\Attribute::TARGET_CLASS)]
class Dto
{

}

#[Dto]
class A
{
    public \DateTimeImmutable $createdAt;
    public B $b;
}

class B {}
CODE;

        $converter = new Converter(new PhpAttributeFilter('Dto'));
        $result = $converter->convert([$codeWithDateTime]);
        $typeScriptGenerator = new TypeScriptGenerator(new SingleFileOutputWriter('generated.ts'), [new ClassNameTypeResolver(), new DateTimeTypeResolver()]);

        $this->expectExceptionMessage('PHP Type B is not supported. PHP class: A');
        $typeScriptGenerator->generate($result);
    }

    public function testPhp81EnumsFailedWhenNonBacked(): void
    {
        $codeWithDateTime = <<<'CODE'
<?php
#[\Attribute(\Attribute::TARGET_CLASS)]
class Dto
{

}

#[Dto]
enum Color
{
    case RED;
    case BLUE;
    case WHITE;
}
CODE;

        $converter = new Converter(new PhpAttributeFilter('Dto'));
        $this->expectExceptionMessageMatches('/^Non-backed enums are not supported because they are not serializable. Please use backed enums/');
        $converter->convert([$codeWithDateTime]);
    }

    public function testPhp81SuccessWhenBacked(): void
    {
        $codeWithDateTime = <<<'CODE'
<?php

#[\Attribute(\Attribute::TARGET_CLASS)]
class Dto
{

}

#[Dto]
enum Color: int
{
    case RED = 0;
    case BLUE = 1;
    case WHITE = 2;
}

#[Dto]
enum Role: string
{
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case READER = 'reader';
}

#[Dto]
class User {
    public function __construct(public Color $color, public readonly int $user, public Role $role)
    {

    }

    public function getColor(): Color
    {
        return $this->color;
    }

    public function getUser(): int
    {
        return $this->user;
    }
}
CODE;

        $converter = new Converter(new PhpAttributeFilter('Dto'));
        $result = $converter->convert([$codeWithDateTime]);

        $typeScriptGenerator = new TypeScriptGenerator(
            new SingleFileOutputWriter('generated.ts'),
            [
                new ClassNameTypeResolver(),
            ]
        );
        $results = ($typeScriptGenerator)->generate($result);
        $this->assertCount(1, $results);
        $this->assertMatchesSnapshot($results[0]->getContent(), new TypeScriptSnapshotComparator());
    }
}
