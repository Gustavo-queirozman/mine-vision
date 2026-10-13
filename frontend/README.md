# MineVision — frontend

Base Next.js (App Router), React, TypeScript e Tailwind CSS. A página inicial apresenta os módulos previstos; autenticação, dashboard e mapas ainda não estão implementados.

## Desenvolvimento

Requer Node.js 22 e npm. Copie `.env.example` para `.env.local`.

```sh
npm install
npm run dev
```

Abra http://localhost:3000. O backend local deve atender em http://127.0.0.1:9501.

`src/lib/api.ts` centraliza as chamadas. `/api/v1/*` é encaminhado ao backend pelo Next.js. Para endpoints protegidos, passe o header Authorization com o JWT obtido no login.

```sh
npm run typecheck
npm run build
```

Na raiz do projeto: `docker compose up --build frontend`. O Dockerfile configura o endereço interno `http://backend:9501` durante o build; após alterar esse endereço, reconstrua a imagem.

Próximos passos: login, TanStack Query, React Hook Form/Zod, componentes shadcn/ui e escolha entre Leaflet e MapLibre GL.
