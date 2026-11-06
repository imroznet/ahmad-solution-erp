# Tax source register

Checked 6 November 2026. A publication date is not an effective date. A filename or index date is not an effective date. No rate in this register is activated, seeded, or applied.

This register records what was inspected for Roadmap Milestone 6. It does not conclude that a supply by Ahmad Solution Ltd. is taxable, exempt, zero-rated, or subject to deduction at source.

## Owner decisions — 6 November 2026

Recorded from Issue #17. These decisions control the implementation. They are business instructions, not a gazette.

- The Mushak 6.3 template is the PDF at the URL in source 1 below. The earlier cached-page listing-date discrepancy does not block that choice.
- Seller name, BIN, registered address, and contact details come from the company profile and are snapshotted when a Mushak 6.3 is issued. A missing company BIN blocks issue.
- The buyer is either an existing customer party or details typed for one document. Typing a buyer does not create a party.
- VAT on Mushak 6.3 is typed by an authorized user. The percentage starts blank. The ERP does not select, infer, or prefill a rate, including 15%. It does not calculate a VAT amount, a taxable base, an inclusive or exclusive conversion, or a total from that percentage. Typed figures are stored as entered facts, with the actor and time, and the document says the ERP did not determine the legal rate.
- Output VAT, VDS, and AIT stay in separate records. VDS and AIT are not added to VAT.
- A Mushak 6.3 stays marked for tax review unless an authorized user records a treatment. Recording a treatment does not change the typed figures and does not mean the ERP calculated them.

## 1. Mushak 6.3 form file

| Item | Record |
| --- | --- |
| Title on the form | কর চালানপত্র, form code মূসক-৬.৩ |
| Issuing authority shown on the form | গণপ্রজাতন্ত্রী বাংলাদেশ সরকার, জাতীয় রাজস্ব বোর্ড |
| Host | Dhaka North Customs, Excise and VAT Commissionerate |
| Identifier | মূসক-৬.৩ |
| URL | https://dknvat.gov.bd/assets/document/form/form_175068235417Xz9DmBrT9uc6V.pdf |
| Date checked | 6 November 2026 |
| Owner confirmation | 6 November 2026, Issue #17. This URL is the layout authority for the initial implementation. |
| Bytes | 93,995 |
| SHA-256 | `c09e853829e19a6265d82b53b0f4408c9db95ca8793fa1666cec39c6870cf5a2` |
| Pages | 1, A4, PDF 1.5, not encrypted |
| PDF creation timestamp | 29 June 2019, 19:26:21 +06, metadata `D:20190629192621+06'00'`. This is the file's authoring time. It is not a legal effective date. |
| PDF author / producer | Arman / Microsoft Word 2010 |
| Filename time | `1750682354` is 23 June 2025, 12:39:14 UTC. That is a hosting token in the URL, not a publication date and not an effective date. |
| Citation printed on the form | `[ বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য ]` |
| How the citation was read | The PDF text layer does not expose those Bengali letters as Unicode. The clause letters were read from a 220 dpi render of this file on 6 November 2026. A wider crop was less reliable. The closer crop is the reading recorded here. |
| Legal effective date | Not established. The form cites Rule 40. The gazette that gives the current text of that rule was not retrieved. See source 3. |
| Scope used here | Field layout for a separate Mushak 6.3 document. The file is not stored in this repository. The URL and checksum are the pin. |
| What this file does not prove | That the layout is unchanged by a 2026 amendment, that every supply must use it, or that a rate applies. |

The field-by-field map is `docs/tax/MUSHAK-6.3-FIELD-MAP.md`.

## 2. Dhaka North forms index

| Item | Record |
| --- | --- |
| Title | Forms index |
| Authority | Dhaka North Customs, Excise and VAT Commissionerate |
| URL | https://dknvat.gov.bd/all-forms |
| Date checked | 6 November 2026, from the milestone notes and the owner's confirmation |
| Publication / effective date | Not used. Search indexing had reported a Mushak 6.3 entry dated 5 November 2026 while a cached rendering still showed 10 August 2026. That discrepancy is recorded. The owner then named source 1 as the file to implement. The index date is not treated as the form's effective date. |

## 3. NBR VAT SRO index

| Item | Record |
| --- | --- |
| Title | VAT SROs |
| Authority | National Board of Revenue |
| URL | https://nbr.gov.bd/regulations/sros/vat-sros/ |
| Date checked | 6 November 2026 |
| Relevant rows | SRO No-125-Ain/2026/330-Mushak, listed 11/06/2026, description on the index: amendment of the Value Added Tax and Supplementary Duty Rules, 2016 (মূল্য সংযোজন ও সম্পূরক শুল্ক বিধিমালা, ২০১৬ এর সংশোধন). SRO No-140-Ain/2026/345-Mushak, listed 11/06/2026, description: amendment gazette to the Value Added Tax Deduction and Collection Rules, 2025. SRO No-182-Ain/2025/310-Mushak, listed 02/06/2025, description: VAT deduction at source rules. |
| Publication date | The index date is the date column above. The index does not give a separate effective date. |
| Effective date | Unknown until the gazette text is read. |
| Gazette body | Not retrieved. The index rows fetched on 6 November 2026 did not include a usable download target in the page text. |
| Scope | Source 125 may amend the rules that contain Rule 40. Whether it changes Mushak 6.3 is unverified. Source 140 amends the deduction and collection rules. Whether Mushak 6.6 is the certificate for this company's workflow is unverified. |
| Decision | No rate, exemption, or Mushak 6.6 form is taken from these SROs. The owner-confirmed PDF remains the layout. Issued Mushak 6.3 documents stay marked for review. VDS is a separate record, not a generated Mushak 6.6. |

Other rows on the same index, including SRO No-147-Ain/2026/352-Mushak through SRO No-126-Ain/2026/331-Mushak, are sector or product measures (startups, appliances, metro rail, gas, vehicles, and similar). None is matched to a transaction category for Ahmad Solution Ltd. None is seeded.

## 4. NBR income-tax rules index

| Item | Record |
| --- | --- |
| Title | Income Tax Rules |
| Authority | National Board of Revenue |
| URL | https://nbr.gov.bd/regulations/rules/income-tax-rules/eng |
| Date checked | 6 November 2026 |
| Rows | The Withholding Tax Rules, 2026, date column 12-06-2026. The Withholding Tax Rules, 2026 (Amendment), date column 06-07-2026. |
| Publication vs effective | The index gives one date column. It does not say whether that date is publication or commencement. |
| Gazette body | Not retrieved. The download column was empty in the page text fetched on 6 November 2026. |
| Decision | No withholding rate and no certificate layout are taken from these rules. AIT is a separate record of figures an authorized user types. |

## 5. NBR income-tax SRO index

| Item | Record |
| --- | --- |
| Title | Income Tax SROs |
| Authority | National Board of Revenue |
| URL | https://nbr.gov.bd/regulations/sros/income-tax-sros/eng |
| Date checked | 6 November 2026, as a starting point named in Issue #17 |
| Decision | No income-tax SRO was selected and no rate was copied. Applicability to a payment or receipt is not inferred. |

## 6. NBR VAT forms index

| Item | Record |
| --- | --- |
| Title | NBR forms |
| Authority | National Board of Revenue |
| URL | https://nbr.portal.gov.bd/pages/nbr-forms |
| Date checked | Named in Issue #17 on 6 November 2026 |
| Decision | The implemented Mushak 6.3 layout is source 1, not a copy from this portal and not a copy from an unofficial template. |

## Open questions for an accountant or lawyer

These questions block a statutory rate and a claim that a form is the current prescribed form. They do not block storing a Mushak 6.3, a VDS record, or an AIT record under the owner decisions above.

1. Does SRO No-125-Ain/2026/330-Mushak change Rule 40 or the Mushak 6.3 layout, and on what effective date?
2. Which clause of the current VAT Act and Rules applies to Ahmad Solution Ltd.'s supplies, and is any supply exempt, zero-rated, or outside the scope?
3. For a supply that is subject to VAT deduction at source, is the note on this Mushak 6.3 file enough, or is a separate Mushak 6.6 required under the VAT Deduction and Collection Rules, 2025 as amended by SRO No-140-Ain/2026/345-Mushak?
4. Which section of the Income Tax Act and which rule of the Withholding Tax Rules, 2026, as amended on the index date 6 July 2026, applies to a particular payment or receipt, and what is the current certificate or statement form?

Until those answers are written into an approved tax rule by an authorized user, the ERP will not choose a rate and will not mark a document as tax-complete.
