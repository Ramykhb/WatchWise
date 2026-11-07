## WatchWise email verification

In `backend/.env`, configure:

```dotenv
MAIL_MAILER=brevo
BREVO_API_KEY=your_brevo_api_key
MAIL_FROM_ADDRESS=your_verified_brevo_sender@example.com
MAIL_FROM_NAME=WatchWise
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
```

Brevo API keys authenticate the transactional API (`POST /v3/smtp/email`). The sender must be verified in Brevo. For the SMTP relay instead, set `MAIL_MAILER=brevo-smtp`, `BREVO_SMTP_USERNAME` to the login from Brevo, and `BREVO_SMTP_KEY` to a separate SMTP key. The relay uses port 587 with STARTTLS; an API key cannot serve as its password.

After changing environment values, run `php artisan config:clear` and restart the backend. Keep `APP_URL` and `FRONTEND_URL` aligned with your externally reachable URLs. No new migration or queue worker is needed for verification.

Signup persists an unverified user and sends a signed verification link after the response. Links expire after 60 minutes. Following a valid link updates `users.email_verified_at` and redirects to the frontend verification page. Repeated clicks before expiry succeed without changing the timestamp. Expired or tampered links redirect to a recovery page with a resend form. Resending is limited to three requests per minute per IP and email, with the same response for unknown and already verified addresses. Previously sent links remain valid until their own expiration.

Unverified sessions can inspect `/api/auth/me`, refresh, log out, and request another email. Other JWT-protected APIs return `403` with `code=email_unverified`; the frontend directs those users to `/verify-email`. Verified social signups continue straight to the app. Verification reads the database, so existing sessions unlock immediately after confirmation.

Mail failures are reported to `storage/logs/laravel.log`; the account remains available for resending. A successful HTTP response from signup/resend confirms the request, not inbox delivery. Check Brevo’s transactional logs for rejected senders, delivery failures, and bounces; users can request another link after resolving a delivery issue.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

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

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
