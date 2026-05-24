# CERNIX Laravel 11 Exam Access & Verification System

CERNIX is a secure examination access and verification system for Adekunle Ajasin University. It provides student exam pass generation, Remita-backed payment verification, QR-based access control, examiner scanning, admin monitoring, and audit/verification logs.

## Local Demo Data

The local/demo student credential list is maintained in [docs/demo-data.md](docs/demo-data.md). The registration page intentionally shows only three examples so the UI stays clean; all 20 demo records remain available through manual entry.

Demo passport photos are stored locally in `public/demo-passports/` and are rendered through passport-style image frames. The setup command verifies the supplied local images and maps them to demo records:

```bash
php artisan cernix:seed-demo-passports
```

## Project Media

Project/testing context images supplied by the project owner are documented in [docs/project-media.md](docs/project-media.md) and stored under `public/docs/project-media/`. They are not used as student passport/demo identity photos.

## Roles And Permissions

The first role-system phase adds the Super Admin foundation only. The implementation notes and future role roadmap are maintained in [docs/roles-and-permissions.md](docs/roles-and-permissions.md).

## Python Intelligence Module

CERNIX includes an optional Python-powered intelligence module at `python_services/risk_analyzer/`. Laravel remains the main web application; the Python module analyzes exported verification/audit/payment-style logs, detects suspicious scan patterns, scores student/examiner/device/IP risk, and produces admin-readable JSON/HTML reports.

The module does not handle authentication, payment verification, QR generation, QR scanning, cryptographic secrets, token lifecycle logic, or production secrets. It is optional, offline-friendly, and works from safe JSON exports.

Run the sample analyzer with:

```bash
python python_services/risk_analyzer/analyze.py
```

Laravel can export safe scan log data for the analyzer with:

```bash
php artisan cernix:export-risk-data
```

## Deployment

Render Docker deployment notes are maintained in [docs/render-deployment.md](docs/render-deployment.md). The Docker setup uses PHP 8.4, PostgreSQL, local committed demo passport images, and Render's `PORT` environment variable.

---

## Laravel Base

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
