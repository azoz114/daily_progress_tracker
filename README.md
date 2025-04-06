# Daily Progress Tracker

The **Daily Progress Tracker** is a custom Drupal module that allows site administrators and users to track task progress on a daily basis. It’s built with extensibility in mind, using a service-based architecture to support future integrations like data visualizations or external reporting.

---

## 🚀 Features

- Admin UI to add, edit, and delete trackable tasks
- Daily check-in system to record task progress
- Permissions-based access control
- Service layer for future extensions (e.g. chart data handling)
- Modular and maintainable code following Drupal 9/10 best practices

---

## 🧱 Technical Overview

- Custom routes defined in `daily_progress_tracker.routing.yml`
- Permissions managed in `daily_progress_tracker.permissions.yml`
- Controller logic in `DailyProgressController.php`
- Services defined in `daily_progress_tracker.services.yml`
- Caching & cacheability metadata handled to support decoupled frontends
- Frontend assets declared in `daily_progress_tracker.libraries.yml`

---

## 🔐 Permissions

| Permission | Description |
|------------|-------------|
| `access daily progress tracker` | Allows users to view and check in to tasks |
| `administer daily progress tracker` | Full admin access to create/edit/delete tasks |

---

## 🧭 Routes

| Path | Description |
|------|-------------|
| `/admin/content/daily-progress` | View list of progress tasks |
| `/admin/content/daily-progress/add` | Add new task |
| `/admin/content/daily-progress/{id}/checkin` | Check in to a task |
| `/admin/content/daily-progress/{id}/edit` | Edit task |
| `/admin/content/daily-progress/{id}/delete` | Delete task |

---

## 📦 Installation

1. Clone the repo into your `modules/custom` directory:

   ```bash
   git clone https://github.com/azoz114/daily_progress_tracker.git
