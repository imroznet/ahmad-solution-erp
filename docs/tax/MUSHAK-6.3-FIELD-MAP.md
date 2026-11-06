# Mushak 6.3 field map

Layout authority: the owner-confirmed file in `docs/tax/SOURCE-REGISTER.md`, source 1, checked 6 November 2026.

SHA-256 `c09e853829e19a6265d82b53b0f4408c9db95ca8793fa1666cec39c6870cf5a2`.

The PDF is one A4 page. The emblem in the header is not copied into this repository. The printed document uses the authority name as text. Bengali labels below are the labels read from that render. English keys are application names for tests and for the PDF engine, which does not shape Bengali conjuncts reliably. The English line is an aid. It is not a claim that the official form is bilingual.

This map does not add fields that were not on the form, except the ERP reference block, which is printed under a separate heading and is not part of the form grid.

VAT behaviour on every money and rate field: the user types it. Blank stays blank. Zero is stored only when the user types zero. Nothing multiplies a percentage by a value. A total is stored only when the user types it, and it is rejected when it does not equal the lines the user also typed.

## Header

| English key | Label read from the form | Source | Issue rule |
| --- | --- | --- | --- |
| `form_authority` | গণপ্রজাতন্ত্রী বাংলাদেশ সরকার / জাতীয় রাজস্ব বোর্ড | Fixed text from the form | Printed |
| `form_code` | মূসক-৬.৩ | Fixed text from the form | Printed |
| `form_title` | কর চালানপত্র | Fixed text from the form | Printed |
| `legal_citation` | [ বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য ] | Visual reading. See the source register. | Printed. Not a compliance claim. |

## Seller

Taken from the company profile. Snapshotted at issue. A later profile edit does not change the snapshot.

| English key | Label read from the form | Company profile | Issue rule |
| --- | --- | --- | --- |
| `seller_name` | নিবন্ধিত ব্যক্তির নাম | `company_profiles.legal_name` | Required. The profile always has a legal name. |
| `seller_bin` | নিবন্ধিত ব্যক্তির বিআইএন | `company_profiles.bin` | Required. Issue is blocked when it is blank. The message names the company profile. No BIN is invented. |
| `seller_issue_address` | চালানপত্র ইস্যুর ঠিকানা | Registered office, or the corporate office when the registered office is absent | Required. Issue is blocked when neither address exists. |
| `seller_contact` | Not a separate form label | Profile email and phone | Printed under the address because the owner required contact details. Omitted when blank. |

## Buyer and supply

The user either selects an active customer party or types a one-off buyer. A typed buyer is not saved as a party. The printed buyer block is frozen at issue.

| English key | Label read from the form | Where it is stored | Issue rule |
| --- | --- | --- | --- |
| `buyer_name` | ক্রেতার নাম | Party legal name, or the typed name | Required. Party details replace a typed name when the source is the party. |
| `buyer_bin` | ক্রেতার বিআইএন | Party BIN, or the typed BIN | Optional. The form has the label. A missing buyer BIN is not invented and does not block issue. Whether the law requires it for this supply is an open question. |
| `supply_destination` | সরবরাহের গন্তব্যস্থল | Typed on the document | Required. Not copied from the party address. |
| `challan_number` | চালানপত্র নম্বর | Allocated at issue as `M63-{Asia/Dhaka year}-{sequence}` | Blank on a draft. This is not the commercial invoice number. |
| `issue_date` | ইস্যুর তারিখ | Typed, or the Dhaka date at issue when blank | Frozen at issue. |
| `issue_time` | ইস্যুর সময় | Typed as `HH:MM`, or the Dhaka time at issue when blank | Frozen at issue. |

ERP snapshot fields that are not form labels: buyer TIN, buyer address, buyer phone, and buyer email. They are kept on the document and shown on the screen. They are not printed inside the form grid. The commercial invoice's customer text is stored separately when the Mushak is linked to an invoice, including when the printed buyer was typed by hand.

## Lines

The official page shows a blank grid, not a legal maximum. The application accepts up to 30 lines. That cap is not a statutory limit.

The footnote under the grid, read from the form, is `১ সকল প্রকার কর ব্যতীত মূল্য`. It is mapped to column 6. The superscript's exact cell was small on the render. The footnote sentence itself was legible, and it defines the value column as value excluding all taxes.

| Column | English key | Label read from the form | Entry |
| --- | --- | --- | --- |
| 1 | `line.serial` | ক্রমিক | Row order |
| 2 | `line.description` | পণ্য বা সেবার বর্ণনা (প্রযোজ্য ক্ষেত্রে ব্র্যান্ড নামসহ) | Typed. Required on issue. Hand-typed, or started from a commercial invoice line description. |
| 3 | `line.unit` | সরবরাহের একক | Typed. Required on issue. |
| 4 | `line.quantity` | পরিমাণ | Typed. Up to 4 decimal places. Required on issue. |
| 5 | `line.unit_price` | একক মূল্য (টাকায়) | Typed. 2 decimal places. Required on issue. |
| 6 | `line.line_value` | মোট মূল্য (টাকায়) | Typed. Required on issue. When quantity, unit price, and this value are all present, the value must equal quantity times unit price, rounded half away from zero to 2 decimal places. A missing value is not filled in. |
| 7 | `line.supplementary_duty` | সম্পূরক শুল্কের পরিমাণ (টাকায়) | Typed. Required on issue, including an explicit zero. Not calculated. |
| 8 | `line.vat_rate` | মূল্য সংযোজন করের হার/সুনির্দিষ্ট কর | Percentage box starts empty. Optional on issue. Stored only when typed, with the user and time. A specific-tax note can be typed beside it. The percentage is not a statutory determination. |
| 9 | `line.vat_amount` | মূল্য সংযোজন কর/সুনির্দিষ্ট কর এর পরিমাণ (টাকায়) | Typed. Required on issue, including an explicit zero. Not calculated from column 8. A percentage of 15 and an amount of 1.00 are stored as entered. |
| 10 | `line.inclusive_value` | সকল প্রকার শুল্ক ও করসহ মূল্য | Typed. Required on issue. When columns 6, 7, 9, and 10 are all present, column 10 must equal column 6 plus column 7 plus column 9. It is not filled in from that sum. |

`সর্বমোট` is the total row. The user types the totals for columns 6, 7, 9, and 10. On issue each total must equal the sum of the typed line amounts. Quantity is not totalled.

## Closing block

| English key | Label read from the form | Issue rule |
| --- | --- | --- |
| `responsible_name` | প্রতিষ্ঠান কর্তৃপক্ষের দায়িত্বপ্রাপ্ত ব্যক্তির নাম | Required |
| `responsible_designation` | পদবী | Required |
| `signature` | স্বাক্ষর | A blank line. The ERP does not apply a digital signature. |
| `seal` | সীল | A blank line. |

The asterisk note read from the form is printed in full:

`* উৎসে কর্তনযোগ্য সরবরাহের ক্ষেত্রে ফরমটি সমন্বিত কর চালানপত্র ও উৎসে কর কর্তন সনদপত্র হিসেবে বিবেচিত হইবে এবং উহা উৎসে কর কর্তনযোগ্য সরবরাহের ক্ষেত্রে প্রযোজ্য হবে।`

The ERP does not decide that a supply is subject to deduction at source. It does not create a VDS record from this note. VDS stays a separate record and is not added to column 9.

## Copies

The note says the original accompanies the goods to the buyer and the duplicate stays with the supplier, for a supply where the note applies. The PDF can be rendered as `original` or `duplicate`. That label is an ERP print choice. It does not create a second document.

## ERP reference block

Printed below the form, under the heading `ERP reference`, so it is not presented as part of the NBR grid.

- Linked commercial invoice number, quotation number, and delivery challan numbers, when the document is linked.
- Tax review state: `review required` or `treatment recorded`.
- The manual-VAT statement: the percentage and the VAT amount were entered by a user, and the ERP did not determine the legal rate.
- Source file URL and the date checked, plus the statement that SRO No-125-Ain/2026/330-Mushak's effect on this layout was not verified from the gazette text.

Internal notes are not printed.

## What issue freezes

Company snapshot, buyer snapshot, commercial-customer snapshot, source references, lines, totals, the manual-VAT statement, the number, and the issue date and time. A later tax-rule amendment does not change them. Void and a treatment record are separate events. Void does not edit the grid and does not create a statutory credit note. A treatment record does not edit the grid.

## Link to a commercial invoice

Optional. The commercial invoice must already be issued. Its lines can prefill description, unit, quantity, unit price, and the commercial line total into column 6, because that invoice stored tax as excluded. Columns 7, 8, 9, and 10 stay blank. The commercial invoice itself is not converted into this document.
