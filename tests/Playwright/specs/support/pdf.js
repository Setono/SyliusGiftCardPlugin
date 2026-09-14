/**
 * Helpers for looking inside the PDFs the plugin serves.
 *
 * The card is drawn by dompdf, so a spec can only see what it put on the page by reading the PDF back. That is
 * worth doing: the back of the card is where the redemption code lives, and a template that renders but leaves
 * the code off still returns a perfectly valid PDF.
 */

const zlib = require('zlib');

const PDF_ESCAPES = { n: '\n', r: '\r', t: '\t', b: '\b', f: '\f' };

/**
 * The number of pages in the document. A gift card is a front and a back.
 *
 * @param {Buffer} body
 */
function pdfPageCount(body) {
    return (body.toString('latin1').match(/\/Type\s*\/Page(?![s])/g) ?? []).length;
}

/**
 * The text drawn in the document, one show-text operation per line.
 *
 * dompdf deflates its content streams and writes the strings in them as UTF-16, so they are inflated and
 * decoded here rather than pulled in with a PDF parser dependency.
 *
 * @param {Buffer} body
 */
function pdfText(body) {
    const raw = body.toString('latin1');
    const streams = /stream\r?\n/g;

    const lines = [];
    let match;

    while (null !== (match = streams.exec(raw))) {
        const start = match.index + match[0].length;
        const end = raw.indexOf('endstream', start);
        if (0 > end) {
            continue;
        }

        let content;
        try {
            content = zlib.inflateSync(Buffer.from(raw.slice(start, end), 'latin1')).toString('latin1');
        } catch {
            // not a deflated stream - fonts and images are in here too
            continue;
        }

        for (const shown of content.matchAll(/\[((?:\((?:\\[\s\S]|[^)\\])*\)|[^\]])*)\]\s*TJ/g)) {
            const strings = shown[1].matchAll(/\((?:\\[\s\S]|[^)\\])*\)/g);

            lines.push([...strings].map((string) => decodeString(string[0].slice(1, -1))).join(''));
        }
    }

    return lines.join('\n');
}

/**
 * Turns one PDF string literal into the text it stands for: the escapes are undone to get the bytes back, and
 * the bytes are read as UTF-16 big endian, which is how dompdf writes anything but the simplest fonts.
 *
 * @param {string} literal the contents of the literal, without its parentheses, read as one byte per character
 */
function decodeString(literal) {
    let bytes = '';

    for (let i = 0; i < literal.length; i++) {
        if ('\\' !== literal[i]) {
            bytes += literal[i];
            continue;
        }

        const escaped = literal[++i];
        if (undefined === escaped) {
            break;
        }

        if (/[0-7]/.test(escaped)) {
            let octal = escaped;
            while (3 > octal.length && /[0-7]/.test(literal[i + 1] ?? '')) {
                octal += literal[++i];
            }

            bytes += String.fromCharCode(parseInt(octal, 8));
            continue;
        }

        bytes += PDF_ESCAPES[escaped] ?? escaped;
    }

    let text = '';
    for (let i = 0; i + 1 < bytes.length; i += 2) {
        text += String.fromCharCode((bytes.charCodeAt(i) << 8) | bytes.charCodeAt(i + 1));
    }

    return text;
}

module.exports = { pdfPageCount, pdfText };
