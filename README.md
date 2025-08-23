# 📚 MangaDex API WordPress Plugin

A custom WordPress plugin to fetch and manage manga data from the [MangaDex API](https://api.mangadex.org/), store it in the WP database, and display manga readers, comments, and rankings via shortcodes.

---

## ✨ Features

- 🔗 **Integration with MangaDex API**  
  Crawl and sync manga list, chapters, genres, themes, and formats.

- 💬 **Comment system (custom)**  
  - Per-chapter comment threads (3-level nesting)  
  - Profanity & toxicity filter (Regex + Perspective API)  
  - User/Admin comment deletion  
  - Lazy loading & auto-scroll  
  - Pruning of fully-deleted threads  

- ⭐ **User Features**  
  - Reading history (per user or guest token, auto-expire after 14 days)  
  - Bookmarks  
  - Ratings  
  - Viewer statistics  

- 🎭 **User Management**  
  - Google Social Login integration (via Nextend Social Login)  
  - Custom avatar handler  

- 🏆 **Ranking & Discovery**  
  - Top manga (followed, rating, etc.)  
  - Featured, latest, search & filter shortcodes  
  - Mega-menu dropdown for genres, themes, formats  

- 🔒 **Membership Integration**  
  - VIP membership promo box (`[vip_membership_promo]`)  
  - Compatible with Paid Member Subscriptions  

---

## 📂 Shortcodes

- `[mangadex_reader]` → Manga reader with comments  
- `[mangadex_detail]` → Manga detail page  
- `[mangadex_home]` → Homepage list  
- `[mangadex_latest]` → Latest manga  
- `[mangadex_featured]` → Featured manga  
- `[manga_search_filter]` → Search + filter UI  
- `[vip_membership_promo]` → VIP upgrade promo box  

---

## 🗄️ Database Schema

The plugin automatically creates custom tables:

- `wp_manga_list` – Manga info  
- `wp_manga_chapter` – Chapters  
- `wp_manga_genre`, `wp_manga_genre_map` – Genre system  
- `wp_manga_comments` – Comments (nested with parent_id)  
- `wp_manga_read_history` – Reading history (user/guest)  
- `wp_manga_bookmarks` – Bookmarks  
- `wp_manga_users` – Synced Google users  
- (Extra: rating, viewer, top ranking tables)

---

## 🚀 Installation

1. Clone or download this repository.  
2. Copy the `mangadex-api/` folder into your WordPress `wp-content/plugins/` directory.  
3. Activate **MangaDex API** from WordPress admin.  
4. Configure settings (API key, cron, etc.) if required.  

---

## ⚠️ Disclaimer

This plugin fetches **metadata only** from MangaDex.  
It does not host or distribute copyrighted manga images.  
Use responsibly and comply with MangaDex terms of service.  

---

## 📜 License

GPL v2 or later.  
Feel free to fork, modify, and contribute.
