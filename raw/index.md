---
canonical_url: "https://relaticle.github.io"
---
<u-page-hero>
<template v-slot:title="">

A drop-in blog for Filament

</template>

<template v-slot:description="">

Ships the admin, SEO components, MCP tools, and Blade components. Bring your own routes for full control — or flip a flag and get `/blog` out of the box.

</template>

<template v-slot:links="">
<u-button color="neutral" size="xl" to="https://relaticle.github.io/getting-started/installation" trailing-icon="i-lucide-arrow-right">

Get started

</u-button>

<u-button color="neutral" size="xl" to="https://github.com/relaticle/ink" icon="simple-icons:github" variant="outline">

Source on GitHub

</u-button>
</template>
</u-page-hero>

<u-page-section>
<template v-slot:title="">

What's included

</template>

<template v-slot:features="">
<u-page-feature icon="i-lucide-layout-dashboard">
<template v-slot:title="">

Filament admin

</template>

<template v-slot:description="">

Posts, categories, tags. Markdown editor, draft and scheduled UX, SEO fields, bulk publish actions.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-search">
<template v-slot:title="">

SEO baked in

</template>

<template v-slot:description="">

Meta tags, Open Graph, Twitter Cards, JSON-LD `BlogPosting`, RSS feed, and a sitemap helper — all publishable.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-bot">
<template v-slot:title="">

MCP tools for AI

</template>

<template v-slot:description="">

14 Model Context Protocol tools so AI agents can write, illustrate, and publish posts. Sanctum-gated and markdown-sanitized.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-hash">
<template v-slot:title="">

Tags taxonomy

</template>

<template v-slot:description="">

Many-to-many tags with admin UI and a public archive at `/blog/tag/{slug}`. All behind one config flag.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-image">
<template v-slot:title="">

MediaLibrary ready

</template>

<template v-slot:description="">

Featured-image uploads switch to `SpatieMediaLibraryFileUpload` when you install the package and flip the flag.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-paintbrush">
<template v-slot:title="">

Tailwind components

</template>

<template v-slot:description="">

Post card, header, body, related posts, preview banner. Dark mode out of the box. Publish and customize.

</template>
</u-page-feature>
</template>
</u-page-section>

## Resources for Agents

- [API catalog: every service document this site publishes](https://relaticle.github.io/.well-known/api-catalog)
- [Sitemap (XML)](https://relaticle.github.io/sitemap.xml)
- [Sitemap (Markdown): every page on the site](https://relaticle.github.io/sitemap.md)
- [MCP server card: MCP endpoint at https://relaticle.github.io/mcp](https://relaticle.github.io/.well-known/mcp/server-card.json)
- [MCP endpoint (streamable HTTP)](https://relaticle.github.io/mcp)
- [llms.txt: index of the documentation for LLMs](https://relaticle.github.io/llms.txt)
- [llms-full.txt: the full documentation as a single file](https://relaticle.github.io/llms-full.txt)
- [OpenAPI document: every route this site serves to agents](https://relaticle.github.io/openapi.json)


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
