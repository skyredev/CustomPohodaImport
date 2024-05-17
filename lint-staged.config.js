export default {
    "*.{js,ts}": "eslint --fix",
    "*.{js,ts,css}": "prettier --write",
    '**/*.ts?(x)': () => 'tsc -p tsconfig.json --noEmit',
}