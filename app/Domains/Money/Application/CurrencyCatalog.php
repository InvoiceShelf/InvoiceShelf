<?php

namespace App\Domains\Money\Application;

/**
 * Every currency an installation knows about.
 *
 * This list used to live inline in `CurrenciesTableSeeder`, in the order
 * currencies happened to be contributed, which made it impossible to see at a
 * glance what was missing. 78 circulating currencies were, the Hungarian
 * Forint and the South Korean Won among them. It also meant a new currency
 * reached an existing installation only through a migration, so the 2.x line
 * accumulated one migration per currency added.
 *
 * Both problems go away by making this the single source and sorting it by
 * code. Adding a currency is one line here; {@see CurrencyService::sync()}
 * carries it to fresh and existing installations alike.
 *
 * The set is ISO 4217's circulating currencies. Fund, bond, precious metal
 * and test codes (`XAU`, `XDR`, `XTS`, `XXX` and the rest) are deliberately
 * absent: nobody invoices in them.
 *
 * `HRK` and `ANG` are kept although ISO has retired both -- Croatia adopted
 * the euro on 2023-01-01, and `XCG` replaced `ANG` on 2025-04-01. Records
 * created before those dates still point at them, and dropping the rows would
 * leave a fresh installation unable to read an upgraded one's invoices.
 *
 * `precision` is the currency's ISO minor unit, which is 0 for the yen and the
 * CFA francs and 3 for the Gulf dinars, not the 2 an amount column assumes.
 */
class CurrencyCatalog
{
    /**
     * Sorted by code, which is the only order in which a gap is visible.
     *
     * @var list<array{code: string, name: string, symbol: string, precision: int, thousand_separator: string, decimal_separator: string, swap_currency_symbol: bool}>
     */
    private const CURRENCIES = [
        ['code' => 'AED', 'name' => 'United Arab Emirates Dirham', 'symbol' => 'DH ', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'AFN', 'name' => 'Afghan Afghani', 'symbol' => '؋', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'ALL', 'name' => 'Albanian Lek', 'symbol' => 'L', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'AMD', 'name' => 'Armenian Dram', 'symbol' => '֏', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => true],
        ['code' => 'ANG', 'name' => 'Netherlands Antillean Guilder', 'symbol' => 'NAƒ', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'AOA', 'name' => 'Angolan Kwanza', 'symbol' => 'Kz', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'ARS', 'name' => 'Argentine Peso', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'AWG', 'name' => 'Aruban Florin', 'symbol' => 'Afl. ', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'AZN', 'name' => 'Azerbaijani Manat', 'symbol' => '₼', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'BAM', 'name' => 'Bosnia and Herzegovina Convertible Mark', 'symbol' => 'KM', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BBD', 'name' => 'Barbadian Dollar', 'symbol' => 'Bds$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => 'Tk', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BGN', 'name' => 'Bulgarian Lev', 'symbol' => 'Лв.', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => 'BD', 'precision' => 3, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BIF', 'name' => 'Burundian Franc', 'symbol' => 'FBu', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BMD', 'name' => 'Bermudian Dollar', 'symbol' => 'BD$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BND', 'name' => 'Brunei Dollar', 'symbol' => 'B$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BOB', 'name' => 'Bolivian Boliviano', 'symbol' => 'Bs.', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'BSD', 'name' => 'Bahamian Dollar', 'symbol' => 'B$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BTN', 'name' => 'Bhutanese Ngultrum', 'symbol' => 'Nu.', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BWP', 'name' => 'Botswana Pula', 'symbol' => 'P', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'BYN', 'name' => 'Belarusian Ruble', 'symbol' => 'Br', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'BZD', 'name' => 'Belize Dollar', 'symbol' => 'BZ$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'CDF', 'name' => 'Congolese Franc', 'symbol' => 'FC', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'Fr.', 'precision' => 2, 'thousand_separator' => '\'', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'CLP', 'name' => 'Chilean Peso', 'symbol' => '$', 'precision' => 0, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'CNY', 'name' => 'Chinese Renminbi', 'symbol' => 'RMB ', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'COP', 'name' => 'Colombian Peso', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'CRC', 'name' => 'Costa Rican Colón', 'symbol' => '₡', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'CUP', 'name' => 'Cuban Peso', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'CVE', 'name' => 'Cape Verdean Escudo', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'CZK', 'name' => 'Czech Koruna', 'symbol' => 'Kč', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'DJF', 'name' => 'Djiboutian Franc', 'symbol' => 'Fdj', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'DOP', 'name' => 'Dominican Peso', 'symbol' => 'RD$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'DZD', 'name' => 'Algerian Dinar', 'symbol' => 'DA', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'E£', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'ERN', 'name' => 'Eritrean Nakfa', 'symbol' => 'Nfk', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'ETB', 'name' => 'Ethiopian Birr', 'symbol' => 'Br', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'FJD', 'name' => 'Fijian Dollar', 'symbol' => 'FJ$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'FKP', 'name' => 'Falkland Islands Pound', 'symbol' => '£', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'GEL', 'name' => 'Georgian Lari', 'symbol' => '₾', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => true],
        ['code' => 'GHS', 'name' => 'Ghanaian Cedi', 'symbol' => '‎GH₵', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'GIP', 'name' => 'Gibraltar Pound', 'symbol' => '£', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'GMD', 'name' => 'Gambian Dalasi', 'symbol' => 'D', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'GNF', 'name' => 'Guinean Franc', 'symbol' => 'FG', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'GTQ', 'name' => 'Guatemalan Quetzal', 'symbol' => 'Q', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'GYD', 'name' => 'Guyanese Dollar', 'symbol' => 'G$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'HNL', 'name' => 'Honduran Lempira', 'symbol' => 'L', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'HRK', 'name' => 'Croatian Kuna', 'symbol' => 'kn', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'HTG', 'name' => 'Haitian Gourde', 'symbol' => 'G', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'HUF', 'name' => 'Hungarian Forint', 'symbol' => 'Ft', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'ILS', 'name' => 'Israeli Shekel', 'symbol' => 'NIS ', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'IQD', 'name' => 'Iraqi Dinar', 'symbol' => 'ع.د', 'precision' => 3, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'IRR', 'name' => 'Iranian Rial', 'symbol' => '﷼', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'ISK', 'name' => 'Icelandic Króna', 'symbol' => 'kr', 'precision' => 0, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'JMD', 'name' => 'Jamaican Dollar', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'JOD', 'name' => 'Jordanian Dinar', 'symbol' => 'JD', 'precision' => 3, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh ', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'KGS', 'name' => 'Kyrgyzstani som', 'symbol' => 'С̲ ', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'KHR', 'name' => 'Cambodian Riel', 'symbol' => '៛', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'KMF', 'name' => 'Comorian Franc', 'symbol' => 'CF', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'KPW', 'name' => 'North Korean Won', 'symbol' => '₩', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'KWD ', 'precision' => 3, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'KYD', 'name' => 'Cayman Islands Dollar', 'symbol' => 'CI$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'KZT', 'name' => 'Kazakhstani Tenge', 'symbol' => '₸', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'LAK', 'name' => 'Lao Kip', 'symbol' => '₭', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'LBP', 'name' => 'Lebanese Pound', 'symbol' => 'L£', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'symbol' => 'LKR', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => true],
        ['code' => 'LRD', 'name' => 'Liberian Dollar', 'symbol' => 'L$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'LSL', 'name' => 'Lesotho Loti', 'symbol' => 'L', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'LYD', 'name' => 'Libyan Dinar', 'symbol' => 'LD', 'precision' => 3, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MAD', 'name' => 'Moroccan Dirham', 'symbol' => 'DH', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MDL', 'name' => 'Moldovan Leu', 'symbol' => 'L', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => true],
        ['code' => 'MGA', 'name' => 'Malagasy Ariary', 'symbol' => 'Ar', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'MKD', 'name' => 'Macedonian Denar', 'symbol' => 'ден', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'MMK', 'name' => 'Myanmar Kyat', 'symbol' => 'K', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MNT', 'name' => 'Mongolian Tugrik', 'symbol' => '₮', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MOP', 'name' => 'Macanese Pataca', 'symbol' => 'MOP$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MRU', 'name' => 'Mauritanian Ouguiya', 'symbol' => 'UM', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => true],
        ['code' => 'MUR', 'name' => 'Mauritian Rupee', 'symbol' => '₨', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MVR', 'name' => 'Maldivian Rufiyaa', 'symbol' => 'Rf', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MWK', 'name' => 'Malawian Kwacha', 'symbol' => 'MK', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'MZN', 'name' => 'Mozambican Metical', 'symbol' => 'MT', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'NAD', 'name' => 'Namibian Dollar', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'NIO', 'name' => 'Nicaraguan Córdoba', 'symbol' => 'C$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'NOK', 'name' => 'Norske Kroner', 'symbol' => 'kr', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'NPR', 'name' => 'Nepali Rupee', 'symbol' => 'रू', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => 'ر.ع.', 'precision' => 3, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'PAB', 'name' => 'Panamanian Balboa', 'symbol' => 'B/.', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'PEN', 'name' => 'Peruvian Soles', 'symbol' => 'S/', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'PGK', 'name' => 'Papua New Guinean Kina', 'symbol' => 'K', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => 'P ', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs ', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'PLN', 'name' => 'Polish Zloty', 'symbol' => 'zł', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'PYG', 'name' => 'Paraguayan Guaraní', 'symbol' => '₲', 'precision' => 0, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'QR', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'RON', 'name' => 'Romanian New Leu', 'symbol' => 'RON', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'RSD', 'name' => 'Serbian Dinar', 'symbol' => 'RSD', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => '₽', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'RWF', 'name' => 'Rwandan Franc', 'symbol' => 'RF ', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '‎SِAR', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SBD', 'name' => 'Solomon Islands Dollar', 'symbol' => 'SI$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SCR', 'name' => 'Seychellois Rupee', 'symbol' => '₨', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SDG', 'name' => 'Sudanese Pound', 'symbol' => 'SDG', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SHP', 'name' => 'Saint Helena Pound', 'symbol' => '£', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SLE', 'name' => 'Sierra Leonean Leone', 'symbol' => 'Le', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SOS', 'name' => 'Somali Shilling', 'symbol' => 'Sh.So.', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SRD', 'name' => 'Surinamese Dollar', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'SSP', 'name' => 'South Sudanese Pound', 'symbol' => 'SSP', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'STN', 'name' => 'São Tomé and Príncipe Dobra', 'symbol' => 'Db', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'SVC', 'name' => 'Salvadoran Colón', 'symbol' => '₡', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SYP', 'name' => 'Syrian Pound', 'symbol' => 'LS', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'SZL', 'name' => 'Swazi Lilangeni', 'symbol' => 'L', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'TJS', 'name' => 'Tajikistani Somoni', 'symbol' => 'SM', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'TMT', 'name' => 'Turkmenistani manat', 'symbol' => 'M ', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'TND', 'name' => 'Tunisian Dinar', 'symbol' => 'DT', 'precision' => 3, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'TOP', 'name' => 'Tongan Paʻanga', 'symbol' => 'T$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => 'TL ', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'TTD', 'name' => 'Trinidad and Tobago Dollar', 'symbol' => 'TT$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'TWD', 'name' => 'Taiwan New Dollar', 'symbol' => 'NT$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'TZS', 'name' => 'Tanzanian Shilling', 'symbol' => 'TSh ', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'UAH', 'name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'UGX', 'name' => 'Ugandan Shilling', 'symbol' => 'USh', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'UYU', 'name' => 'Uruguayan Peso', 'symbol' => '$', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'UZS', 'name' => 'Uzbekistani Som', 'symbol' => 'so\'m', 'precision' => 2, 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
        ['code' => 'VES', 'name' => 'Venezuelan Bolívar', 'symbol' => 'Bs.', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'precision' => 0, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'VUV', 'name' => 'Vanuatu Vatu', 'symbol' => 'VT', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'WST', 'name' => 'Samoan Tala', 'symbol' => 'WS$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'XAF', 'name' => 'Central African Franc', 'symbol' => 'CFA ', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'XCD', 'name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'XCG', 'name' => 'Caribbean Guilder', 'symbol' => 'Cg', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'XOF', 'name' => 'West African Franc', 'symbol' => 'CFA ', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'XPF', 'name' => 'CFP Franc', 'symbol' => '₣', 'precision' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'YER', 'name' => 'Yemeni Rial', 'symbol' => '﷼', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false],
        ['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'ZK', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
        ['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'symbol' => 'ZiG', 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false],
    ];

    /**
     * @return list<array{code: string, name: string, symbol: string, precision: int, thousand_separator: string, decimal_separator: string, swap_currency_symbol: bool}>
     */
    public function all(): array
    {
        return self::CURRENCIES;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_column(self::CURRENCIES, 'code');
    }
}
