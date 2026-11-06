# Tax source register

Checked 6 November 2026. A publication date is not an effective date. Nothing in this register is an instruction to charge a rate. No percentage is seeded, and a stored rule percentage is not copied onto a Mushak 6.3.

## Mushak 6.3 form file

| Field | Record |
| --- | --- |
| Title | কর চালানপত্র / Mushak 6.3 |
| Issuing authority | The file is published by the Dhaka North Customs, Excise and VAT Commissionerate. The form heading names the National Board of Revenue. |
| Identifier | Mushak 6.3. The form cites Rule 40, sub-rule (1), clauses (গ) and (চ). |
| URL | https://dknvat.gov.bd/assets/document/form/form_175068235417Xz9DmBrT9uc6V.pdf |
| Owner confirmation | On 6 November 2026 the owner said this exact URL is the form to use for the first implementation. |
| File check | Downloaded 6 November 2026. HTTP 200, `application/pdf`, 93,995 bytes, 1 A4 page. |
| SHA-256 | `c09e853829e19a6265d82b53b0f4408c9db95ca8793fa1666cec39c6870cf5a2` |
| PDF metadata | Author Arman. Creator Microsoft Word 2010. Creation and modification 29 June 2019, 13:26:21 UTC. |
| Text layer | Poppler could not extract the Bangla text. The field map comes from looking at a rendered page of this file. |
| What this does not prove | The 2019 metadata date is not treated as a later amendment date. The owner's confirmation is the reason this file is the layout reference. It is not a certification that no later gazette changed a field. |

The national emblem on the form is not copied into the application.

## Other official indexes

These pages were named in the milestone. Their amendment text was not downloaded into this repository, and no rate from them is activated.

| Source | What was recorded | What was not established |
| --- | --- | --- |
| VAT SRO index, https://nbr.gov.bd/regulations/sros/vat-sros/ | The milestone names SRO No-125-Ain/2026/330-Mushak and SRO No-140-Ain/2026/345-Mushak, both listed with 11 June 2026 dates. The first is described as an amendment of the VAT and Supplementary Duty Rules, 2016. The second is described as an amendment of the VAT Deduction and Collection Rules, 2025. | The gazette text, the effective date as distinct from the listing date, and whether either SRO changes Mushak 6.3 or sets a rate for this company's supplies were not verified here. |
| Income tax rules, https://nbr.gov.bd/regulations/rules/income-tax-rules/eng | The milestone names The Withholding Tax Rules, 2026 (12 June 2026) and an amendment (6 July 2026). | The rule text and any rate or certificate form were not verified here. |
| Income tax SROs, https://nbr.gov.bd/regulations/sros/income-tax-sros/eng | Named as the index to check. | No SRO was selected or applied. |
| NBR forms, https://nbr.portal.gov.bd/pages/nbr-forms | Named as an index. | Not used as the Mushak 6.3 art. The owner-confirmed Dhaka North file is the layout reference. |

## Decisions that follow from the gap

- Output VAT, VDS, and AIT are separate records. A VDS or AIT amount is never added to output VAT.
- Mushak 6.6 is not implemented. The VDS and AIT screens say the certificate form is unverified and they do not issue one.
- Supplementary duty has a column on the form. This release does not calculate it and prints the column blank.
- The form's "specified tax" alternative to a VAT percentage is not implemented. The percentage column shows only a percentage the user typed.
- A transaction with no approved matching rule can still be issued only after the user records a review reason and confirms the manual treatment. That confirmation is not a legal determination.
