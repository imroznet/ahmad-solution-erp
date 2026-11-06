# Mushak 6.3 field map

Source file: the owner-confirmed PDF recorded in `docs/tax/SOURCE-REGISTER.md`. The page was rendered and read on 6 November 2026. Bengali headings below are the readings of that page. Stacked letters in the table are easier to misread than the header, so the column count and the role of each column are the parts treated as verified. This map is not a claim that the 2019 file timestamp is a gazette effective date.

## Header

| Form label | ERP source | Notes |
| --- | --- | --- |
| গণপ্রজাতন্ত্রী বাংলাদেশ সরকার / জাতীয় রাজস্ব বোর্ড | Printed as identifying text | Not company data. |
| মুশক-৬.৩ | Document type | Distinct from a commercial invoice. |
| কর চালানপত্র | Document title | |
| বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) | Citation stored in the source register | The year of the rules is not printed on the file. |
| নিবন্ধিত ব্যক্তির নাম | Company profile legal name, snapshotted at issue | |
| নিবন্ধিত ব্যক্তির বিআইএন | Company profile BIN, snapshotted at issue | Issue is blocked when it is empty. |
| চালানপত্র ইস্যুর ঠিকানা | Registered office, or corporate office if that is the one the letterhead already uses | Issue is blocked when no address line is available. |
| ক্রেতার নাম | Invoice customer, a selected party, or a one-off name | A one-off name does not create a party. Snapshotted at issue. |
| ক্রেতার বিআইএন | Party or typed BIN | Not treated as mandatory. The form has the label and the sample row is blank. |
| সরবরাহের গন্তব্যস্থল | Typed destination | Required before save. |
| চালানপত্র নম্বর | `M63-{Asia/Dhaka year}-{sequence}`, assigned at issue | A draft has no number. |
| ইস্যুর তারিখ | Typed document date | |
| ইস্যুর সময় | Time of the issue action in Asia/Dhaka | Empty on a draft. |

## Columns

The table has ten columns and a সর্বমোট total row. Four blank sample rows are printed on the form. The ERP uses one row for each current commercial-invoice line.

| Column | Reading | ERP behaviour |
| --- | --- | --- |
| 1 | ক্রমিক | Line position. |
| 2 | পণ্য বা সেবার বর্ণনা (including brand name where applicable) | Starts as the commercial-invoice description. Editable on the draft. The source description is kept. |
| 3 | সরবরাহের একক | Unit copied from the commercial-invoice line. |
| 4 | পরিমাণ | Quantity copied from the commercial-invoice line. |
| 5 | একক মূল্য (টাকায়) | Source unit price from the commercial invoice. The footnote treatment is value excluding all taxes. |
| 6 | মোট মূল্য (টাকায়) | VAT base. Starts as the commercial line total. The user can change it. This is the base in `final = base + VAT + adjustment`. |
| 7 | সম্পূরক শুল্কের পরিমাণ (টাকায়) | Supplementary duty amount, typed or left blank. Not calculated. Not part of the VAT formula. |
| 8 | মূল্য সংযোজন করের হার/সুনির্দিষ্ট কর | The VAT percentage typed by the user. Blank until typed. Explicit 0 is allowed. A specific-tax amount that is not a percentage is not calculated. |
| 9 | মূল্য সংযোজন কর/সুনির্দিষ্ট কর এর পরিমাণ (টাকায়) | VAT amount calculated from the typed percentage and the base. |
| 10 | সকল প্রকার শুল্ক ও করসহ মূল্য | Presented as base + VAT + supplementary duty, in poisha. The rounding adjustment is not given a column. |

The footer has the responsible person's name, designation, signature, and seal. The ERP stores the name and designation. Signature and seal are left as lines for a handwritten signature. It does not draw a seal.

The page includes a starred note about source tax (উৎসে কর). That amount is not calculated on this document. AIT is a separate record and is not added to column 9 or column 10.

## Calculation, presentation, and what is not a form field

- Forward: VAT = base × percentage / 100, rounded once to the selected precision. Final = base + VAT + adjustment.
- Reverse, when the user edits the final amount: base = (final − adjustment) / (1 + percentage / 100), rounded once. VAT is the residual so the entered final amount is kept exactly.
- Changing the adjustment changes the final amount and does not change the base or the percentage.
- A blank percentage does not produce a VAT amount or a final amount.
- Precision choices are 2, 3, and 4. Rounding choices are half up (ties away from zero), half even, toward zero, ceiling (toward positive infinity), and floor (toward negative infinity).
- Stored amounts use `DECIMAL(19,4)`. The form's BDT columns are then shown at 2 decimal places, half up. That presentation is not written back.
- If the adjustment is not zero, the PDF says so in a block titled as a supplementary calculation, not as a Mushak column.
- The selected precision, rounding method, percentage, amounts, and actor/time are stored on the draft and frozen at issue.
- A linked tax rule, if the user picks one, is a source reference. Its explanatory figure is not copied into column 8.

## Review boundary

Arithmetic from a typed percentage is not a legal determination. The document stays "review required" unless a user explicitly confirms the treatment. That confirmation is stored with the user and the time, and the PDF still says the system did not decide the percentage.
