import { defineConfig } from 'eslint/config';
import globals from 'globals';
import babelParser from '@babel/eslint-parser';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import js from '@eslint/js';
import * as fs from 'fs';
import { FlatCompat } from '@eslint/eslintrc';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const compat = new FlatCompat({
    baseDirectory: __dirname,
    recommendedConfig: js.configs.recommended,
    allConfig: js.configs.all,
});

const configs = [{
    extends: compat.extends('airbnb'),

    languageOptions: {
        globals: {
            ...globals.amd,
            ...globals.commonjs,
            ...globals.browser,
            ...globals.jquery,
        },

        parser: babelParser,
        // ecmaVersion: 5,
        // sourceType: "script",

        parserOptions: {
            requireConfigFile: false,
        },
    },

    rules: {
        'func-names': 0,
        'global-require': 0,
        'max-len': 0,
        'no-new': 0,
        'no-continue': 0,
        indent: ['error', 4],
        'import/no-extraneous-dependencies': 0,

        'no-param-reassign': ['error', {
            props: false,
        }],

        'no-use-before-define': ['error', {
            functions: false,
        }],

        'import/prefer-default-export': 'off',
    },
}];

if (fs.existsSync('.eslintrc.json')) {
    configs.push(JSON.parse(fs.readFileSync('.eslintrc.json')));
}

export default defineConfig(configs);
