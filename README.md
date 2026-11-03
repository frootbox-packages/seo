# frootbox/seo

SEO for the [Frootbox CMS](https://github.com/Frootbox/frootbox):

- `/sitemap.xml` with every published address in every language, with
  `hreflang` alternates, without locked pages and pages excluded from
  search engines
- `/robots.txt` that points to the sitemap
- Open Graph and Twitter card tags, with the first image of the page or
  the image of an object such as a news article
- Structured data as JSON-LD: `WebSite` on the start page,
  `BreadcrumbList` on the others
- The query `frootbox/seo/page.check` and the screen "SEO" in the backend
  with hints for each page: title and description missing, too short,
  too long or the same as on another page

The description of a page itself belongs to the core, so it can be
written without this module.

MIT licensed.
