# Hierarchical Term Selector (HTS)

HTS keeps the original Drupal taxonomy reference workflow: editors select taxonomy terms through cascading, parent-dependent selectors instead of a flat autocomplete or select list. When an editor chooses a parent term, HTS updates the next selector so it only offers the relevant child terms; the 2.x line preserves that behavior while adding modern Drupal compatibility, Views exposed filters, hierarchy-aware output, optional BEF support, and optional Select2 support.

## Support and upgrade matrix

| HTS line | Drupal core | Plugin discovery | Status in this workspace |
|---|---|---|---|
| 1.x | Drupal 9 and 10 | Annotations | Backport blocked until the maintained 1.x workspace is supplied |
| 2.x | Drupal 10.3, 11, and 12 | PHP attributes | Current source line |

The 2.x code is exercised by the Drupal 11 and Drupal 10 test harnesses. Dedicated Drupal 10.3-min and Drupal 12 harnesses are still required before release. The CI file reports those rows as blocked instead of treating them as passes.

Upgrade each release line independently. Before moving from 1.x to 2.x, update the site to Drupal 10.3 or newer, review custom code that extends HTS plugins, update configuration, and run database updates plus the site's Kernel and FunctionalJavascript suites. The Composer package name for 2.x is `drupal/hts`.

## Requirements

- Drupal core `^10.3 || ^11 || ^12` for the 2.x line.
- Core Field and Taxonomy modules.
- No optional module is required for the plain HTML select widget.

## Installation

```sh
composer require drupal/hts
drush en hts
```

Configure a taxonomy entity-reference field to use **Hierarchical Term Selector** as its form widget. Existing sites can keep using HTS as a field widget; the newer integrations are additive.

## Features

- Cascading taxonomy term field widget using one selector per hierarchy level.
- Parent-dependent option lists: choosing a parent term updates the next level with its child terms.
- AJAX loading/resetting of descendant selectors, preserving the underlying entity-reference value.
- Fixed hierarchy depth or unlimited depth when hierarchy depth is `0`.
- Per-level labels, descriptions, and empty-option labels.
- Support for single-value and multi-value taxonomy reference fields, following field cardinality.
- Deterministic first-parent ancestry for terms with multiple parents.
- Views exposed filter support for taxonomy reference fields.
- Views field for access-safe, translated hierarchy paths.
- Breadcrumb, hierarchical-list, and A1 leaf-only field formatters.

A1 leaf-only behavior keeps selected terms that are not ancestors of another selected term. A lone selected parent therefore renders; when both parent and child are selected, the child wins.

## Optional integrations

- [Select2](https://www.drupal.org/project/select2): enables the Select2 widget type and the `hts-select2` class. HTS detects the module at runtime and falls back to plain selects when it is absent.
- [Better Exposed Filters](https://www.drupal.org/project/better_exposed_filters): enables the BEF 7 HTS filter wrapper on the 2.x line. Core Views exposed filters continue to use HTS without BEF.
- [Search API](https://www.drupal.org/project/search_api): adds the HTS cascading exposed filter to Views whose base table is a Search API index. It renders the same selector and applies the resolved subtree as a Search API `IN` condition. HTS registers this filter only when Search API is installed and does not require the "Add hierarchy" processor, because HTS already expands the selection to the full subtree.
- [Facets](https://www.drupal.org/project/facets): adds the **HTS hierarchical** facet widget. Select it on a Search API-backed facet over a taxonomy field to render the facet results as a nested, cascading taxonomy selector (parent then child) instead of a flat checkbox or link list. The widget is URL-driven like all Facets widgets, so it needs no JavaScript, and HTS only ships the widget plugin, so the file is inert on sites without Facets. For a complete hierarchical facet, configure:
  - Facets **Hierarchy** processor on the facet — nests children under their parents. Without it the widget renders a flat list.
  - Facets **Transform entity ID to label** processor on the facet — shows term names instead of raw term IDs.
  - Search API **Index hierarchy** processor on the indexed term field — indexes each term's ancestors, so selecting a parent facet narrows the results to the whole subtree (unlike the exposed filter, the facet applies the single clicked value, so ancestor indexing is what makes a parent match its descendants).

Optional modules are suggestions, not Composer requirements.

## Views depth and subtree behavior

Use core's `taxonomy_index_tid_depth` filter or argument when a finite descendant depth is sufficient. HTS does not provide an `hts_subtree` argument in 2.x: under assumption A2, that plugin remains deferred until an unbounded-descendant requirement is confirmed. This avoids duplicating core behavior.

## Multiple-parent policy

`first` is the runtime default and follows parent field storage order. The hierarchical-list formatter may use `all` to render every stored ancestry path. Breadcrumb, widget, Views-field, and leaf-only behavior remain deterministic-first except that leaf-only checks all ancestry paths for A1 correctness.

## Quality and testing

Module-owned gates are defined in `phpcs.xml`, `phpmd.xml`, and `phpstan.neon`. They contain no baselines, ignored errors, ignored patterns, warning exclusions, or source suppressions. `.gitlab-ci.yml` drives the available Drupal 11 and Drupal 10 harness layouts and explicitly reports missing Drupal 10.3-min, Drupal 12, BEF, and Select2 matrix rows.

FunctionalJavascript tests require a WebDriver configured through `MINK_DRIVER_ARGS_WEBDRIVER`.

## Help and support

Drupal's module help page summarizes HTS behavior. Report defects and feature requests in the [HTS issue queue](https://www.drupal.org/project/issues/hts).

## Maintainer

[cesarmsfelipe](https://www.drupal.org/u/cesarmsfelipe)

## License

GNU General Public License, version 2 or later. See `LICENSE`.
