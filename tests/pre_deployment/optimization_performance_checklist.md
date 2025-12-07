## ⚙️ Optimization & Performance Checklist

- [✅] Remove unused code, variables, functions, and imports/includes.
- [✅] Optimize database queries (use proper indexing, avoid N+1, use joins and pagination).
- [✅] Minimize memory usage and avoid memory leaks in long-running scripts or daemons.
- [✅] Use caching where appropriate (e.g., expensive DB queries, API responses, static assets).
- [✅] Profile and benchmark critical code paths (e.g., login, dashboard, reporting) via per-request timing in `core/bootstrap.php` (no changes to business logic).
- [✅] Ensure asynchronous operations are handled efficiently (e.g., queues, background jobs).
- [✅] Avoid blocking operations in performance-critical areas (e.g., large file I/O on main request) by limiting heavy file I/O to backup/export utilities instead of login/dashboard/reporting flows.
- [✅] Compress and minify assets (CSS, JS) and optimize images for web delivery.

