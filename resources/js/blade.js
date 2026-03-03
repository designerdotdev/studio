export default {
    renderBladeTemplate(template, variables) {
        let result = template;

        // Step 0: Handle @foreach loops (must run before @if and variable replacement)
        result = this.renderForeach(result, variables);

        // Step 1: Handle @if($var ?? false) ... @endif blocks
        // This pattern checks if a variable is truthy
        const ifFalseRegex = /@if\s*\(\s*\$(\w+)\s*\?\?\s*false\s*\)([\s\S]*?)@endif/g;
        result = result.replace(ifFalseRegex, (match, varName, content) => {
            const value = variables[varName];
            // Show content only if variable exists and is truthy
            if (value && value !== '' && value !== '0' && value !== 'false') {
                return content;
            }
            return '';
        });

        // Step 2: Handle @if($var) ... @endif blocks (simple truthy check)
        const ifSimpleRegex = /@if\s*\(\s*\$(\w+)\s*\)([\s\S]*?)@endif/g;
        result = result.replace(ifSimpleRegex, (match, varName, content) => {
            const value = variables[varName];
            if (value && value !== '' && value !== '0' && value !== 'false') {
                return content;
            }
            return '';
        });

        // Step 3: Handle @if($var ?? 'default') ... @endif blocks
        const ifDefaultRegex = /@if\s*\(\s*\$(\w+)\s*\?\?\s*['"]([^'"]*)['"]\s*\)([\s\S]*?)@endif/g;
        result = result.replace(ifDefaultRegex, (match, varName, defaultValue, content) => {
            const value = variables[varName] ?? defaultValue;
            if (value && value !== '' && value !== '0' && value !== 'false') {
                return content;
            }
            return '';
        });

        // Step 4: Handle {{ $var ?? 'default' }} - escaped output with default
        // Single-quoted defaults (may contain double quotes inside)
        const echoDefaultSingleRegex = /{{\s*\$(\w+)\s*\?\?\s*'([^']*)'\s*}}/g;
        result = result.replace(echoDefaultSingleRegex, (match, varName, defaultValue) => {
            const value = Object.hasOwnProperty.call(variables, varName) ? variables[varName] : defaultValue;
            return this.escapeHtml(value);
        });
        // Double-quoted defaults (may contain single quotes inside)
        const echoDefaultDoubleRegex = /{{\s*\$(\w+)\s*\?\?\s*"([^"]*)"\s*}}/g;
        result = result.replace(echoDefaultDoubleRegex, (match, varName, defaultValue) => {
            const value = Object.hasOwnProperty.call(variables, varName) ? variables[varName] : defaultValue;
            return this.escapeHtml(value);
        });

        // Step 5: Handle {!! $var ?? 'default' !!} - unescaped output with default
        // Single-quoted defaults (may contain double quotes inside)
        const rawDefaultSingleRegex = /{!!\s*\$(\w+)\s*\?\?\s*'([^']*)'\s*!!}/g;
        result = result.replace(rawDefaultSingleRegex, (match, varName, defaultValue) => {
            const value = Object.hasOwnProperty.call(variables, varName) ? variables[varName] : defaultValue;
            return value;
        });
        // Double-quoted defaults (may contain single quotes inside)
        const rawDefaultDoubleRegex = /{!!\s*\$(\w+)\s*\?\?\s*"([^"]*)"\s*!!}/g;
        result = result.replace(rawDefaultDoubleRegex, (match, varName, defaultValue) => {
            const value = Object.hasOwnProperty.call(variables, varName) ? variables[varName] : defaultValue;
            return value;
        });

        // Step 6: Handle {{ $var }} - escaped output without default
        const echoSimpleRegex = /{{\s*\$(\w+)\s*}}/g;
        result = result.replace(echoSimpleRegex, (match, varName) => {
            const value = variables[varName] ?? '';
            return this.escapeHtml(value);
        });

        // Step 7: Handle {!! $var !!} - unescaped output without default
        const rawSimpleRegex = /{!!\s*\$(\w+)\s*!!}/g;
        result = result.replace(rawSimpleRegex, (match, varName) => {
            return variables[varName] ?? '';
        });

        return result;
    },

    renderForeach(template, variables) {
        // Match @foreach($collection as $item) ... @endforeach
        // Uses balanced depth counting to handle nested @foreach correctly
        let result = template;
        let safety = 0;

        while (safety < 50) {
            safety++;

            // Find the first @foreach($varName as $itemName) with a simple variable
            const startRegex = /@foreach\s*\(\s*\$(\w+)\s+as\s+\$(\w+)\s*\)/;
            const startMatch = result.match(startRegex);
            if (!startMatch) break;

            const collectionName = startMatch[1];
            const itemName = startMatch[2];
            const startIndex = startMatch.index;
            const afterStart = startIndex + startMatch[0].length;

            // Find the matching @endforeach by counting depth
            let depth = 1;
            let searchPos = afterStart;
            let endPos = -1;

            while (depth > 0 && searchPos < result.length) {
                const nextForeach = result.indexOf('@foreach', searchPos);
                const nextEndforeach = result.indexOf('@endforeach', searchPos);

                if (nextEndforeach === -1) break;

                if (nextForeach !== -1 && nextForeach < nextEndforeach) {
                    depth++;
                    searchPos = nextForeach + 8;
                } else {
                    depth--;
                    if (depth === 0) {
                        endPos = nextEndforeach;
                    }
                    searchPos = nextEndforeach + 11;
                }
            }

            if (endPos === -1) break;

            const loopBody = result.substring(afterStart, endPos);
            const fullMatch = result.substring(startIndex, endPos + 11);

            const collection = variables[collectionName];
            if (!Array.isArray(collection)) {
                result = result.replace(fullMatch, '');
                continue;
            }

            let rendered = '';
            for (let i = 0; i < collection.length; i++) {
                const item = collection[i];
                let itemHtml = loopBody;

                // Replace {{ $item['key'] }} — escaped output
                itemHtml = itemHtml.replace(new RegExp('\\{\\{\\s*\\$' + itemName + "\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]\\s*\\}\\}", 'g'), (m, key) => {
                    const val = item[key] ?? '';
                    return this.escapeHtml(val);
                });

                // Replace {!! $item['key'] !!} — unescaped output
                itemHtml = itemHtml.replace(new RegExp('\\{!!\\s*\\$' + itemName + "\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]\\s*!!\\}", 'g'), (m, key) => {
                    return item[key] ?? '';
                });

                // Replace {{ $item['key'] ?? 'default' }} — escaped with default
                itemHtml = itemHtml.replace(new RegExp('\\{\\{\\s*\\$' + itemName + "\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]\\s*\\?\\?\\s*['\"]([^'\"]*)['\"]\\s*\\}\\}", 'g'), (m, key, def) => {
                    const val = item[key] ?? def;
                    return this.escapeHtml(val);
                });

                // Handle @if(count($item['children']) > 0) ... @else ... @endif style checks
                // (must run before the simpler @if check below)
                itemHtml = itemHtml.replace(new RegExp('@if\\s*\\(\\s*count\\s*\\(\\s*\\$' + itemName + "\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]\\s*\\)\\s*>\\s*0\\s*\\)([\\s\\S]*?)@endif", 'g'), (m, key, fullContent) => {
                    const val = item[key];
                    const elseParts = fullContent.split('@else');
                    const ifContent = elseParts[0];
                    const elseContent = elseParts.length > 1 ? elseParts[1] : '';
                    if (Array.isArray(val) && val.length > 0) {
                        return ifContent;
                    }
                    return elseContent;
                });

                // Handle @if($item['key']) ... @else ... @endif inside loops
                itemHtml = itemHtml.replace(new RegExp('@if\\s*\\(\\s*\\$' + itemName + "\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]\\s*\\)([\\s\\S]*?)@endif", 'g'), (m, key, fullContent) => {
                    const val = item[key];
                    const elseParts = fullContent.split('@else');
                    const ifContent = elseParts[0];
                    const elseContent = elseParts.length > 1 ? elseParts[1] : '';
                    if (val && val !== '' && val !== '0' && val !== 'false' && !(Array.isArray(val) && val.length === 0)) {
                        return ifContent;
                    }
                    return elseContent;
                });

                // Handle nested @foreach for children: @foreach($item['children'] as $child)
                itemHtml = itemHtml.replace(new RegExp('@foreach\\s*\\(\\s*\\$' + itemName + "\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]\\s+as\\s+\\$(\\w+)\\s*\\)([\\s\\S]*?)@endforeach", 'g'), (m, key, childName, childBody) => {
                    const children = item[key];
                    if (!Array.isArray(children)) return '';

                    let childRendered = '';
                    for (let j = 0; j < children.length; j++) {
                        const child = children[j];
                        let childHtml = childBody;

                        childHtml = childHtml.replace(new RegExp('\\{\\{\\s*\\$' + childName + "\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]\\s*\\}\\}", 'g'), (cm, ck) => {
                            return this.escapeHtml(child[ck] ?? '');
                        });

                        childHtml = childHtml.replace(new RegExp('\\{!!\\s*\\$' + childName + "\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]\\s*!!\\}", 'g'), (cm, ck) => {
                            return child[ck] ?? '';
                        });

                        childRendered += childHtml;
                    }
                    return childRendered;
                });

                rendered += itemHtml;
            }

            result = result.replace(fullMatch, rendered);
        }

        return result;
    },

    escapeHtml(text) {
        if (text == null) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
};
