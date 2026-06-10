<div align="center">

<img src="public/images/logo.png" alt="ProAdvisor Support" width="320">

# Bulk Email Platform

**A self-hosted, multi-SMTP bulk email marketing platform built with Laravel.**

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-CDN_JIT-38BDF8?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/license-MIT-22C55E?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/version-v1.8.0-6366F1?style=flat-square)](https://github.com/itsalexwilliam-pixel/bulk-email-platform/releases)

</div>

---

## 📖 Overview

**ProAdvisor Support Bulk Email Platform** is a production-ready, self-hosted email marketing application. Send campaigns through any SMTP server you own — no dependency on Mailchimp, SendGrid, or SES. You control every byte of your data and deliverability infrastructure.

Built for marketers, developers, and agencies who need a reliable, white-label bulk email solution with professional UI and enterprise-grade features.

---

## ✨ Key Features

| Feature | Description |
|---------|-------------|
| 📧 **Campaign Builder** | Rich-text (Quill.js) + Raw HTML editor, file attachments, scheduling |
| 🌡️ **Email Warm-up** | Built-in warm-up schedule to protect sender reputation |
| 👥 **Audience Management** | Contacts, Groups, CSV import with per-row error feedback |
| 📬 **Multi-SMTP Routing** | Unlimited SMTP accounts with priority routing & failover |
| 📋 **Templates Library** | Reusable email templates, load into campaigns in one click |
| 📊 **Live Dashboard** | Real-time stats (queued / sent / opened / failed) via Chart.js |
| 🔓 **Compliance** | Auto unsubscribe footer, `List-Unsubscribe` headers, suppression list |
| 🎨 **7 Themes** | Light, Dark, Pro Teal, Midnight Navy, Deep Emerald, Royal Purple, Charcoal |
| 🔒 **Security** | Rate limiting, encrypted SMTP passwords, CSRF, account isolation |
| ⚙️ **Production Ready** | Laravel Queue, Supervisor config, per-minute throttle |

> See [FEATURES.md](FEATURES.md) for the complete feature list and USP table.

---

## 🖼️ Screenshots

> Themes preview — Light, Dark, Midnight Navy, Royal Purple

| Light | Dark |
|-------|------|
| Clean white SaaS dashboard | Full Tailwind dark mode |

| Midnight Navy | Royal Purple |
|---------------|--------------|
| Deep blue corporate | Elegant violet |

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 · PHP 8.2+ |
| Frontend CSS | Tailwind CSS (CDN JIT) |
| Rich text editor | Quill.js 2.0 |
| Charts | Chart.js |
| Database | MySQL / SQLite (Eloquent ORM) |
| Queue | Laravel Queue (database driver) |
| Process manager | Supervisor |
| Authentication | Laravel Breeze |
| Email transport | Symfony Mailer |

---

## 🚀 Quick Start

### Requirements

- PHP **8.2+** with extensions: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`
- Composer 2.x
- Node.js 18+ & npm (for asset compilation, optional — CDN is used)
- MySQL 8+ **or** SQLite 3

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/itsalexwilliam-pixel/bulk-email-platform.git
cd bulk-email-platform

# 2. Install PHP dependencies
composer install

# 3. Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# 4. Configure your database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=novelio_mailer
# DB_USERNAME=root
# DB_PASSWORD=

# 5. Run migrations
php artisan migrate

# 6. Create your first admin user
php artisan make:user  # or register via /register

# 7. Start the development server
php artisan serve
```

Open **http://127.0.0.1:8000** and log in.

### Queue Worker (required for sending emails)

```bash
# Development
php artisan queue:work

# Production (using included supervisor.conf)
sudo cp supervisor.conf /etc/supervisor/conf.d/novelio-worker.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start novelio-worker:*
```

---

## ⚙️ Configuration

### `.env` key settings

```dotenv
APP_NAME="ProAdvisor Support"
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=novelio_mailer
DB_USERNAME=dbuser
DB_PASSWORD=dbpassword

QUEUE_CONNECTION=database

LOG_LEVEL=error
```

### SMTP Servers

SMTP credentials are configured **inside the application** (Settings → SMTP / Sending), not in `.env`. This allows multiple SMTP accounts with per-account priority routing. Passwords are stored encrypted using Laravel's `encrypt()`.

---

## 📂 Project Structure

```
bulk-email-platform/
├── app/
│   ├── Http/Controllers/         # Campaign, Contact, SMTP, Template, Import...
│   ├── Mail/                     # CampaignMail, SingleEmailMail
│   ├── Models/                   # Campaign, Contact, Group, SmtpServer, EmailTemplate...
│   └── Support/TracksEmailContent.php   # Unsubscribe footer trait
├── database/migrations/          # All schema migrations
├── resources/views/
│   ├── campaigns/                # Create, edit, index
│   ├── contacts/                 # CRUD
│   ├── groups/
│   ├── smtp/                     # SMTP management
│   ├── templates/                # Email templates
│   ├── import/                   # CSV import + result
│   ├── layouts/app.blade.php     # Main SaaS layout (7 themes)
│   └── components/               # Sidebar, navbar, stat-card
├── routes/web.php
├── supervisor.conf               # Production queue worker config
├── FEATURES.md                   # Full feature & USP documentation
└── README.md                     # This file
```

---

## 🗺️ Roadmap

- [ ] Automation / drip sequences
- [ ] A/B split testing
- [ ] Advanced open/click tracking
- [ ] REST API for external integrations
- [ ] Team & multi-user accounts (per-account RBAC)
- [ ] Email preview across popular clients

---

## 📦 Versioning

| Version | Highlights |
|---------|-----------|
| v1.0.0 | Initial release — core campaign & contact management |
| v1.1.0 | ProAdvisor Support rebrand, SMTP encryption, Quill editor sync fixes |
| v1.2.0 | Security hardening, rate limiting, Supervisor config |
| v1.3.0 | Rich account-scoped dashboard (Chart.js) |
| v1.4.0 | Branded unsubscribe footer & List-Unsubscribe headers |
| v1.5.0 | Email Templates Library with campaign integration |
| v1.6.0 | Code refactoring — centralised account ID, real error messages |
| v1.7.0 | Per-row CSV import validation feedback |
| **v1.8.0** | **7 themes, ProAdvisor Support logo/favicon, table layouts, Load Template fix** |

---

## 🤝 Contributing

Contributions, issues and feature requests are welcome!

1. Fork the project
2. Create your feature branch: `git checkout -b feat/amazing-feature`
3. Commit your changes: `git commit -m 'feat: add amazing feature'`
4. Push to the branch: `git push origin feat/amazing-feature`
5. Open a Pull Request

---

## 🔒 Security

If you discover a security vulnerability, please email the maintainer directly instead of opening a public issue.

---

## 📄 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

---

<div align="center">

**Built with ❤️ by [ProAdvisor Support](https://github.com/itsalexwilliam-pixel)**

⭐ Star this repo if you find it useful!

</div>
