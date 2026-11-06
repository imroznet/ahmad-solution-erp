# Mushak 6.3 field map

Source file and checksum: see `SOURCE-REGISTER.md`. The labels below are the English names of the fields read from the rendered page on 6 November 2026, with the Bangla heading printed on the PDF when the embedded Noto Sans Bengali font can draw it.

The PDF is one A4 page. It does not show an original/duplicate/triplicate set, so this release does not invent copy labels. It does not show a rounding-adjustment column. That amount is printed only in a supplementary note titled "Rounding / Decimal Adjustment".

## Heading

| Form text | Application |
| --- | --- |
| গণপ্রজাতন্ত্রী বাংলাদেশ সরকার | Printed. The national emblem is not reproduced. |
| জাতীয় রাজস্ব বোর্ড | Printed. |
| কর চালানপত্র | Printed, with the English "Tax invoice". |
| মূসক-৬.৩ | Printed, with the English "Mushak 6.3". |
| বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও (চ) | Printed as read from the page. |

## Parties and document identity

| Form field | Source | Required to issue |
| --- | --- | --- |
| Registered person's name | Company profile legal name, snapshotted at issue | Yes |
| Registered person's BIN | Company profile BIN, snapshotted at issue | Yes. Issue is blocked when it is blank. |
| Registered person's address | Registered office, or corporate office, snapshotted at issue | Printed when the profile has one |
| Invoice number | `M63-{Asia/Dhaka year}-{sequence}`, assigned at issue | A draft has no number |
| Date of issue | Document date | Yes |
| Time of issue | `issued_at` in Asia/Dhaka | Printed after issue |
| Buyer name | Selected customer, or a one-off name | Yes. A one-off name does not create a customer. |
| Buyer BIN | Customer BIN, or a typed value | No. A buyer without a BIN is not given an invented one. |
| Buyer address | Customer address, or typed text | Yes |
| Place of supply | Typed destination | No |

## Lines

The price and total columns are marked on the form as exclusive of all taxes. Supplementary duty is the next amount column. VAT rate and VAT amount are separate columns. The last column is the value including all taxes and duties.

| Column | What this release prints |
| --- | --- |
| Serial | Line position |
| Description of goods or service | Typed line, starting from the commercial invoice line |
| Unit of supply | Typed unit |
| Quantity | Typed quantity, up to 4 decimal places |
| Unit price, exclusive | Stored base divided by quantity, then shown at 2 decimal places. The stored base is the calculation input. |
| Total price, exclusive | Stored base, shown at 2 decimal places |
| Supplementary duty | Blank. It is not calculated and it is not the rounding adjustment. |
| VAT rate | The percentage the user typed, or blank on a draft. It is not taken from a tax rule. |
| VAT amount | Calculated VAT, shown at 2 decimal places |
| Value including VAT | Base plus VAT at 2 decimal places. The rounding adjustment is not added into this column, because the form has no adjustment field and the adjustment is not VAT. |

BDT on these columns is always 2 decimal places, using the rounding method stored on the document. A calculation precision of 3 or 4 is not shown as a fractional taka in these columns. The stored figures are in the supplementary note.

## Signature

The form asks for the name, designation, signature, and seal of the person responsible. The PDF prints the issuing user's name and an optional designation. It does not draw a seal.

## What is not on the form

The supplementary note prints base, the typed percentage, VAT, the rounding adjustment, the adjustment reason, the final amount, the residual, the precision, and the rounding method. Final = base + VAT + adjustment, at the stored precision. The note says the percentage was entered by a user and was not certified by the system.
