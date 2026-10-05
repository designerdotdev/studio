#!/usr/bin/env node
/**
 * Checks resources/js/section-source.js against real sections:
 *   node bin/section-source-check.mjs <folder of .blade.php files>…
 *
 * For every file: marking then unmarking is byte-identical, every node's
 * offsets say what the node holds, every attribute value can be spliced
 * back in place, and the marked source numbers each element once.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { parse, mark, unmark, rows } from '../resources/js/section-source.js';

const files = [];
const walk = (dir) => {
    for (const name of readdirSync(dir)) {
        if (name === 'node_modules' || name === '.git' || name === 'vendor') continue;
        const path = join(dir, name);
        if (statSync(path).isDirectory()) walk(path);
        else if (name.endsWith('.blade.php')) files.push(path);
    }
};
process.argv.slice(2).forEach(walk);

let failed = 0;
let elements = 0;
const fail = (file, message) => { failed++; console.error(`FAIL ${file}: ${message}`); };

for (const file of files) {
    const text = readFileSync(file, 'utf8');
    let tree;

    try {
        tree = parse(text);
    } catch (error) {
        fail(file, 'parse threw: ' + error.message);
        continue;
    }

    elements += tree.elements.length;

    const marked = mark(text, tree);
    if (unmark(marked) !== text) fail(file, 'mark → unmark is not the source');
    if ((marked.match(/ data-sn="\d+"/g) || []).length !== tree.elements.length) fail(file, 'marker count differs from element count');

    // Marked source parses to the same shape
    if (parse(marked).elements.length !== tree.elements.length) fail(file, 'marked source parses differently');

    const check = (nodes) => {
        for (const node of nodes) {
            if (node.type === 'element') {
                if (text.slice(node.start + 1, node.nameEnd) !== node.name) fail(file, `element name offsets (${node.name})`);
                if (!(node.start < node.openEnd && node.openEnd <= node.end)) fail(file, `element range (${node.name} @${node.start})`);
                for (const attr of node.attrs) {
                    if (attr.kind !== 'attr' || attr.value === null) continue;
                    if (text.slice(attr.valueStart, attr.valueEnd) !== attr.value) fail(file, `attribute offsets (${attr.name})`);
                    const spliced = text.slice(0, attr.valueStart) + attr.value + text.slice(attr.valueEnd);
                    if (spliced !== text) fail(file, `attribute splice (${attr.name})`);
                }
                check(node.children);
            } else if (text.slice(node.start, node.end) !== node.value) {
                fail(file, `${node.type} offsets @${node.start}`);
            }
        }
    };
    check(tree.nodes);

    // Everything unfolded draws without throwing
    const all = new Set();
    const collect = (nodes) => nodes.forEach((n) => { if (n.type === 'element') { all.add(n.key); collect(n.children); } });
    collect(tree.nodes);
    rows(tree, all);
}

console.log(`${files.length} files, ${elements} elements, ${failed} failures`);
process.exit(failed ? 1 : 0);
