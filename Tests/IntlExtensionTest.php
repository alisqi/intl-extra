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

    public function setUp(): void {
        $config = ['cache' => false,'strict_variables' => true];
        $loader = new ArrayLoader([]);
        $this->twig = new Environment($loader, $config);
        $this->twig->getExtension(CoreExtension::class)->setTimeZone('UTC');
    }

    public function testFormatterProto()
    {
        $dateFormatterProto = new \IntlDateFormatter('fr', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
        $numberFormatterProto = new \NumberFormatter('fr', \NumberFormatter::DECIMAL);
        $numberFormatterProto->setTextAttribute(\NumberFormatter::POSITIVE_PREFIX, '++');
        $numberFormatterProto->setAttribute(\NumberFormatter::FRACTION_DIGITS, 1);
        $ext = new IntlExtension($dateFormatterProto, $numberFormatterProto);
        $this->assertSame('++12,3', $ext->formatNumber('12.3456'));
    }

    /**
     * @dataProvider getTestData
     */
    public function testIntlDateFormatter(string $targetLocale, string $targetTimezone, string $input, string $expected) {
        $ext = new IntlExtension(
            (new \IntlDateFormatter(
                $targetLocale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL,
                new \DateTimeZone($targetTimezone)
            ))
        );

        self::assertEquals($expected, $ext->formatDateTime($this->twig, $input));

        $this->twig->setLoader(new ArrayLoader(['test.twig' => "{{ inputDateTime|format_datetime() }}"]));
        $this->twig->addExtension($ext);
        $rendered = $this->twig->render('test.twig', ['inputDateTime' => $input]);
        self::assertStringContainsString($expected, $rendered);
    }

    public function getTestData(): array {
        // starting timezone is UTC
        // [targetLocale, targetTimezone, input datetime, expected output datetime]
        return [
            ['en', 'America/New_York', '2020-02-22 13:37:30',  'Saturday, February 22, 2020 at 8:37:30 AM Eastern Standard Time'        ],
            ['fr', 'Europe/Paris',     '2020-02-22 13:37:30',  'samedi 22 février 2020 à 14:37:30 heure normale d’Europe centrale'      ],
            ['de', 'Australia/Sydney', '2020-02-22 13:37:30',  'Sonntag, 23. Februar 2020 um 00:37:30 Ostaustralische Sommerzeit'       ], // locale and timezone should be independent
            ['nl', 'Pacific/Honolulu', '2020-02-22 13:37:30',  'zaterdag 22 februari 2020 om 03:37:30 Hawaii-Aleoetische standaardtijd' ],
        ];
    }
}
