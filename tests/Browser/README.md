# Editor workflow regression check

This isolated DOM check exercises the compact link workflow without a Cacti
server: saved interface display, selection retention, explicit application of
an interface, main/advanced fields, friendly names and retry after a failed
interface-summary request. It uses Node.js 24, jsdom 30 and jQuery 3.7.1.

Install those two test dependencies in a temporary directory, then run with
`NODE_PATH=/path/to/temporary/node_modules node tests/Browser/EditorWorkflowTest.cjs`.
It does not replace manual browser testing or the PHP test suite.

`EditorPickerLayeringTest.cjs` loads Cacti's real jQuery UI and invokes the editor's
actual `show_dialog()` callback. It checks menu ownership on opening and reopening.
Run it with the same dependencies and `CACTI_JS_DIR=/path/to/cacti/include/js`.
