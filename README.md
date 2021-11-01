Twig Intl Extension
===================

This package is a Twig extension that provides the following:

 * [`country_name`][1] filter: returns the country name given its two-letter/five-letter code;
 * [`currency_name`][2] filter: returns the currency name given its three-letter code;
 * [`currency_symbol`][3] filter: returns the currency symbol given its three-letter code;
 * [`language_name`][4] filter: returns the language name given its two-letter/five-letter code;
 * [`locale_name`][5] filter: returns the language name given its two-letter/five-letter code;
 * [`timezone_name`][6] filter: returns the timezone name given its identifier;
 * [`country_timezones`][7] filter: returns the timezone identifiers of the given country code;
 * [`format_currency`][8] filter: formats a number as a currency;
 * [`format_number`][9] filter: formats a number;
 * [`format_datetime`][10] filter: formats a date time;
 * [`format_date`][11] filter: formats a date;
 * [`format_time`][12] filter: formats a time.
 * [`format_datetime_pretty`](#Using pretty formatting) filter: formats a date to time of day, 'Today', 'Yesterday', or a date.
 * [`format_date_pretty`](#Using pretty formatting) filter: formats a date to 'Today', 'Yesterday', or a date.

[1]: https://twig.symfony.com/country_name
[2]: https://twig.symfony.com/currency_name
[3]: https://twig.symfony.com/currency_symbol
[4]: https://twig.symfony.com/language_name
[5]: https://twig.symfony.com/locale_name
[6]: https://twig.symfony.com/timezone_name
[7]: https://twig.symfony.com/country_timezones
[8]: https://twig.symfony.com/format_currency
[9]: https://twig.symfony.com/format_number
[10]: https://twig.symfony.com/format_datetime
[11]: https://twig.symfony.com/format_date
[12]: https://twig.symfony.com/format_time

## AlisQI fork

This repository was forked from `twigphp/intl-extra` in order to make `format_date`, `format_time` and `format_datetime`
use the 'prototype' values of `IntlDateFormatter` when `IntlExtension` is instantiated with a custom formatter.

See: [https://github.com/twigphp/Twig/issues/3568](https://github.com/twigphp/Twig/issues/3568)

### Using pretty formatting

In order to use the `format_datetime_pretty` and `format_date_pretty` filters, you must supply the `IntlExtension` 
constructor with a `Closure` that returns the 'pretty' formatted date. This way you can apply your opinionated format and any custom
translations if your application is not yet on PHP8.

Here is an example Closure that you can use:

```php
/**
 * Same day                 "today at 1:37 PM"
 * Yesterday                "yesterday at 1:37 PM"
 * Within the last 7 days   "Monday", "Tuesday", etc
 * Older than 7 days        $dateFormat default
 * In the future            $dateFormat default
 *
 * If the date somehow cannot be resolved otherwise, will return default formatting for IntlDateFormatter argument.
 */
$closure = function (\DateTimeInterface $dateTime, \IntlDateFormatter $formatter) 
{
    $date = new \DateTimeImmutable($dateTime->format('Y-m-d'));
    $now = new \DateTimeImmutable('today');
    $daysAgo = (int)$date->diff($now)->format('%r%a');

    // past week: "Thursday"
    if ($daysAgo > 1 && $daysAgo < 7) {
        return (new \IntlDateFormatter(
            $formatter->getLocale(), self::DATE_FORMATS['none'], self::DATE_FORMATS['none'],
            $formatter->getTimeZone(), $formatter->getCalendar(), 'EEEE'
        ))->format($dateTime);
    }

    // today or yesterday with time
    // This will return date format 'short' if PHP version is < 8.
    if ($daysAgo === 0 || $daysAgo === 1) {
        return (new \IntlDateFormatter(
            $formatter->getLocale(), self::DATE_FORMATS['relative_short'], self::DATE_FORMATS['short'],
            $formatter->getTimeZone(), $formatter->getCalendar(), ''
        ))->format($dateTime);
    }

    // default formatting without time
    return (new \IntlDateFormatter(
        $formatter->getLocale(), $formatter->getDateType(), self::DATE_FORMATS['none'],
        $formatter->getTimeZone(), $formatter->getCalendar(), $formatter->getPattern()
    ))->format($dateTime);
};

// usage
$intlExtension = new IntlExtension(
    new IntlExtension(
        new \IntlDateFormatter(
            'nl',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            new \DateTimeZone('Europe/Amsterdam')
        ),
        new \NumberFormatter('nl', \NumberFormatter::DECIMAL),
        $closure
    );
);
```
