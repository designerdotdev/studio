export default {
    renderBladeTemplate(template, variables) {
        // Regular expression to match all instances of Laravel Blade syntax in the template
        const regex = /{{\s*\$(\w+)\s*\?\?\s*['"]([^'"]*)['"]\s*}}|{!!\s*\$(\w+)\s*\?\?\s*['"]([^'"]*)['"]\s*!!}/g;

        // Replace all instances in the template using the replacer function
        return template.replace(regex, (match, varName1, defaultValue1, varName2, defaultValue2) =>
            this.replacer(variables, match, varName1, defaultValue1, varName2, defaultValue2)
        );
    },
    escapeHtml(text) {
        if (text == null) return ''; // Handle null and undefined
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    },
    replacer(variables, match, varName1, defaultValue1, varName2, defaultValue2) {
        // Determine if the match is for HTML or text
        const isHtml = !!varName2;
        const varName = varName1 || varName2;
        const defaultValue = defaultValue1 || defaultValue2;

        // Get the value from the variables object or use the default value
        const value = Object.hasOwnProperty.call(variables, varName) ? variables[varName] : defaultValue;

        // If the output is HTML, return as is; otherwise, escape HTML characters
        return isHtml ? value : this.escapeHtml(value);
    }
};
