/**
 * Parse the Next.js RSC "flight" data embedded in a saved Decathlon page.
 *
 * Decathlon's product pages embed the full product catalog in
 * `<script>self.__next_f.push([1, "..."]);</script>` chunks. This helper
 * extracts those chunks, joins them into the flight stream, locates the
 * `itemGroup` (the product data object), converts the RSC-specific markers
 * (`"$undefined"`, `"$L..."` refs) into JSON-safe values, and returns it.
 *
 * Usage (CLI):
 *   node parse-next-flight.js [htmlFile] [outJsonFile]
 *
 *   htmlFile    - path to the saved page HTML (default: decathlon-page.html)
 *   outJsonFile - optional path to write the pretty JSON (default: prints to stdout)
 *
 * Usage (as a module):
 *   const {extractItemGroupFromHtml} = require('./parse-next-flight');
 *   const itemGroup = extractItemGroupFromHtml(htmlString);
 */
const fs = require('fs');

const CHUNK_RE = /<script>self\.__next_f\.push\(\[1,\s*("(?:[^"\\]|\\.)*")\]\)<\/script>/g;

/**
 * Extract all `self.__next_f.push([1, "..."])` chunks from the HTML and
 * return them as decoded strings.
 */
function extractChunks(html) {
    const chunks = [];
    let match;

    while ((match = CHUNK_RE.exec(html)) !== null) {
        try {
            chunks.push(JSON.parse(match[1]));
        } catch (err) {
            console.error('Skipping unparseable __next_f chunk:', err.message);
        }
    }

    return chunks;
}

/**
 * Split the joined flight stream into `{id, value}` rows.
 */
function splitRows(joined) {
    return joined.split('\n').map((line) => {
        const colon = line.indexOf(':');
        if (colon === -1) {
            return {id: null, value: line};
        }

        return {id: line.slice(0, colon), value: line.slice(colon + 1)};
    });
}

/**
 * Return the balanced JSON-ish object substring starting at `braceIdx` (which
 * must point at a `{`). Handles nested braces and string contents.
 */
function extractBalancedObject(str, braceIdx) {
    let depth = 0;
    let inString = false;
    let escaped = false;

    for (let i = braceIdx; i < str.length; i++) {
        const ch = str[i];

        if (inString) {
            if (escaped) {
                escaped = false;
            } else if (ch === '\\') {
                escaped = true;
            } else if (ch === '"') {
                inString = false;
            }
            continue;
        }

        if (ch === '"') {
            inString = true;
        } else if (ch === '{') {
            depth++;
        } else if (ch === '}') {
            depth--;
            if (depth === 0) {
                return str.slice(braceIdx, i + 1);
            }
        }
    }

    return null;
}

/**
 * Convert an RSC-serialized value into valid JSON by replacing the special
 * markers Next.js uses:
 *   "$undefined"  -> null
 *   "$Lxxx" refs  -> string placeholder "$ref:Lxxx" (kept so you can see them)
 *   "$Sxxx"       -> string placeholder
 */
function rscToJsonSafe(value) {
    return value
        .replace(/"\$undefined"/g, 'null')
        .replace(/"\$L([^"]+)"/g, '"$ref:L$1"')
        .replace(/"\$S([^"]+)"/g, '"$server:$1"');
}

/**
 * Find every row in the flight stream that contains an `"itemGroup"` key and
 * return the parsed itemGroup object(s). Rows are deduplicated by JSON string.
 */
function extractItemGroupRows(rows) {
    const found = [];

    for (const row of rows) {
        let searchFrom = 0;
        let keyIdx;

        while ((keyIdx = row.value.indexOf('"itemGroup"', searchFrom)) !== -1) {
            const braceIdx = row.value.indexOf('{', keyIdx + 12);
            if (braceIdx === -1) {
                searchFrom = keyIdx + 1;
                continue;
            }

            const raw = extractBalancedObject(row.value, braceIdx);
            if (!raw) {
                searchFrom = keyIdx + 1;
                continue;
            }

            const jsonSafe = rscToJsonSafe(raw);
            try {
                const parsed = JSON.parse(jsonSafe);

                // Only keep genuine itemGroup objects. The payload also embeds
                // other `{...}` objects near the "itemGroup" key (e.g. the
                // reviews object), so filter by the type marker.
                if (parsed?.type === 'itemGroup') {
                    found.push(parsed);
                }
            } catch (err) {
                console.error('Failed to JSON-parse itemGroup row:', err.message);
            }

            searchFrom = braceIdx + 1;
        }
    }

    // Deduplicate identical itemGroup objects, preserving order.
    const seen = new Set();
    return found.filter((obj) => {
        const key = JSON.stringify(obj);
        if (seen.has(key)) {
            return false;
        }
        seen.add(key);
        return true;
    });
}

/**
 * Main entry point: read an HTML file, parse the flight data, return all
 * itemGroup objects found.
 */
function extractItemGroupFromHtml(html) {
    const chunks = extractChunks(html);
    if (chunks.length === 0) {
        return [];
    }

    const joined = chunks.join('\n');
    const rows = splitRows(joined);

    return extractItemGroupRows(rows);
}

// CLI mode
if (require.main === module) {
    const htmlFile = process.argv[2] || 'decathlon-page.html';
    const outFile = process.argv[3] || null;

    if (!fs.existsSync(htmlFile)) {
        console.error(`File not found: ${htmlFile}`);
        process.exit(1);
    }

    const html = fs.readFileSync(htmlFile, 'utf8');
    const itemGroups = extractItemGroupFromHtml(html);

    if (itemGroups.length === 0) {
        console.error('No itemGroup data found in the flight payload.');
        process.exit(1);
    }

    console.log(`Found ${itemGroups.length} itemGroup object(s) in ${htmlFile}`);

    if (outFile) {
        fs.writeFileSync(outFile, JSON.stringify(itemGroups, null, 2));
        console.log(`Written to ${outFile}`);
    } else {
        console.log('');
        console.log(JSON.stringify(itemGroups, null, 2));
    }
}

module.exports = {extractChunks, splitRows, extractBalancedObject, rscToJsonSafe, extractItemGroupFromHtml};
