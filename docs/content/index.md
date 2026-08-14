---
seo:
  title: Ink — Headless blog package for Filament
  description: Drop a blog into any Filament 5 app. Ships admin, SEO, MCP tools, and Blade components — bring your own routes or opt into the built-in ones with a config flag.
---

::u-page-hero
#title
A drop-in blog for Filament

#description
Ships the admin, SEO components, MCP tools, and Blade components. Bring your own routes for full control — or flip a flag and get `/blog` out of the box.

#links
  :::u-button
  ---
  color: neutral
  size: xl
  to: /getting-started/installation
  trailing-icon: i-lucide-arrow-right
  ---
  Get started
  :::

  :::u-button
  ---
  color: neutral
  icon: simple-icons:github
  size: xl
  to: https://github.com/relaticle/ink
  variant: outline
  ---
  Source on GitHub
  :::
::

::u-page-section
#title
What's included

#features
  :::u-page-feature
  ---
  icon: i-lucide-layout-dashboard
  ---
  #title
  Filament admin
  
  #description
  Posts, categories, tags. Markdown editor, draft and scheduled UX, SEO fields, bulk publish actions.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-search
  ---
  #title
  SEO baked in
  
  #description
  Meta tags, Open Graph, Twitter Cards, JSON-LD `BlogPosting`, RSS feed, and a sitemap helper — all publishable.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-bot
  ---
  #title
  MCP tools for AI
  
  #description
  14 Model Context Protocol tools so AI agents can write, illustrate, and publish posts. Sanctum-gated and markdown-sanitized.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-hash
  ---
  #title
  Tags taxonomy
  
  #description
  Many-to-many tags with admin UI and a public archive at `/blog/tag/{slug}`. All behind one config flag.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-image
  ---
  #title
  MediaLibrary ready
  
  #description
  Featured-image uploads switch to `SpatieMediaLibraryFileUpload` when you install the package and flip the flag.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-paintbrush
  ---
  #title
  Tailwind components
  
  #description
  Post card, header, body, related posts, preview banner. Dark mode out of the box. Publish and customize.
  :::
::
