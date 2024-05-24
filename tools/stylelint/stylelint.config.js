'use strict';

let config = {
    plugins: ["stylelint-prettier"],
    extends: [
        "stylelint-config-standard-scss",
        "stylelint-config-rational-order",
    ],
    rules: {
        "no-duplicate-selectors": null,
        "declaration-block-no-duplicate-properties": [
            true,
            {
                ignore: ["consecutive-duplicates-with-different-syntaxes"]
            }
        ],
        "no-descending-specificity": null,
        "selector-class-pattern": [
            "^[a-z0-9\\-_]+$",
            { message: "Class selector should be written in lowercase with hyphens (selector-class-pattern)" }
        ],
        "selector-id-pattern": [
            "^[a-z0-9\\-_]+$",
            { message: "ID selector should be written in lowercase with hyphens (selector-id-pattern)" }
        ],
        "custom-property-pattern": null,
        "scss/dollar-variable-pattern": null,
        "comment-whitespace-inside": "always",
        "scss/double-slash-comment-whitespace-inside": "always",
        "font-family-no-missing-generic-family-keyword": [
            true,
            { "ignoreFontFamilies": ["icomoon"] }
        ],
        "declaration-empty-line-before": null,
        "at-rule-empty-line-before": [
            "always",
            {
                except: [
                    "blockless-after-same-name-blockless",
                    "first-nested"
                ],
                ignore: [
                    "after-comment"
                ],
                ignoreAtRules: [
                    "include",
                    "extend",
                    "else"
                ]
            }
        ],
        "prettier/prettier": [
            true,
            {
                singleQuote: true,
                tabWidth: 4,
                printWidth: 120,
            }
        ],
    }
};

const fs = require('fs');
if (fs.existsSync('.stylelintrc')) {
    const merge = require('deepmerge')
    config = merge(config, JSON.parse(fs.readFileSync('.stylelintrc')));
}

module.exports = config;
