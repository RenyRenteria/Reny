# Project 4 Photos Actions

Date: 2026-06-19
Issue: #62 Proyecto 4: Definir y completar acciones avanzadas de Photos
Branch: dos/photos-actions-62

## Goal

Complete the visible `/photos` action surface without adding fake controls:

- Next and previous navigation inside the lightbox.
- Deep links for individual photos via `/photos?photo={slug}`.
- Share action with Web Share API and clipboard/manual fallback.
- Save/bookmark action persisted in browser storage.
- Visible broken-image states in the masonry and lightbox.

## Scope Decisions

- Photo comments stay out of this pass because the repo has no photo comment model or endpoints yet.
- New Royal gates stay out of this pass because `/photos` already filters CMS content through `visibleFor($user)` before rendering.
- CMS photos use the editorial slug for stable deep links. Static fallback photos derive a slug from their title.

## Verification

- Feature tests should cover the rendered action contract and absence of comment controls.
- Vite build should pass after the JS/CSS changes.
- Browser smoke should open `/photos`, open a tile, navigate next/previous, use the deep link query, and confirm no visible broken layout.
