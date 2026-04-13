# Rakizat AI HRMS

AI-powered HR Management System for **Rakizat.com.sa** built with **Laravel 13**.

This platform is designed for modern HR operations with a strong focus on:

- attendance and biometric device integration
- employee management
- shifts and working hours
- leave and absence handling
- dashboards and reporting
- secure API ingestion
- future AI-powered HR workflows

---

## Project Overview

**Rakizat AI HRMS** is a scalable enterprise-ready Human Resource Management System designed to integrate biometric attendance devices such as **ZKTeco MB2000** and **UFace800** with a Laravel-based dashboard and API platform.

The system follows a clean architecture:

- **Laravel 13** for the web application, API, dashboard, and business logic
- **Python ZK Client** as a separate service for polling attendance devices
- **PostgreSQL or MySQL** for application data storage
- **SQLite locally on device client** for resilient edge buffering
- AI-ready foundation for advanced HR intelligence and automation

---

## Core Modules

- Authentication and RBAC
- Sites / Branches
- Devices
- Employees
- Attendance Logs
- Attendance Dashboard
- Shifts
- Leaves
- Reports
- API Ingest for attendance clients
- AI-ready HR assistant layer

---

## Architecture

### Main Application
- Laravel 13
- PHP 8.3+
- PostgreSQL recommended
- Blade + Alpine.js + Axios
- Secure JSON API ingest for attendance clients

### Device Integration
Biometric devices do **not** push directly into the dashboard reliably.  
Instead, a dedicated Python client polls devices periodically and syncs normalized logs into the Laravel API.

This provides:

- better reliability
- no log loss
- audit trail support
- retry-safe synchronization
- clean device isolation

---

## Attendance Flow

1. Device client polls ZKTeco devices periodically
2. Raw device events are stored locally in SQLite
3. Valid normalized events are added to outbox
4. Outbox records are sent to Laravel API
5. Laravel stores logs idempotently
6. Dashboard displays live device status and attendance logs

---

## AI Strategy

AI is used for **HR intelligence**, not for core attendance integrity.

Recommended AI use cases:

- attendance anomaly summaries
- leave policy Q&A
- HR assistant
- employee insights
- report generation
- future AI workflow automation

Core attendance ingest remains deterministic and non-AI.

---

## Tech Stack

- Laravel 13
- PHP 8.3+
- PostgreSQL / MySQL
- Blade
- Alpine.js
- Axios
- Python ZK client
- SQLite (edge buffering)
- Nginx / Apache
- Optional Laravel AI SDK for advanced AI features

---

## Installation

```bash
git clone https://github.com/skdevelopers/rakizat-ai-hrms.git
cd rakizat-ai-hrms

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
