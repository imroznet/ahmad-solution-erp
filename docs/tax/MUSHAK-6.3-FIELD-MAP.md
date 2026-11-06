# Mushak 6.3 field map

This map describes one file: the PDF linked as মূসক ৬.৩ from https://dknvat.gov.bd/all-forms on 6 November 2026.

- URL: https://dknvat.gov.bd/assets/document/form/form_175068235417Xz9DmBrT9uc6V.pdf
- SHA-256: `c09e853829e19a6265d82b53b0f4408c9db95ca8793fa1666cec39c6870cf5a2`
- Render inspected: one A4 page, 150 DPI PNG made with Poppler from those bytes
- The map is a field inventory of that file. It is not a claim that the file is the statutory form after 1 July 2026. See `SOURCE-REGISTER.md`.

The application does not print this layout as an issued tax invoice. A working paper may name these fields. It must say that it is not an issued Mushak 6.3.

## Header, as read from the render

| Position | What the form prints | Application meaning | Status |
| --- | --- | --- | --- |
| Top centre | গণপ্রজাতন্ত্রী বাংলাদেশ সরকার, জাতীয় রাজস্ব বোর্ড, and the government emblem | Prescribed heading. Not redrawn on the working paper | Blocked |
| Top right box | মূসক-৬.৩ | Form code | Recorded |
| Title | কর চালানপত্র | Tax invoice. A commercial invoice is a different document | Recorded |
| Citation under the title | বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) | Rule 40(1) clauses (গ) and (চ) of the VAT and SD Rules, 2016. SRO 125 amends rule 40 from 1 July 2026 and does not replace this specimen | Unverified after 1 July 2026 |

`pdftotext` on the same bytes returns broken Bangla because of the font encoding. The labels above come from reading the render, not from that extraction.

## Parties and issue block

| Form label | Likely source already in this system | Stored on a Mushak working paper |
| --- | --- | --- |
| নিবন্ধিত ব্যক্তির নাম | Company legal name, from the commercial invoice's company snapshot | Copied snapshot. A later company edit must not change it |
| নিবন্ধিত ব্যক্তির বিআইএন | Company BIN on that snapshot | Copied snapshot. The application does not check an NBR checksum |
| চালানপত্র ইস্যুর ঠিকানা | Company address lines on that snapshot | Copied snapshot |
| ক্রেতার নাম | Customer legal name on the commercial invoice | Copied snapshot |
| ক্রেতার বিআইএন | Customer BIN on the commercial invoice | Copied snapshot. Blank is allowed, because the party record allows a blank BIN |
| সরবরাহের গন্তব্যস্থল | Not a field on the commercial invoice. The challan has a delivery location | Not invented. The working paper leaves it blank and says it was not on the commercial invoice |
| চালানপত্র নম্বর | Not the commercial invoice number `CI-…` | Not assigned. Issue is blocked, so there is no Mushak number |
| ইস্যুর তারিখ | Would be the tax-invoice issue date | Not set. The commercial invoice date is shown as the linked document's date, not as a Mushak issue date |
| ইস্যুর সময় | Not stored on the commercial invoice | Not invented |

The commercial invoice number, quotation number, and challan numbers are kept beside this map so the tax document can be traced. They are not printed as if they were the চালানপত্র নম্বর.

## Line columns

The form has a ten-column line table and a সর্বমোট row. Column headings, as read:

| No. | Heading on the form | Commercial invoice has it | Working paper |
| --- | --- | --- | --- |
| 1 | ক্রমিক | Line position | Copied |
| 2 | পণ্য বা সেবার নাম (প্রযোজ্য ক্ষেত্রে ব্র্যান্ড নামসহ) | Hand-typed description. There is no product or brand catalogue | Copied description. No brand is added |
| 3 | সরবরাহের একক | Unit | Copied |
| 4 | পরিমাণ | Quantity, 4 decimal places | Copied |
| 5 | একক মূল্য (টাকা) | Unit price, 2 decimal places. Footnote 1 says this value is exclusive of all kinds of tax | Copied. The commercial invoice is already tax-exclusive |
| 6 | মোট মূল্য (টাকা) | Line total, tax-exclusive | Copied. Not recalculated with a rate |
| 7 | সম্পূরক শুল্কের পরিমাণ (টাকা) | No supplementary duty | Left empty. No supplementary-duty rule is seeded |
| 8 | মূল্য সংযোজন করের হার / সুনির্দিষ্ট কর | No rate | Left empty unless an approved output-VAT rule is pinned, and then the pinned rate is shown as a preview, not as a form entry |
| 9 | মূল্য সংযোজন কর / সুনির্দিষ্ট কর এর পরিমাণ (টাকা) | No VAT amount | Left empty. A preview, when a rule is pinned, is labelled as a preview and is not written onto the commercial invoice |
| 10 | সকল প্রকার শুল্ক ও করসহ মূল্য | Not stored | Not computed. Column 10 needs columns 7 and 9 |

The footnote under column 5 was read as "সকল প্রকার কর ব্যতীত মূল্য".

## Footer

| Form text | Treatment |
| --- | --- |
| প্রতিষ্ঠান কর্তৃপক্ষের দায়িত্বপ্রাপ্ত ব্যক্তির নাম, পদবী, স্বাক্ষর, সীল | Not captured. The working paper has no signature block that could be mistaken for an issued invoice |
| Note that, for a centrally registered person, the original tax invoice and the VAT-deduction certificate are recorded in the centrally registered person's account, and VAT deduction applies where the supply is subject to it | Not implemented as a workflow. The note describes central registration and VDS treatment that has not been confirmed for this company |
| The note does not identify Mushak 6.6 | Mushak 6.6 is not built |

## Calculation rules that are not taken from this form

The form has a rate column. It does not print a number in that column. Filling it requires an approved rule for the supply classification. See the source register. Rounding on this form is not stated. `App\Support\Money` rounds half away from zero at two decimal places, and that remains an application rule, not an NBR rule.

## What a later issue would have to freeze

If the open questions in the source register are answered and issuance is deliberately enabled in code, an issued row must store, and then refuse to edit:

- the Mushak number, issue date, and issue time
- the company, customer, quotation, challan, and commercial-invoice snapshots
- every line value above, including supplementary duty and VAT
- the tax-rule version id and a snapshot of that version's rate, basis, rounding, threshold, legal instrument, and source URL
- the calculation inputs and the results

A later amendment of the rule must not change that snapshot. A correction of an issued Mushak would be a separate document. That document is not designed here, because the legal correction path was not verified.
