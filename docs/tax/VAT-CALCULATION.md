# VAT calculation

The percentage is typed. The ERP does not choose it, prefill it, or treat 15% as a default. A blank percentage does not produce a VAT amount. An explicit 0% does. VDS and AIT are not inputs.

## Controls

Calculation precision is 2, 3, or 4 decimal places. The rounding method is one of:

| Method | Meaning |
| --- | --- |
| Half up | Nearest digit. A tie rounds away from zero. |
| Half even | Nearest digit. A tie rounds to the even digit. |
| Toward zero | Extra digits are dropped toward zero. |
| Away from zero | Any non-zero extra digit rounds away from zero. |
| Toward positive | Toward positive infinity. |
| Toward negative | Toward negative infinity. |

Extra digits on an amount are rounded once with that method. They are not truncated. The chosen precision and method are stored on the draft and copied onto the issued document. Columns are `DECIMAL(20,4)`, so a 4-decimal result is stored without an earlier round to poisha. The Mushak face still shows BDT at 2 decimal places.

The rounding adjustment is a separate signed amount. Zero is the neutral value. It does not change the percentage. A non-zero adjustment needs a reason.

## Invariant

When VAT is calculated, at the selected precision:

`final = base + VAT + adjustment`

From the base amount, VAT is `round(base × rate / 100)` and the final amount is the sum. The adjustment is kept.

From the final amount, the rate and the adjustment stay fixed.

`net = final − adjustment`

`base = round(net × 100 / (100 + rate))`

`VAT = net − base`

That VAT is exact, so the typed final amount is preserved. The residual is that VAT minus `round(base × rate / 100)`. The residual is stored and is not added again.

Editing the adjustment rounds only the adjustment. Base, rate, and VAT stay as they were, and the final amount is recalculated. Changing the precision or the rounding method recalculates from the base and keeps the adjustment, rounding it to the new precision.

## What the system does not decide

The stored percentage, the actor, and the time are kept on the line. The document text says the system calculated from that percentage and did not decide whether it applies. A matching tax rule explains which version covered the date and classification. It does not fill the percentage. An empty classification is its own scope, not a wildcard.
