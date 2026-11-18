# Changelog

## 1.0.0

First release.

- Comments on content nodes in Neos Studio, optionally on one property: a
  Comments panel (this page or all pages, open or resolved), a Comment action
  on the element handle and in the node menus, and badges for open comments
  in the document tree and the outline.
- Private to their author: every operation only sees the caller's comments.
- The operations `listComments`, `getComment`, `createComment`,
  `resolveComment`, `reopenComment` and `deleteComment` in the Neos API, and so
  as tools of the Neos MCP server, with the scopes `comments.read` and
  `comments.update`.
- Requires Neos Studio ^2.5, Neos ^9.2 and PHP ^8.4.
