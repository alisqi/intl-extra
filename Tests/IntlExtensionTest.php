<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Extra\Intl\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;

class IntlExtensionTest extends TestCase
{
    private Environment $twig;
    private static array $dateFormats;

    public function setUp(): void {
        $this->twig = new Environment(
            new ArrayLoader([]),
            ['cache' => false, 'strict_variables' => true]
        );
        $this->twig->getExtension(CoreExtension::class)->setTimeZone('UTC');

        if (empty(self::$dateFormats)) {
            $reflectionProperty = new \ReflectionClassConstant(IntlExtension::class, 'DATE_FORMATS');
            self::$dateFormats = $reflectionProperty->getValue();
        }
    }

    public function testFormatterProto(): void
    {
        $dateFormatterProto = new \IntlDateFormatter('fr', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
        $numberFormatterProto = new \NumberFormatter('fr', \NumberFormatter::DECIMAL);
        $numberFormatterProto->setTextAttribute(\NumberFormatter::POSITIVE_PREFIX, '++');
        $numberFormatterProto->setAttribute(\NumberFormatter::FRACTION_DIGITS, 1);
        $ext = new IntlExtension($dateFormatterProto, $numberFormatterProto);
        $this->assertSame('++12,3', $ext->formatNumber('12.3456'));
    }

    /**
     * Tests that the extension handles IntlDateFormatter settings correctly when calling format_datetime without arguments
     * @dataProvider getIntlDateFormatterData
     */
    public function testIntlDateFormatter(string $targetLocale, string $targetTimezone, ?string $dateFormat, ?string $timeFormat, string $input, string $expected): void
    {
        $ext = new IntlExtension(
            (new \IntlDateFormatter(
                $targetLocale, self::$dateFormats[$dateFormat], self::$dateFormats[$timeFormat],
                new \DateTimeZone($targetTimezone)
            ))
        );

        $this->twig->setLoader(new ArrayLoader(['test.twig' => "{{ inputDateTime|format_datetime() }}"]));
        $this->twig->addExtension($ext);
        $rendered = $this->twig->render('test.twig', ['inputDateTime' => $input]);
        self::assertStringContainsString($expected, $rendered);
    }

    /**
     * Tests that the IntlDateFormatter settings are always overridden by the arguments given to format_datetime
     * @dataProvider getIntlDateFormatterData
     */
    public function testIntlDateFormatterOverride(string $targetLocale, string $targetTimezone, ?string $dateFormat, ?string $timeFormat, string $input, string $expected): void
    {
        $ext = new IntlExtension(
            (new \IntlDateFormatter(
                'ru', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL,
                new \DateTimeZone('Europe/Moscow')
            ))
        );

        $this->twig->setLoader(new ArrayLoader([
            'test.twig' => "{{ inputDateTime|format_datetime('$dateFormat', '$timeFormat', locale='$targetLocale', timezone='$targetTimezone') }}"
        ]));
        $this->twig->addExtension($ext);
        self::assertStringContainsString($expected, $this->twig->render('test.twig', ['inputDateTime' => $input]));
    }

    /**
     * Test that the pattern from the IntlDateFormatter will not override dateFormat or timeFormat if one of them is null.
     * @dataProvider getIntlDateFormatterOverrideData
     */
    public function testIntlDateFormatterDateOrTimeOverride(string $formatDateTimeArgs, string $input, string $expected): void
    {
        $ext = new IntlExtension(
            (new \IntlDateFormatter(
                'es', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL,
                    new \DateTimeZone('America/Puerto_Rico')
            ))
        );
        $this->twig->addExtension($ext);

        $this->twig->setLoader(new ArrayLoader(['test.twig' => "{{ inputDateTime|format_datetime($formatDateTimeArgs) }}"]));
        self::assertStringContainsString($expected, $this->twig->render('test.twig', ['inputDateTime' => $input]));
    }

    /**
     * @dataProvider getDateTimePrettyData
     *
     * @param \DateTimeInterface|string|null  $input     A date or null to use the current time
     */
    public function testIntlDateFormatterDateTimePretty(\DateTimeImmutable $now, $input, string $expected): void
    {
        $ext = new IntlExtension(
            (new \IntlDateFormatter(
                'en', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL,
                new \DateTimeZone('UTC')
            )),
            null,
            IntlExtension::getDefaultPrettyFormatClosure()
        );
        $this->twig->addExtension($ext);

        $this->twig->setLoader(new ArrayLoader(['test.twig' => "{{ inputDateTime|format_datetime_pretty() }}"]));
        self::assertStringContainsString($expected, $this->twig->render('test.twig', ['inputDateTime' => $input]));
    }

    public function getIntlDateFormatterData(): array {
        // starting timezone is UTC
        // [targetLocale, targetTimezone, dateFormat, timeFormat, input datetime, expected output datetime]
        return [
            ['en', 'America/New_York', 'full', 'full', '2020-02-22 13:37:30', 'Saturday, February 22, 2020 at 8:37:30 AM Eastern Standard Time'],
            ['fr', 'Europe/Paris',     'full', 'full', '2020-02-22 13:37:30', 'samedi 22 février 2020 à 14:37:30 heure normale d’Europe centrale'],
            ['de', 'Australia/Sydney', 'full', 'full', '2020-02-22 13:37:30', 'Sonntag, 23. Februar 2020 um 00:37:30 Ostaustralische Sommerzeit'], // locale and timezone should be independent
            ['nl', 'Pacific/Honolulu', 'full', 'full', '2020-02-22 13:37:30', 'zaterdag 22 februari 2020 om 03:37:30 Hawaii-Aleoetische standaardtijd'],  // locale and timezone should be independent

            ['en', 'Europe/Amsterdam', 'short', 'none',  '2020-02-22 13:37:30', '2/22/20'],
            ['de', 'Europe/Amsterdam', 'none',  'short', '2020-02-22 13:37:30', '14:37'],
            ['fr', 'Europe/Amsterdam', 'short', 'short', '2020-02-22 13:37:30', '22/02/2020 14:37'],

            ['en', 'Europe/Amsterdam', 'medium', 'none',   '2020-02-22 13:37:30', 'Feb 22, 2020'],
            ['de', 'Europe/Amsterdam', 'none',   'medium', '2020-02-22 13:37:30', '14:37:30'],
            ['fr', 'Europe/Amsterdam', 'medium', 'medium', '2020-02-22 13:37:30', '22 févr. 2020, 14:37:30'],

            ['en', 'Europe/Amsterdam', 'long', 'none', '2020-02-22 13:37:30', 'February 22, 2020'],
            ['de', 'Europe/Amsterdam', 'none', 'long', '2020-02-22 13:37:30', '14:37:30 MEZ'],
            ['fr', 'Europe/Amsterdam', 'long', 'long', '2020-02-22 13:37:30', '22 février 2020 à 14:37:30 UTC+1'],

            ['en', 'Europe/Amsterdam', 'full', 'none', '2020-02-22 13:37:30', 'Saturday, February 22, 2020'],
            ['de', 'Europe/Amsterdam', 'none', 'full', '2020-02-22 13:37:30', '14:37:30 Mitteleuropäische Normalzeit'],
            ['fr', 'Europe/Amsterdam', 'none', 'none', '2020-02-22 13:37:30', '20200222 02:37 PM'],

            ['en', 'Europe/Amsterdam', 'short',  'medium', '2020-02-22 13:37:30', '2/22/20, 2:37:30 PM'],
            ['de', 'Europe/Amsterdam', 'short',  'long',   '2020-02-22 13:37:30', '22.02.20, 14:37:30 MEZ'],
            ['fr', 'Europe/Amsterdam', 'short',  'full',   '2020-02-22 13:37:30', '22/02/2020 14:37:30 heure normale d’Europe centrale'],
            ['en', 'Europe/Amsterdam', 'medium', 'short',  '2020-02-22 13:37:30', 'Feb 22, 2020, 2:37 PM'],
            ['de', 'Europe/Amsterdam', 'medium', 'long',   '2020-02-22 13:37:30', '22.02.2020, 14:37:30 MEZ'],
            ['fr', 'Europe/Amsterdam', 'medium', 'full',   '2020-02-22 13:37:30', '22 févr. 2020, 14:37:30 heure normale d’Europe centrale'],
            ['en', 'Europe/Amsterdam', 'long',   'short',  '2020-02-22 13:37:30', 'February 22, 2020 at 2:37 PM'],
            ['de', 'Europe/Amsterdam', 'long',   'medium', '2020-02-22 13:37:30', '22. Februar 2020 um 14:37:30'],
            ['fr', 'Europe/Amsterdam', 'full',   'short',  '2020-02-22 13:37:30', 'samedi 22 février 2020 à 14:37'],
            ['en', 'Europe/Amsterdam', 'full',   'medium', '2020-02-22 13:37:30', 'Saturday, February 22, 2020 at 2:37:30 PM'],
        ];
    }

    public function getIntlDateFormatterOverrideData(): array {
        // [format_datetime function arguments, input, expected]
        return [
            ["'medium', locale='en', timezone='Europe/Amsterdam'", '2020-02-22 13:37:30', 'Feb 22, 2020, 2:37:30 PM Central European Standard Time'],
            ["'medium', null, locale='en', timezone='Europe/Amsterdam'", '2020-02-22 13:37:30', 'Feb 22, 2020, 2:37:30 PM Central European Standard Time'],
            ["dateFormat='medium', timeFormat=null", '2020-02-22 13:37:30', '22 feb 2020 9:37:30 (hora estándar del Atlántico)'],
            ["null, 'medium', locale='en', timezone='Europe/Amsterdam'", '2020-02-22 13:37:30', 'Saturday, February 22, 2020 at 2:37:30 PM'],
            ["dateFormat=null, timeFormat='medium', locale='en', timezone='Europe/Amsterdam'", '2020-02-22 13:37:30', 'Saturday, February 22, 2020 at 2:37:30 PM'],
            ["dateFormat=null, timeFormat='medium'", '2020-02-22 13:37:30', 'sábado, 22 de febrero de 2020, 9:37:30'],
            ["locale='en', timezone='Europe/Amsterdam'", '2020-02-22 13:37:30', 'Saturday, February 22, 2020 at 2:37:30 PM Central European Standard Time']
        ];
    }

    public function getDateTimePrettyData(): array {
        $now = (new \DateTimeImmutable())->setTime(13, 37, 00);

        // description -> [now, input, expected]
        return [
            'convert string datetime'   => [$now, $now->format('Y-m-d H:i:s'), '1:37 PM'],
            'future next day'           => [$now, $now->modify('+1 day')->format('Y-m-d H:i:s'), $now->modify('+1 day')->format('Y-m-d')],
            'far future'                => [$now, $now->modify('+1 month')->format('Y-m-d H:i:s'), $now->modify('+1 month')->format('Y-m-d')],
            'today past time'           => [$now, $now->modify('-1 hour')->format('Y-m-d H:i:s'), '12:37 PM'],
            'today no time'             => [$now, $now->format('Y-m-d'), 'today'],
            'today future time'         => [$now, $now->modify('+1 hour')->format('Y-m-d H:i:s'), '2:37 PM'],
            'yesterday no time'         => [$now, $now->modify('-1 day')->format('Y-m-d'), 'yesterday'],
            'this week'                 => [$now, $now->modify('-3 days')->format('Y-m-d'), $now->modify('-3 days')->format('D')],
            'older than past week'      => [$now, $now->modify('-14 days')->format('Y-m-d'), $now->modify('-14 days')->format('Y-m-d')],
            'handle datetime obj'       => [$now, $now, '1:37 PM'],
            'obj future next day '      => [$now, $now->modify('+1 day'), $now->modify('+1 day')->format('Y-m-d')],
            'obj today past time'       => [$now, $now->modify('-2 hours'), '11:37 AM'],
            'obj yesterday'             => [$now, $now->modify('-1 day'), 'yesterday 1:37 PM'],
            'obj this week'             => [$now, $now->modify('-3 days'),  $now->modify('-3 days')->format('D')],
            'obj older than past week'  => [$now, $now->modify('-14 days'), $now->modify('-14 days')->format('Y-m-d')],
        ];
    }
}
