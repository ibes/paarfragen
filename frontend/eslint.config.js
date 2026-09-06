// ESLint — Vue/TS lint. Formatting is Prettier's job (see script/format-frontend
// and the eslintConfigPrettier import below, which turns off any ESLint
// rule that would fight it).
//
// Not type-checked linting (tseslint.configs.recommendedTypeChecked): tried
// it first, but plain typescript-eslint doesn't resolve .vue SFC types the
// way vue-tsc's own language-service plugin does — main.ts's
// createApp(App) came back "unsafe argument of type error" on the very
// first file, a tool-interop problem, not a real bug. vue-tsc
// (script/check-frontend-types) already gives full type-safety on real
// code; this stays syntactic-only rather than fighting that interop for
// marginal extra coverage.
import eslint from "@eslint/js";
import eslintConfigPrettier from "eslint-config-prettier";
import tseslint from "typescript-eslint";
import pluginVue from "eslint-plugin-vue";

export default tseslint.config(
  { ignores: ["dist/**", "dev-dist/**"] },
  eslint.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs["flat/recommended"],
  {
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
        extraFileExtensions: [".vue"],
      },
    },
  },
  {
    // console.log left in shipped code is the JS analogue of Mago's
    // no-debug-symbols — a real oversight, not a style choice.
    // console.warn/error stay allowed: deliberate diagnostics (e.g. a
    // dropped, permanently-rejected offline-queue item) are legitimate,
    // not debug leftovers.
    //
    // no-undef off: plain (non-type-checked) ESLint tracks globals
    // syntactically and doesn't know about tsconfig's DOM lib
    // (@vue/tsconfig/tsconfig.dom.json) — it flagged `window` as
    // undefined even though vue-tsc already type-checks it correctly.
    // typescript-eslint's own docs recommend disabling this rule for
    // TS projects for exactly this reason; vue-tsc
    // (script/check-frontend-types) is the real authority on undefined
    // globals here.
    rules: {
      "no-console": ["error", { allow: ["warn", "error"] }],
      "no-undef": "off",
    },
  },
  eslintConfigPrettier,
);
