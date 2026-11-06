<?php

namespace App\Tax;

/**
 * Labels read from the owner-confirmed Mushak 6.3 file on 6 November 2026.
 * The field map in docs/tax/MUSHAK-6.3-FIELD-MAP.md is the narrative form of this list.
 */
final class MushakFieldMap
{
    public const SOURCE_URL = 'https://dknvat.gov.bd/assets/document/form/form_175068235417Xz9DmBrT9uc6V.pdf';

    public const SOURCE_SHA256 = 'c09e853829e19a6265d82b53b0f4408c9db95ca8793fa1666cec39c6870cf5a2';

    public const FORM_AUTHORITY = 'গণপ্রজাতন্ত্রী বাংলাদেশ সরকার';

    public const FORM_BOARD = 'জাতীয় রাজস্ব বোর্ড';

    public const FORM_CODE = 'মূসক-৬.৩';

    public const FORM_TITLE = 'কর চালানপত্র';

    public const LEGAL_CITATION = '[ বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য ]';

    public const SELLER_NAME = 'নিবন্ধিত ব্যক্তির নাম';

    public const SELLER_BIN = 'নিবন্ধিত ব্যক্তির বিআইএন';

    public const SELLER_ADDRESS = 'চালানপত্র ইস্যুর ঠিকানা';

    public const BUYER_NAME = 'ক্রেতার নাম';

    public const BUYER_BIN = 'ক্রেতার বিআইএন';

    public const DESTINATION = 'সরবরাহের গন্তব্যস্থল';

    public const CHALLAN_NUMBER = 'চালানপত্র নম্বর';

    public const ISSUE_DATE = 'ইস্যুর তারিখ';

    public const ISSUE_TIME = 'ইস্যুর সময়';

    public const COLUMN_SERIAL = 'ক্রমিক';

    public const COLUMN_DESCRIPTION = 'পণ্য বা সেবার বর্ণনা (প্রযোজ্য ক্ষেত্রে ব্র্যান্ড নামসহ)';

    public const COLUMN_UNIT = 'সরবরাহের একক';

    public const COLUMN_QUANTITY = 'পরিমাণ';

    public const COLUMN_UNIT_PRICE = 'একক মূল্য (টাকায়)';

    public const COLUMN_LINE_VALUE = 'মোট মূল্য (টাকায়)';

    public const COLUMN_SD = 'সম্পূরক শুল্কের পরিমাণ (টাকায়)';

    public const COLUMN_VAT_RATE = 'মূল্য সংযোজন করের হার/সুনির্দিষ্ট কর';

    public const COLUMN_VAT_AMOUNT = 'মূল্য সংযোজন কর/সুনির্দিষ্ট কর এর পরিমাণ (টাকায়)';

    public const COLUMN_INCLUSIVE = 'সকল প্রকার শুল্ক ও করসহ মূল্য';

    public const TOTAL_LABEL = 'সর্বমোট';

    public const RESPONSIBLE_NAME = 'প্রতিষ্ঠান কর্তৃপক্ষের দায়িত্বপ্রাপ্ত ব্যক্তির নাম';

    public const RESPONSIBLE_DESIGNATION = 'পদবী';

    public const SIGNATURE = 'স্বাক্ষর';

    public const SEAL = 'সীল';

    public const SOURCE_DEDUCTION_NOTE = '* উৎসে কর্তনযোগ্য সরবরাহের ক্ষেত্রে ফরমটি সমন্বিত কর চালানপত্র ও উৎসে কর কর্তন সনদপত্র হিসেবে বিবেচিত হইবে এবং উহা উৎসে কর কর্তনযোগ্য সরবরাহের ক্ষেত্রে প্রযোজ্য হবে।';

    public const VALUE_FOOTNOTE = '১ সকল প্রকার কর ব্যতীত মূল্য';

    public const MANUAL_NOTICE = 'The VAT percentage and the VAT amount were entered by an authorized user. The ERP did not select, infer, or calculate a statutory rate. A blank percentage is not zero. VDS and AIT are not included in these amounts.';

    /**
     * Labels that must appear on the printed form.
     *
     * @return list<string>
     */
    public static function printedLabels(): array
    {
        return [
            self::FORM_AUTHORITY,
            self::FORM_BOARD,
            self::FORM_CODE,
            self::FORM_TITLE,
            self::LEGAL_CITATION,
            self::SELLER_NAME,
            self::SELLER_BIN,
            self::SELLER_ADDRESS,
            self::BUYER_NAME,
            self::BUYER_BIN,
            self::DESTINATION,
            self::CHALLAN_NUMBER,
            self::ISSUE_DATE,
            self::ISSUE_TIME,
            self::COLUMN_SERIAL,
            self::COLUMN_DESCRIPTION,
            self::COLUMN_UNIT,
            self::COLUMN_QUANTITY,
            self::COLUMN_UNIT_PRICE,
            self::COLUMN_LINE_VALUE,
            self::COLUMN_SD,
            self::COLUMN_VAT_RATE,
            self::COLUMN_VAT_AMOUNT,
            self::COLUMN_INCLUSIVE,
            self::TOTAL_LABEL,
            self::RESPONSIBLE_NAME,
            self::RESPONSIBLE_DESIGNATION,
            self::SIGNATURE,
            self::SEAL,
            self::SOURCE_DEDUCTION_NOTE,
            self::VALUE_FOOTNOTE,
        ];
    }
}
