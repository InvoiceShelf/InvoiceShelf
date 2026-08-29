# Lokale Anpassungen dieses Forks

Basis ist das Upstream-Tag `2.4.2`. Der Fork enthaelt genau zwei
funktionale Aenderungen, jeweils als eigener Commit:

| Commit | Betroffene Dateien |
|--------|--------------------|
| `Allow inline base64 images in sanitized PDF HTML` | `app/Support/PdfHtmlSanitizer.php` |
| `Add image paste, drop and drag-resize to the rich text editor` | `resources/scripts/components/base/base-editor/*`, `package.json`, `pnpm-lock.yaml` |

Dieser Commit (`PATCHES.md`) ist reine Betriebsdokumentation und kann
weggelassen werden, falls die Aenderungen jemals upstream angeboten werden.

## Was die Aenderungen bewirken

Bilder lassen sich in jedem Rich-Text-Feld (Notizen von Angeboten und
Rechnungen, Notizvorlagen, E-Mail-Texte) mit Strg+V einfuegen oder per
Drag & Drop ablegen. Sie werden clientseitig auf max. 650 px Breite
herunterskaliert, als base64-Data-URI direkt im HTML gespeichert und
landen damit ohne Netzwerkzugriff und ohne Datei auf der Platte im PDF.
Die Groesse laesst sich am Anfasser unten rechts mit der Maus ziehen.

Die Breite wird als HTML-Attribut `width` gespeichert, nicht als
Inline-CSS: Der Sanitizer entfernt `style`, laesst `width` aber durch,
und dompdf wertet das Attribut aus.

Der Bildknoten ist als **inline** registriert. Als Blockknoten zeigte der
Editor Zeilenumbrueche an, die im gespeicherten HTML nicht vorhanden
waren und im PDF folglich nicht auftauchten.

## Sicherheitsrelevanz

`PdfHtmlSanitizer` ist eine SSRF-Schutzmassnahme: Sie verhindert, dass
dompdf beim Rendern eines Dokuments Ressourcen nachlaedt. Die Aenderung
oeffnet ausschliesslich `<img>`, und dort ausschliesslich `src`-Werte der
Form `data:image/(png|jpeg|jpg|gif|webp);base64,...`.

Weiterhin entfernt werden:

- jedes `src` mit `http:`, `https:`, `file:` oder anderem Schema
- `data:image/svg+xml` (SVG kann Skript transportieren)
- `on*`-Handler, `style`, `href`, `srcset`, `srcdoc`, `poster`,
  `formaction`, `xlink:href` auf allen Elementen

## Ausrollen auf eine Tarball-Installation

Die Produktivinstallation unter `/opt/invoiceshelf` ist kein
Git-Checkout, sondern ein entpacktes Release. Die Aenderungen werden
daher als Dateien kopiert. Vorher Snapshot des Containers anlegen.

```bash
scp app/Support/PdfHtmlSanitizer.php \
    root@HOST:/opt/invoiceshelf/app/Support/
scp package.json pnpm-lock.yaml \
    root@HOST:/opt/invoiceshelf/
scp resources/scripts/components/base/base-editor/BaseEditor.vue \
    resources/scripts/components/base/base-editor/ResizableImage.js \
    resources/scripts/components/base/base-editor/ResizableImageView.vue \
    root@HOST:/opt/invoiceshelf/resources/scripts/components/base/base-editor/
```

Danach auf dem Server:

```bash
cd /opt/invoiceshelf
corepack pnpm install --frozen-lockfile
corepack pnpm run build
chown -R www-data:www-data node_modules public/build package.json pnpm-lock.yaml app/Support resources/scripts
php artisan optimize:clear
```

Der Build erzeugt Dateinamen mit neuem Hash; im Browser ist danach ein
harter Reload (Strg+Shift+R) noetig.

## Upgrade auf eine neue InvoiceShelf-Version

Der eigentliche Zweck des Forks. Vor dem Upgrade beantwortet ein einziger
Befehl, ob ueberhaupt Konflikte moeglich sind:

```bash
git fetch upstream --tags
git log --oneline 2.4.2..NEUER_TAG -- app/Support/PdfHtmlSanitizer.php resources/scripts/components/base/base-editor/ package.json
```

Keine Ausgabe bedeutet: Upstream hat keine der von uns beruehrten Dateien
angefasst, das Rebase ist trivial. Anschliessend:

```bash
git rebase --onto NEUER_TAG 2.4.2 feature/paste-images
```

Konflikte zeigt Git explizit an, statt dass eine Datei still kaputtgeht.
Genau das leisten lose Patch-Dateien nicht.

## Bekannte Abweichungen und offene Punkte

- **Absatzabstand im PDF.** `GeneratesPdfTrait::getFormattedString()`
  macht aus `</p>` ein einzelnes `<br />`. Die Zeilenaufteilung zwischen
  Editor und PDF stimmt exakt, der vertikale Abstand zwischen Absaetzen
  ist im PDF aber geringer. Upstream-Verhalten, nicht durch diesen Patch
  verursacht.
- **Kein Touch-Support.** Der Anfasser reagiert auf `mousedown`, nicht auf
  Touch-Events. Auf Tablets laesst sich die Groesse nicht ziehen.
- **Speicherort.** Bilder liegen als base64 im Notizfeld, also in der
  Datenbank, nicht im Dateisystem. Bei SQLite unkritisch (kein
  TEXT-Limit). Bei einer spaeteren Migration nach MySQL waere die
  Spaltengroesse von `notes` zu pruefen, `TEXT` fasst dort nur 64 KB.
- **TipTap-Version.** `package.json` hebt alle `@tiptap/*`-Pakete von
  `^3.0.0` auf `^3.30.5`, damit nur eine Kopie von `@tiptap/core`
  geladen wird. Fuer ein Upstream-PR waere die kleinere Variante zu
  pruefen: Specifier auf `^3.0.0` belassen und lediglich
  `@tiptap/extension-image` ergaenzen.
- **Produktivsystem-Zwitter.** Auf `/opt/invoiceshelf` wurde seinerzeit
  mit `npm install` gearbeitet, obwohl das Projekt pnpm verwendet.
  `node_modules` hat pnpm-Layout mit npm-Ergaenzungen, zusaetzlich liegt
  dort eine `package-lock.json` (upstream via `.gitignore` ausgeschlossen).
  Beim naechsten Deployment nach obiger Anleitung bereinigt sich das:
  `package-lock.json` loeschen und `corepack pnpm install` verwenden.
