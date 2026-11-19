<style>
    @page { margin: 10mm; }
    @font-face {
        font-family: "Taka Sign";
        font-style: normal;
        font-weight: 400;
        src: url("{{ \App\Documents\PdfFactory::fileUrl(resource_path('fonts/TakaSign.ttf')) }}") format("truetype");
    }
    @font-face {
        font-family: "Taka Sign";
        font-style: normal;
        font-weight: 700;
        src: url("{{ \App\Documents\PdfFactory::fileUrl(resource_path('fonts/TakaSign.ttf')) }}") format("truetype");
    }
    body { font-family: DejaVu Sans, sans-serif; color: #17211c; font-size: 10px; margin: 0; }
    .taka { font-family: "Taka Sign", DejaVu Sans, sans-serif; font-weight: 400; }
    .letterhead-name { font-size: 9px; font-weight: 700; margin: 0; line-height: 1.2; }
    .letterhead-meta { font-size: 8px; margin: 0; line-height: 1.25; color: #243028; }
    p { margin: 0 0 2px; }
    h2 { font-size: 12px; margin: 0 0 2px; }
    table.head { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
    table.head td { border: 0; padding: 0; vertical-align: top; }
    table.sheet { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.sheet th, table.sheet td { border-bottom: 1px solid #d5ddd8; padding: 3px 2px; vertical-align: top; text-align: left; }
    table.sheet th { color: #5d6b64; font-weight: 600; }
    .right { text-align: right; }
    .total { font-weight: 700; }
    .muted { color: #5d6b64; }
    .banner { font-size: 11px; letter-spacing: 1px; }
    .notice { margin-top: 8px; }
</style>
