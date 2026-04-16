const fs = require('fs');
const path = require('path');

const baseDir = '/Users/anhd/Documents/wordpress/demo-pdn/app/public/wp-content/themes/lacadev-client-child/block-gutenberg';

const blocks = fs.readdirSync(baseDir).filter(f => fs.statSync(path.join(baseDir, f)).isDirectory());

blocks.forEach(block => {
    const editJsPath = path.join(baseDir, block, 'edit.js');
    if (!fs.existsSync(editJsPath)) return;

    let editJs = fs.readFileSync(editJsPath, 'utf8');
    
    // Fix ", bgColor" at the start of a line or after another comma
    // e.g. "   ,\n    , bgColor" -> "   ,\n      bgColor"
    let modified = false;

    // Regex to match ",\s*," -> ","
    // Also "}\s*,\s*bgColor" shouldn't happen, but just in case.
    
    // The previous script did: 
    // let newAttrs = attrs.replace(/backgroundColor\s*,?/, '');
    // return `const { ${newAttrs}, bgColor, bgOpacity } = attributes;`;
    
    // So if newAttrs ended with a comma like "a, b, \n", it became "const { a, b, \n, bgColor, bgOpacity } = attributes;"
    // This is valid JS! 
    // Wait, why did video-feedback-block fail?
    // Because in video-feedback-block it was:
    // showNavigation,
    // , bgColor, bgOpacity } = attributes;
    // This is "showNavigation,\n    , bgColor"
    // Wait, "var { a,, b } = obj" is INVALID syntax in object destructuring! Array destructuring allows it, object destructuring doesn't!
    
    const fixRegex = /,\s*,\s*bgColor/g;
    if (fixRegex.test(editJs)) {
        editJs = editJs.replace(fixRegex, ',\n    bgColor');
        modified = true;
    }
    
    // Sometimes it might just be trailing comma followed by newline and then comma
    const fixRegex2 = /,\s*\n\s*,\s*bgColor/g;
    if (fixRegex2.test(editJs)) {
        editJs = editJs.replace(fixRegex2, ',\n    bgColor');
        modified = true;
    }
    
    // Let's just use a broader regex to replace multiple consecutive commas with a single comma
    const fixRegex3 = /,(?:\s*,)+\s*bgColor/g;
    if (fixRegex3.test(editJs)) {
        editJs = editJs.replace(fixRegex3, ',\n    bgColor');
        modified = true;
    }
    
    // Check if it's "const { heading, bgColor, steps , bgColor, bgOpacity } = attributes;"
    // Duplicate bgColor!
    const fixRegex4 = /bgColor([^}]*)bgColor/g;
    if (fixRegex4.test(editJs) && editJs.includes('} = attributes')) {
        // Just remove the first bgColor
        editJs = editJs.replace(/bgColor\s*,/, '');
        modified = true;
    }

    if (modified) {
        fs.writeFileSync(editJsPath, editJs);
        console.log(`Fixed commas for ${block}`);
    }
});
