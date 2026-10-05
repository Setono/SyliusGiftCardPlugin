<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The plugin ships every text in English, Danish and French. A key added to one language only shows the key itself, or
 * the English text, to a shop in the others, and a translation that drops a placeholder shows the customer a sentence
 * with a hole in it
 */
final class TranslationFilesTest extends TestCase
{
    private const LOCALES = ['da', 'fr'];

    /**
     * @test
     *
     * @dataProvider domains
     */
    public function every_language_has_the_keys_the_english_one_has(string $domain): void
    {
        $english = array_keys($this->translations($domain, 'en'));

        foreach (self::LOCALES as $locale) {
            $translated = array_keys($this->translations($domain, $locale));

            self::assertSame([], array_values(array_diff($english, $translated)), sprintf('missing in %s.%s.yml', $domain, $locale));
            self::assertSame([], array_values(array_diff($translated, $english)), sprintf('only in %s.%s.yml', $domain, $locale));
        }
    }

    /**
     * @test
     *
     * @dataProvider domains
     */
    public function every_translation_keeps_the_placeholders_of_the_english_one(string $domain): void
    {
        $english = $this->translations($domain, 'en');

        foreach (self::LOCALES as $locale) {
            foreach ($this->translations($domain, $locale) as $key => $translation) {
                if (!isset($english[$key])) {
                    continue;
                }

                self::assertSame(
                    $this->placeholders($english[$key]),
                    $this->placeholders($translation),
                    sprintf('%s in %s.%s.yml', $key, $domain, $locale),
                );
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function domains(): iterable
    {
        yield 'messages' => ['messages'];
        yield 'validators' => ['validators'];
        yield 'flashes' => ['flashes'];
    }

    /**
     * @return array<string, string> the translations by their full key
     */
    private function translations(string $domain, string $locale): array
    {
        $translations = Yaml::parseFile(sprintf('%s/../../../src/Resources/translations/%s.%s.yml', __DIR__, $domain, $locale));
        self::assertIsArray($translations);

        return $this->flatten($translations);
    }

    /**
     * @param array<array-key, mixed> $translations
     *
     * @return array<string, string>
     */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $flattened = [];
        foreach ($translations as $key => $value) {
            $key = '' === $prefix ? (string) $key : sprintf('%s.%s', $prefix, $key);

            if (is_array($value)) {
                $flattened += $this->flatten($value, $key);

                continue;
            }

            self::assertIsString($value, $key);
            $flattened[$key] = $value;
        }

        return $flattened;
    }

    /**
     * @return list<string> the %placeholder% and {{ placeholder }} parameters of a translation, sorted
     */
    private function placeholders(string $translation): array
    {
        preg_match_all('/%[a-z_]+%|\{\{ ?[a-z_]+ ?\}\}/', $translation, $matches);

        $placeholders = array_map(static fn (string $placeholder): string => str_replace(' ', '', $placeholder), $matches[0]);
        sort($placeholders);

        return $placeholders;
    }
}
