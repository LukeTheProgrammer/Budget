# Contract: Read file headers & preview (client-side)

Per research D2, header reading and row preview happen client-side; there is no server endpoint for this step. This documents the client contract the `column-mapper.tsx` component implements.

## Input
- A browser `File` selected by the user (CSV / delimited text with a header row).

## Behavior
1. Read the file text (or a leading slice sufficient for headers + preview).
2. Parse the **first line** into an ordered list of header strings (handle quoted fields and embedded commas/quotes).
3. Parse the next up to **5 data rows** for preview.
4. Expose:
   - `headers: string[]`
   - `previewRows: string[][]` (aligned to `headers`)

## Output (component state, not HTTP)
```ts
type ParsedFile = {
  headers: string[];          // e.g. ["Transaction Date", "Post Date", "Description", "Amount"]
  previewRows: string[][];    // up to 5 rows
};
```

## Errors / edge cases
- Empty file or header-only file → surface "No data rows found" and block import.
- Blank/duplicate headers → still listed; duplicates disambiguated for display, but mapping references the exact header string (a duplicated header is a user-resolvable ambiguity surfaced in the UI).
- Non-text/oversized file → blocked client-side before parse; server re-validates regardless.

## Notes
- This parse is **advisory**. The authoritative parse is server-side during import (`POST transactions/upload`). The client never needs perfect CSV fidelity.
