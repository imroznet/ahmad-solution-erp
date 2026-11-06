# Mushak 6.3 field map

Source file and checksum: see `SOURCE-REGISTER.md`. The page was read on 6 November 2026. Labels below are English readings of the Bangla form. The generated PDF uses those English labels because DejaVu Sans does not include Bengali. It is not a claim that the PDF is the official Bangla original.

## Header

| Form text, as read | ERP field | Rule |
| --- | --- | --- |
| Mushak 6.3 | Document title | Fixed. |
| Tax invoice | Document title | This is not the commercial invoice. |
| Rule 40, sub-rule (1) | Citation stored in the source register | The clause letters were read from the form. They are not re-decided here. |
| Name of the registered person | Company legal name | Copied from the company profile. Snapshotted at issue. |
| BIN of the registered person | Company BIN | Required at issue. Missing BIN blocks issue. It is not invented. |
| Address of the registered person | Company address lines | Copied when the profile has an address. |
| Invoice number | `M63-{Asia/Dhaka year}-{sequence}` | Assigned at issue. This is the company's series, not a claimed NBR number format. |
| Date of issue | `document_date` | Entered on the draft. |
| Time of issue | `issued_at` in Asia/Dhaka | Set when the document is issued. A draft has no time. |

## Buyer

| Form text, as read | ERP field | Rule |
| --- | --- | --- |
| Buyer name | `buyer_legal_name` | Saved customer, or a manual name. Manual entry does not create a party. |
| Buyer BIN | `buyer_bin` | Copied or typed. Blank is allowed. A BIN is not invented. |
| Place of supply / destination | `buyer_address` | Required. A saved customer uses the billing address. |

Buyer TIN is stored for the ERP record. It is not a separate column on the inspected form, so the PDF does not give it a column.

## Lines

The form is a line table. Calculation is per line. Document totals are the sums of the line amounts.

| Column, as read | ERP field | Calculation |
| --- | --- | --- |
| Serial | `position` | Assigned in line order. |
| Description of goods or service | `description` | Starts from the commercial invoice line. Editable on the draft. |
| Unit of supply | `unit` | Starts from the commercial invoice line. |
| Quantity | `quantity` | Starts from the commercial invoice line. Editing quantity or unit price sets the base to quantity times unit price, rounded half away from zero to two decimal places. |
| Unit price, excluding tax | `unit_price` | Two decimal places. |
| Total price, excluding tax | `base_amount` | The VAT base. Editing it recalculates VAT and the VAT-inclusive amount from the current percentage. |
| Supplementary duty | `supplementary_duty_amount` | Optional amount entered by the user. It is not calculated and it is not part of the VAT amount. |
| VAT rate | `vat_rate_percent` | Blank until the user types it. Zero is an explicit entry. The system does not choose, infer, or prefill a percentage. |
| VAT amount | `vat_amount` | Calculated. Forward: round(base × rate / 100, 2), half away from zero. |
| Value including duties and taxes | `duties_amount` | Base is not shown again. This column is the VAT-inclusive amount plus any supplementary duty entered. When supplementary duty is blank it adds nothing. |

The VAT-inclusive amount used by the reverse calculation is base + VAT, before supplementary duty. Editing that amount keeps the percentage and keeps the edited amount exactly to the poisha. The base is round(inclusive × 100 / (100 + rate), 2). VAT is inclusive minus that base, so base + VAT equals the edited amount exactly.

Changing the percentage recalculates VAT and the VAT-inclusive amount from the base. It does not keep the previous inclusive amount.

BDT rounding to two decimal places, half away from zero, is an application rule. It is not recorded as an NBR rounding rule.

## What is not on this form

VDS and AIT have their own records. They are not columns on Mushak 6.3 and they are not added to the VAT amount.

Mushak 6.6 is not generated. The deduction rules were identified on the NBR index, and their certificate layout was not verified.

The commercial invoice stays tax-excluded. Issuing Mushak 6.3 does not change it.

## Issue and corrections

Issue freezes the lines, the buyer, the company snapshot, the selected rule snapshot, and the amounts. A later company edit, party edit, or tax-rule amendment does not rewrite the issued document.

A missing percentage blocks issue. A draft may be saved with a blank percentage, and its VAT total stays uncalculated.

Void keeps the row. It does not edit the amounts and it does not void the commercial invoice. A statutory credit or debit note is not created. One non-void Mushak 6.3 is allowed for each commercial invoice.

The document stays `review_required` unless the user selects an approved output-VAT rule whose window covers the document date and records that they are attesting that treatment. The line percentage is still the typed figure. The system does not certify it.
