/**
 * Parse delimited (CSV) text into rows of string cells. Handles quoted fields,
 * escaped quotes (`""`), embedded commas/newlines, and CRLF line endings.
 *
 * This is an advisory client-side parser used only to read headers and preview
 * rows for the column mapper; the authoritative parse happens server-side.
 */
export function parseDelimited(text: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let field = '';
    let inQuotes = false;

    const pushField = (): void => {
        row.push(field);
        field = '';
    };

    const pushRow = (): void => {
        pushField();
        rows.push(row);
        row = [];
    };

    for (let i = 0; i < text.length; i++) {
        const char = text[i];

        if (inQuotes) {
            if (char === '"') {
                if (text[i + 1] === '"') {
                    field += '"';
                    i++;
                } else {
                    inQuotes = false;
                }
            } else {
                field += char;
            }

            continue;
        }

        if (char === '"') {
            inQuotes = true;
        } else if (char === ',') {
            pushField();
        } else if (char === '\n') {
            pushRow();
        } else if (char !== '\r') {
            field += char;
        }
    }

    // Flush the trailing field/row unless the file ended on a newline.
    if (field !== '' || row.length > 0) {
        pushRow();
    }

    return rows.filter((cells) => cells.some((cell) => cell !== ''));
}
