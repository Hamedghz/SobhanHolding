import fs from 'node:fs/promises';
import path from 'node:path';
import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';

const inputPath = process.argv[2];
const outputDir = process.argv[3];
if (!inputPath || !outputDir) throw new Error('input and output paths are required');

await fs.mkdir(outputDir, { recursive: true });
const workbook = await SpreadsheetFile.importXlsx(await FileBlob.load(inputPath));
const sheetSummary = await workbook.inspect({ kind: 'sheet', include: 'id,name', maxChars: 4000 });
console.log('SHEETS');
console.log(sheetSummary.ndjson);

const sheets = workbook.worksheets.items;
if (!sheets.length) throw new Error('Workbook has no sheets');
for (const sheet of sheets) {
  const used = sheet.getUsedRange(true);
  if (!used) throw new Error(`Sheet is blank: ${sheet.name}`);
  const inspection = await workbook.inspect({
    kind: 'region',
    sheetId: sheet.name,
    range: 'A1:Z10',
    maxChars: 6000,
    tableMaxRows: 10,
    tableMaxCols: 26,
  });
  console.log(`REGION ${sheet.name}`);
  console.log(inspection.ndjson);
  const preview = await workbook.render({ sheetName: sheet.name, autoCrop: 'all', scale: 1, format: 'png' });
  const safeName = sheet.name.replace(/[^\p{L}\p{N}._-]+/gu, '-');
  await fs.writeFile(path.join(outputDir, `${safeName}.png`), new Uint8Array(await preview.arrayBuffer()));
}

const errors = await workbook.inspect({
  kind: 'match',
  searchTerm: '#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A',
  options: { useRegex: true, maxResults: 100 },
  summary: 'formula error scan',
});
console.log('FORMULA_ERRORS');
console.log(errors.ndjson);
