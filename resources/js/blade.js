export default {
    renderBladeTemplate(template, variables) {
        let result = template;

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
        const echoDefaultRegex = /{{\s*\$(\w+)\s*\?\?\s*['"]([^'"]*)['"]\s*}}/g;
        result = result.replace(echoDefaultRegex, (match, varName, defaultValue) => {
            const value = Object.hasOwnProperty.call(variables, varName) ? variables[varName] : defaultValue;
            return this.escapeHtml(value);
        });

        // Step 5: Handle {!! $var ?? 'default' !!} - unescaped output with default
        const rawDefaultRegex = /{!!\s*\$(\w+)\s*\?\?\s*['"]([^'"]*)['"]\s*!!}/g;
        result = result.replace(rawDefaultRegex, (match, varName, defaultValue) => {
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
