=== WP Post Rank ===
Contributors: yourdevice
Tags: posts, sort, custom field, rank, gutenberg, latest posts
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 1.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Numerisches Attribut „Rang“ für Beiträge (höher = weiter oben). Admin-sortierbar, Shortcode **[posts_by_rank]**, **Blocks** (Neueste Beiträge / Query-Loop) via Klasse `order-by-rank`. v1.2.2 erzwingt die Sortierung auf **SQL-Ebene** für diese Blöcke – HTML/Layout bleiben unverändert.

== Verwendung ==
1. Beim Beitrag **Rang** setzen.
2. Im Block **Neueste Beiträge** oder **Query-Loop** → **Erweitert → Zusätzliche CSS-Klasse(n)**: `order-by-rank`.
3. Cache leeren.

== Changelog ==
= 1.2.2 =
* Harte Durchsetzung per `pre_get_posts` + SQL-Filtern (`posts_orderby`, `posts_join`, `posts_groupby`) nur für Block-Queries mit Flag – so greift Rang-Sortierung auch wenn Themes eigene Order setzen.

= 1.2.1 =
* Sortierung nur über Block-Args (Layout bleibt unberührt).

= 1.2.0 =
* (früher) kompletter Neu-Render – verworfen.

= 1.1.1/1.1.0 =
* Block-Unterstützung & Prioritäten.
