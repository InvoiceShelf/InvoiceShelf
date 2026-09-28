{{--
    The stylesheet every report PDF renders with.

    One content edge: PdfPageSetup::forReports() puts a 1.2cm margin on the
    @page box, and that is the only outer inset in the whole document. Nothing
    below adds a second one, so the header, the section headings, the table
    columns, the total rules and the footer band all start on the same left
    edge and end on the same right edge.

    Vertical rhythm is two steps: 24px between sections, 8px inside one.
--}}
<style type="text/css">
    body {
        margin: 0px;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    p {
        margin: 0px;
        padding: 0px;
    }

    /* -- Header -- */

    .report-header td {
        vertical-align: top;
    }

    .report-logo {
        display: block;
    }

    .report-company-name {
        font-size: 15px;
        line-height: 22px;
        letter-spacing: 0.05em;
        color: #040405;
    }

    .report-date-range {
        font-size: 12px;
        line-height: 22px;
        text-align: right;
        color: #595959;
    }

    .report-title {
        padding-top: 8px;
        font-size: 16px;
        line-height: 24px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #55547A;
    }

    /* -- Sections -- */

    /* Keep a section whole where it fits. Sections are small (one customer,
       one expense category), so a break inside one leaves a stray Total row
       under repeated column headings on the next page. A section taller than a
       page still breaks: this is a hint, not a guarantee. */
    .report-section {
        page-break-inside: avoid;
        padding-top: 24px;
    }

    /* A heading and its column headings must not be the last thing on a page
       with the rows they introduce overleaf. Both renderers keep the two
       together when the break is allowed to fall before the heading instead,
       and a section longer than a page still breaks normally. */
    .report-section-heading {
        page-break-after: avoid;
        padding-bottom: 8px;
        font-size: 12px;
        line-height: 18px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #55547A;
    }

    /* -- Tables -- */

    /* Zero horizontal cell padding is deliberate: dompdf's own stylesheet gives
       every cell 1px of it, which is enough to push a table off the content
       edge the headings sit on. */
    .report-table th {
        padding: 0 0 8px;
        border-bottom: 0.62px solid #E8E8E8;
        font-size: 10px;
        line-height: 15px;
        text-transform: uppercase;
        text-align: left;
        color: #55547A;
    }

    .report-table td {
        padding: 8px 0;
        font-size: 12px;
        line-height: 18px;
        color: #040405;
    }

    /* A report is one table per section, and a renderer left to itself sizes
       each of them on its own content: the expenses note column landed anywhere
       between 132pt and 190pt down a single page. Declaring the two fixed-shape
       columns pins them, and the descriptive column absorbs the slack in the
       same place in every section of every report. */
    .report-table .report-col-date {
        width: 18%;
    }

    .report-table .report-col-count {
        width: 18%;
    }

    .report-table .report-col-amount {
        width: 22%;
    }

    /* Scoped to the table so these beat the `.report-table th` / `td`
       typography above them rather than losing to it on specificity. */
    .report-table .report-amount {
        text-align: right;
    }

    .report-table .report-total {
        font-weight: bold;
    }

    .report-table .report-muted,
    p.report-muted {
        font-size: 10px;
        line-height: 15px;
        color: #595959;
    }

    p.report-muted {
        margin-bottom: 8px;
    }

    /* Collapsed borders make this one rule across the full table width, so a
       section total is ruled off on exactly the content edge. */
    .report-total-row td {
        border-top: 1px solid #E8E8E8;
    }

    /* -- Footer band -- */

    /* The band's inset lives on a wrapping div rather than on the table it
       holds, because padding does not apply to a table in border-collapse
       mode: dompdf applies it anyway and Chromium drops it, which puts the two
       renderers 22.5pt apart on each side. A plain block is honoured
       identically by both. The same holds for any indent step a report needs
       later, so wrap, never pad a collapsed table. */
    .report-footer {
        margin: 0 -12px;
        padding: 12px;
        background: #F9FBFF;
    }

    /* Same reason as the report table: the band's inset is its own 12px, not
       12px plus whatever the renderer pads a cell by. */
    .report-footer td {
        padding: 0px;
    }

    .report-footer-label {
        font-size: 12px;
        line-height: 18px;
        font-weight: bold;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #55547A;
    }

    .report-footer-value {
        font-size: 16px;
        line-height: 24px;
        font-weight: bold;
        text-align: right;
        color: #5851D8;
    }
</style>
