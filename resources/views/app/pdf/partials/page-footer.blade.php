{{--
    Chromium's footer template, repeated on every page.

    Every style is inline on purpose: Chromium renders header and footer
    templates in their own context, inheriting none of the document's CSS, and
    defaults them to a font size small enough to look blank. It substitutes the
    pageNumber and totalPages spans itself.

    Deliberately not localised. The spans are replaced by the browser, so
    wrapping words around them ("Page X of Y") reads badly once translated and
    cannot be reordered per locale. A numeral pair carries the same meaning
    everywhere.

    The footer draws inside the bottom page margin, so it is invisible if that
    margin is zero. See Settings -> PDF Generation.
--}}
<div style="font-size:9px;width:100%;padding:0 15mm;color:#6b7280;text-align:right;font-family:sans-serif;">
    <span class="pageNumber"></span> / <span class="totalPages"></span>
</div>
