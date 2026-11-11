# Browser regression checks

Each `*.cjs` file here is a standalone jsdom check of the viewer or editor
JavaScript; none needs a Cacti server. They need Node.js 24.15 or later on the
24 line, or 26 and later, because the locked jsdom supports no other releases,
and use the exact jsdom and jQuery versions locked in `package-lock.json`.
Several load Cacti's real jQuery UI from `CACTI_JS_DIR`, a Cacti core
checkout's `include/js` directory.

Run the whole suite from this directory:

```sh
npm ci --ignore-scripts
CACTI_JS_DIR=/path/to/cacti/include/js npm test
```

`npm test` runs `run-tests.cjs`, which executes every `*.cjs` file in sorted
order in its own Node process, prints each file name and stops at the first
failure with a non-zero exit. It also fails when `CACTI_JS_DIR` lacks
`jquery-ui.js` or no test files are found. CI runs the same commands against
the Cacti 1.2.x checkout. To run one file, use
`CACTI_JS_DIR=/path/to/cacti/include/js node ClickableHoverTest.cjs`.

These checks do not replace manual browser testing or the PHP test suite.

`EditorWorkflowTest.cjs` exercises the compact link workflow: saved interface
display, selection retention, explicit application of an interface,
main/advanced fields, friendly names and retry after a failed
interface-summary request.

`EditorPickerLayeringTest.cjs` loads Cacti's real jQuery UI and invokes the editor's
actual `show_dialog()` callback. It checks menu ownership on opening and reopening.
It needs `CACTI_JS_DIR`.

`EditorPickerReopeningTest.cjs` also needs `CACTI_JS_DIR`. It exercises real
jQuery UI focus/blur timing, Browse all, input/arrow reopening, changing an
existing selection and typed filtering in both link and node pickers.
