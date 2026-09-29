# Radar Brasil — Frontend

## API local

Copie `.env.example` para `.env.local` e mantenha `VITE_API_URL=http://localhost:8000` para o backend Docker local. Inicie o frontend com `npm run dev`.

A variável contém somente a URL pública do backend, sem o sufixo `/api`. Variáveis `VITE_` ficam expostas ao navegador e não devem conter segredos. Reinicie o Vite após alterar o ambiente; o build incorpora o valor configurado.

O mapa carrega os metadados de `GET /api/states`. A geometria permanece local e a associação usa o código IBGE. A requisição é cancelada ao desmontar o componente e tem limite de 15 segundos; falhas mantêm o espaço do mapa e exibem uma mensagem, sem usar nomes locais como fallback.

## Template original

This template provides a minimal setup to get React working in Vite with HMR and some Oxlint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Oxc](https://oxc.rs)
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/)

## React Compiler

The React Compiler is not enabled on this template because of its impact on dev & build performances. To add it, see [this documentation](https://react.dev/learn/react-compiler/installation).

## Expanding the Oxlint configuration

If you are developing a production application, we recommend enabling type-aware lint rules by installing `oxlint-tsgolint` and editing `.oxlintrc.json`:

```json
{
  "$schema": "./node_modules/oxlint/configuration_schema.json",
  "plugins": ["react", "typescript", "oxc"],
  "options": {
    "typeAware": true
  },
  "rules": {
    "react/rules-of-hooks": "error",
    "react/only-export-components": ["warn", { "allowConstantExport": true }]
  }
}
```

See the [Oxlint rules documentation](https://oxc.rs/docs/guide/usage/linter/rules) for the full list of rules and categories.
