const XLSX = require('xlsx');

const filePath = process.argv[2];

if (!filePath) {
  console.error('Missing spreadsheet path.');
  process.exit(1);
}

const workbook = XLSX.readFile(filePath, {
  cellDates: true,
  dateNF: 'yyyy-mm-dd',
  raw: false,
});

const sheets = workbook.SheetNames.map((sheetName) => {
  const sheet = workbook.Sheets[sheetName];
  const matrix = XLSX.utils.sheet_to_json(sheet, {
    header: 1,
    defval: '',
    blankrows: false,
    raw: false,
  });

  const rows = matrix.map((row, index) => {
    const cells = {};

    row.forEach((value, columnIndex) => {
      cells[columnIndex + 1] = String(value ?? '').trim();
    });

    return {
      index: index + 1,
      cells,
    };
  });

  return {
    name: sheetName,
    rows,
  };
});

process.stdout.write(JSON.stringify({ sheets }));
